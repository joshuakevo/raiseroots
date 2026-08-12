<?php

namespace App\Http\Controllers;

use App\Services\SmsSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarzPayWebhookController extends Controller
{
    public function handle(Request $request, SmsSubscriptionService $subscriptions)
    {
        $expected = config('services.marzpay.webhook_secret');

        // Without a configured secret there's no way to tell a real MarzPay callback
        // from a forged one — refuse rather than silently trusting every payload.
        if (!$expected || !hash_equals($expected, (string) $request->query('token'))) {
            Log::warning('MarzPay webhook rejected: missing or invalid token');
            abort(403);
        }

        $payload = $request->all();
        Log::info('MarzPay webhook received', $payload);

        $subscriptions->applyWebhookPayload($payload);

        return response()->json(['status' => 'ok']);
    }
}
