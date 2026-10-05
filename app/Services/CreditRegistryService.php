<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the shared ElTech Credit Registry (registry-service/): pushes summaries of
 * this system's disbursed loans, keyed by the client's national ID, and looks up a
 * national ID's loans at the other ElTech lenders. Configured in Settings; blank URL or
 * key = off. Never throws - a registry outage must not block lending.
 */
class CreditRegistryService
{
    /** Loan ids changed during this request, pushed once after the response is sent. */
    protected static array $pendingLoanIds = [];
    protected static bool $flushRegistered = false;

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '';
    }

    /**
     * Queue a loan for pushing after the response (called from Loan model events), so a
     * slow or unreachable registry never delays the user's request.
     */
    public static function queuePush(Loan $loan): void
    {
        static::$pendingLoanIds[$loan->id] = true;

        if (!static::$flushRegistered) {
            static::$flushRegistered = true;
            app()->terminating(function () {
                $ids = array_keys(static::$pendingLoanIds);
                static::$pendingLoanIds = [];
                static::$flushRegistered = false;

                $service = app(static::class);
                if (!$ids || !$service->isConfigured()) {
                    return;
                }
                $service->pushLoans(
                    Loan::withoutGlobalScopes()->with(['client' => fn ($q) => $q->withoutGlobalScopes()])->whereIn('id', $ids)->get()
                );
            });
        }
    }

    /**
     * Push loans in batches. Returns ['saved' => n, 'removed' => n, 'error' => ?string].
     */
    public function pushLoans(iterable $loans): array
    {
        $result = ['saved' => 0, 'removed' => 0, 'error' => null];
        if (!$this->isConfigured()) {
            $result['error'] = 'Credit Registry is not configured (Settings → ElTech Credit Registry).';
            return $result;
        }

        $payload = [];
        foreach ($loans as $loan) {
            if ($row = $this->loanPayload($loan)) {
                $payload[] = $row;
            }
        }

        foreach (array_chunk($payload, 200) as $batch) {
            try {
                $response = $this->http()->post($this->baseUrl() . '/api/loans.php', ['loans' => $batch]);
                if (!$response->successful()) {
                    $result['error'] = 'Registry rejected the update (HTTP ' . $response->status() . '): ' . ($response->json('error') ?? 'no details');
                    break;
                }
                $result['saved']   += (int) $response->json('saved', 0);
                $result['removed'] += (int) $response->json('removed', 0);
            } catch (\Throwable $e) {
                $result['error'] = 'Could not reach the registry: ' . $e->getMessage();
                break;
            }
        }

        if ($result['error']) {
            Log::warning('Credit registry push failed: ' . $result['error']);
        }

        return $result;
    }

    /**
     * Loans at OTHER ElTech lenders for this national ID.
     * Returns ['ok' => bool, 'records' => [...], 'error' => ?string].
     */
    public function lookup(string $nationalId): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'records' => [], 'error' => 'Credit Registry is not configured (Settings → ElTech Credit Registry).'];
        }
        if (trim($nationalId) === '') {
            return ['ok' => false, 'records' => [], 'error' => 'No national ID number on record.'];
        }

        try {
            $response = $this->http()->get($this->baseUrl() . '/api/lookup.php', ['national_id' => $nationalId]);
            if (!$response->successful()) {
                return ['ok' => false, 'records' => [], 'error' => 'Registry error (HTTP ' . $response->status() . '): ' . ($response->json('error') ?? 'no details')];
            }
            return ['ok' => true, 'records' => $response->json('records', []), 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'records' => [], 'error' => 'Could not reach the registry. Try again shortly.'];
        }
    }

    /**
     * What the registry holds for one loan. Disbursed loans only; anything else (pending,
     * approved, reversed back to pending, deleted) is sent as "removed" so the registry
     * drops it.
     */
    public function loanPayload(Loan $loan): ?array
    {
        $ref = (string) $loan->id;

        if ($loan->trashed() || !in_array($loan->status, ['active', 'defaulted', 'closed'], true)) {
            return ['loan_ref' => $ref, 'status' => 'removed'];
        }

        $nationalId = trim((string) ($loan->client?->id_number ?? ''));
        if ($nationalId === '') {
            return null;
        }

        $oldestDue = $loan->schedules()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '<', today()->toDateString())
            ->min('due_date');

        return [
            'loan_ref'        => $ref,
            'national_id'     => $nationalId,
            'client_name'     => (string) $loan->client->name,
            'status'          => $loan->status,
            'principal'       => round((float) $loan->principal, 2),
            'outstanding'     => $loan->status === 'closed' ? 0 : round(
                $loan->outstanding_principal + $loan->outstanding_interest + $loan->outstanding_penalty + $loan->outstanding_admin_fee, 2
            ),
            'disbursed_on'    => $loan->disbursement_date?->toDateString(),
            'days_in_arrears' => $oldestDue && $loan->status !== 'closed' ? today()->diffInDays($oldestDue) : 0,
        ];
    }

    protected function http()
    {
        return Http::withToken($this->apiKey())->acceptJson()->timeout(8);
    }

    protected function baseUrl(): string
    {
        return rtrim(trim((string) SystemSetting::get('crb_registry_url', '')), '/');
    }

    protected function apiKey(): string
    {
        return trim((string) SystemSetting::get('crb_api_key', ''));
    }
}
