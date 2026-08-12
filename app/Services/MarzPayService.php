<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for MarzPay's collections API (https://wallet.wearemarz.com/documentation/collections).
 * Charges a mobile money number and credits the result to the business's MarzPay wallet.
 */
class MarzPayService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?string $apiSecret;
    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->baseUrl       = rtrim(config('services.marzpay.base_url'), '/');
        $this->apiKey        = config('services.marzpay.api_key');
        $this->apiSecret     = config('services.marzpay.api_secret');
        $this->webhookSecret = config('services.marzpay.webhook_secret');
    }

    /**
     * Initiate a mobile money collection. $reference must be a UUID v4, unique per attempt.
     * Returns the decoded response body. Throws on transport/auth failure or a non-2xx response —
     * callers are expected to catch and surface this as a failed subscription attempt.
     */
    public function collectMoney(string $phone, float $amount, string $reference, string $description = ''): array
    {
        $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
            ->timeout(20)
            ->asForm()
            ->post("{$this->baseUrl}/collect-money", [
                'phone_number' => $this->toInternational($phone),
                'amount'       => (int) round($amount),
                'country'      => 'UG',
                'reference'    => $reference,
                'description'  => $description,
                'callback_url' => route('webhooks.marzpay', ['token' => $this->webhookSecret]),
            ]);

        if (!$response->successful()) {
            Log::warning('MarzPayService: collect-money failed', [
                'phone'  => $phone,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('MarzPay declined the request: ' . $response->body());
        }

        return $response->json();
    }

    /** Fetch the current status of a collection by its MarzPay transaction UUID. */
    public function getCollectionStatus(string $transactionUuid): array
    {
        $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
            ->timeout(15)
            ->get("{$this->baseUrl}/collect-money/{$transactionUuid}");

        if (!$response->successful()) {
            Log::warning('MarzPayService: status check failed', [
                'uuid'   => $transactionUuid,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Could not fetch collection status: ' . $response->body());
        }

        return $response->json();
    }

    /** MarzPay requires phone numbers in +256XXXXXXXXX form. */
    protected function toInternational(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '256') && strlen($digits) === 12) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+256' . substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            return '+256' . $digits;
        }

        return '+' . $digits;
    }
}
