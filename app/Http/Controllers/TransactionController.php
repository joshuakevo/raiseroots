<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\FixedDepositProduct;
use App\Models\GroupTransaction;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\MemberShare;
use App\Models\PayrollRun;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\ShareTransaction;
use App\Models\Transaction;
use App\Services\AccountingService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function __construct(protected AccountingService $accountingService) {}

    public function index(Request $request)
    {
        $transactions = Transaction::with('createdBy')
            ->withSum('lines', 'debit')
            ->when($request->from_date, fn($q) => $q->where('date', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->where('date', '<=', $request->to_date))
            ->when($request->reference, fn($q) => $q->where('reference', 'like', "%{$request->reference}%"))
            ->latest('date')
            ->paginate(30);

        return view('transactions.index', compact('transactions'));
    }

    public function create()
    {
        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();
        $clients  = \App\Models\Client::orderBy('name')->get(['id', 'client_number', 'name']);
        $lineRows = $this->normalizedJournalLineRowsFromOld();

        return view('transactions.create', compact('accounts', 'clients', 'lineRows'));
    }

    /**
     * @return list<array{account_id: string, client_id: string, description: string, debit: string, credit: string}>
     */
    private function normalizedJournalLineRowsFromOld(): array
    {
        $default = ['account_id' => '', 'client_id' => '', 'description' => '', 'debit' => '0', 'credit' => '0'];
        $raw     = old('lines');
        if (!is_array($raw)) {
            return [$default, $default];
        }

        $lineRows = [];
        foreach (array_values($raw) as $row) {
            $lineRows[] = array_merge($default, is_array($row) ? $row : []);
        }
        while (count($lineRows) < 2) {
            $lineRows[] = $default;
        }

        return $lineRows;
    }

    public function store(Request $request)
    {
        $request->validate([
            'date'              => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'description'       => 'required|string|max:500',
            'reference'         => 'required|string|max:100|unique:transactions,reference',
            'lines'              => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.client_id'  => 'nullable|exists:clients,id',
            'lines.*.debit'      => 'required|numeric|min:0',
            'lines.*.credit'     => 'required|numeric|min:0',
        ]);

        $allLines = array_values($request->lines);
        $this->assertJournalSubLedgerTargetsExist($allLines);

        $lines = array_values(array_filter($request->lines, fn($l) => ($l['debit'] + $l['credit']) > 0));

        $transaction = $this->accountingService->post(
            $request->date,
            $request->description,
            $lines,
            'manual',
            null,
            $request->reference ?: null
        );

        // Automatically update sub-ledgers for any line tagged with a client
        $this->syncSubLedgersFromLines($transaction, $lines, $request->date, $request->description);

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaction posted successfully.');
    }

    /**
     * Before posting: every line with a client and amounts on a sub-ledger GL must have a matching
     * open record (same rules as syncSubLedgersFromLines). Uses full submitted line list so row
     * numbers match the form. $allLines must be 0-indexed in display order.
     */
    private function assertJournalSubLedgerTargetsExist(array $allLines): void
    {
        if ($allLines === []) {
            return;
        }

        $accountIds = array_values(array_unique(array_map(
            'intval',
            array_filter(array_column($allLines, 'account_id'), fn($id) => $id !== null && $id !== '')
        )));
        if ($accountIds === []) {
            return;
        }

        $codeMap = Account::whereIn('id', $accountIds)->pluck('account_code', 'id');

        $savingsByLiability = SavingsProduct::whereIn('savings_liability_account_id', $accountIds)
            ->get()
            ->groupBy('savings_liability_account_id');

        $loanProductsByReceivable = LoanProduct::whereIn('receivable_account_id', $accountIds)
            ->get()
            ->groupBy('receivable_account_id');

        $fdProductsByLiability = FixedDepositProduct::whereIn('deposit_liability_account_id', $accountIds)
            ->get()
            ->groupBy('deposit_liability_account_id');

        $errors = [];

        foreach ($allLines as $idx => $line) {
            $clientId = $line['client_id'] ?? null;
            if (!$clientId) {
                continue;
            }

            $accId  = (int) ($line['account_id'] ?? 0);
            $code   = $codeMap[$accId] ?? null;
            $debit  = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $n         = $idx + 1;
            $client    = Client::find($clientId);
            $label     = $client ? "{$client->name} ({$client->client_number})" : "Client #{$clientId}";
            $glAccount = Account::find($accId);
            $acctLabel = $glAccount ? "{$glAccount->account_code} — {$glAccount->account_name}" : "Account #{$accId}";

            // ── Savings liability ───────────────────────────────────────────
            $isSavingsLiability = $savingsByLiability->has($accId)
                || ($code && in_array($code, ['2001', '2005'], true));
            if ($isSavingsLiability) {
                $savingsAccount = null;
                if ($savingsByLiability->has($accId)) {
                    $productIds = $savingsByLiability->get($accId)->pluck('id');
                    $savingsAccount = SavingsAccount::where('client_id', $clientId)
                        ->whereIn('product_id', $productIds)
                        ->where('status', 'active')
                        ->orderBy('id')
                        ->first();
                } else {
                    $savingsAccount = SavingsAccount::where('client_id', $clientId)
                        ->where('status', 'active')
                        ->orderBy('id')
                        ->first();
                }
                if (!$savingsAccount) {
                    $errors[] = "Line {$n}: {$label} has no active savings account for {$acctLabel}. Open a matching savings account or clear the client.";
                }
                continue;
            }

            // ── Fixed deposit liability ─────────────────────────────────────
            if ($fdProductsByLiability->has($accId)) {
                $productIds = $fdProductsByLiability->get($accId)->pluck('id');
                $fd         = FixedDeposit::where('client_id', $clientId)
                    ->whereIn('product_id', $productIds)
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->first();
                if (!$fd) {
                    $errors[] = "Line {$n}: {$label} has no active fixed deposit for {$acctLabel}. Create or activate an FD or clear the client.";
                }
                continue;
            }

            // ── Share capital 3001 ──────────────────────────────────────────
            if ($code === '3001') {
                if ($credit > 0) {
                    $share = MemberShare::where('client_id', $clientId)
                        ->whereIn('status', ['unpaid', 'partial'])
                        ->orderBy('id')
                        ->first();
                    if (!$share) {
                        $errors[] = "Line {$n}: {$label} has no unpaid/partial share subscription for {$acctLabel}. Record shares first or clear the client.";
                    }
                } elseif ($debit > 0) {
                    $share = MemberShare::where('client_id', $clientId)
                        ->where('status', '!=', 'liquidated')
                        ->where('amount_paid', '>', 0)
                        ->orderByDesc('id')
                        ->first();
                    if (!$share) {
                        $errors[] = "Line {$n}: {$label} has no share balance to refund against for {$acctLabel}. Clear the client or use a member with share payments.";
                    }
                }
                continue;
            }

            // ── Loan receivable (credit = principal) ────────────────────────
            if ($credit > 0) {
                $isLoanReceivable = $loanProductsByReceivable->has($accId)
                    || ($code && in_array($code, ['1101', '1102', '1103'], true));
                if ($isLoanReceivable) {
                    $loan = null;
                    if ($loanProductsByReceivable->has($accId)) {
                        $pids = $loanProductsByReceivable->get($accId)->pluck('id');
                        $loan = Loan::where('client_id', $clientId)
                            ->whereIn('loan_product_id', $pids)
                            ->whereIn('status', ['active', 'defaulted'])
                            ->orderByDesc('id')
                            ->first();
                    }
                    if (!$loan && $code && in_array($code, ['1101', '1102', '1103'], true)) {
                        $loan = Loan::where('client_id', $clientId)
                            ->whereIn('status', ['active', 'defaulted'])
                            ->orderByDesc('id')
                            ->first();
                    }
                    if (!$loan) {
                        $errors[] = "Line {$n}: {$label} has no active loan for {$acctLabel}. Clear the client or pick a member with an open loan.";
                    }
                }
            }

            // ── Membership fee income ─────────────────────────────────────
            if ($code === '4008' && $credit > 0) {
                if (!$client) {
                    $errors[] = "Line {$n}: Invalid client for membership fee posting.";
                    continue;
                }
                if ((float) $client->membership_fee <= 0) {
                    $errors[] = "Line {$n}: {$label} has no membership fee amount configured on their profile. Set membership fee or clear the client.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'journal_entry' => implode("\n", $errors),
            ]);
        }
    }

    /**
     * Scan journal lines that have a client_id and auto-update the matching sub-ledger
     * from the GL account (product-linked liability/receivable when possible, else chart codes).
     */
    private function syncSubLedgersFromLines(Transaction $transaction, array $lines, string $date, string $description): void
    {
        $accountIds = array_unique(array_column($lines, 'account_id'));
        $codeMap    = Account::whereIn('id', $accountIds)->pluck('account_code', 'id');

        $savingsByLiability = SavingsProduct::whereIn('savings_liability_account_id', $accountIds)
            ->get()
            ->groupBy('savings_liability_account_id');

        $loanProductsByReceivable = LoanProduct::whereIn('receivable_account_id', $accountIds)
            ->get()
            ->groupBy('receivable_account_id');

        $fdProductsByLiability = FixedDepositProduct::whereIn('deposit_liability_account_id', $accountIds)
            ->get()
            ->groupBy('deposit_liability_account_id');

        foreach ($lines as $line) {
            $clientId = $line['client_id'] ?? null;
            if (!$clientId) continue;

            $accId  = (int) $line['account_id'];
            $code   = $codeMap[$accId] ?? null;
            $debit  = (float)($line['debit']  ?? 0);
            $credit = (float)($line['credit'] ?? 0);
            if ($debit <= 0 && $credit <= 0) continue;

            // ── Savings liability (product-linked or legacy 2001 / 2005) ─────
            $savingsAccount = null;
            if ($savingsByLiability->has($accId)) {
                $productIds = $savingsByLiability->get($accId)->pluck('id');
                $savingsAccount = SavingsAccount::where('client_id', $clientId)
                    ->whereIn('product_id', $productIds)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->first();
            } elseif ($code && in_array($code, ['2001', '2005'], true)) {
                $savingsAccount = SavingsAccount::where('client_id', $clientId)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->first();
            }

            if ($savingsAccount) {
                $type      = $credit > 0 ? 'deposit' : 'withdrawal';
                $amount    = $credit > 0 ? $credit : $debit;
                $balBefore = $savingsAccount->balance;
                $balAfter  = $type === 'deposit' ? $balBefore + $amount : max(0, $balBefore - $amount);

                $savingsAccount->update(['balance' => $balAfter]);

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => $type,
                    'amount'             => $amount,
                    'balance_before'     => $balBefore,
                    'balance_after'      => $balAfter,
                    'transaction_date'   => $date,
                    'reference'          => $transaction->reference,
                    'description'        => $description,
                    'transaction_id'     => $transaction->id,
                    'created_by'         => auth()->id(),
                ]);
                continue;
            }

            // ── Fixed deposit principal (FD product liability account) ────────
            if ($fdProductsByLiability->has($accId)) {
                $productIds = $fdProductsByLiability->get($accId)->pluck('id');
                $fd         = FixedDeposit::where('client_id', $clientId)
                    ->whereIn('product_id', $productIds)
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->first();
                if ($fd) {
                    if ($credit > 0) {
                        $fd->increment('principal', $credit);
                    } elseif ($debit > 0) {
                        $fd->update(['principal' => max(0, $fd->principal - $debit)]);
                    }
                }
                continue;
            }

            // ── Share Capital ─────────────────────────────────────────────────
            if ($code === '3001') {
                if ($credit > 0) {
                    $share = MemberShare::where('client_id', $clientId)
                        ->whereIn('status', ['unpaid', 'partial'])
                        ->orderBy('id')
                        ->first();
                } else {
                    $share = MemberShare::where('client_id', $clientId)
                        ->where('status', '!=', 'liquidated')
                        ->where('amount_paid', '>', 0)
                        ->orderByDesc('id')
                        ->first();
                }
                if ($share) {
                    $amount = $credit > 0 ? $credit : $debit;
                    $type   = $credit > 0 ? 'payment' : 'refund';

                    $newPaid   = $credit > 0
                        ? min($share->share_value, $share->amount_paid + $amount)
                        : max(0, $share->amount_paid - $amount);
                    $newStatus = $newPaid >= $share->share_value ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');

                    $share->update(['amount_paid' => $newPaid, 'status' => $newStatus]);

                    \App\Models\ShareTransaction::create([
                        'share_id'                 => $share->id,
                        'client_id'                => $clientId,
                        'type'                     => $type,
                        'amount'                   => $amount,
                        'transaction_date'         => $date,
                        'reference'                => $transaction->reference,
                        'notes'                    => $description,
                        'journal_transaction_id'   => $transaction->id,
                        'created_by'               => auth()->id(),
                    ]);
                }
                continue;
            }

            // ── Loan receivable (product-linked or legacy 1101–1103; credit = principal) ──
            if ($credit > 0) {
                $loan = null;
                if ($loanProductsByReceivable->has($accId)) {
                    $pids = $loanProductsByReceivable->get($accId)->pluck('id');
                    $loan = Loan::where('client_id', $clientId)
                        ->whereIn('loan_product_id', $pids)
                        ->whereIn('status', ['active', 'defaulted'])
                        ->orderByDesc('id')
                        ->first();
                }
                if (!$loan && $code && in_array($code, ['1101', '1102', '1103'], true)) {
                    $loan = Loan::where('client_id', $clientId)
                        ->whereIn('status', ['active', 'defaulted'])
                        ->orderByDesc('id')
                        ->first();
                }
                if ($loan) {
                    $newPrincipal = max(0, $loan->outstanding_principal - $credit);
                    $status       = $newPrincipal <= 0 && $loan->outstanding_interest <= 0 ? 'closed' : $loan->status;
                    $loan->update(['outstanding_principal' => $newPrincipal, 'status' => $status]);
                }
            }

            // ── Membership fee income ─────────────────────────────────────────
            if ($code === '4008' && $credit > 0) {
                $client = Client::find($clientId);
                if ($client) {
                    $newPaid   = min($client->membership_fee, $client->membership_fee_paid + $credit);
                    $newStatus = $newPaid >= $client->membership_fee ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
                    $client->update(['membership_fee_paid' => $newPaid, 'membership_fee_status' => $newStatus]);
                }
            }
        }
    }

    public function show(Transaction $transaction)
    {
        $transaction->load('lines.account', 'createdBy', 'originalTransaction', 'reversalTransaction');
        return view('transactions.show', compact('transaction'));
    }

    public function edit(Transaction $transaction)
    {
        if ($transaction->module !== 'manual') {
            return back()->with('error', 'Only manual journal entries can be edited.');
        }
        if ($transaction->isReversed() || $transaction->isReversal()) {
            return back()->with('error', 'Reversed transactions cannot be edited.');
        }

        $transaction->load('lines.account');
        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();
        $clients  = Client::orderBy('name')->get(['id', 'client_number', 'name']);

        $lineRows = $transaction->lines->map(fn($l) => [
            'account_id'  => $l->account_id,
            'client_id'   => $l->client_id,
            'description' => $l->description,
            'debit'       => $l->debit,
            'credit'      => $l->credit,
        ])->values()->toArray();

        return view('transactions.edit', compact('transaction', 'accounts', 'clients', 'lineRows'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        if ($transaction->module !== 'manual') {
            return back()->with('error', 'Only manual journal entries can be edited.');
        }
        if ($transaction->isReversed() || $transaction->isReversal()) {
            return back()->with('error', 'Reversed transactions cannot be edited.');
        }

        $request->validate([
            'date'               => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'description'        => 'required|string|max:500',
            'reference'          => 'required|string|max:100|unique:transactions,reference,' . $transaction->id,
            'lines'              => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.client_id'  => 'nullable|exists:clients,id',
            'lines.*.debit'      => 'required|numeric|min:0',
            'lines.*.credit'     => 'required|numeric|min:0',
        ]);

        $lines = array_values(array_filter($request->lines, fn($l) => ($l['debit'] + $l['credit']) > 0));

        $totalDebit  = array_sum(array_column($lines, 'debit'));
        $totalCredit = array_sum(array_column($lines, 'credit'));
        if (abs($totalDebit - $totalCredit) >= 0.01) {
            return back()->withInput()->with('error', 'Transaction is not balanced. Debits must equal credits.');
        }

        \DB::transaction(function () use ($transaction, $request, $lines) {
            // Reverse old sub-ledger impacts
            $this->reverseManualSubLedgers($transaction);

            // Delete old lines
            $transaction->lines()->delete();

            // Update header
            $transaction->update([
                'date'        => $request->date,
                'description' => $request->description,
                'reference'   => $request->reference,
            ]);

            // Insert new lines
            foreach ($lines as $line) {
                $transaction->lines()->create([
                    'account_id'  => $line['account_id'],
                    'client_id'   => $line['client_id'] ?: null,
                    'description' => $line['description'] ?? null,
                    'debit'       => $line['debit'],
                    'credit'      => $line['credit'],
                ]);
            }

            // Sync new sub-ledger impacts
            $this->syncSubLedgersFromLines($transaction, $lines, $request->date, $request->description);
        });

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaction updated successfully.');
    }

    public function reverse(Request $request, Transaction $transaction)
    {
        if ($transaction->isReversed()) {
            return back()->with('error', 'This transaction has already been reversed.');
        }

        if ($transaction->isReversal()) {
            return back()->with('error', 'A reversal transaction cannot itself be reversed.');
        }

        $request->validate([
            'reversal_reason' => 'required|string|max:255',
            'reversal_date'   => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
        ]);

        $transaction->load('lines');
        $this->assertSubLedgerReversible($transaction, false);

        $reversalLines = $transaction->lines->map(fn($l) => [
            'account_id'  => $l->account_id,
            'debit'       => $l->credit,
            'credit'      => $l->debit,
            'description' => 'Reversal: ' . $l->description,
        ])->toArray();

        $reversal = $this->accountingService->post(
            $request->reversal_date,
            'REVERSAL of ' . $transaction->reference . ': ' . $request->reversal_reason,
            $reversalLines,
            'reversal',
            $transaction->id
        );

        $reversal->update(['reversal_of' => $transaction->id, 'reversal_reason' => $request->reversal_reason]);
        $transaction->update(['reversed_by' => $reversal->id]);

        $this->reverseModuleImpact($transaction);

        return redirect()->route('transactions.show', $reversal)
            ->with('success', 'Transaction reversed successfully. Reversal entry: ' . $reversal->reference);
    }

    public function destroy(Transaction $transaction)
    {
        $ref = $transaction->reference;

        $transaction->load('lines');
        $this->assertSubLedgerReversible($transaction, true);

        $this->reverseModuleImpact($transaction);

        $transaction->lines()->delete();
        $transaction->delete();

        return redirect()->route('transactions.index')
            ->with('success', "Transaction {$ref} has been permanently deleted.");
    }

    // ── Unified module impact dispatcher ─────────────────────────────────────

    private function reverseModuleImpact(Transaction $transaction): void
    {
        $transaction->loadMissing('lines');

        match ($transaction->module) {
            'client'        => $this->reverseMembershipFeeImpact($transaction),
            'loan'          => $this->reverseLoanImpact($transaction),
            'savings'       => $this->reverseSavingsImpact($transaction),
            'payroll'       => $this->reversePayrollImpact($transaction),
            'member_share'  => $this->reverseMemberShareImpact($transaction),
            'fixed_deposit' => $this->reverseFixedDepositImpact($transaction),
            'groups'        => $this->reverseGroupTransactionImpact($transaction),
            'manual'        => $this->reverseManualSubLedgers($transaction),
            default         => null,
        };
    }

    /**
     * Block reversal/delete when sub-ledgers cannot be safely unwound (e.g. disbursement after repayments).
     */
    private function assertSubLedgerReversible(Transaction $transaction, bool $isDestroy): void
    {
        $desc = strtolower($transaction->description ?? '');

        if ($transaction->module === 'loan' && str_contains($desc, 'loan disbursement')) {
            $loan = Loan::find($transaction->module_id);
            if ($loan && LoanRepayment::where('loan_id', $loan->id)->exists()) {
                $msg = 'Cannot reverse this loan disbursement because repayments exist. Reverse those repayment journals first, or use a corrective journal entry.';
                if ($isDestroy) {
                    throw new HttpResponseException(
                        redirect()->route('transactions.index')->with('error', $msg)
                    );
                }
                throw ValidationException::withMessages(['reversal_reason' => $msg]);
            }
        }
    }

    /**
     * Loan journals: savings-linked rows (fees from savings), repayments, or disbursement unwind.
     */
    private function reverseLoanImpact(Transaction $transaction): void
    {
        $desc = strtolower($transaction->description ?? '');

        $this->reverseSavingsImpact($transaction);

        if (str_contains($desc, 'loan repayment')) {
            $this->reverseLoanRepaymentImpact($transaction);

            return;
        }

        if (str_contains($desc, 'loan disbursement')) {
            $this->reverseLoanDisbursementImpact($transaction);

            return;
        }

        $this->reverseLoanAccrualImpact($transaction, $desc);
    }

    /**
     * Journals that move interest/admin fee through GL 2006 (Unearned Interest & Fees)
     * outside a repayment or disbursement - put the loan back the way it was before them.
     */
    private function reverseLoanAccrualImpact(Transaction $transaction, string $desc): void
    {
        $loan = Loan::find($transaction->module_id);
        $unearnedId = Account::where('account_code', '2006')->value('id');
        if (!$loan || !$unearnedId) {
            return;
        }

        $lines = $transaction->lines()->where('account_id', $unearnedId)->get();

        // Opening accrual (eltech:accrue-loan-receivables): loan goes back to un-accrued.
        if (str_contains($desc, 'loan receivable accrual (opening)')) {
            $loan->update(['income_accrued' => false]);

            return;
        }

        // Admin Cost added after disbursement (LoanService::setAdminCost).
        if (str_contains($desc, 'loan receivable accrual (admin fee)')) {
            $amount = (float) $lines->sum('credit');
            $loan->update([
                'admin_cost'            => max(0, $loan->admin_cost - $amount),
                'outstanding_admin_fee' => max(0, $loan->outstanding_admin_fee - $amount),
            ]);

            return;
        }

        // Admin Fee collected on an accrued loan (LoanService::recordAdminFee) - it's owed again.
        if (str_contains($desc, 'admin cost') && $lines->sum('debit') > 0) {
            $loan->update([
                'outstanding_admin_fee' => $loan->outstanding_admin_fee + (float) $lines->sum('debit'),
                'status'                => $loan->status === 'closed' ? 'active' : $loan->status,
            ]);
        }
    }

    private function reverseLoanDisbursementImpact(Transaction $transaction): void
    {
        $loan = Loan::find($transaction->module_id);
        if (!$loan) {
            return;
        }

        $loan->schedules()->delete();
        $loan->update([
            'status'                => 'pending',
            'disbursement_date'     => null,
            'maturity_date'         => null,
            'outstanding_principal' => 0,
            'outstanding_interest'  => 0,
            'outstanding_penalty'   => 0,
            'income_accrued'        => false,
        ]);
    }

    /**
     * Share module journals: savings leg + share_transactions / member_shares state.
     */
    private function reverseMemberShareImpact(Transaction $transaction): void
    {
        $this->reverseSavingsImpact($transaction);

        $rows = ShareTransaction::where('journal_transaction_id', $transaction->id)->get();

        foreach ($rows as $st) {
            $share = MemberShare::find($st->share_id);
            if (!$share) {
                $st->delete();
                continue;
            }

            match ($st->type) {
                'payment'     => $this->reverseSharePaymentLedger($share, $st),
                'revaluation' => $this->reverseShareRevaluationLedger($share, $st),
                'liquidation' => $this->reverseShareLiquidationLedger($share, $st),
                default       => null,
            };

            $st->delete();
        }
    }

    private function reverseSharePaymentLedger(MemberShare $share, ShareTransaction $st): void
    {
        $newPaid   = max(0, $share->amount_paid - $st->amount);
        $newStatus = $newPaid >= $share->share_value ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
        $share->update(['amount_paid' => $newPaid, 'status' => $newStatus]);
    }

    private function reverseShareRevaluationLedger(MemberShare $share, ShareTransaction $st): void
    {
        if ($st->old_value === null) {
            return;
        }

        $restoredPaid = $st->amount_paid_before !== null
            ? (float) $st->amount_paid_before
            : min((float) $share->amount_paid, (float) $st->old_value);
        $newStatus = $restoredPaid >= (float) $st->old_value ? 'paid' : ($restoredPaid > 0 ? 'partial' : 'unpaid');
        $share->update([
            'share_value' => $st->old_value,
            'amount_paid' => $restoredPaid,
            'status'      => $newStatus,
        ]);
    }

    private function reverseShareLiquidationLedger(MemberShare $share, ShareTransaction $st): void
    {
        $paid = (float) $share->amount_paid;
        $val  = (float) $share->share_value;
        $share->update([
            'status'            => $paid >= $val ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            'liquidated_at'     => null,
            'liquidation_notes' => null,
            'liquidated_by'     => null,
        ]);
    }

    /**
     * Payroll journal: reverse salary deposits on member savings (via transaction_id) and set run back to draft.
     */
    private function reversePayrollImpact(Transaction $transaction): void
    {
        $this->reverseSavingsImpact($transaction);

        $run = PayrollRun::find($transaction->module_id);
        if ($run && $run->status === 'processed') {
            $run->update([
                'status'       => 'draft',
                'processed_by' => null,
                'processed_at' => null,
            ]);
        }
    }

    // ── Client — membership fee ───────────────────────────────────────────────

    private function reverseMembershipFeeImpact(Transaction $transaction): void
    {
        if (!str_contains($transaction->description, 'Membership fee')) return;

        $client = Client::find($transaction->module_id);
        if (!$client) return;

        $membershipIncomeId = Account::where('account_code', '4008')->value('id');
        $amount = $transaction->lines->where('account_id', $membershipIncomeId)->sum('credit');
        if ($amount <= 0) return;

        $newPaid   = max(0, $client->membership_fee_paid - $amount);
        $newStatus = $newPaid <= 0 ? 'unpaid' : ($newPaid >= $client->membership_fee ? 'paid' : 'partial');
        $client->update(['membership_fee_paid' => $newPaid, 'membership_fee_status' => $newStatus]);

        SavingsTransaction::where('transaction_id', $transaction->id)->each(function ($st) use ($amount) {
            if ($st->savingsAccount) {
                $st->savingsAccount->decrement('balance', $amount);
            }
            $st->delete();
        });
    }

    // ── Loan — repayment ─────────────────────────────────────────────────────

    private function reverseLoanRepaymentImpact(Transaction $transaction): void
    {
        if (!str_contains($transaction->description, 'Loan repayment')) return;

        $repayment = LoanRepayment::where('transaction_id', $transaction->id)->first();
        if (!$repayment) return;

        $loan = Loan::find($transaction->module_id);
        if (!$loan) return;

        $loan->update([
            'outstanding_principal' => $loan->outstanding_principal + $repayment->principal_paid,
            'outstanding_interest'  => $loan->outstanding_interest  + $repayment->interest_paid,
            'outstanding_penalty'   => $loan->outstanding_penalty   + $repayment->penalty_paid,
            'outstanding_admin_fee' => $loan->outstanding_admin_fee + $repayment->admin_fee_paid,
            'status'                => $loan->status === 'closed' ? 'active' : $loan->status,
        ]);

        $remainingPrincipal = $repayment->principal_paid;
        $remainingInterest  = $repayment->interest_paid;

        $loan->schedules()
            ->whereIn('status', ['paid', 'partial'])
            ->orderByDesc('installment_no')
            ->each(function ($schedule) use (&$remainingPrincipal, &$remainingInterest) {
                if ($remainingPrincipal <= 0 && $remainingInterest <= 0) return false;

                $pRemove = min($remainingPrincipal, $schedule->principal_paid);
                $iRemove = min($remainingInterest,  $schedule->interest_paid);
                $schedule->principal_paid -= $pRemove;
                $schedule->interest_paid  -= $iRemove;
                $remainingPrincipal       -= $pRemove;
                $remainingInterest        -= $iRemove;

                $schedule->status = ($schedule->principal_paid <= 0.001 && $schedule->interest_paid <= 0.001)
                    ? ($schedule->due_date < now()->toDateString() ? 'overdue' : 'pending')
                    : 'partial';
                $schedule->save();
            });

        $repayment->delete();
    }

    // ── Savings — deposit / withdrawal / transfer ─────────────────────────────

    private function reverseSavingsImpact(Transaction $transaction): void
    {
        // Find all savings transactions linked to this journal entry
        $savingsTxns = SavingsTransaction::where('transaction_id', $transaction->id)->get();

        foreach ($savingsTxns as $st) {
            $account = SavingsAccount::find($st->savings_account_id);
            if (!$account) { $st->delete(); continue; }

            // Reverse the balance movement
            if ($st->transaction_type === 'deposit') {
                $account->decrement('balance', $st->amount);
            } else {
                // withdrawal, transfer — add back
                $account->increment('balance', $st->amount);
            }

            // If this was an interest posting, roll back last_interest_date so
            // the same period can be re-posted after deletion/reversal.
            if (str_contains(strtolower($st->description ?? ''), 'interest credit')) {
                $prevInterest = SavingsTransaction::where('savings_account_id', $account->id)
                    ->where('id', '<', $st->id)
                    ->where('description', 'like', '%Interest credit%')
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('id')
                    ->value('transaction_date');
                $account->update(['last_interest_date' => $prevInterest]);
            }

            $st->delete();
        }
    }

    // ── Fixed Deposit — creation / maturity / payout / interest ──────────────

    private function reverseFixedDepositImpact(Transaction $transaction): void
    {
        $deposit = FixedDeposit::withTrashed()->find($transaction->module_id);
        if (!$deposit) {
            return;
        }

        $desc      = strtolower($transaction->description ?? '');
        $payableId = Account::where('account_code', '2003')->value('id');
        $transaction->loadMissing('lines');

        // Creation / placement from savings or cash — remove FD (soft) and restore savings statement
        if (str_contains($desc, 'fixed deposit placement') || str_contains($desc, 'fixed deposit creation')) {
            SavingsTransaction::where('transaction_id', $transaction->id)->each(function ($st) {
                $account = SavingsAccount::find($st->savings_account_id);
                if ($account) {
                    $account->increment('balance', $st->amount);
                }
                $st->delete();
            });
            $deposit->delete();

            return;
        }

        // FD early break — interest-only GL row (restore accrued on sub-ledger)
        if (str_contains($desc, 'early break') && str_contains($desc, 'interest reversal')) {
            $restore = $payableId ? (float) $transaction->lines->where('account_id', $payableId)->sum('debit') : 0;
            if ($restore > 0) {
                $deposit->increment('accrued_interest', $restore);
            }

            return;
        }

        // Periodic interest accrual posting
        if (str_contains($desc, 'interest accrual')) {
            $cut = $payableId ? (float) $transaction->lines->where('account_id', $payableId)->sum('credit') : 0;
            if ($cut > 0) {
                $deposit->decrement('accrued_interest', min($cut, $deposit->accrued_interest));
            }

            return;
        }

        // Maturity to savings or cash — reopen FD as active, unwind savings deposit line
        if (str_contains($desc, 'fixed deposit maturity')) {
            SavingsTransaction::where('transaction_id', $transaction->id)->each(function ($st) {
                $account = SavingsAccount::find($st->savings_account_id);
                if ($account) {
                    $account->decrement('balance', $st->amount);
                }
                $st->delete();
            });
            if ($deposit->trashed()) {
                $deposit->restore();
            }
            $deposit->update(['status' => 'active', 'closed_date' => null]);

            return;
        }

        // Early break — principal returned to savings or cash
        if (str_contains($desc, 'early break') && (str_contains($desc, 'principal return') || str_contains($desc, 'principal cash'))) {
            SavingsTransaction::where('transaction_id', $transaction->id)->each(function ($st) {
                $account = SavingsAccount::find($st->savings_account_id);
                if ($account) {
                    $account->decrement('balance', $st->amount);
                }
                $st->delete();
            });
            if ($deposit->trashed()) {
                $deposit->restore();
            }
            $deposit->update(['status' => 'active', 'closed_date' => null]);
        }
    }

    // ── Group — deposit / withdrawal / interest / transfer ───────────────────

    private function reverseGroupTransactionImpact(Transaction $transaction): void
    {
        $groupTxns = GroupTransaction::where('journal_transaction_id', $transaction->id)->get();

        foreach ($groupTxns as $gt) {
            $member = \App\Models\GroupMember::find($gt->member_id);
            if (!$member) { $gt->delete(); continue; }

            $isCredit = in_array($gt->type, ['deposit', 'interest']);
            $isDebit  = in_array($gt->type, ['withdrawal', 'transfer_out']);

            if ($isCredit) {
                $member->decrement('balance', $gt->amount);
            } elseif ($isDebit) {
                $member->increment('balance', $gt->amount);
            }

            $gt->delete();
        }

        // Also reverse any linked savings transaction (transfer_out creates one)
        SavingsTransaction::where('transaction_id', $transaction->id)->each(function ($st) {
            $account = SavingsAccount::find($st->savings_account_id);
            if ($account) {
                $account->decrement('balance', $st->amount);
            }
            $st->delete();
        });
    }

    // ── Manual — reverse auto-detected sub-ledger effects ────────────────────

    private function reverseManualSubLedgers(Transaction $transaction): void
    {
        // Reverse any savings transactions created by auto-detection
        SavingsTransaction::where('transaction_id', $transaction->id)->each(function ($st) {
            $account = SavingsAccount::find($st->savings_account_id);
            if ($account) {
                if ($st->transaction_type === 'deposit') {
                    $account->decrement('balance', $st->amount);
                } else {
                    $account->increment('balance', $st->amount);
                }
            }
            $st->delete();
        });

        // Reverse any share transactions created by auto-detection
        \App\Models\ShareTransaction::where('journal_transaction_id', $transaction->id)->each(function ($st) {
            $share = \App\Models\MemberShare::find($st->share_id);
            if ($share) {
                if ($st->type === 'payment') {
                    $newPaid = max(0, $share->amount_paid - $st->amount);
                } else {
                    $newPaid = min($share->share_value, $share->amount_paid + $st->amount);
                }
                $newStatus = $newPaid >= $share->share_value ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
                $share->update(['amount_paid' => $newPaid, 'status' => $newStatus]);
            }
            $st->delete();
        });

        // Reverse loan principal reductions (re-add credit amounts to outstanding_principal)
        $transaction->loadMissing('lines');
        $lineAccountIds = $transaction->lines->pluck('account_id')->unique()->filter();
        $codeMap         = Account::whereIn('id', $lineAccountIds)->pluck('account_code', 'id');
        $loanCodes       = ['1101', '1102', '1103'];

        $loanProductsByReceivable = LoanProduct::whereIn('receivable_account_id', $lineAccountIds)
            ->get()
            ->groupBy('receivable_account_id');

        $fdProductsByLiability = FixedDepositProduct::whereIn('deposit_liability_account_id', $lineAccountIds)
            ->get()
            ->groupBy('deposit_liability_account_id');

        foreach ($transaction->lines as $line) {
            if (!$line->client_id) continue;

            $accId = (int) $line->account_id;
            $code  = $codeMap[$accId] ?? '';

            // Membership fee (4008 credit on original = fee collected)
            if ($line->credit > 0 && $code === '4008') {
                $client = Client::find($line->client_id);
                if ($client) {
                    $newPaid   = max(0, $client->membership_fee_paid - $line->credit);
                    $newStatus = $newPaid <= 0 ? 'unpaid' : ($newPaid >= $client->membership_fee ? 'paid' : 'partial');
                    $client->update(['membership_fee_paid' => $newPaid, 'membership_fee_status' => $newStatus]);
                }
            }

            // FD principal adjustments on liability account
            if ($fdProductsByLiability->has($accId)) {
                $productIds = $fdProductsByLiability->get($accId)->pluck('id');
                $fd         = FixedDeposit::where('client_id', $line->client_id)
                    ->whereIn('product_id', $productIds)
                    ->orderByDesc('id')
                    ->first();
                if ($fd) {
                    if ($line->credit > 0) {
                        $fd->update(['principal' => max(0, $fd->principal - $line->credit)]);
                    }
                    if ($line->debit > 0) {
                        $fd->increment('principal', $line->debit);
                    }
                }
            }

            // Loan principal repayment reversal
            if ($line->credit <= 0) continue;
            $isLoanReceivable = $loanProductsByReceivable->has($accId)
                || in_array($code, $loanCodes, true);
            if (!$isLoanReceivable) continue;

            $loan = Loan::where('client_id', $line->client_id)
                ->whereIn('status', ['active', 'defaulted', 'closed'])
                ->orderByDesc('id')
                ->first();
            if (!$loan) continue;

            $loan->update([
                'outstanding_principal' => $loan->outstanding_principal + $line->credit,
                'status'                => $loan->status === 'closed' ? 'active' : $loan->status,
            ]);
        }
    }
}
