<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Services\LoanService;

class ClientPortalController extends Controller
{
    public function __construct(protected LoanService $loanService) {}
    /** All clients linked to the logged-in user (pivot + same-email auto-discovery). */
    private function linkedClients()
    {
        $user = auth()->user();

        // Pivot-linked clients
        $clients = $user->portalClients;

        // Auto-discover: any client sharing this user's email (covers legacy client_id links
        // and cases where only one of multiple same-email clients was ever invited)
        if ($user->email) {
            $byEmail = \App\Models\Client::where('email', $user->email)->get();
            if ($byEmail->isNotEmpty()) {
                // Silently sync them into the pivot so future loads are consistent
                $user->portalClients()->syncWithoutDetaching($byEmail->pluck('id')->toArray());
                $clients = $clients->merge($byEmail)->unique('id');
            }
        }

        if ($clients->isEmpty()) {
            abort(403, 'No client profile linked to this account.');
        }

        return $clients;
    }

    /** The currently active client (from session, or first linked). */
    private function activeClient()
    {
        $clients  = $this->linkedClients();
        $activeId = session('portal_client_id');
        return $clients->firstWhere('id', $activeId) ?? $clients->first();
    }

    /** Switch the active client and redirect back to dashboard. */
    public function switchAccount(Client $client)
    {
        // Make sure the requested client is actually linked to this user
        abort_unless(auth()->user()->portalClients->contains($client->id), 403);
        session(['portal_client_id' => $client->id]);
        return redirect()->route('client-portal.dashboard');
    }

    public function overview()
    {
        session(['active_portal' => 'client']);
        $clients = $this->linkedClients();
        $client  = $this->activeClient();

        $clientIds = collect([$client->id]);

        $totalSavings  = SavingsAccount::where('client_id', $client->id)->sum('balance');
        $totalLoanDebt = Loan::where('client_id', $client->id)
            ->whereIn('status', ['active', 'defaulted'])
            ->selectRaw('SUM(outstanding_principal + outstanding_interest + outstanding_penalty) as total')
            ->value('total') ?? 0;
        $totalFdValue  = FixedDeposit::where('client_id', $client->id)->where('status', 'active')->sum('principal');
        $activeLoans   = Loan::where('client_id', $client->id)->whereIn('status', ['active', 'defaulted'])->count();

        // Monthly savings trend — last 6 months
        $savingsAccountIds = SavingsAccount::where('client_id', $client->id)->pluck('id');
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $net = SavingsTransaction::whereIn('savings_account_id', $savingsAccountIds)
                ->whereMonth('transaction_date', $month->month)
                ->whereYear('transaction_date', $month->year)
                ->selectRaw("SUM(CASE WHEN transaction_type='deposit' THEN amount ELSE -amount END) as net")
                ->value('net') ?? 0;
            $trend[] = ['label' => $month->format('M Y'), 'amount' => (float) $net];
        }

        // Next loan due
        $nextDue = LoanSchedule::whereHas('loan', fn($q) => $q->where('client_id', $client->id))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '>=', today())
            ->orderBy('due_date')
            ->first();

        // Overdue installments
        $overdueCount = LoanSchedule::whereHas('loan', fn($q) => $q->where('client_id', $client->id))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '<', today())
            ->count();

        // Savings accounts
        $savingsAccounts = SavingsAccount::where('client_id', $client->id)->with('product')->get();

        // Active FDs
        $activeFds = FixedDeposit::where('client_id', $client->id)
            ->where('status', 'active')->with('product')->orderBy('maturity_date')->get();

        // This month cashflow
        $depositsThisMonth    = SavingsTransaction::whereIn('savings_account_id', $savingsAccountIds)
            ->where('transaction_type', 'deposit')
            ->whereYear('transaction_date', now()->year)->whereMonth('transaction_date', now()->month)
            ->sum('amount');
        $withdrawalsThisMonth = SavingsTransaction::whereIn('savings_account_id', $savingsAccountIds)
            ->where('transaction_type', 'withdrawal')
            ->whereYear('transaction_date', now()->year)->whereMonth('transaction_date', now()->month)
            ->sum('amount');

        // Active loans list
        $activeLoansData = Loan::where('client_id', $client->id)
            ->whereIn('status', ['active', 'defaulted'])->with('product')->get();

        return view('client-portal.overview', compact(
            'client', 'clients', 'totalSavings', 'totalLoanDebt', 'totalFdValue',
            'activeLoans', 'trend', 'nextDue', 'overdueCount',
            'savingsAccounts', 'activeFds',
            'depositsThisMonth', 'withdrawalsThisMonth', 'activeLoansData'
        ));
    }

    public function accounts()
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        $client->load(['savingsAccounts.product', 'loans.product', 'fixedDeposits.product']);

        return view('client-portal.accounts', compact('client', 'clients'));
    }

    public function activity()
    {
        $clients           = $this->linkedClients();
        $client            = $this->activeClient();
        $savingsAccountIds = SavingsAccount::where('client_id', $client->id)->pluck('id');

        $transactions = SavingsTransaction::whereIn('savings_account_id', $savingsAccountIds)
            ->with('savingsAccount')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(30);

        return view('client-portal.activity', compact('client', 'clients', 'transactions'));
    }

    public function savingsStatement(SavingsAccount $savingsAccount)
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        abort_if((int) $savingsAccount->client_id !== (int) $client->id, 403);

        $savingsAccount->load('product');
        $transactions = $savingsAccount->transactions()
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(30);

        return view('client-portal.savings', compact('client', 'clients', 'savingsAccount', 'transactions'));
    }

    public function loanStatement(Loan $loan)
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        abort_if((int) $loan->client_id !== (int) $client->id, 403);

        $loan->load('product', 'schedules');
        $repayments       = $loan->repayments()->orderByDesc('payment_date')->orderByDesc('id')->get();
        $schedule         = $loan->schedules()->orderBy('due_date')->get();
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);

        return view('client-portal.loan', compact('client', 'clients', 'loan', 'repayments', 'schedule', 'currentPenalty', 'penaltyBreakdown'));
    }

    public function fdStatement(FixedDeposit $fixedDeposit)
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        abort_if((int) $fixedDeposit->client_id !== (int) $client->id, 403);

        $fixedDeposit->load('product');

        return view('client-portal.fd', compact('client', 'clients', 'fixedDeposit'));
    }

    public function savingsStatementPdf(SavingsAccount $savingsAccount)
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        abort_if((int) $savingsAccount->client_id !== (int) $client->id, 403);

        $savingsAccount->load('product');
        $transactions = $savingsAccount->transactions()
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.savings-statement', [
            'account'      => $savingsAccount,
            'client'       => $client,
            'transactions' => $transactions,
            'fromDate'     => null,
            'toDate'       => null,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("savings-statement-{$savingsAccount->account_number}.pdf");
    }

    public function loanStatementPdf(Loan $loan)
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        abort_if((int) $loan->client_id !== (int) $client->id, 403);

        $loan->load('product', 'schedules');
        $repayments       = $loan->repayments()->orderByDesc('payment_date')->orderByDesc('id')->get();
        $schedule         = $loan->schedules()->orderBy('due_date')->get();
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.loan-statement', compact(
            'client', 'loan', 'repayments', 'schedule', 'currentPenalty', 'penaltyBreakdown'
        ))->setPaper('a4', 'portrait');

        return $pdf->download("loan-statement-{$loan->loan_number}.pdf");
    }

    public function fdStatementPdf(FixedDeposit $fixedDeposit)
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        abort_if((int) $fixedDeposit->client_id !== (int) $client->id, 403);

        $fixedDeposit->load('product');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.fd-certificate', compact('client', 'fixedDeposit'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("fd-statement-{$fixedDeposit->deposit_number}.pdf");
    }
}
