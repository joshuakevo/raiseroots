<?php

namespace App\Http\Controllers;

use App\Models\SmsLog;
use App\Services\ClientMessagingService;
use App\Services\SmsSubscriptionService;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct(
        protected ClientMessagingService $messaging,
        protected SmsSubscriptionService $subscriptions,
    ) {}

    public function index()
    {
        return view('sms.index', [
            'groups'            => ClientMessagingService::GROUPS,
            'extraPlaceholders' => ClientMessagingService::EXTRA_PLACEHOLDERS,
            'defaultTemplates'  => ClientMessagingService::DEFAULT_TEMPLATES,
            'subscriptionRequired' => $this->subscriptions->isRequired(),
            'subscription'      => $this->subscriptions->current(),
            'pendingPayment'    => $this->subscriptions->latestPending(),
            'trialRemaining'    => $this->subscriptions->trialRemaining(),
            'sendAllowed'       => $this->subscriptions->isActive(),
            'subscriptionAmount'=> \App\Models\SystemSetting::get('sms_subscription_amount', 50000),
        ]);
    }

    /** JSON: live recipient list for the selected group + filters. */
    public function recipients(Request $request)
    {
        $data = $request->validate([
            'group'        => 'required|in:' . implode(',', array_keys(ClientMessagingService::GROUPS)),
            'due_within'   => 'nullable|integer|min:1|max:365',
            'dormant_days' => 'nullable|integer|min:1|max:3650',
            'search'       => 'nullable|string|max:100',
        ]);

        $rows = $this->messaging->search($data['group'], $data);

        return response()->json([
            'count'      => $rows->count(),
            'recipients' => $rows->map(fn ($r) => [
                'client_id' => $r['client_id'],
                'name'      => $r['name'],
                'phone'     => $r['phone'],
                'detail'    => $r['detail'],
            ])->values(),
        ]);
    }

    public function send(Request $request)
    {
        if (!$this->subscriptions->isActive()) {
            return back()->with('error', 'Your SMS subscription has expired. Subscribe below to resume sending SMS.')->withInput();
        }

        $data = $request->validate([
            'group'         => 'required|in:' . implode(',', array_keys(ClientMessagingService::GROUPS)),
            'due_within'    => 'nullable|integer|min:1|max:365',
            'dormant_days'  => 'nullable|integer|min:1|max:3650',
            'search'        => 'nullable|string|max:100',
            'message'       => 'required|string|max:459',
            'client_ids'    => 'required|array|min:1',
            'client_ids.*'  => 'integer',
        ]);

        $summary = $this->messaging->sendToSelected(
            $data['group'],
            $data,
            $data['message'],
            $data['client_ids'],
            auth()->id()
        );

        if ($summary['total'] === 0) {
            return back()->with('error', 'None of the selected clients still match this group/filter — nothing was sent.')->withInput();
        }

        return redirect()->route('sms.index')
            ->with('success', "Sent to {$summary['sent']} of {$summary['total']} clients." . ($summary['failed'] > 0 ? " {$summary['failed']} failed — see the Delivery Log for details." : ''));
    }

    /** Send to a single recipient — called repeatedly by the frontend to drive a live progress bar. */
    public function sendOne(Request $request)
    {
        if (!$this->subscriptions->isActive()) {
            return response()->json(['sent' => false, 'error' => 'Subscription inactive'], 422);
        }

        $data = $request->validate([
            'group'        => 'required|in:' . implode(',', array_keys(ClientMessagingService::GROUPS)),
            'due_within'   => 'nullable|integer|min:1|max:365',
            'dormant_days' => 'nullable|integer|min:1|max:3650',
            'search'       => 'nullable|string|max:100',
            'message'      => 'required|string|max:459',
            'client_id'    => 'required|integer',
        ]);

        $summary = $this->messaging->sendToSelected(
            $data['group'], $data, $data['message'], [$data['client_id']], auth()->id()
        );

        if ($summary['total'] === 0) {
            return response()->json(['sent' => false, 'client' => null, 'phone' => null, 'error' => 'No longer matches this group/filter']);
        }

        return response()->json($summary['results'][0]);
    }

    public function deliveries(Request $request)
    {
        $logs = SmsLog::with('sentBy')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('client_name', 'like', "%{$request->search}%")
                   ->orWhere('phone', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('sms.deliveries', [
            'logs'   => $logs,
            'groups' => ClientMessagingService::GROUPS,
        ]);
    }
}
