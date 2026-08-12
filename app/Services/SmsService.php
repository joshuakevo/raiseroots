<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Thin client for the MarzSMS gateway (https://sms.wearemarz.com).
 *
 * MarzSMS does not publish public API docs, so the endpoint/payload below
 * follow the common REST convention for this class of gateway and need to
 * be confirmed against a real send. Everything else in the app only calls
 * send() — if MarzSMS expects a different path or field names, this is the
 * one place to fix.
 */
class SmsService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?string $secret;
    protected string $senderId;

    public function __construct(protected SmsSubscriptionService $subscriptions)
    {
        $this->baseUrl  = rtrim(config('services.marzsms.base_url'), '/');
        $this->apiKey   = config('services.marzsms.api_key');
        $this->secret   = config('services.marzsms.secret');
        $this->senderId = config('services.marzsms.sender_id');
    }

    /**
     * Send an SMS. Returns true if the gateway accepted the message.
     * Never throws — failures are logged so they can't break the loan
     * workflow (disbursement, repayment, etc.) that triggered the SMS.
     *
     * Every outbound message funnels through here, so this is also the one
     * place the monthly SMS subscription is enforced — once it lapses,
     * sending is blocked app-wide until it's renewed.
     *
     * $logContext, when given, records an SmsLog row for this send (used by
     * transactional callers — the bulk composer in ClientMessagingService
     * logs its own batch instead, so it omits this). Expected keys:
     * client_id, client_name, recipient_group.
     */
    public function send(string $phoneNumber, string $message, ?array $logContext = null): bool
    {
        $recipient = $this->normalizePhone($phoneNumber);
        $sent      = $this->attemptSend($phoneNumber, $recipient, $message);

        if ($logContext) {
            SmsLog::create([
                'batch_reference' => (string) Str::uuid(),
                'client_id'       => $logContext['client_id'] ?? null,
                'client_name'     => $logContext['client_name'] ?? '',
                'phone'           => $recipient ?? $phoneNumber,
                'recipient_group' => $logContext['recipient_group'] ?? 'transactional',
                'message'         => $message,
                'status'          => $sent ? 'sent' : 'failed',
                'sent_by'         => $logContext['sent_by'] ?? null,
            ]);
        }

        return $sent;
    }

    private function attemptSend(string $phoneNumber, ?string $recipient, string $message): bool
    {
        if (!$this->subscriptions->isActive()) {
            Log::warning('SmsService: skipped send, SMS subscription is not active', ['phone' => $phoneNumber]);
            return false;
        }

        if (!$recipient) {
            Log::warning('SmsService: skipped send, invalid phone number', ['phone' => $phoneNumber]);
            return false;
        }

        if (!$this->apiKey || !$this->secret) {
            Log::warning('SmsService: MARZSMS_API_KEY/MARZSMS_SECRET not configured, skipping send');
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->apiKey, $this->secret)
                ->timeout(15)
                ->post("{$this->baseUrl}/sms/send", [
                    'recipient' => $recipient,
                    'sender_id' => $this->senderId,
                    'message'   => $message,
                ]);

            if (!$response->successful()) {
                Log::warning('SmsService: send failed', [
                    'phone'  => $recipient,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            $this->subscriptions->consumeTrialIfApplicable();
            return true;
        } catch (\Throwable $e) {
            Log::error('SmsService: send threw an exception', [
                'phone' => $recipient,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Normalize Ugandan numbers (07XXXXXXXX, 7XXXXXXXX, +2567XXXXXXXX, 2567XXXXXXXX)
     * to the 2567XXXXXXXX format local SMS gateways expect.
     */
    public function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;

        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') return null;

        if (str_starts_with($digits, '256') && strlen($digits) === 12) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '256' . substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            return '256' . $digits;
        }

        return $digits;
    }
}
