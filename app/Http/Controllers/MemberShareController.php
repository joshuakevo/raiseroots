<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Client;
use App\Models\MemberShare;
use App\Models\ShareTransaction;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberShareController extends Controller
{
    public function __construct(protected AccountingService $accounting) {}

    // ── Global Shares Index ───────────────────────────────────────────

    public function index(Request $request)
    {
        $shares = MemberShare::with('client')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('share_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")))
            ->orderByDesc('created_at')
            ->paginate(30);

        $totals = MemberShare::whereNotIn('status', ['liquidated'])->selectRaw('
            COUNT(*) as total_shares,
            SUM(share_value) as total_value,
            SUM(amount_paid) as total_paid,
            SUM(share_value - amount_paid) as total_outstanding
        ')->first();

        return view('shares.index', compact('shares', 'totals'));
    }

    // ── Share Statement for a client ─────────────────────────────────

    public function statement(Client $client)
    {
        $shares = $client->shares()->with('transactions.createdBy')->get();

        $totals = [
            'share_value'  => $shares->whereNotIn('status', ['liquidated'])->sum('share_value'),
            'amount_paid'  => $shares->whereNotIn('status', ['liquidated'])->sum('amount_paid'),
            'outstanding'  => $shares->whereNotIn('status', ['liquidated'])->sum(fn($s) => $s->balance),
            'liquidated'   => $shares->where('status', 'liquidated')->sum('amount_paid'),
        ];

        return view('shares.statement', compact('client', 'shares', 'totals'));
    }

    public function statementPdf(Client $client)
    {
        $shares = $client->shares()->with('transactions.createdBy')->get();

        $totals = [
            'share_value'  => $shares->whereNotIn('status', ['liquidated'])->sum('share_value'),
            'amount_paid'  => $shares->whereNotIn('status', ['liquidated'])->sum('amount_paid'),
            'outstanding'  => $shares->whereNotIn('status', ['liquidated'])->sum(fn($s) => $s->balance),
            'liquidated'   => $shares->where('status', 'liquidated')->sum('amount_paid'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.share-statement', compact('client', 'shares', 'totals'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("share-statement-{$client->client_number}.pdf");
    }

    // ── Membership Fee Payment ────────────────────────────────────────

    public function payMembership(Request $request, Client $client)
    {
        $isSavings = $request->payment_source_account_id === 'savings';

        $request->validate([
            'amount'                    => 'required|numeric|min:1',
            'payment_date'              => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'payment_source_account_id' => 'required',
            'savings_account_id'        => $isSavings ? 'required|integer' : 'nullable|integer',
            'reference'                 => 'required|string|max:100|unique:transactions,reference',
            'notes'                     => 'nullable|string|max:255',
        ]);

        $amount  = $request->amount;
        $balance = $client->membership_fee - $client->membership_fee_paid;

        if ($amount > $balance) {
            return back()->withInput()->with('error', 'Payment amount exceeds outstanding balance of ' . number_format($balance, 2));
        }

        $savingsAccount = $isSavings ? $this->resolveSavingsAccount($request, $client, $amount) : null;
        if ($savingsAccount === false) return back()->withInput()->with('error', session()->pull('_sav_err'));

        $glAccountId = $isSavings ? null : (int) $request->payment_source_account_id;

        DB::transaction(function () use ($client, $amount, $request, $savingsAccount, $glAccountId) {
            $newPaid   = $client->membership_fee_paid + $amount;
            $newStatus = $newPaid >= $client->membership_fee ? 'paid' : 'partial';
            $client->update(['membership_fee_paid' => $newPaid, 'membership_fee_status' => $newStatus]);

            if ($savingsAccount) {
                $this->deductFromSavings($savingsAccount, $amount, $request->payment_date,
                    "Membership fee — {$client->client_number}",
                    $this->getMembershipFeeIncomeAccount(), 'client', $client->id, $request->reference);
            } else {
                $this->accounting->post($request->payment_date,
                    "Membership fee payment — {$client->client_number}",
                    [
                        ['account_id' => $glAccountId ?? $this->getCashAccount(), 'debit' => $amount, 'credit' => 0, 'description' => "Membership fee — {$client->name}"],
                        ['account_id' => $this->getMembershipFeeIncomeAccount(), 'debit' => 0, 'credit' => $amount, 'description' => "Membership fee — {$client->name}"],
                    ], 'client', $client->id, $request->reference);
            }
        });

        return back()->with('success', 'Membership fee payment of ' . number_format($amount, 0) . ' recorded.');
    }

    // ── Add Share Subscription ────────────────────────────────────────

    public function addShare(Request $request, Client $client)
    {
        $request->validate([
            'share_value' => 'required|numeric|min:1000',
            'notes'       => 'nullable|string|max:255',
        ]);

        MemberShare::create([
            'client_id'    => $client->id,
            'share_number' => $this->generateShareNumber(),
            'share_value'  => $request->share_value,
            'amount_paid'  => 0,
            'status'       => 'unpaid',
            'notes'        => $request->notes,
            'created_by'   => auth()->id(),
        ]);

        return back()->with('success', 'New share subscription added.');
    }

    // ── Share Payment ─────────────────────────────────────────────────

    public function payShare(Request $request, Client $client, MemberShare $share)
    {
        abort_if($share->client_id !== $client->id, 403);
        abort_if($share->isLiquidated(), 403, 'Cannot pay a liquidated share.');

        $isSavings = $request->payment_source_account_id === 'savings';

        $request->validate([
            'amount'                    => 'required|numeric|min:1',
            'payment_date'              => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'payment_source_account_id' => 'required',
            'savings_account_id'        => $isSavings ? 'required|integer' : 'nullable|integer',
            'reference'                 => 'required|string|max:100|unique:transactions,reference',
            'notes'                     => 'nullable|string|max:255',
        ]);

        $amount  = $request->amount;
        $balance = $share->share_value - $share->amount_paid;

        if ($amount > $balance) {
            return back()->withInput()->with('error', 'Payment exceeds share balance of ' . number_format($balance, 0));
        }

        $savingsAccount = $isSavings ? $this->resolveSavingsAccount($request, $client, $amount) : null;
        if ($savingsAccount === false) return back()->withInput()->with('error', session()->pull('_sav_err'));

        $glAccountId = $isSavings ? null : (int) $request->payment_source_account_id;

        DB::transaction(function () use ($share, $client, $amount, $request, $savingsAccount, $glAccountId, $isSavings) {
            $newPaid   = $share->amount_paid + $amount;
            $newStatus = $newPaid >= $share->share_value ? 'paid' : 'partial';
            $share->update(['amount_paid' => $newPaid, 'status' => $newStatus, 'notes' => $request->notes ?? $share->notes]);

            if ($savingsAccount) {
                $journal = $this->deductFromSavings($savingsAccount, $amount, $request->payment_date,
                    "Share payment — {$share->share_number}",
                    $this->getShareCapitalAccount(), 'member_share', $share->id, $request->reference);
            } else {
                $journal = $this->accounting->post($request->payment_date,
                    "Share payment — {$share->share_number}",
                    [
                        ['account_id' => $glAccountId ?? $this->getCashAccount(), 'debit' => $amount, 'credit' => 0, 'description' => "Share payment — {$client->name}"],
                        ['account_id' => $this->getShareCapitalAccount(), 'debit' => 0, 'credit' => $amount, 'description' => "Share capital — {$share->share_number}"],
                    ], 'member_share', $share->id, $request->reference);
            }

            ShareTransaction::create([
                'share_id'               => $share->id,
                'client_id'              => $client->id,
                'type'                   => 'payment',
                'amount'                 => $amount,
                'transaction_date'       => $request->payment_date,
                'reference'              => $request->reference,
                'payment_method'         => $isSavings ? 'savings' : 'direct',
                'notes'                  => $request->notes,
                'journal_transaction_id' => $journal->id ?? null,
                'created_by'             => auth()->id(),
            ]);
        });

        return back()->with('success', 'Share payment of ' . number_format($amount, 0) . ' recorded.');
    }

    // ── Revalue a Share ───────────────────────────────────────────────

    public function revalue(Request $request, Client $client, MemberShare $share)
    {
        abort_if($share->client_id !== $client->id, 403);
        abort_if($share->isLiquidated(), 403, 'Cannot revalue a liquidated share.');

        $request->validate([
            'new_share_value' => 'required|numeric|min:1',
            'effective_date'  => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'notes'           => 'nullable|string|max:255',
        ]);

        $oldValue = $share->share_value;
        $newValue = (float) $request->new_share_value;

        if (abs($oldValue - $newValue) < 0.01) {
            return back()->with('error', 'New value is the same as the current value.');
        }

        DB::transaction(function () use ($share, $client, $oldValue, $newValue, $request) {
            $amountPaidBefore = $share->amount_paid;
            $newPaid   = min($share->amount_paid, $newValue); // paid can't exceed new value
            $newStatus = $newPaid >= $newValue ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');

            $share->update([
                'share_value' => $newValue,
                'amount_paid' => $newPaid,
                'status'      => $newStatus,
            ]);

            // Journal: record the revaluation as a share capital adjustment
            $delta = $newValue - $oldValue;
            if ($delta > 0) {
                // Increase: DR Share Subscriptions Receivable / CR Share Capital
                $journal = $this->accounting->post($request->effective_date,
                    "Share revaluation — {$share->share_number} ({$oldValue} → {$newValue})",
                    [
                        ['account_id' => $this->getShareReceivableAccount(), 'debit' => $delta, 'credit' => 0, 'description' => "Share revaluation increase — {$client->name}"],
                        ['account_id' => $this->getShareCapitalAccount(),    'debit' => 0, 'credit' => $delta, 'description' => "Share capital increase — {$share->share_number}"],
                    ], 'member_share', $share->id);
            } else {
                // Decrease: DR Share Capital / CR Share Subscriptions Receivable
                $delta = abs($delta);
                $journal = $this->accounting->post($request->effective_date,
                    "Share revaluation — {$share->share_number} ({$oldValue} → {$newValue})",
                    [
                        ['account_id' => $this->getShareCapitalAccount(),    'debit' => $delta, 'credit' => 0, 'description' => "Share capital decrease — {$share->share_number}"],
                        ['account_id' => $this->getShareReceivableAccount(), 'debit' => 0, 'credit' => $delta, 'description' => "Share revaluation decrease — {$client->name}"],
                    ], 'member_share', $share->id);
            }

            ShareTransaction::create([
                'share_id'               => $share->id,
                'client_id'              => $client->id,
                'type'                   => 'revaluation',
                'amount'                 => abs($newValue - $oldValue),
                'old_value'              => $oldValue,
                'new_value'              => $newValue,
                'amount_paid_before'     => $amountPaidBefore,
                'transaction_date'       => $request->effective_date,
                'notes'                  => $request->notes,
                'journal_transaction_id' => $journal->id,
                'created_by'             => auth()->id(),
            ]);
        });

        return back()->with('success', "Share {$share->share_number} revalued from " . number_format($oldValue, 0) . " to " . number_format($newValue, 0) . ".");
    }

    // ── Bulk Revalue All Active Shares ────────────────────────────────

    public function revalueAll(Request $request)
    {
        $request->validate([
            'new_share_value' => 'required|numeric|min:1',
            'effective_date'  => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'notes'           => 'nullable|string|max:255',
        ]);

        $newValue = (float) $request->new_share_value;
        $shares   = MemberShare::whereNotIn('status', ['liquidated'])->with('client')->get();
        $count    = 0;

        DB::transaction(function () use ($shares, $newValue, $request, &$count) {
            foreach ($shares as $share) {
                $oldValue = $share->share_value;
                if (abs($oldValue - $newValue) < 0.01) continue;

                $amountPaidBefore = $share->amount_paid;
                $newPaid   = min($share->amount_paid, $newValue);
                $newStatus = $newPaid >= $newValue ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
                $share->update(['share_value' => $newValue, 'amount_paid' => $newPaid, 'status' => $newStatus]);

                $delta = $newValue - $oldValue;
                if ($delta > 0) {
                    $journal = $this->accounting->post($request->effective_date,
                        "Bulk share revaluation — {$share->share_number}",
                        [
                            ['account_id' => $this->getShareReceivableAccount(), 'debit' => $delta, 'credit' => 0, 'description' => "Revaluation — {$share->client->name}"],
                            ['account_id' => $this->getShareCapitalAccount(),    'debit' => 0, 'credit' => $delta, 'description' => "Share capital increase — {$share->share_number}"],
                        ], 'member_share', $share->id);
                } else {
                    $d = abs($delta);
                    $journal = $this->accounting->post($request->effective_date,
                        "Bulk share revaluation — {$share->share_number}",
                        [
                            ['account_id' => $this->getShareCapitalAccount(),    'debit' => $d, 'credit' => 0, 'description' => "Share capital decrease — {$share->share_number}"],
                            ['account_id' => $this->getShareReceivableAccount(), 'debit' => 0, 'credit' => $d, 'description' => "Revaluation — {$share->client->name}"],
                        ], 'member_share', $share->id);
                }

                ShareTransaction::create([
                    'share_id'               => $share->id,
                    'client_id'              => $share->client_id,
                    'type'                   => 'revaluation',
                    'amount'                 => abs($newValue - $oldValue),
                    'old_value'              => $oldValue,
                    'new_value'              => $newValue,
                    'amount_paid_before'     => $amountPaidBefore,
                    'transaction_date'       => $request->effective_date,
                    'notes'                  => $request->notes ?? 'Bulk revaluation',
                    'journal_transaction_id' => $journal->id,
                    'created_by'             => auth()->id(),
                ]);
                $count++;
            }
        });

        return back()->with('success', "All {$count} share(s) revalued to " . number_format($newValue, 0) . ".");
    }

    // ── Liquidate a Share ─────────────────────────────────────────────

    public function liquidate(Request $request, Client $client, MemberShare $share)
    {
        abort_if($share->client_id !== $client->id, 403);
        abort_if($share->isLiquidated(), 403, 'Already liquidated.');

        $request->validate([
            'liquidation_date'   => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'payout_method'      => 'required|in:cash,savings',
            'savings_account_id' => 'required_if:payout_method,savings|nullable|exists:savings_accounts,id',
            'liquidation_notes'  => 'nullable|string|max:255',
        ]);

        $payout = $share->amount_paid; // only pay back what was actually paid

        if ($payout <= 0) {
            return back()->with('error', 'No paid amount to liquidate.');
        }

        DB::transaction(function () use ($share, $client, $payout, $request) {
            if ($request->payout_method === 'savings') {
                $savingsAccount = SavingsAccount::findOrFail($request->savings_account_id);
                $balBefore = $savingsAccount->balance;
                $balAfter  = $balBefore + $payout;

                // DR Share Capital / CR Savings Liability
                $journal = $this->accounting->post($request->liquidation_date,
                    "Share liquidation payout — {$share->share_number}",
                    [
                        ['account_id' => $this->getShareCapitalAccount(),                                 'debit' => $payout, 'credit' => 0,      'description' => "Share liquidation — {$client->name}"],
                        ['account_id' => $savingsAccount->product->savings_liability_account_id, 'debit' => 0,       'credit' => $payout, 'description' => "Share payout to savings — {$savingsAccount->account_number}"],
                    ], 'member_share', $share->id);

                $savingsAccount->update(['balance' => $balAfter]);

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => 'deposit',
                    'amount'             => $payout,
                    'balance_before'     => $balBefore,
                    'balance_after'      => $balAfter,
                    'transaction_date'   => $request->liquidation_date,
                    'reference'          => $journal->reference,
                    'description'        => "Share liquidation — {$share->share_number}",
                    'transaction_id'     => $journal->id,
                    'created_by'         => auth()->id(),
                ]);
            } else {
                // DR Share Capital / CR Cash
                $journal = $this->accounting->post($request->liquidation_date,
                    "Share liquidation payout — {$share->share_number}",
                    [
                        ['account_id' => $this->getShareCapitalAccount(), 'debit' => $payout, 'credit' => 0,      'description' => "Share liquidation — {$client->name}"],
                        ['account_id' => $this->getCashAccount(),          'debit' => 0,       'credit' => $payout, 'description' => "Share payout cash — {$share->share_number}"],
                    ], 'member_share', $share->id);
            }

            $share->update([
                'status'             => 'liquidated',
                'liquidated_at'      => $request->liquidation_date,
                'liquidation_notes'  => $request->liquidation_notes,
                'liquidated_by'      => auth()->id(),
            ]);

            ShareTransaction::create([
                'share_id'               => $share->id,
                'client_id'              => $client->id,
                'type'                   => 'liquidation',
                'amount'                 => $payout,
                'transaction_date'       => $request->liquidation_date,
                'payment_method'         => $request->payout_method,
                'notes'                  => $request->liquidation_notes,
                'journal_transaction_id' => $journal->id,
                'created_by'             => auth()->id(),
            ]);
        });

        return back()->with('success', "Share {$share->share_number} liquidated. Payout of " . number_format($payout, 0) . " processed.");
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function resolveSavingsAccount(Request $request, Client $client, float $amount)
    {
        if ($request->payment_source_account_id !== 'savings' && $request->payment_source !== 'savings' && $request->payout_method !== 'savings') return null;
        $savingsAccount = SavingsAccount::where('id', $request->savings_account_id)
            ->where('client_id', $client->id)->where('status', 'active')->first();
        if (!$savingsAccount) {
            session(['_sav_err' => 'Invalid or inactive savings account.']);
            return false;
        }
        $minBalance  = $savingsAccount->product->minimum_balance ?? 0;
        $balAsOfDate = SavingsTransaction::where('savings_account_id', $savingsAccount->id)
            ->where('transaction_date', '<=', $request->payment_date ?? $request->effective_date)
            ->orderByDesc('transaction_date')->orderByDesc('id')->value('balance_after') ?? 0;
        if (($balAsOfDate - $amount) < $minBalance) {
            session(['_sav_err' => 'Insufficient savings balance. Available: ' . number_format(max(0, $balAsOfDate - $minBalance), 0)]);
            return false;
        }
        return $savingsAccount;
    }

    private function deductFromSavings(SavingsAccount $account, float $amount, string $date, string $desc, int $creditAccountId, string $module, int $moduleId, ?string $reference = null)
    {
        $balBefore = $account->balance;
        $balAfter  = $balBefore - $amount;

        $journal = $this->accounting->post($date, $desc,
            [
                ['account_id' => $account->product->savings_liability_account_id, 'debit' => $amount, 'credit' => 0,       'description' => $desc],
                ['account_id' => $creditAccountId,                                 'debit' => 0,       'credit' => $amount, 'description' => $desc],
            ], $module, $moduleId, $reference);

        $account->update(['balance' => $balAfter]);

        SavingsTransaction::create([
            'savings_account_id' => $account->id,
            'transaction_type'   => 'withdrawal',
            'amount'             => $amount,
            'balance_before'     => $balBefore,
            'balance_after'      => $balAfter,
            'transaction_date'   => $date,
            'reference'          => $reference ?? $journal->reference,
            'description'        => $desc,
            'transaction_id'     => $journal->id,
            'created_by'         => auth()->id(),
        ]);

        return $journal;
    }

    protected function getCashAccount(): int              { return Account::where('account_code', '1001')->value('id') ?? 1; }
    protected function getMembershipFeeIncomeAccount(): int { return Account::where('account_code', '4008')->value('id') ?? 1; }
    protected function getShareCapitalAccount(): int       { return Account::where('account_code', '3001')->value('id') ?? 1; }
    protected function getShareReceivableAccount(): int
    {
        // Share subscriptions receivable — use member shares receivable or fall back to general receivable
        return Account::where('account_code', '1102')->value('id')
            ?? Account::where('account_code', '1101')->value('id') ?? 1;
    }

    protected function generateShareNumber(): string
    {
        $year = now()->format('Y');
        $last = MemberShare::withoutGlobalScope('branch')->whereYear('created_at', $year)->count() + 1;
        return 'SHR-' . $year . '-' . str_pad($last, 5, '0', STR_PAD_LEFT);
    }
}
