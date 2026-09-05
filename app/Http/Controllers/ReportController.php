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

    public function trialBalance(Request $request)
    {
        $fromDate = $request->from_date;
        $toDate   = $request->to_date ?? now()->toDateString();

        $data = $this->accounting->getTrialBalance($fromDate, $toDate);

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

        return view('reports.trial-balance', compact('data', 'fromDate', 'toDate'));
    }

    public function incomeStatement(Request $request)
    {
        $fromDate = $request->from_date ?? now()->startOfMonth()->toDateString();
        $toDate   = $request->to_date   ?? now()->toDateString();

        $data = $this->accounting->getIncomeStatement($fromDate, $toDate);

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

        return view('reports.income-statement', compact('data', 'fromDate', 'toDate'));
    }

    public function balanceSheet(Request $request)
    {
        $asOf = $request->as_of ?? now()->toDateString();
        $data = $this->accounting->getBalanceSheet($asOf);

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

        return view('reports.balance-sheet', compact('data', 'asOf'));
    }

    public function generalLedger(Request $request)
    {
        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();

        if (!$request->filled('account_id')) {
            return view('reports.general-ledger', [
                'accounts'  => $accounts,
                'account'   => null,
                'rows'      => collect(),
                'fromDate'  => null,
                'toDate'    => now()->toDateString(),
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

        return view('reports.general-ledger', compact('account', 'rows', 'fromDate', 'toDate', 'accounts'));
    }

    public function loanPortfolio(Request $request)
    {
        $loans = Loan::with('client', 'product')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
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
            $rows[] = ['Loan #', 'Client', 'Product', 'Method', 'Principal', 'Outstanding Principal', 'Outstanding Interest', 'Disbursed', 'Maturity', 'Status'];
            foreach ($loans as $loan) {
                $rows[] = [
                    $loan->loan_number,
                    $loan->client->name,
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
            $rows[] = ['', 'TOTALS', '', '', $summary['total_disbursed'], $summary['total_outstanding'], '', '', '', ''];
            return $this->csvDownload($rows, 'loan-portfolio-' . now()->format('Y-m-d'));
        }

        return view('reports.loan-portfolio', compact('loans', 'summary'));
    }

    public function loanAging(Request $request)
    {
        $asOf = $request->as_of ?? now()->toDateString();

        $overdueSchedules = LoanSchedule::with('loan.client', 'loan.product')
            ->where('due_date', '<', $asOf)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
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

        return view('reports.loan-aging', compact('buckets', 'asOf'));
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
        $asOf       = $request->as_of ?? now()->toDateString();
        $shareValue = 100000;

        // --- Bulk precompute financial metrics as of $asOf to avoid N+1 queries ---

        // Savings: balance_after of the last transaction on or before $asOf, per client
        $savingsBalances = \DB::table('savings_accounts as sa')
            ->leftJoinSub(
                \DB::table('savings_transactions')
                    ->where('transaction_date', '<=', $asOf)
                    ->select('savings_account_id', \DB::raw('MAX(id) as last_id'))
                    ->groupBy('savings_account_id'),
                'latest',
                'latest.savings_account_id', '=', 'sa.id'
            )
            ->leftJoin('savings_transactions as st', 'st.id', '=', 'latest.last_id')
            ->groupBy('sa.client_id')
            ->select('sa.client_id', \DB::raw('COALESCE(SUM(st.balance_after), 0) as balance'))
            ->pluck('balance', 'client_id');

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

        // Fixed Deposits: principal of FDs that started on or before $asOf and mature after $asOf
        $fdAmounts = \DB::table('fixed_deposits')
            ->where('start_date', '<=', $asOf)
            ->where('maturity_date', '>=', $asOf)
            ->whereNull('deleted_at')
            ->groupBy('client_id')
            ->select('client_id', \DB::raw('SUM(principal) as amount'))
            ->pluck('amount', 'client_id');

        // Shares: amount paid on shares created on or before $asOf
        $shareAmounts = \DB::table('member_shares')
            ->whereDate('created_at', '<=', $asOf)
            ->groupBy('client_id')
            ->select('client_id', \DB::raw('SUM(amount_paid) as paid'))
            ->pluck('paid', 'client_id');

        // Group balance: for group-type clients, sum of their group members' balances
        $groupBalances = \DB::table('groups as g')
            ->join('group_members as gm', 'gm.group_id', '=', 'g.id')
            ->whereNotNull('g.client_id')
            ->where('gm.status', 'active')
            ->groupBy('g.client_id')
            ->select('g.client_id', \DB::raw('SUM(gm.balance) as balance'))
            ->pluck('balance', 'client_id');

        // Fetch clients registered on or before $asOf
        $members = Client::whereDate('created_at', '<=', $asOf)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->get()
            ->map(function ($client) use ($savingsBalances, $loanPrincipals, $loanInterests, $fdAmounts, $shareAmounts, $groupBalances, $shareValue) {
                $savingsBalance = (float) ($savingsBalances[$client->id] ?? 0);
                $loanPrincipal  = (float) ($loanPrincipals[$client->id]  ?? 0);
                $loanInterest   = (float) ($loanInterests[$client->id]   ?? 0);
                $fdAmount       = (float) ($fdAmounts[$client->id]        ?? 0);
                $sharePaid      = (float) ($shareAmounts[$client->id]     ?? 0);
                $groupBalance   = (float) ($groupBalances[$client->id]    ?? 0);
                $shareUnits     = $shareValue > 0 ? floor($sharePaid / $shareValue) : 0;

                return (object) [
                    'client'         => $client,
                    'savings_balance'=> $savingsBalance,
                    'loan_principal' => $loanPrincipal,
                    'loan_interest'  => $loanInterest,
                    'fd_amount'      => $fdAmount,
                    'group_balance'  => $groupBalance,
                    'share_units'    => $shareUnits,
                    'share_total'    => $sharePaid,
                    'total_assets'   => $savingsBalance + $fdAmount + $sharePaid + $groupBalance,
                    'total_liability'=> $loanPrincipal + $loanInterest,
                ];
            });

        $totals = [
            'savings'        => $members->sum('savings_balance'),
            'loan_principal' => $members->sum('loan_principal'),
            'loan_interest'  => $members->sum('loan_interest'),
            'fd_amount'      => $members->sum('fd_amount'),
            'group_balance'  => $members->sum('group_balance'),
            'share_units'    => $members->sum('share_units'),
            'share_total'    => $members->sum('share_total'),
        ];

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('pdf.member-summary', compact('members', 'totals', 'shareValue', 'asOf'))
                ->setPaper('a4', 'landscape');
            return $pdf->download('member-summary-' . $asOf . '.pdf');
        }

        if ($request->format === 'excel') {
            $rows = [];
            $rows[] = ['#', 'Member Name', 'Client #', 'Savings Balance', 'Loan Principal', 'Loan Interest', 'Fixed Deposits', 'Group Balance', 'Share Units', 'Share Value'];
            foreach ($members as $i => $row) {
                $rows[] = [
                    $i + 1,
                    $row->client->name,
                    $row->client->client_number,
                    $row->savings_balance,
                    $row->loan_principal,
                    $row->loan_interest,
                    $row->fd_amount,
                    $row->group_balance,
                    $row->share_units,
                    $row->share_total,
                ];
            }
            $rows[] = ['', 'TOTALS', '', $totals['savings'], $totals['loan_principal'], $totals['loan_interest'], $totals['fd_amount'], $totals['group_balance'], $totals['share_units'], $totals['share_total']];
            return $this->csvDownload($rows, 'member-summary-' . $asOf);
        }

        return view('reports.member-summary', compact('members', 'totals', 'shareValue', 'asOf'));
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
