<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    /**
     * Post a double-entry transaction.
     *
     * @param  string  $date       e.g. '2024-01-15'
     * @param  string  $description
     * @param  array   $lines      [['account_id'=>X,'debit'=>Y,'credit'=>Z,'description'=>'...'], ...]
     * @param  string|null  $module
     * @param  int|null  $moduleId
     * @return Transaction
     */
    public function post(string $date, string $description, array $lines, ?string $module = null, ?int $moduleId = null, ?string $reference = null): Transaction
    {
        $this->validateLines($lines);

        return DB::transaction(function () use ($date, $description, $lines, $module, $moduleId, $reference) {
            $transaction = Transaction::create([
                'date'        => $date,
                'reference'   => $reference ?: $this->generateReference(),
                'description' => $description,
                'module'      => $module,
                'module_id'   => $moduleId,
                'created_by'  => auth()->id(),
            ]);

            foreach ($lines as $line) {
                TransactionLine::create([
                    'transaction_id' => $transaction->id,
                    'account_id'     => $line['account_id'],
                    'client_id'      => $line['client_id'] ?? null,
                    'debit'          => $line['debit'] ?? 0,
                    'credit'         => $line['credit'] ?? 0,
                    'description'    => $line['description'] ?? null,
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Validate that lines are balanced and have at least 2 entries.
     */
    protected function validateLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('A transaction must have at least 2 lines.');
        }

        $totalDebit  = array_sum(array_column($lines, 'debit'));
        $totalCredit = array_sum(array_column($lines, 'credit'));

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw ValidationException::withMessages([
                'lines' => "Transaction is not balanced. Debits: {$totalDebit}, Credits: {$totalCredit}",
            ]);
        }
    }

    protected function generateReference(): string
    {
        $prefix = 'TXN';
        $date   = now()->format('Ymd');
        $last   = Transaction::whereDate('created_at', today())->count() + 1;
        return "{$prefix}-{$date}-" . str_pad($last, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get account balance between dates.
     */
    public function getAccountBalance(int $accountId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = TransactionLine::where('account_id', $accountId)
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id');

        if ($fromDate) {
            $query->where('transactions.date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('transactions.date', '<=', $toDate);
        }

        $debit  = $query->sum('transaction_lines.debit');
        $credit = $query->sum('transaction_lines.credit');

        return [
            'debit'   => $debit,
            'credit'  => $credit,
            'balance' => $debit - $credit,
        ];
    }

    /**
     * Generate trial balance.
     */
    public function getTrialBalance(?string $fromDate = null, ?string $toDate = null): array
    {
        $accounts = Account::where('is_active', true)->get();
        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $bal = $this->getAccountBalance($account->id, $fromDate, $toDate);
            if ($bal['debit'] == 0 && $bal['credit'] == 0) continue;

            $rows[] = [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'account_type' => $account->account_type,
                'debit'        => $bal['debit'],
                'credit'       => $bal['credit'],
            ];

            $totalDebit  += $bal['debit'];
            $totalCredit += $bal['credit'];
        }

        return [
            'rows'         => $rows,
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced'     => abs($totalDebit - $totalCredit) < 0.001,
        ];
    }

    /**
     * Display-only grouping of expense accounts for the Income Statement — not part of
     * the real chart of accounts hierarchy (every expense account's actual parent is
     * the single '5000 Expenses' header). Any expense account code not listed here
     * falls into "Other Expenses" so newly added accounts never silently disappear.
     */
    private const EXPENSE_CATEGORIES = [
        'Personnel Costs'       => ['5001', '5003'],
        'Financial Charges'     => ['5002', '5010', '5008'],
        'Operating Expenses'    => ['5004', '5005', '5009'],
        'Provisions & Non-Cash' => ['5006', '5007'],
    ];

    /**
     * Group already-computed expense rows into the categories above, preserving
     * category order and skipping empty ones. Uncategorized accounts land in a
     * trailing "Other Expenses" group.
     */
    private function groupExpenseRows(array $expenseRows): array
    {
        $groups = [];
        $categorized = [];

        foreach (self::EXPENSE_CATEGORIES as $label => $codes) {
            $rows = array_values(array_filter(
                $expenseRows,
                fn ($row) => in_array($row['account']->account_code, $codes, true)
            ));
            if (empty($rows)) continue;

            $groups[] = ['label' => $label, 'rows' => $rows, 'subtotal' => array_sum(array_column($rows, 'balance'))];
            array_push($categorized, ...$codes);
        }

        $otherRows = array_values(array_filter(
            $expenseRows,
            fn ($row) => !in_array($row['account']->account_code, $categorized, true)
        ));
        if (!empty($otherRows)) {
            $groups[] = ['label' => 'Other Expenses', 'rows' => $otherRows, 'subtotal' => array_sum(array_column($otherRows, 'balance'))];
        }

        return $groups;
    }

    /**
     * Generate income statement (Revenue - Expense).
     */
    public function getIncomeStatement(?string $fromDate = null, ?string $toDate = null): array
    {
        $revenues = Account::where('account_type', 'revenue')->where('is_active', true)->get();
        $expenses = Account::where('account_type', 'expense')->where('is_active', true)->get();

        $revenueRows = [];
        $totalRevenue = 0;
        foreach ($revenues as $acc) {
            $bal = $this->getAccountBalance($acc->id, $fromDate, $toDate);
            $balance = $bal['credit'] - $bal['debit'];
            if ($balance == 0) continue;
            $revenueRows[] = ['account' => $acc, 'balance' => $balance];
            $totalRevenue += $balance;
        }

        $expenseRows = [];
        $totalExpense = 0;
        foreach ($expenses as $acc) {
            $bal = $this->getAccountBalance($acc->id, $fromDate, $toDate);
            $balance = $bal['debit'] - $bal['credit'];
            if ($balance == 0) continue;
            $expenseRows[] = ['account' => $acc, 'balance' => $balance];
            $totalExpense += $balance;
        }

        return [
            'revenue_rows'   => $revenueRows,
            'expense_rows'   => $expenseRows,
            'expense_groups' => $this->groupExpenseRows($expenseRows),
            'total_revenue'  => $totalRevenue,
            'total_expense'  => $totalExpense,
            'net_income'     => $totalRevenue - $totalExpense,
        ];
    }

    /**
     * Generate balance sheet (Assets, Liabilities, Equity).
     */
    public function getBalanceSheet(?string $asOf = null): array
    {
        $asOf = $asOf ?? now()->toDateString();

        $result = [];
        foreach (['asset', 'liability', 'equity'] as $type) {
            $accounts = Account::where('account_type', $type)->where('is_active', true)->get();
            $rows = [];
            $total = 0;
            foreach ($accounts as $acc) {
                $bal = $this->getAccountBalance($acc->id, null, $asOf);
                $balance = ($type === 'asset') ? $bal['debit'] - $bal['credit'] : $bal['credit'] - $bal['debit'];
                if ($balance == 0) continue;
                $rows[] = ['account' => $acc, 'balance' => $balance];
                $total += $balance;
            }
            $result[$type] = ['rows' => $rows, 'total' => $total];
        }

        // Net income (Revenue - Expenses) up to $asOf is part of equity on the balance sheet.
        // Revenue accounts are credit-normal; expense accounts are debit-normal.
        $revenues = Account::where('account_type', 'revenue')->where('is_active', true)->get();
        $expenses = Account::where('account_type', 'expense')->where('is_active', true)->get();

        $totalRevenue = 0;
        foreach ($revenues as $acc) {
            $bal = $this->getAccountBalance($acc->id, null, $asOf);
            $totalRevenue += $bal['credit'] - $bal['debit'];
        }

        $totalExpense = 0;
        foreach ($expenses as $acc) {
            $bal = $this->getAccountBalance($acc->id, null, $asOf);
            $totalExpense += $bal['debit'] - $bal['credit'];
        }

        $netIncome = $totalRevenue - $totalExpense;

        // Append net income as a pseudo-row under equity
        if (abs($netIncome) >= 0.01) {
            $result['equity']['rows'][] = [
                'account' => (object)['account_code' => '—', 'account_name' => 'Net Income (Current Period)'],
                'balance' => $netIncome,
            ];
            $result['equity']['total'] += $netIncome;
        }

        $result['net_income'] = $netIncome;

        return $result;
    }
}
