<?php

namespace App\Services;

use App\Models\SmsSubscription;
use App\Models\SystemSetting;
use Illuminate\Support\Str;

class SmsSubscriptionService
{
    /** Free SMS given before any subscription payment is required. */
    public const TRIAL_LIMIT = 5;

    public function __construct(protected MarzPayService $marzPay) {}

    /** Whether the subscription gate is turned on at all (Settings > Modules). */
    public function isRequired(): bool
    {
        return (bool) SystemSetting::get('sms_subscription_required', true);
    }

    /** The subscription currently covering today, if any. */
    public function current(): ?SmsSubscription
    {
        return SmsSubscription::where('status', 'completed')
            ->whereDate('period_end', '>=', today())
            ->orderByDesc('period_end')
            ->first();
    }

    public function trialUsed(): int
    {
        return (int) SystemSetting::get('sms_trial_used_count', 0);
    }

    public function trialRemaining(): int
    {
        return max(0, self::TRIAL_LIMIT - $this->trialUsed());
    }

    /** True if SMS sending is allowed — the gate is off, there's a live subscription, or trial SMS remain. */
    public function isActive(): bool
    {
        if (!$this->isRequired()) {
            return true;
        }
        if ($this->current()?->isActive()) {
            return true;
        }
        return $this->trialRemaining() > 0;
    }

    /**
     * Call after a message actually sends. A no-op unless it was the free trial
     * (not the subscription gate being off, not a paid subscription) that allowed it.
     */
    public function consumeTrialIfApplicable(): void
    {
        if (!$this->isRequired() || $this->current()?->isActive()) {
            return;
        }
        SystemSetting::set('sms_trial_used_count', $this->trialUsed() + 1);
    }

    /** A payment that's still processing at MarzPay, if any. */
    public function latestPending(): ?SmsSubscription
    {
        return SmsSubscription::where('status', 'pending')->latest()->first();
    }

    public function latest(): ?SmsSubscription
    {
        return SmsSubscription::latest()->first();
    }

    /**
     * Kick off a MarzPay collection for the subscription fee. Never throws — a failure
     * is recorded as a 'failed' subscription row so the UI can show what happened.
     */
    public function subscribe(string $phone, float $amount, ?int $userId): SmsSubscription
    {
        $subscription = SmsSubscription::create([
            'amount'       => $amount,
            'phone_number' => $phone,
            'reference'    => (string) Str::uuid(),
            'status'       => 'pending',
            'initiated_by' => $userId,
        ]);

        try {
            $response = $this->marzPay->collectMoney(
                $phone,
                $amount,
                $subscription->reference,
                'Monthly SMS subscription'
            );

            $subscription->update([
                'transaction_uuid' => $response['data']['transaction']['uuid'] ?? null,
                'raw_response'      => json_encode($response),
            ]);

            $this->applyRemoteStatus($subscription, $response['data']['transaction']['status'] ?? 'processing');
        } catch (\Throwable $e) {
            $subscription->update([
                'status'       => 'failed',
                'raw_response' => $e->getMessage(),
            ]);
        }

        return $subscription->fresh();
    }

    /** Re-poll MarzPay for a still-pending subscription's current status. */
    public function refreshStatus(SmsSubscription $subscription): SmsSubscription
    {
        if ($subscription->status !== 'pending' || !$subscription->transaction_uuid) {
            return $subscription;
        }

        try {
            $response = $this->marzPay->getCollectionStatus($subscription->transaction_uuid);
            $subscription->update(['raw_response' => json_encode($response)]);
            $this->applyRemoteStatus($subscription, $response['data']['transaction']['status'] ?? 'processing');
        } catch (\Throwable $e) {
            // Transient lookup failure — leave as pending, a later check can retry.
        }

        return $subscription->fresh();
    }

    /** Apply a webhook payload from MarzPay to the matching subscription. */
    public function applyWebhookPayload(array $payload): ?SmsSubscription
    {
        $uuid      = $payload['transaction']['uuid'] ?? null;
        $reference = $payload['transaction']['reference'] ?? null;

        $subscription = SmsSubscription::where('transaction_uuid', $uuid)
            ->orWhere('reference', $reference)
            ->first();

        if (!$subscription || $subscription->status !== 'pending') {
            return $subscription;
        }

        $subscription->update(['raw_response' => json_encode($payload)]);
        $this->applyRemoteStatus($subscription, $payload['transaction']['status'] ?? 'processing');

        return $subscription->fresh();
    }

    /**
     * Manually abandon a stuck pending payment (e.g. the user cancelled the mobile
     * money prompt). MarzPay's status vocabulary for that case isn't confirmed, so
     * applyRemoteStatus() may never see anything it recognizes as terminal and the
     * payment would otherwise sit as "pending" indefinitely — this is the reliable
     * escape hatch regardless of what MarzPay actually reports.
     */
    public function cancelPending(SmsSubscription $subscription): SmsSubscription
    {
        if ($subscription->status === 'pending') {
            $subscription->update([
                'status'       => 'failed',
                'raw_response' => 'Cancelled manually from the SMS page.',
            ]);
        }

        return $subscription->fresh();
    }

    /** Safety-net poll for any subscription still pending — call from a scheduled command. */
    public function checkAllPending(): int
    {
        $pending = SmsSubscription::where('status', 'pending')->get();
        foreach ($pending as $subscription) {
            $this->refreshStatus($subscription);
        }
        return $pending->count();
    }

    protected function applyRemoteStatus(SmsSubscription $subscription, string $remoteStatus): void
    {
        if ($remoteStatus === 'completed') {
            $subscription->update([
                'status'       => 'completed',
                'period_start' => today(),
                'period_end'   => today()->addDays(30),
            ]);
        } elseif ($remoteStatus === 'failed') {
            $subscription->update(['status' => 'failed']);
        }
        // 'processing' — leave as pending, check again later.
    }
}
