<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\SmsSubscriptionService;
use Illuminate\Http\Request;

class SmsSubscriptionController extends Controller
{
    public function __construct(protected SmsSubscriptionService $subscriptions) {}

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'phone_number' => 'required|string|max:20',
        ]);

        if ($this->subscriptions->current()?->isActive()) {
            return back()->with('error', 'Your SMS subscription is already active.');
        }

        // The amount is fixed by Settings, never trusted from the request.
        $amount = (float) SystemSetting::get('sms_subscription_amount', 50000);

        $subscription = $this->subscriptions->subscribe($data['phone_number'], $amount, auth()->id());

        if ($subscription->status === 'failed') {
            return back()->with('error', 'MarzPay could not start the payment. Please try again.');
        }

        return back()->with('success', 'Payment request sent to ' . $data['phone_number'] . '. Approve it on your phone, then check status below.');
    }

    /** Re-poll MarzPay for the latest pending payment and report back. */
    public function refresh()
    {
        $pending = $this->subscriptions->latestPending();
        if (!$pending) {
            return back()->with('error', 'There is no pending SMS subscription payment to check.');
        }

        $pending = $this->subscriptions->refreshStatus($pending);

        return match ($pending->status) {
            'completed' => back()->with('success', "Payment confirmed. SMS is active until {$pending->period_end->format('d M Y')}."),
            'failed'    => back()->with('error', 'The payment failed or was not approved.'),
            default     => back()->with('success', 'Still processing — check again shortly.'),
        };
    }

    /** Manually abandon a stuck pending payment — see SmsSubscriptionService::cancelPending(). */
    public function cancel()
    {
        $pending = $this->subscriptions->latestPending();
        if (!$pending) {
            return back()->with('error', 'There is no pending SMS subscription payment to cancel.');
        }

        $this->subscriptions->cancelPending($pending);

        return back()->with('success', 'Pending payment cancelled. You can subscribe again below.');
    }
}
