<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use App\Models\SmsLog;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClientMessagingService
{
    public function __construct(protected SmsService $sms) {}

    public const GROUPS = [
        'all'                   => 'All Clients',
        'active_loans'          => 'Active Loan Clients',
        'loan_due_soon'         => 'Loan Repayments Due Soon',
        'overdue'               => 'Overdue / Defaulting Loans',
        'awaiting_disbursement' => 'Awaiting Disbursement',
        'active_savings'        => 'Active Savings Clients',
        'dormant_savings'       => 'Dormant Savings Accounts',
    ];

    /** Extra placeholders available per group, beyond the common {name}/{client_number}/{org}. */
    public const EXTRA_PLACEHOLDERS = [
        'all'                   => [],
        'active_loans'          => ['loan_number'],
        'loan_due_soon'         => ['amount', 'due_date', 'loan_number'],
        'overdue'               => ['amount', 'days', 'loan_number'],
        'awaiting_disbursement' => ['amount', 'loan_number'],
        'active_savings'        => ['balance', 'account_number'],
        'dormant_savings'       => ['balance', 'days', 'account_number'],
    ];

    /** Default message text per group, pre-filled in the composer and swapped when the group changes. */
    public const DEFAULT_TEMPLATES = [
        'all'                   => "Dear {name}, this is a message from {org}. Thank you for banking with us.",
        'active_loans'          => "Dear {name}, this is a reminder about your active loan {loan_number} with {org}. Please ensure your payments are up to date.",
        'loan_due_soon'         => "Dear {name}, this is a reminder that your loan installment of UGX {amount} is due on {due_date}. Kindly make your payment on time. - {org}",
        'overdue'               => "Dear {name}, your loan {loan_number} has an overdue balance of UGX {amount} ({days} days overdue). Please pay urgently to avoid further penalties. - {org}",
        'awaiting_disbursement' => "Dear {name}, your loan application {loan_number} for UGX {amount} is being processed. We will notify you once it has been disbursed. - {org}",
        'active_savings'        => "Dear {name}, your savings account {account_number} balance is UGX {balance}. Keep saving with {org}!",
        'dormant_savings'       => "Dear {name}, we noticed your savings account {account_number} (balance UGX {balance}) has been inactive for {days} days. Visit any {org} branch to reactivate it.",
    ];

    /**
     * Find recipients matching a group + filters. Each row:
     * ['client_id','name','client_number','phone','detail','placeholders']
     */
    public function search(string $group, array $filters = []): Collection
    {
        $dueWithin   = max(1, (int) ($filters['due_within'] ?? 7));
        $dormantDays = max(1, (int) ($filters['dormant_days'] ?? 60));
        $search      = trim((string) ($filters['search'] ?? ''));

        $rows = match ($group) {
            'active_loans'          => $this->searchActiveLoans(),
            'loan_due_soon'         => $this->searchLoanDueSoon($dueWithin),
            'overdue'               => $this->searchOverdue(),
            'awaiting_disbursement' => $this->searchAwaitingDisbursement(),
            'active_savings'        => $this->searchActiveSavings(),
            'dormant_savings'       => $this->searchDormantSavings($dormantDays),
            default                 => $this->searchAll(),
        };

        if ($search !== '') {
            $needle = strtolower($search);
            $rows = $rows->filter(
                fn ($row) => str_contains(strtolower($row['name']), $needle)
                    || str_contains(strtolower($row['client_number']), $needle)
            )->values();
        }

        return $rows;
    }

    /** Replace {placeholder} tokens in the message template. */
    public function renderTemplate(string $template, array $placeholders): string
    {
        $replacements = [];
        foreach ($placeholders as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }
        return strtr($template, $replacements);
    }

    /**
     * Re-resolves recipients server-side (never trusts client-submitted detail/placeholder
     * data) and sends + logs one personalized SMS per selected client.
     */
    public function sendToSelected(string $group, array $filters, string $template, array $selectedClientIds, ?int $sentBy): array
    {
        $rows     = $this->search($group, $filters)->keyBy('client_id');
        $batchRef = (string) Str::uuid();
        $results  = [];

        foreach ($selectedClientIds as $clientId) {
            $row = $rows->get((int) $clientId);
            if (!$row) continue; // no longer matches the group/filters — skip rather than guess

            $message = $this->renderTemplate($template, $row['placeholders']);
            $sent    = $this->sms->send($row['phone'], $message);

            SmsLog::create([
                'batch_reference' => $batchRef,
                'client_id'       => $row['client_id'],
                'client_name'     => $row['name'],
                'phone'           => $row['phone'],
                'recipient_group' => $group,
                'message'         => $message,
                'status'          => $sent ? 'sent' : 'failed',
                'sent_by'         => $sentBy,
            ]);

            $results[] = ['client' => $row['name'], 'phone' => $row['phone'], 'sent' => $sent];
        }

        return [
            'batch_reference' => $batchRef,
            'total'           => count($results),
            'sent'            => count(array_filter($results, fn ($r) => $r['sent'])),
            'failed'          => count(array_filter($results, fn ($r) => !$r['sent'])),
            'results'         => $results,
        ];
    }

    // ── Group resolvers ──────────────────────────────────────────────

    protected function searchAll(): Collection
    {
        return $this->activeClientsWithPhone()
            ->get()
            ->map(fn (Client $c) => $this->row($c, 'Active client'));
    }

    protected function searchActiveLoans(): Collection
    {
        return $this->activeClientsWithPhone()
            ->whereHas('loans', fn ($q) => $q->where('status', 'active'))
            ->with(['loans' => fn ($q) => $q->where('status', 'active')->latest()])
            ->get()
            ->map(function (Client $c) {
                $loan = $c->loans->first();
                return $this->row($c, "Loan {$loan->loan_number} · UGX " . number_format($loan->principal, 0), [
                    'loan_number' => $loan->loan_number,
                ]);
            });
    }

    protected function searchLoanDueSoon(int $dueWithin): Collection
    {
        $schedules = LoanSchedule::whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($dueWithin)->toDateString())
            ->whereHas('loan', fn ($q) => $q->where('status', 'active'))
            ->with('loan.client')
            ->orderBy('due_date')
            ->get()
            ->unique(fn ($s) => $s->loan->client_id); // earliest due schedule per client

        return $schedules
            ->filter(fn ($s) => $s->loan->client && $s->loan->client->status === 'active' && $this->hasPhone($s->loan->client))
            ->map(function (LoanSchedule $s) {
                $client = $s->loan->client;
                $due    = ($s->principal_due - $s->principal_paid) + ($s->interest_due - $s->interest_paid);

                return $this->row(
                    $client,
                    'UGX ' . number_format($due, 0) . ' due ' . $s->due_date->format('d M Y') . " · {$s->loan->loan_number}",
                    [
                        'amount'      => number_format($due, 0),
                        'due_date'    => $s->due_date->format('d M Y'),
                        'loan_number' => $s->loan->loan_number,
                    ]
                );
            })
            ->values();
    }

    protected function searchOverdue(): Collection
    {
        $loans = Loan::whereIn('status', ['active', 'defaulted'])
            ->with('client')
            ->get();

        $rows = collect();

        foreach ($loans as $loan) {
            if (!$loan->client || !$this->hasPhone($loan->client) || $loan->client->status !== 'active') continue;

            $overdueSchedules = $loan->schedules()
                ->where('due_date', '<', now()->toDateString())
                ->where('status', '!=', 'paid')
                ->get();

            if ($overdueSchedules->isEmpty() && $loan->status !== 'defaulted') continue;

            $overdueAmount = $overdueSchedules->sum(
                fn ($s) => ($s->principal_due - $s->principal_paid) + ($s->interest_due - $s->interest_paid)
            );
            $daysOverdue = $overdueSchedules->isEmpty() ? 0 : now()->diffInDays($overdueSchedules->min('due_date'));

            $rows->push($this->row(
                $loan->client,
                'UGX ' . number_format($overdueAmount, 0) . ", {$daysOverdue}d overdue · {$loan->loan_number}",
                [
                    'amount'      => number_format($overdueAmount, 0),
                    'days'        => $daysOverdue,
                    'loan_number' => $loan->loan_number,
                ]
            ));
        }

        return $rows->unique('client_id')->values();
    }

    protected function searchAwaitingDisbursement(): Collection
    {
        return Loan::whereIn('status', ['pending', 'approved'])
            ->with('client')
            ->get()
            ->filter(fn (Loan $l) => $l->client && $this->hasPhone($l->client) && $l->client->status === 'active')
            ->unique('client_id')
            ->map(function (Loan $loan) {
                $label = $loan->status === 'approved' ? 'Approved, awaiting disbursement' : 'Pending approval';
                return $this->row($loan->client, "{$loan->loan_number} · UGX " . number_format($loan->principal, 0) . " · {$label}", [
                    'amount'      => number_format($loan->principal, 0),
                    'loan_number' => $loan->loan_number,
                ]);
            })
            ->values();
    }

    protected function searchActiveSavings(): Collection
    {
        return $this->activeClientsWithPhone()
            ->whereHas('savingsAccounts', fn ($q) => $q->where('status', 'active'))
            ->with(['savingsAccounts' => fn ($q) => $q->where('status', 'active')])
            ->get()
            ->map(function (Client $c) {
                $accounts = $c->savingsAccounts;
                $balance  = $accounts->sum('balance');
                return $this->row($c, 'UGX ' . number_format($balance, 0) . ' · ' . $accounts->first()->account_number, [
                    'balance'         => number_format($balance, 0),
                    'account_number'  => $accounts->first()->account_number,
                ]);
            });
    }

    protected function searchDormantSavings(int $dormantDays): Collection
    {
        return SavingsAccount::where('status', 'active')
            ->with(['client', 'transactions' => fn ($q) => $q->limit(1)])
            ->get()
            ->filter(function (SavingsAccount $acc) use ($dormantDays) {
                if (!$acc->client || !$this->hasPhone($acc->client) || $acc->client->status !== 'active') return false;
                $lastActivity = $acc->transactions->first()?->transaction_date ?? $acc->opened_date;
                return $lastActivity && Carbon::parse($lastActivity)->diffInDays(now()) >= $dormantDays;
            })
            ->unique('client_id')
            ->map(function (SavingsAccount $acc) {
                $lastActivity = $acc->transactions->first()?->transaction_date ?? $acc->opened_date;
                $days = Carbon::parse($lastActivity)->diffInDays(now());
                return $this->row($acc->client, "UGX " . number_format($acc->balance, 0) . ", dormant {$days}d · {$acc->account_number}", [
                    'balance'        => number_format($acc->balance, 0),
                    'days'           => $days,
                    'account_number' => $acc->account_number,
                ]);
            })
            ->values();
    }

    // ── Helpers ──────────────────────────────────────────────────────

    protected function activeClientsWithPhone()
    {
        return Client::where('status', 'active')->whereNotNull('phone')->where('phone', '!=', '');
    }

    protected function hasPhone(Client $client): bool
    {
        return !empty($client->phone);
    }

    protected function row(Client $client, string $detail, array $extraPlaceholders = []): array
    {
        return [
            'client_id'     => $client->id,
            'name'          => $client->name,
            'client_number' => $client->client_number,
            'phone'         => $client->phone,
            'detail'        => $detail,
            'placeholders'  => array_merge([
                'name'          => $client->name,
                'client_number' => $client->client_number,
                'org'           => SystemSetting::get('org_name', 'ElTech Finance'),
            ], $extraPlaceholders),
        ];
    }
}
