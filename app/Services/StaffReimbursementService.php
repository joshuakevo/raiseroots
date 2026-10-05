<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\StaffReimbursement;
use Illuminate\Support\Facades\DB;

/**
 * Pays a staff reimbursement immediately and posts it:
 *   DR expense account          amount
 *   CR deduction account        deduction   (if any - e.g. Staff Advances, or the expense
 *                                            account itself for a part that isn't allowed)
 *   CR cash/bank or savings     net paid    (amount - deduction)
 * A savings payout also credits the employee's savings statement, linked to the journal
 * so reversing the journal unwinds it (TransactionController, module staff_reimbursement).
 */
class StaffReimbursementService
{
    public function __construct(protected AccountingService $accounting) {}

    public function pay(array $data): StaffReimbursement
    {
        $employee  = Employee::findOrFail($data['employee_id']);
        $amount    = round((float) $data['amount'], 2);
        $deduction = round((float) ($data['deduction'] ?? 0), 2);
        $net       = round($amount - $deduction, 2);

        if ($deduction > $amount) {
            throw new \InvalidArgumentException('The deduction can\'t be more than the amount claimed.');
        }

        $savingsAccount = null;
        if ($data['payment_method'] === 'savings') {
            $savingsAccount = SavingsAccount::with('product')->find($employee->savings_account_id);
            if (!$savingsAccount || $savingsAccount->status !== 'active' || !$savingsAccount->product?->savings_liability_account_id) {
                throw new \InvalidArgumentException("{$employee->name} has no active savings account linked. Pay from a cash/bank account instead, or link one on the employee.");
            }
        }

        return DB::transaction(function () use ($data, $employee, $amount, $deduction, $net, $savingsAccount) {
            $reimbursement = StaffReimbursement::create([
                'reference'            => $this->nextReference(),
                'employee_id'          => $employee->id,
                'date'                 => $data['date'],
                'description'          => $data['description'],
                'expense_account_id'   => $data['expense_account_id'],
                'amount'               => $amount,
                'deduction'            => $deduction,
                'deduction_reason'     => $deduction > 0 ? ($data['deduction_reason'] ?? null) : null,
                'deduction_account_id' => $deduction > 0 ? $data['deduction_account_id'] : null,
                'net_amount'           => $net,
                'payment_method'       => $data['payment_method'],
                'payment_account_id'   => $data['payment_method'] === 'cash' ? $data['payment_account_id'] : null,
                'savings_account_id'   => $savingsAccount?->id,
                'status'               => 'paid',
                'created_by'           => auth()->id(),
            ]);

            $who   = "{$employee->name} ({$reimbursement->reference})";
            $lines = [[
                'account_id'  => $data['expense_account_id'],
                'debit'       => $amount,
                'credit'      => 0,
                'description' => "Staff reimbursement - {$who}: {$data['description']}",
            ]];
            if ($deduction > 0) {
                $lines[] = [
                    'account_id'  => $data['deduction_account_id'],
                    'debit'       => 0,
                    'credit'      => $deduction,
                    'description' => "Deduction - {$who}" . (!empty($data['deduction_reason']) ? ": {$data['deduction_reason']}" : ''),
                ];
            }
            if ($net > 0) {
                $lines[] = [
                    'account_id'  => $savingsAccount ? $savingsAccount->product->savings_liability_account_id : $data['payment_account_id'],
                    'debit'       => 0,
                    'credit'      => $net,
                    'description' => "Reimbursement paid - {$who}",
                    'client_id'   => $savingsAccount?->client_id,
                ];
            }

            $journal = $this->accounting->post(
                $data['date'],
                "Staff reimbursement: {$reimbursement->reference} - {$employee->name}",
                $lines,
                'staff_reimbursement',
                $reimbursement->id
            );
            $reimbursement->update(['transaction_id' => $journal->id]);

            if ($savingsAccount && $net > 0) {
                $savingsAccount = SavingsAccount::query()->whereKey($savingsAccount->id)->lockForUpdate()->first();
                $before = (float) $savingsAccount->balance;
                $savingsAccount->update(['balance' => $before + $net]);

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => 'deposit',
                    'amount'             => $net,
                    'balance_before'     => $before,
                    'balance_after'      => $before + $net,
                    'transaction_date'   => $data['date'],
                    'reference'          => $journal->reference,
                    'description'        => "Staff reimbursement - {$reimbursement->reference}: {$data['description']}",
                    'transaction_id'     => $journal->id,
                    'created_by'         => auth()->id(),
                ]);
            }

            return $reimbursement;
        });
    }

    protected function nextReference(): string
    {
        $prefix = 'SR-' . now()->format('Y') . '-';
        $last   = StaffReimbursement::where('reference', 'like', $prefix . '%')
            ->max(DB::raw("CAST(SUBSTRING_INDEX(reference, '-', -1) AS UNSIGNED)")) ?? 0;

        return $prefix . str_pad((int) $last + 1, 4, '0', STR_PAD_LEFT);
    }
}
