<?php
namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\SavingsAccount;
use App\Models\PayrollRun;
use App\Models\SavingsTransaction;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller {
    public function __construct(protected AccountingService $accounting) {}

    public function index() {
        $runs = PayrollRun::withCount('items')->latest()->paginate(20);
        return view('payroll.index', compact('runs'));
    }

    public function create() {
        $employees = Employee::with('savingsAccount.product', 'paymentSourceAccount')->where('status', 'active')->get();
        return view('payroll.create', compact('employees'));
    }

    public function store(Request $request) {
        $request->validate([
            'period_month'  => 'required|integer|min:1|max:12',
            'period_year'   => 'required|integer|min:2000|max:2100',
            'description'   => 'nullable|string|max:200',
            'items'         => 'required|array|min:1',
            'items.*.employee_id'    => 'required|exists:employees,id',
            'items.*.basic_salary'   => 'required|numeric|min:0',
            'items.*.allowances'     => 'nullable|numeric|min:0',
            'items.*.deductions'     => 'nullable|numeric|min:0',
        ]);

        // Prevent duplicate employees before creating anything
        $employeeIds = array_column($request->items, 'employee_id');
        if (count($employeeIds) !== count(array_unique($employeeIds))) {
            return back()->withErrors(['items' => 'Duplicate employees detected. Each employee can only appear once per payroll run.'])->withInput();
        }

        $run = PayrollRun::create([
            'run_number'    => $this->generateRunNumber(),
            'period_month'  => $request->period_month,
            'period_year'   => $request->period_year,
            'description'   => $request->description,
            'total_gross'   => 0,
            'status'        => 'draft',
            'created_by'    => auth()->id(),
        ]);

        $totalGross = 0;
        foreach ($request->items as $item) {
            $employee  = Employee::findOrFail($item['employee_id']);
            $basic     = (float) $item['basic_salary'];
            $allow     = (float) ($item['allowances'] ?? 0);
            $deduct    = (float) ($item['deductions'] ?? 0);
            $net       = $basic + $allow - $deduct;
            $totalGross += $net;

            PayrollItem::create([
                'payroll_run_id'     => $run->id,
                'employee_id'        => $employee->id,
                'savings_account_id' => $employee->savings_account_id,
                'basic_salary'       => $basic,
                'allowances'         => $allow,
                'deductions'         => $deduct,
                'net_salary'         => $net,
            ]);
        }

        $run->update(['total_gross' => $totalGross]);

        return redirect()->route('payroll.show', $run)->with('success', 'Payroll run created. Review and process when ready.');
    }

    public function show(PayrollRun $payroll) {
        $payroll->load('items.employee.paymentSourceAccount', 'items.savingsAccount.product', 'processedBy');
        return view('payroll.show', compact('payroll'));
    }

    public function process(Request $request, PayrollRun $payroll) {
        if ($payroll->status === 'processed') {
            return back()->with('error', 'This payroll run has already been processed.');
        }

        $request->validate(['payment_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()]]);
        $paymentDate = $request->payment_date;

        $payroll->load('items.employee.paymentSourceAccount', 'items.savingsAccount.product');

        $salaryExpenseAccountId = Account::where('account_code', '5001')->value('id')
            ?: Account::where('account_code', '5003')->value('id');
        if (!$salaryExpenseAccountId) {
            throw ValidationException::withMessages([
                'payment_date' => 'Chart of accounts is missing a salary expense account (codes 5001 or 5003). Add one under Chart of Accounts.',
            ]);
        }

        foreach ($payroll->items as $item) {
            if ($item->net_salary <= 0) {
                continue;
            }
            $name = $item->employee?->name ?? 'Employee #' . $item->employee_id;

            if ($item->employee?->payment_method === 'cash') {
                $payoutAccount = $item->employee->paymentSourceAccount;
                if (!$payoutAccount || !$payoutAccount->is_active) {
                    throw ValidationException::withMessages([
                        'payment_date' => "Cannot process: {$name} has no active payout account. Edit the employee and assign one.",
                    ]);
                }
                continue;
            }

            if (!$item->savings_account_id) {
                throw ValidationException::withMessages([
                    'payment_date' => "Cannot process: {$name} has no payroll savings account linked. Edit the employee and assign an active savings account.",
                ]);
            }
            $acc = $item->savingsAccount;
            if (!$acc || $acc->status !== 'active') {
                throw ValidationException::withMessages([
                    'payment_date' => "Cannot process: {$name}'s linked savings account is missing or not active.",
                ]);
            }
            $product = $acc->product;
            if (!$product || !$product->savings_liability_account_id) {
                $pn = $product?->name ?? '(missing product)';
                throw ValidationException::withMessages([
                    'payment_date' => "Cannot process: savings product “{$pn}” has no liability GL account. Configure it under Savings Products.",
                ]);
            }
        }

        DB::transaction(function () use ($payroll, $paymentDate, $salaryExpenseAccountId) {
            $totalNet    = 0;
            $creditLines = [];

            // 1) Totals + journal lines (no sub-ledger writes yet)
            foreach ($payroll->items as $item) {
                if ($item->net_salary <= 0) {
                    continue;
                }

                $totalNet += $item->net_salary;

                $creditAccountId = $item->employee?->payment_method === 'cash'
                    ? $item->employee->payment_source_account_id
                    : ($item->savings_account_id ? $item->savingsAccount->product->savings_liability_account_id : null);

                if (!$creditAccountId) {
                    continue;
                }

                if (!isset($creditLines[$creditAccountId])) {
                    $creditLines[$creditAccountId] = 0;
                }
                $creditLines[$creditAccountId] += $item->net_salary;
            }

            // 2) Post GL first so we have transaction.id for savings_transactions.transaction_id
            $journalTx = null;
            if ($totalNet > 0) {
                $journalLines = [
                    [
                        'account_id'  => $salaryExpenseAccountId,
                        'debit'       => $totalNet,
                        'credit'      => 0,
                        'description' => "Salary expense — {$payroll->run_number}",
                    ],
                ];
                foreach ($creditLines as $accountId => $amount) {
                    $journalLines[] = [
                        'account_id'  => $accountId,
                        'debit'       => 0,
                        'credit'      => $amount,
                        'description' => "Salary paid — {$payroll->run_number}",
                    ];
                }

                $journalTx = $this->accounting->post(
                    $paymentDate,
                    "Payroll: {$payroll->run_number} — {$payroll->period_label}",
                    $journalLines,
                    'payroll',
                    $payroll->id
                );
            }

            // 3) Credit savings + statement lines linked to the journal (enables reversal)
            foreach ($payroll->items as $item) {
                if (!$item->savings_account_id || $item->net_salary <= 0) {
                    continue;
                }

                $savingsAccount = SavingsAccount::query()->whereKey($item->savings_account_id)->lockForUpdate()->first();
                if (!$savingsAccount) {
                    continue;
                }

                $balBefore = (float) $savingsAccount->balance;
                $balAfter  = $balBefore + $item->net_salary;

                $savingsAccount->update(['balance' => $balAfter]);

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => 'deposit',
                    'amount'             => $item->net_salary,
                    'balance_before'     => $balBefore,
                    'balance_after'      => $balAfter,
                    'transaction_date'   => $paymentDate,
                    'reference'          => $journalTx?->reference,
                    'description'        => "Salary — {$payroll->run_number} ({$payroll->period_label})",
                    'transaction_id'     => $journalTx?->id,
                    'created_by'         => auth()->id(),
                ]);
            }

            $payroll->update([
                'status'       => 'processed',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'total_gross'  => $totalNet,
            ]);
        });

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payroll processed. Salaries paid to employee savings accounts and payout accounts.');
    }

    public function destroy(PayrollRun $payroll) {
        if ($payroll->status !== 'draft') {
            return back()->with('error', 'Only draft payroll runs can be deleted.');
        }
        $payroll->items()->delete();
        $payroll->delete();
        return redirect()->route('payroll.index')->with('success', 'Draft payroll run deleted.');
    }

    private function generateRunNumber(): string {
        $year   = now()->format('Y');
        $month  = now()->format('m');
        $prefix = 'PAY-' . $year . $month . '-';
        $max = \DB::table('payroll_runs')
            ->where('run_number', 'like', $prefix . '%')
            ->max('run_number');
        $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
