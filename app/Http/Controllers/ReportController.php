<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\MemberShare;
use App\Models\SavingsAccount;
use App\Models\TransactionLine;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected AccountingService $accounting) {}

    public function index()
    {
        return view('reports.index');
    }

    /**
     * Which branch a financial statement should be filtered to. Branch-scoped
     * staff are locked to their own branch; org-wide roles can optionally pick
     * one via ?branch_id=, defaulting to null (all branches combined).
     */
    private function resolveReportBranchId(Request $request): ?int
    {
        $user = auth()->user();
        if ($user->isBranchScoped()) {
            return $user->branch_id;
        }
        return $request->filled('branch_id') ? (int) $request->branch_id : null;
    }

    public function trialBalance(Request $request)
    {
        $fromDate = $request->from_date;
        $toDate   = $request->to_date ?? now()->toDateString();
        $branchId = $this->resolveReportBranchId($request);
        $branches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get();

        $data = $this->accounting->getTrialBalance($fromDate, $toDate, $branchId);

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.reports.trial-balance', compact('data', 'fromDate', 'toDate'))
                ->setPaper('a4', 'portrait');
            return $pdf->download('trial-balance-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $rows = [];
            $rows[] = ['Code', 'Account Name', 'Type', 'Debit', 'Credit'];
            foreach ($data['rows'] as $row) {
                $rows[] = [$row['account_code'], $row['account_name'], ucfirst($row['account_type']), $row['debit'], $row['credit']];
            }
            $rows[] = ['', 'TOTALS', '', $data['total_debit'], $data['total_credit']];
            return $this->csvDownload($rows, 'trial-balance-' . now()->format('Y-m-d'));
        }

        return view('reports.trial-balance', compact('data', 'fromDate', 'toDate', 'branchId', 'branches'));
    }

    public function incomeStatement(Request $request)
    {
        $fromDate = $request->from_date ?? now()->startOfMonth()->toDateString();
        $toDate   = $request->to_date   ?? now()->toDateString();
        $branchId = $this->resolveReportBranchId($request);
        $branches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get();

        $data = $this->accounting->getIncomeStatement($fromDate, $toDate, $branchId);

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.reports.income-statement', compact('data', 'fromDate', 'toDate'))
                ->setPaper('a4', 'portrait');
            return $pdf->download('income-statement-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $rows = [];
            $rows[] = ['Section', 'Code', 'Account Name', 'Amount'];
            foreach ($data['revenue_rows'] as $row) {
                $rows[] = ['Revenue', $row['account']->account_code, $row['account']->account_name, $row['balance']];
            }
            $rows[] = ['Revenue', '', 'Total Revenue', $data['total_revenue']];
            $rows[] = [];
            foreach ($data['expense_groups'] as $group) {
                $rows[] = ['Expenses', '', $group['label'], $group['subtotal']];
                foreach ($group['rows'] as $row) {
                    $rows[] = ['Expenses', $row['account']->account_code, $row['account']->account_name, $row['balance']];
                }
            }
            $rows[] = ['Expenses', '', 'Total Expenses', $data['total_expense']];
            $rows[] = [];
            $rows[] = ['', '', 'Net Income', $data['net_income']];
            return $this->csvDownload($rows, 'income-statement-' . now()->format('Y-m-d'));
        }

        return view('reports.income-statement', compact('data', 'fromDate', 'toDate', 'branchId', 'branches'));
    }

    public function balanceSheet(Request $request)
    {
        $asOf = $request->as_of ?? now()->toDateString();
        $branchId = $this->resolveReportBranchId($request);
        $branches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get();
        $data = $this->accounting->getBalanceSheet($asOf, $branchId);

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.reports.balance-sheet', compact('data', 'asOf'))
                ->setPaper('a4', 'portrait');
            return $pdf->download('balance-sheet-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $rows = [];
            $rows[] = ['Section', 'Code', 'Account Name', 'Amount'];
            foreach ($data['asset']['rows'] as $row) {
                $rows[] = ['Assets', $row['account']->account_code, $row['account']->account_name, $row['balance']];
            }
            $rows[] = ['Assets', '', 'Total Assets', $data['asset']['total']];
            $rows[] = [];
            foreach ($data['liability']['rows'] as $row) {
                $rows[] = ['Liabilities', $row['account']->account_code, $row['account']->account_name, $row['balance']];
            }
            $rows[] = ['Liabilities', '', 'Total Liabilities', $data['liability']['total']];
            $rows[] = [];
            foreach ($data['equity']['rows'] as $row) {
                $rows[] = ['Equity', $row['account']->account_code, $row['account']->account_name, $row['balance']];
            }
            $rows[] = ['Equity', '', 'Total Equity', $data['equity']['total']];
            return $this->csvDownload($rows, 'balance-sheet-' . now()->format('Y-m-d'));
        }

        return view('reports.balance-sheet', compact('data', 'asOf', 'branchId', 'branches'));
    }

    public function generalLedger(Request $request)
    {
        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();
        $branchId = $this->resolveReportBranchId($request);
        $branches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get();

        if (!$request->filled('account_id')) {
            return view('reports.general-ledger', [
                'accounts'  => $accounts,
                'account'   => null,
                'rows'      => collect(),
                'fromDate'  => null,
                'toDate'    => now()->toDateString(),
                'branchId'  => $branchId,
                'branches'  => $branches,
            ]);
        }

        $request->validate([
            'account_id' => 'required|exists:accounts,id',
        ]);

        $account  = Account::findOrFail($request->account_id);
        $fromDate = $request->from_date;
        $toDate   = $request->to_date ?? now()->toDateString();

        $lines = TransactionLine::with('transaction')
            ->where('account_id', $account->id)
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->when($fromDate, fn($q) => $q->where('transactions.date', '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->where('transactions.date', '<=', $toDate))
            ->when($branchId, fn($q) => $q->where('transactions.branch_id', $branchId))
            ->orderBy('transactions.date')
            ->select('transaction_lines.*')
            ->get();

        $runningBalance = 0;
        $rows = $lines->map(function ($line) use (&$runningBalance, $account) {
            $runningBalance += $account->isDebitNormal()
                ? ($line->debit - $line->credit)
                : ($line->credit - $line->debit);
            return [
                'line'    => $line,
                'balance' => $runningBalance,
            ];
        });

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.reports.general-ledger', compact('account', 'rows', 'fromDate', 'toDate'))
                ->setPaper('a4', 'landscape');
            return $pdf->download('general-ledger-' . $account->account_code . '-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $csvRows = [];
            $csvRows[] = ['Date', 'Reference', 'Description', 'Debit', 'Credit', 'Balance'];
            foreach ($rows as $row) {
                $csvRows[] = [
                    $row['line']->transaction->date->format('Y-m-d'),
                    $row['line']->transaction->reference,
                    $row['line']->description ?? $row['line']->transaction->description,
                    $row['line']->debit,
                    $row['line']->credit,
                    $row['balance'],
                ];
            }
            return $this->csvDownload($csvRows, 'general-ledger-' . $account->account_code . '-' . now()->format('Y-m-d'));
        }

        return view('reports.general-ledger', compact('account', 'rows', 'fromDate', 'toDate', 'accounts', 'branchId', 'branches'));
    }

    public function loanPortfolio(Request $request)
    {
        $loans = Loan::with('client.relationshipManager', 'product')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->relationship_manager_id, fn($q) => $q->whereHas(
                'client', fn($q2) => $q2->where('relationship_manager_id', $request->relationship_manager_id)
            ))
            ->when($request->from_date, fn($q) => $q->whereDate('disbursement_date', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->whereDate('disbursement_date', '<=', $request->to_date))
            ->get();

        // Relationship managers are any active staff user - excludes client-portal-only logins.
        $relationshipManagers = \App\Models\User::where('is_active', true)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client', 'group_member', 'group_leader']))
            ->orderBy('name')
            ->get();

        $summary = [
            'total_loans'       => $loans->count(),
            'total_disbursed'   => $loans->whereIn('status', ['active', 'closed', 'defaulted'])->sum('principal'),
            'total_outstanding' => $loans->whereIn('status', ['active', 'defaulted'])->sum(fn($l) => $l->outstanding_principal + $l->outstanding_interest),
            'active_loans'      => $loans->where('status', 'active')->count(),
            'closed_loans'      => $loans->where('status', 'closed')->count(),
            'defaulted_loans'   => $loans->where('status', 'defaulted')->count(),
        ];

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.reports.loan-portfolio', compact('loans', 'summary'))
                ->setPaper('a4', 'landscape');
            return $pdf->download('loan-portfolio-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $rows = [];
            $rows[] = ['Loan #', 'Client', 'Relationship Manager', 'Product', 'Method', 'Principal', 'Outstanding Principal', 'Outstanding Interest', 'Disbursed', 'Maturity', 'Status'];
            foreach ($loans as $loan) {
                $rows[] = [
                    $loan->loan_number,
                    $loan->client->name,
                    $loan->client->relationshipManager?->name ?? '',
                    $loan->product->name,
                    ucfirst($loan->interest_method),
                    $loan->principal,
                    $loan->outstanding_principal,
                    $loan->outstanding_interest,
                    $loan->disbursement_date?->format('Y-m-d') ?? '',
                    $loan->maturity_date?->format('Y-m-d') ?? '',
                    ucfirst($loan->status),
                ];
            }
            $rows[] = [];
            $rows[] = ['', 'TOTALS', '', '', '', $summary['total_disbursed'], $summary['total_outstanding'], '', '', '', ''];
            return $this->csvDownload($rows, 'loan-portfolio-' . now()->format('Y-m-d'));
        }

        return view('reports.loan-portfolio', compact('loans', 'summary', 'relationshipManagers'));
    }

    public function loanAging(Request $request)
    {
        $asOf = $request->as_of ?? now()->toDateString();

        $overdueSchedules = LoanSchedule::with('loan.client.relationshipManager', 'loan.product')
            ->where('due_date', '<', $asOf)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->when($request->status, fn($q) => $q->whereHas(
                'loan', fn($q2) => $q2->where('status', $request->status)
            ))
            ->when($request->relationship_manager_id, fn($q) => $q->whereHas(
                'loan.client', fn($q2) => $q2->where('relationship_manager_id', $request->relationship_manager_id)
            ))
            ->when($request->from_date, fn($q) => $q->whereHas(
                'loan', fn($q2) => $q2->whereDate('disbursement_date', '>=', $request->from_date)
            ))
            ->when($request->to_date, fn($q) => $q->whereHas(
                'loan', fn($q2) => $q2->whereDate('disbursement_date', '<=', $request->to_date)
            ))
            ->get();

        // Relationship managers are any active staff user - excludes client-portal-only logins.
        $relationshipManagers = \App\Models\User::where('is_active', true)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client', 'group_member', 'group_leader']))
            ->orderBy('name')
            ->get();

        $buckets = [
            '1-30'   => [],
            '31-60'  => [],
            '61-90'  => [],
            '91-180' => [],
            '181+'   => [],
        ];

        foreach ($overdueSchedules as $schedule) {
            $days = Carbon::parse($schedule->due_date)->diffInDays($asOf);
            $outstanding = ($schedule->principal_due - $schedule->principal_paid) + ($schedule->interest_due - $schedule->interest_paid);

            if ($days <= 30)       $buckets['1-30'][]   = ['schedule' => $schedule, 'days' => $days, 'outstanding' => $outstanding];
            elseif ($days <= 60)   $buckets['31-60'][]  = ['schedule' => $schedule, 'days' => $days, 'outstanding' => $outstanding];
            elseif ($days <= 90)   $buckets['61-90'][]  = ['schedule' => $schedule, 'days' => $days, 'outstanding' => $outstanding];
            elseif ($days <= 180)  $buckets['91-180'][] = ['schedule' => $schedule, 'days' => $days, 'outstanding' => $outstanding];
            else                   $buckets['181+'][]   = ['schedule' => $schedule, 'days' => $days, 'outstanding' => $outstanding];
        }

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.reports.loan-aging', compact('buckets', 'asOf'))
                ->setPaper('a4', 'landscape');
            return $pdf->download('loan-aging-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $rows = [];
            $rows[] = ['Bucket', 'Loan #', 'Client', 'Product', 'Due Date', 'Days Overdue', 'Outstanding'];
            foreach ($buckets as $bucket => $items) {
                foreach ($items as $item) {
                    $rows[] = [
                        $bucket . ' Days',
                        $item['schedule']->loan->loan_number,
                        $item['schedule']->loan->client->name,
                        $item['schedule']->loan->product->name,
                        $item['schedule']->due_date->format('Y-m-d'),
                        $item['days'],
                        $item['outstanding'],
                    ];
                }
            }
            return $this->csvDownload($rows, 'loan-aging-' . now()->format('Y-m-d'));
        }

        return view('reports.loan-aging', compact('buckets', 'asOf', 'relationshipManagers'));
    }

    public function repaymentSchedule(Request $request)
    {
        $loan = null;
        if ($request->loan_id) {
            $loan = Loan::with('client', 'product', 'schedules')->findOrFail($request->loan_id);
        }

        $loans = Loan::with('client')->whereIn('status', ['active', 'defaulted'])->get();

        if ($loan && $request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.reports.repayment-schedule', compact('loan'))
                ->setPaper('a4', 'portrait');
            return $pdf->download('repayment-schedule-' . $loan->loan_number . '.pdf');
        }

        if ($loan && $request->format === 'excel') {
            $rows = [];
            $rows[] = ['#', 'Due Date', 'Principal Due', 'Interest Due', 'Total Due', 'Principal Paid', 'Interest Paid', 'Balance After', 'Status'];
            foreach ($loan->schedules as $s) {
                $rows[] = [
                    $s->installment_no,
                    $s->due_date->format('Y-m-d'),
                    $s->principal_due,
                    $s->interest_due,
                    $s->total_due,
                    $s->principal_paid,
                    $s->interest_paid,
                    $s->balance_after,
                    ucfirst($s->status),
                ];
            }
            return $this->csvDownload($rows, 'repayment-schedule-' . $loan->loan_number);
        }

        return view('reports.repayment-schedule', compact('loan', 'loans'));
    }

    public function interestIncome(Request $request)
    {
        $fromDate = $request->from_date ?? now()->startOfMonth()->toDateString();
        $toDate   = $request->to_date   ?? now()->toDateString();

        $incomeAccounts = Account::where('account_type', 'revenue')
            ->where('account_name', 'like', '%interest%')
            ->orWhere('account_code', 'like', '4%')
            ->where('account_type', 'revenue')
            ->get();

        $rows = [];
        $total = 0;
        foreach ($incomeAccounts as $account) {
            $bal = $this->accounting->getAccountBalance($account->id, $fromDate, $toDate);
            $balance = $bal['credit'] - $bal['debit'];
            if ($balance == 0) continue;
            $rows[] = ['account' => $account, 'balance' => $balance];
            $total += $balance;
        }

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.reports.interest-income', compact('rows', 'total', 'fromDate', 'toDate'))
                ->setPaper('a4', 'portrait');
            return $pdf->download('interest-income-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $csvRows = [];
            $csvRows[] = ['Account Code', 'Account Name', 'Amount'];
            foreach ($rows as $row) {
                $csvRows[] = [$row['account']->account_code, $row['account']->account_name, $row['balance']];
            }
            $csvRows[] = ['', 'TOTAL', $total];
            return $this->csvDownload($csvRows, 'interest-income-' . now()->format('Y-m-d'));
        }

        return view('reports.interest-income', compact('rows', 'total', 'fromDate', 'toDate'));
    }

    public function savingsBalances(Request $request)
    {
        $accounts = SavingsAccount::with('client', 'product')
            ->where('status', 'active')
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->get();

        $total = $accounts->sum('balance');

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.reports.savings-balances', compact('accounts', 'total'))
                ->setPaper('a4', 'portrait');
            return $pdf->download('savings-balances-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $rows = [];
            $rows[] = ['Account #', 'Client', 'Product', 'Balance', 'Status'];
            foreach ($accounts as $acc) {
                $rows[] = [$acc->account_number, $acc->client->name, $acc->product->name, $acc->balance, ucfirst($acc->status)];
            }
            $rows[] = ['', 'TOTAL', '', $total, ''];
            return $this->csvDownload($rows, 'savings-balances-' . now()->format('Y-m-d'));
        }

        return view('reports.savings-balances', compact('accounts', 'total'));
    }

    public function fixedDepositMaturity(Request $request)
    {
        $fromDate = $request->from_date ?? now()->toDateString();
        $toDate   = $request->to_date   ?? now()->addMonths(3)->toDateString();

        $deposits = FixedDeposit::with('client', 'product')
            ->where('status', 'active')
            ->whereBetween('maturity_date', [$fromDate, $toDate])
            ->orderBy('maturity_date')
            ->get();

        $total = $deposits->sum('maturity_amount');

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.reports.fd-maturity', compact('deposits', 'total', 'fromDate', 'toDate'))
                ->setPaper('a4', 'portrait');
            return $pdf->download('fd-maturity-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $rows = [];
            $rows[] = ['Deposit #', 'Client', 'Product', 'Principal', 'Interest Amount', 'Maturity Amount', 'Maturity Date'];
            foreach ($deposits as $fd) {
                $rows[] = [
                    $fd->deposit_number,
                    $fd->client->name,
                    $fd->product->name,
                    $fd->principal,
                    $fd->interest_amount,
                    $fd->maturity_amount,
                    $fd->maturity_date->format('Y-m-d'),
                ];
            }
            $rows[] = ['', '', 'TOTAL', '', '', $total, ''];
            return $this->csvDownload($rows, 'fd-maturity-' . now()->format('Y-m-d'));
        }

        return view('reports.fd-maturity', compact('deposits', 'total', 'fromDate', 'toDate'));
    }

    public function memberSummary(Request $request)
    {
        $asOf = $request->as_of ?? now()->toDateString();

        // --- Bulk precompute financial metrics as of $asOf to avoid N+1 queries ---

        // Loans: outstanding principal = principal − repayments up to $asOf
        $loanPrincipals = \DB::table('loans as l')
            ->leftJoinSub(
                \DB::table('loan_repayments')
                    ->where('payment_date', '<=', $asOf)
                    ->groupBy('loan_id')
                    ->select('loan_id', \DB::raw('SUM(principal_paid) as paid')),
                'rp', 'rp.loan_id', '=', 'l.id'
            )
            ->where('l.disbursement_date', '<=', $asOf)
            ->whereNull('l.deleted_at')
            ->groupBy('l.client_id')
            ->select('l.client_id', \DB::raw('SUM(GREATEST(0, l.principal - COALESCE(rp.paid, 0))) as outstanding'))
            ->pluck('outstanding', 'client_id');

        // Loans: outstanding interest = scheduled interest due on or before $asOf − interest paid up to $asOf
        $loanInterests = \DB::table('loans as l')
            ->leftJoinSub(
                \DB::table('loan_schedules')
                    ->where('due_date', '<=', $asOf)
                    ->groupBy('loan_id')
                    ->select('loan_id', \DB::raw('SUM(GREATEST(0, interest_due - interest_paid)) as interest_os')),
                'sched', 'sched.loan_id', '=', 'l.id'
            )
            ->where('l.disbursement_date', '<=', $asOf)
            ->whereNull('l.deleted_at')
            ->groupBy('l.client_id')
            ->select('l.client_id', \DB::raw('COALESCE(SUM(sched.interest_os), 0) as interest'))
            ->pluck('interest', 'client_id');

        // Loans taken = number of loans actually disbursed on or before $asOf (excludes
        // pending/approved-not-yet-disbursed, since disbursement_date is null for those).
        $loanCounts = \DB::table('loans')
            ->where('disbursement_date', '<=', $asOf)
            ->whereNull('deleted_at')
            ->groupBy('client_id')
            ->select('client_id', \DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'client_id');

        // Fetch clients registered on or before $asOf
        $members = Client::with('relationshipManager')
            ->whereDate('created_at', '<=', $asOf)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->get()
            ->map(function ($client) use ($loanPrincipals, $loanInterests, $loanCounts) {
                return (object) [
                    'client'         => $client,
                    'loan_principal' => (float) ($loanPrincipals[$client->id] ?? 0),
                    'loan_interest'  => (float) ($loanInterests[$client->id]  ?? 0),
                    'loans_taken'    => (int) ($loanCounts[$client->id] ?? 0),
                ];
            });

        $totals = [
            'loan_principal' => $members->sum('loan_principal'),
            'loan_interest'  => $members->sum('loan_interest'),
            'loans_taken'    => $members->sum('loans_taken'),
        ];

        // Relationship managers are any active staff user — excludes client-portal-only logins.
        $relationshipManagers = \App\Models\User::where('is_active', true)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client', 'group_member', 'group_leader']))
            ->orderBy('name')
            ->get();

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.member-summary', compact('members', 'totals', 'asOf'))
                ->setPaper('a4', 'landscape');
            return $pdf->download('member-summary-' . $asOf . '.pdf');
        }

        if ($request->format === 'excel') {
            $rows = [];
            $rows[] = ['#', 'Member Name', 'Client #', 'Loans Taken', 'Loan Principal', 'Loan Interest', 'Relationship Manager'];
            foreach ($members as $i => $row) {
                $rows[] = [
                    $i + 1,
                    $row->client->name,
                    $row->client->client_number,
                    $row->loans_taken,
                    $row->loan_principal,
                    $row->loan_interest,
                    $row->client->relationshipManager?->name ?? '',
                ];
            }
            $rows[] = ['', 'TOTALS', '', $totals['loans_taken'], $totals['loan_principal'], $totals['loan_interest'], ''];
            return $this->csvDownload($rows, 'member-summary-' . $asOf);
        }

        return view('reports.member-summary', compact('members', 'totals', 'asOf', 'relationshipManagers'));
    }

    /**
     * Stream a 2D array as a CSV download.
     */
    private function csvDownload(array $rows, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename . '.csv', [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ]);
    }
}
