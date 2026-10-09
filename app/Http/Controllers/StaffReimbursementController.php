<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Employee;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\StaffReimbursementItem;
use App\Models\StaffReimbursementRun;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff reimbursements as runs, same flow as payroll: create a draft listing staff with
 * an amount and optional deduction each, review/edit, then process - one journal pays
 * everyone to their usual salary payout (cash/bank account or savings):
 *   DR expense account            total amount
 *   CR deduction account          total deductions (default: the expense account itself)
 *   CR payout accounts / savings  each person's net
 * Reversing that journal (TransactionController, module staff_reimbursement_run) undoes
 * the savings credits and puts the run back to draft.
 */
class StaffReimbursementController extends Controller
{
    public function __construct(protected AccountingService $accounting) {}

    public function index()
    {
        $runs = StaffReimbursementRun::withCount('items')->latest()->paginate(20);

        return view('staff-reimbursements.index', compact('runs'));
    }

    public function create()
    {
        return view('staff-reimbursements.create', $this->formData());
    }

    public function store(Request $request)
    {
        if ($error = $this->validateRun($request)) {
            return $error;
        }

        $run = DB::transaction(function () use ($request) {
            $run = StaffReimbursementRun::create([
                'run_number'           => $this->generateRunNumber(),
                'period_month'         => $request->period_month,
                'period_year'          => $request->period_year,
                'description'          => $request->description,
                'expense_account_id'   => $request->expense_account_id,
                'deduction_account_id' => $request->deduction_account_id ?: null,
                'status'               => 'draft',
                'created_by'           => auth()->id(),
            ]);
            $this->saveItems($run, $request->items);
            return $run;
        });

        return redirect()->route('staff-reimbursements.show', $run)->with('success', 'Reimbursement run created. Review and process when ready.');
    }

    public function edit(StaffReimbursementRun $run)
    {
        if ($run->status !== 'draft') {
            return redirect()->route('staff-reimbursements.show', $run)->with('error', 'Only draft runs can be edited.');
        }
        $run->load('items');

        return view('staff-reimbursements.create', $this->formData($run) + ['run' => $run]);
    }

    public function update(Request $request, StaffReimbursementRun $run)
    {
        if ($run->status !== 'draft') {
            return redirect()->route('staff-reimbursements.show', $run)->with('error', 'Only draft runs can be edited.');
        }
        if ($error = $this->validateRun($request)) {
            return $error;
        }

        DB::transaction(function () use ($request, $run) {
            $run->update([
                'period_month'         => $request->period_month,
                'period_year'          => $request->period_year,
                'description'          => $request->description,
                'expense_account_id'   => $request->expense_account_id,
                'deduction_account_id' => $request->deduction_account_id ?: null,
            ]);
            $run->items()->delete();
            $this->saveItems($run, $request->items);
        });

        return redirect()->route('staff-reimbursements.show', $run)->with('success', 'Reimbursement run updated. Review and process when ready.');
    }

    public function show(StaffReimbursementRun $run)
    {
        $run->load('items.employee.paymentSourceAccount', 'items.savingsAccount.product', 'expenseAccount', 'deductionAccount', 'transaction', 'processedBy');

        return view('staff-reimbursements.show', compact('run'));
    }

    public function process(Request $request, StaffReimbursementRun $run)
    {
        if ($run->status === 'processed') {
            return back()->with('error', 'This reimbursement run has already been processed.');
        }

        $request->validate(['payment_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()]]);
        $paymentDate = $request->payment_date;

        $run->load('items.employee.paymentSourceAccount', 'items.savingsAccount.product');

        // Same payout checks as payroll: everyone being paid needs a working payout.
        foreach ($run->items as $item) {
            if ($item->net_amount <= 0) {
                continue;
            }
            $name = $item->employee?->name ?? 'Employee #' . $item->employee_id;

            if ($item->employee?->payment_method === 'cash') {
                $payout = $item->employee->paymentSourceAccount;
                if (!$payout || !$payout->is_active) {
                    throw ValidationException::withMessages(['payment_date' => "Cannot process: {$name} has no active payout account. Edit the employee and assign one."]);
                }
                continue;
            }

            $acc = $item->savingsAccount;
            if (!$acc || $acc->status !== 'active') {
                throw ValidationException::withMessages(['payment_date' => "Cannot process: {$name} has no active savings account linked. Edit the employee and assign one."]);
            }
            if (!$acc->product?->savings_liability_account_id) {
                throw ValidationException::withMessages(['payment_date' => "Cannot process: savings product “" . ($acc->product?->name ?? '(missing)') . "” has no liability GL account."]);
            }
        }

        DB::transaction(function () use ($run, $paymentDate) {
            $payouts = [];
            foreach ($run->items as $item) {
                if ($item->net_amount <= 0) {
                    continue;
                }
                $accountId = $item->employee?->payment_method === 'cash'
                    ? $item->employee->payment_source_account_id
                    : $item->savingsAccount->product->savings_liability_account_id;
                $payouts[$accountId] = ($payouts[$accountId] ?? 0) + $item->net_amount;
            }

            $totalAmount    = $run->items->sum('amount');
            $totalDeduction = $run->items->sum('deduction');

            $lines = [[
                'account_id'  => $run->expense_account_id,
                'debit'       => $totalAmount,
                'credit'      => 0,
                'description' => "Staff reimbursements — {$run->run_number}",
            ]];
            if ($totalDeduction > 0) {
                $lines[] = [
                    'account_id'  => $run->deduction_account_id ?: $run->expense_account_id,
                    'debit'       => 0,
                    'credit'      => $totalDeduction,
                    'description' => "Reimbursement deductions — {$run->run_number}",
                ];
            }
            foreach ($payouts as $accountId => $amount) {
                $lines[] = [
                    'account_id'  => $accountId,
                    'debit'       => 0,
                    'credit'      => $amount,
                    'description' => "Reimbursements paid — {$run->run_number}",
                ];
            }

            $journal = $this->accounting->post(
                $paymentDate,
                "Staff reimbursements: {$run->run_number} — {$run->period_label}",
                $lines,
                'staff_reimbursement_run',
                $run->id
            );

            // Savings payouts land on each person's statement, linked to the journal so a reversal unwinds them.
            foreach ($run->items as $item) {
                if ($item->net_amount <= 0 || $item->employee?->payment_method === 'cash') {
                    continue;
                }
                $account = SavingsAccount::query()->whereKey($item->savings_account_id)->lockForUpdate()->first();
                $before  = (float) $account->balance;
                $account->update(['balance' => $before + $item->net_amount]);

                SavingsTransaction::create([
                    'savings_account_id' => $account->id,
                    'transaction_type'   => 'deposit',
                    'amount'             => $item->net_amount,
                    'balance_before'     => $before,
                    'balance_after'      => $before + $item->net_amount,
                    'transaction_date'   => $paymentDate,
                    'reference'          => $journal->reference,
                    'description'        => "Staff reimbursement — {$run->run_number}" . ($item->description ? ": {$item->description}" : ''),
                    'transaction_id'     => $journal->id,
                    'created_by'         => auth()->id(),
                ]);
            }

            $run->update([
                'status'         => 'processed',
                'payment_date'   => $paymentDate,
                'transaction_id' => $journal->id,
                'processed_by'   => auth()->id(),
                'processed_at'   => now(),
            ]);
        });

        return redirect()->route('staff-reimbursements.show', $run)->with('success', 'Reimbursements paid and posted.');
    }

    public function destroy(StaffReimbursementRun $run)
    {
        if ($run->status !== 'draft') {
            return back()->with('error', 'Only draft runs can be deleted.');
        }
        $run->items()->delete();
        $run->delete();

        return redirect()->route('staff-reimbursements.index')->with('success', 'Draft reimbursement run deleted.');
    }

    private function formData(?StaffReimbursementRun $run = null): array
    {
        // Keep staff already on the run selectable even if they have since been deactivated.
        $employees = Employee::with('savingsAccount', 'paymentSourceAccount')
            ->where(fn ($q) => $q->where('status', 'active')
                ->when($run, fn ($q2) => $q2->orWhereIn('id', $run->items->pluck('employee_id'))))
            ->orderBy('name')
            ->get();

        return [
            'employees'       => $employees,
            'expenseAccounts' => Account::where('account_type', 'expense')->where('is_active', true)->orderBy('account_code')->get(),
            'allAccounts'     => Account::where('is_active', true)->orderBy('account_code')->get(),
        ];
    }

    /** Validates a run form; returns a redirect back on a problem, null when OK. */
    private function validateRun(Request $request)
    {
        $request->validate([
            'period_month'               => 'required|integer|min:1|max:12',
            'period_year'                => 'required|integer|min:2000|max:2100',
            'description'                => 'nullable|string|max:200',
            'expense_account_id'         => 'required|exists:accounts,id',
            'deduction_account_id'       => 'nullable|exists:accounts,id',
            'items'                      => 'required|array|min:1',
            'items.*.employee_id'        => 'required|exists:employees,id',
            'items.*.description'        => 'nullable|string|max:255',
            'items.*.amount'             => 'nullable|numeric|min:0',
            'items.*.deduction'          => 'nullable|numeric|min:0',
            'items.*.deduction_reason'   => 'nullable|string|max:255',
        ]);

        $employeeIds = array_column($request->items, 'employee_id');
        if (count($employeeIds) !== count(array_unique($employeeIds))) {
            return back()->withErrors(['items' => 'Each staff member can only appear once per run.'])->withInput();
        }

        $withAmount = 0;
        foreach ($request->items as $item) {
            $amount    = (float) ($item['amount'] ?? 0);
            $deduction = (float) ($item['deduction'] ?? 0);
            if ($deduction > $amount) {
                return back()->withErrors(['items' => 'A deduction can\'t be more than that person\'s amount.'])->withInput();
            }
            $withAmount += $amount > 0 ? 1 : 0;
        }
        if ($withAmount === 0) {
            return back()->withErrors(['items' => 'Enter an amount for at least one staff member.'])->withInput();
        }

        return null;
    }

    /** Rows left at zero (e.g. after "Add All Active") are skipped. */
    private function saveItems(StaffReimbursementRun $run, array $items): void
    {
        $totals = ['amount' => 0, 'deduction' => 0, 'net' => 0];
        foreach ($items as $item) {
            $amount = round((float) ($item['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $deduction = round((float) ($item['deduction'] ?? 0), 2);
            $employee  = Employee::findOrFail($item['employee_id']);

            StaffReimbursementItem::create([
                'staff_reimbursement_run_id' => $run->id,
                'employee_id'                => $employee->id,
                'savings_account_id'         => $employee->savings_account_id,
                'description'                => $item['description'] ?? null,
                'amount'                     => $amount,
                'deduction'                  => $deduction,
                'deduction_reason'           => $deduction > 0 ? ($item['deduction_reason'] ?? null) : null,
                'net_amount'                 => round($amount - $deduction, 2),
            ]);
            $totals['amount']    += $amount;
            $totals['deduction'] += $deduction;
            $totals['net']       += $amount - $deduction;
        }

        $run->update([
            'total_amount'    => round($totals['amount'], 2),
            'total_deduction' => round($totals['deduction'], 2),
            'total_net'       => round($totals['net'], 2),
        ]);
    }

    private function generateRunNumber(): string
    {
        $prefix = 'REIMB-' . now()->format('Ym') . '-';
        $count  = StaffReimbursementRun::where('run_number', 'like', $prefix . '%')->count() + 1;

        return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
