<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\TransactionLine;
use App\Models\Account;
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
        $totalOutstanding = Loan::whereIn('status', ['active', 'defaulted'])->sum('outstanding_principal');
        $issuedLoans      = Loan::whereIn('status', ['active', 'closed', 'defaulted']);
        $totalLoansIssued = (clone $issuedLoans)->count();

        $stats = [
            'total_loans_issued'    => $totalLoansIssued,
            'active_loans'          => Loan::where('status', 'active')->count(),
            'total_outstanding'     => $totalOutstanding,
            'outstanding_interest'  => Loan::whereIn('status', ['active', 'defaulted'])->sum('outstanding_interest'),
            'total_interest_earned' => LoanRepayment::sum('interest_paid'),
            'overdue_loans'         => Loan::where('status', 'defaulted')->count(),
            'pending_loans'         => Loan::where('status', 'pending')->count(),
            'approved_loans'        => Loan::where('status', 'approved')->count(),
            'average_loan_size'     => $totalLoansIssued > 0 ? (clone $issuedLoans)->avg('principal') : 0,
        ];

        // ── Loan Portfolio by Status ─────────────────────────────────────────
        $loanStatusCounts = Loan::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $loanStatusBreakdown = collect(['pending', 'active', 'closed', 'defaulted'])
            ->mapWithKeys(fn ($status) => [$status => (int) ($loanStatusCounts[$status] ?? 0)]);

        // ── Upcoming Installments Due ─────────────────────────────────────────
        $upcomingInstallments = LoanSchedule::whereIn('status', ['pending', 'partial'])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->whereHas('loan', fn ($q) => $q->whereIn('status', ['active', 'defaulted']))
            ->with('loan.client')
            ->orderBy('due_date')
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

        $monthlyLoanDisbursements = $months->map(function ($m) {
            return (float) Loan::whereYear('disbursement_date', $m->year)
                ->whereMonth('disbursement_date', $m->month)
                ->whereIn('status', ['active', 'closed', 'defaulted'])
                ->sum('principal');
        });

        // ── Risk ───────────────────────────────────────────────────────────────
        $parLoans = Loan::whereIn('status', ['active', 'defaulted'])
            ->whereHas('schedules', fn($q) => $q
                ->where('due_date', '<', now()->subDays(30)->toDateString())
                ->whereIn('status', ['pending', 'partial', 'overdue'])
            )->sum('outstanding_principal');
        $par30 = $totalOutstanding > 0 ? round(($parLoans / $totalOutstanding) * 100, 1) : 0;

        $defaultRate = $totalLoansIssued > 0
            ? round(($loanStatusBreakdown['defaulted'] / $totalLoansIssued) * 100, 1)
            : 0;

        // ── Client Activity ──────────────────────────────────────────────────
        $totalClients    = Client::count();
        $activeBorrowers = Loan::whereIn('status', ['active', 'defaulted'])->distinct('client_id')->count('client_id');

        // ── Top Repeat Borrowers ─────────────────────────────────────────────
        $topBorrowers = Loan::select('client_id')
            ->selectRaw('COUNT(*) as loan_count')
            ->selectRaw('SUM(principal) as total_borrowed')
            ->selectRaw("SUM(CASE WHEN status IN ('active','defaulted') THEN outstanding_principal ELSE 0 END) as current_outstanding")
            ->whereIn('status', ['active', 'closed', 'defaulted'])
            ->groupBy('client_id')
            ->orderByDesc('loan_count')
            ->orderByDesc('total_borrowed')
            ->with('client')
            ->take(5)
            ->get();

        // ── Portfolio Insights ────────────────────────────────────────────────
        $lastMonthLoans = Loan::whereYear('disbursement_date', now()->subMonth()->year)
            ->whereMonth('disbursement_date', now()->subMonth()->month)
            ->whereIn('status', ['active', 'closed', 'defaulted'])
            ->sum('principal');

        $thisMonthLoans = $monthlyLoanDisbursements->last() ?? 0;
        $loanGrowth = $lastMonthLoans > 0
            ? round((($thisMonthLoans - $lastMonthLoans) / $lastMonthLoans) * 100, 1)
            : 0;

        // ── Strategic Recommendations ─────────────────────────────────────────
        $recommendations = [];
        if ($par30 == 0) {
            $recommendations[] = ['type' => 'success', 'icon' => 'bi-shield-fill-check',
                'text' => 'Clean Portfolio: No loans past due 30+ days. Excellent credit risk management.'];
        } elseif ($par30 > 10) {
            $recommendations[] = ['type' => 'danger', 'icon' => 'bi-exclamation-triangle-fill',
                'text' => "High PAR30 ({$par30}%): Over 10% of loan portfolio is at risk. Intensify collections and review lending criteria."];
        }
        if ($defaultRate > 15) {
            $recommendations[] = ['type' => 'danger', 'icon' => 'bi-exclamation-triangle-fill',
                'text' => "High Default Rate ({$defaultRate}%): A large share of loans ever issued are currently defaulted. Review lending criteria and intensify collections."];
        } elseif ($defaultRate == 0 && $totalLoansIssued > 0) {
            $recommendations[] = ['type' => 'success', 'icon' => 'bi-check-circle-fill',
                'text' => 'No defaults on record: Every loan issued is either performing or fully closed.'];
        }
        if ($loanGrowth > 0) {
            $recommendations[] = ['type' => 'info', 'icon' => 'bi-lightbulb-fill',
                'text' => "Growing Portfolio: Disbursements are up {$loanGrowth}% versus last month."];
        } elseif ($loanGrowth < 0) {
            $recommendations[] = ['type' => 'warning', 'icon' => 'bi-graph-down-arrow',
                'text' => "Slower Disbursements: Down " . abs($loanGrowth) . "% versus last month — worth checking demand or approval pipeline."];
        }
        if (empty($recommendations)) {
            $recommendations[] = ['type' => 'secondary', 'icon' => 'bi-info-circle-fill',
                'text' => 'Stable Portfolio: Loan portfolio is stable with good balance between growth and risk management.'];
        }

        return view('dashboard', compact(
            'stats', 'loanStatusBreakdown', 'upcomingInstallments', 'topBorrowers',
            'monthLabels', 'monthlyIncome', 'monthlyExpenses', 'monthlyProfit', 'monthlyLoanDisbursements',
            'par30', 'defaultRate',
            'activeBorrowers', 'totalClients',
            'loanGrowth', 'recommendations'
        ));
    }
}
