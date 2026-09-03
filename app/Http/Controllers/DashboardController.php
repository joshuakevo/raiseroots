<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        session(['active_portal' => 'staff']);
        if (auth()->user()->hasRole('group_leader')) {
            return redirect()->route('group-portal.leader');
        }
        if (auth()->user()->hasRole('group_member')) {
            return redirect()->route('group-portal.member');
        }

        // ── Core stats ──────────────────────────────────────────────────────
        $totalSavings      = SavingsAccount::where('status', 'active')->sum('balance');
        $totalOutstanding  = Loan::whereIn('status', ['active', 'defaulted'])->sum('outstanding_principal');
        $totalClients      = Client::count();

        $stats = [
            'total_loans_issued'    => Loan::whereIn('status', ['active', 'closed', 'defaulted'])->count(),
            'total_outstanding'     => $totalOutstanding,
            'outstanding_interest'  => Loan::whereIn('status', ['active', 'defaulted'])->sum('outstanding_interest'),
            'total_interest_earned' => LoanRepayment::sum('interest_paid'),
            'overdue_loans'         => Loan::where('status', 'defaulted')->count(),
            'total_savings_balance' => $totalSavings,
            'active_clients'        => Client::where('status', 'active')->count(),
            'active_savings'        => SavingsAccount::where('status', 'active')->count(),
            'active_fds'            => FixedDeposit::where('status', 'active')->count(),
            'pending_loans'         => Loan::where('status', 'pending')->count(),
        ];

        // ── Upcoming FD Maturities ───────────────────────────────────────────
        $upcomingMaturities = FixedDeposit::with('client', 'product')
            ->where('status', 'active')
            ->where('maturity_date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('maturity_date')
            ->take(5)
            ->get();

        // ── Monthly Trends (last 6 months) ───────────────────────────────────
        $months      = collect();
        $monthLabels = collect();
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $months->push($m);
            $monthLabels->push($m->format('M Y'));
        }

        // Income vs Expenses (from GL accounts)
        $incomeAccountIds  = Account::where('account_type', 'revenue')->pluck('id');
        $expenseAccountIds = Account::where('account_type', 'expense')->pluck('id');

        $monthlyIncome = $months->map(function ($m) use ($incomeAccountIds) {
            return (float) TransactionLine::whereIn('account_id', $incomeAccountIds)
                ->whereHas('transaction', fn($q) => $q
                    ->whereYear('date', $m->year)
                    ->whereMonth('date', $m->month))
                ->sum('credit');
        });

        $monthlyExpenses = $months->map(function ($m) use ($expenseAccountIds) {
            return (float) TransactionLine::whereIn('account_id', $expenseAccountIds)
                ->whereHas('transaction', fn($q) => $q
                    ->whereYear('date', $m->year)
                    ->whereMonth('date', $m->month))
                ->sum('debit');
        });

        $monthlyProfit = $monthlyIncome->zip($monthlyExpenses)->map(fn($pair) => round($pair[0] - $pair[1], 2));

        // Cumulative savings & loans
        $monthlySavingsDeposits = $months->map(function ($m) {
            return (float) SavingsTransaction::where('transaction_type', 'deposit')
                ->whereYear('transaction_date', $m->year)
                ->whereMonth('transaction_date', $m->month)
                ->sum('amount');
        });

        $monthlyLoanDisbursements = $months->map(function ($m) {
            return (float) Loan::whereYear('disbursement_date', $m->year)
                ->whereMonth('disbursement_date', $m->month)
                ->whereIn('status', ['active', 'closed', 'defaulted'])
                ->sum('principal');
        });

        // ── Liquidity & Risk ─────────────────────────────────────────────────
        $loanToSavingsRatio = $totalSavings > 0 ? round($totalOutstanding / $totalSavings, 2) : 0;

        $parLoans = Loan::whereIn('status', ['active', 'defaulted'])
            ->whereHas('schedules', fn($q) => $q
                ->where('due_date', '<', now()->subDays(30)->toDateString())
                ->whereIn('status', ['pending', 'partial', 'overdue'])
            )->sum('outstanding_principal');
        $par30 = $totalOutstanding > 0 ? round(($parLoans / $totalOutstanding) * 100, 1) : 0;

        // ── Client Activity ──────────────────────────────────────────────────
        $activeBorrowers = Loan::whereIn('status', ['active', 'defaulted'])->distinct('client_id')->count('client_id');
        $activeSavers    = SavingsAccount::where('status', 'active')->where('balance', '>', 0)->distinct('client_id')->count('client_id');

        $dormantAccounts = SavingsAccount::where('status', 'active')
            ->whereDoesntHave('transactions', fn($q) => $q
                ->where('transaction_date', '>=', now()->subMonths(6)->toDateString())
            )->count();

        // ── Savings Insights ─────────────────────────────────────────────────
        $newSavingsThisMonth = SavingsAccount::whereYear('opened_date', now()->year)
            ->whereMonth('opened_date', now()->month)
            ->count();

        $depositsThisMonth = SavingsTransaction::where('transaction_type', 'deposit')
            ->whereYear('transaction_date', now()->year)
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');

        $withdrawalsThisMonth = SavingsTransaction::where('transaction_type', 'withdrawal')
            ->whereYear('transaction_date', now()->year)
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');

        $netCashFlow = $depositsThisMonth - $withdrawalsThisMonth;

        // ── Portfolio Insights ────────────────────────────────────────────────
        $lastMonthSavings = SavingsTransaction::where('transaction_type', 'deposit')
            ->whereYear('transaction_date', now()->subMonth()->year)
            ->whereMonth('transaction_date', now()->subMonth()->month)
            ->sum('amount');

        $lastMonthLoans = Loan::whereYear('disbursement_date', now()->subMonth()->year)
            ->whereMonth('disbursement_date', now()->subMonth()->month)
            ->whereIn('status', ['active', 'closed', 'defaulted'])
            ->sum('principal');

        $savingsGrowth = $lastMonthSavings > 0
            ? round((($depositsThisMonth - $lastMonthSavings) / $lastMonthSavings) * 100, 1)
            : 0;

        $thisMonthLoans = $monthlyLoanDisbursements->last() ?? 0;
        $loanGrowth = $lastMonthLoans > 0
            ? round((($thisMonthLoans - $lastMonthLoans) / $lastMonthLoans) * 100, 1)
            : 0;

        // ── Strategic Recommendations ─────────────────────────────────────────
        $recommendations = [];
        if ($loanToSavingsRatio > 1) {
            $recommendations[] = ['type' => 'danger', 'icon' => 'bi-exclamation-triangle-fill',
                'text' => 'High Risk: Loan portfolio exceeds savings balance. Focus on increasing member savings and consider loan restructuring.'];
        }
        if ($savingsGrowth > 0) {
            $recommendations[] = ['type' => 'success', 'icon' => 'bi-check-circle-fill',
                'text' => 'Positive Growth: Savings balance growing steadily. Maintain current savings mobilisation strategies.'];
        }
        if ($par30 == 0) {
            $recommendations[] = ['type' => 'success', 'icon' => 'bi-shield-fill-check',
                'text' => 'Clean Portfolio: No loans past due 30+ days. Excellent credit risk management.'];
        } elseif ($par30 > 10) {
            $recommendations[] = ['type' => 'danger', 'icon' => 'bi-exclamation-triangle-fill',
                'text' => "High PAR30 ({$par30}%): Over 10% of loan portfolio is at risk. Intensify collections and review lending criteria."];
        }
        if ($loanGrowth == 0 && $savingsGrowth > 0) {
            $recommendations[] = ['type' => 'info', 'icon' => 'bi-lightbulb-fill',
                'text' => 'Savings Focus: Strong savings growth with controlled lending. Consider expanding loan products to balance portfolio.'];
        }
        if (empty($recommendations)) {
            $recommendations[] = ['type' => 'secondary', 'icon' => 'bi-info-circle-fill',
                'text' => 'Stable Portfolio: Loan portfolio is stable with good balance between growth and risk management.'];
        }

        return view('dashboard', compact(
            'stats', 'upcomingMaturities',
            'monthLabels', 'monthlyIncome', 'monthlyExpenses', 'monthlyProfit',
            'monthlySavingsDeposits', 'monthlyLoanDisbursements',
            'loanToSavingsRatio', 'par30',
            'activeBorrowers', 'activeSavers', 'dormantAccounts', 'totalClients',
            'newSavingsThisMonth', 'depositsThisMonth', 'withdrawalsThisMonth', 'netCashFlow',
            'savingsGrowth', 'loanGrowth', 'recommendations'
        ));
    }
}
