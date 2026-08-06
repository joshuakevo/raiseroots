<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Group;
use App\Models\MemberShare;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('client_number', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->withCount('shares')
            ->with(['shares' => fn($q) => $q->select('client_id', 'share_value', 'amount_paid', 'status')])
            ->latest()
            ->paginate(20);

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        $branches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get();
        return view('clients.create', compact('branches'));
    }

    public function store(Request $request)
    {
        if ($request->input('client_type') === 'group') {
            $isPooled = $request->input('group_type') === 'pooled';
            $data = $request->validate([
                'group_name'             => 'required|string|max:200',
                'group_type'             => 'required|in:savings,pooled',
                'phone'                  => 'nullable|string|max:20',
                'email'                  => 'nullable|email|max:255',
                'address'                => 'nullable|string',
                'branch_id'              => 'nullable|exists:branches,id',
                'status'                 => 'required|in:active,inactive,blacklisted',
                'joining_date'           => 'nullable|date',
                'membership_fee'         => 'required|numeric|min:0',
                'monthly_interest_rate'  => $isPooled ? 'nullable|numeric|min:0|max:100' : 'required|numeric|min:0|max:100',
                'expected_contribution'  => $isPooled ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
                'contribution_cycle'     => $isPooled ? 'required|in:weekly,biweekly,monthly,quarterly' : 'nullable',
            ]);

            $data['client_type']     = 'group';
            $data['name']            = $data['group_name'];
            $data['first_name']      = $data['group_name'];
            $data['last_name']       = 'Group';
            $data['client_number']   = $this->generateClientNumber();
            $data['created_by']      = auth()->id();
            $data['loan_interest']   = false;
            $data['membership_fee_paid']   = 0;
            $data['membership_fee_status'] = 'unpaid';
            unset($data['group_name'], $data['monthly_interest_rate'], $data['group_type'],
                  $data['expected_contribution'], $data['contribution_cycle']);

            $client = Client::create($data);

            Group::create([
                'client_id'             => $client->id,
                'group_number'          => $this->generateGroupNumber(),
                'name'                  => $client->name,
                'group_type'            => $request->input('group_type', 'savings'),
                'registration_date'     => $client->joining_date ?? today(),
                'membership_fee'        => $request->input('membership_fee'),
                'monthly_interest_rate' => $request->input('monthly_interest_rate', 0),
                'expected_contribution' => $request->input('expected_contribution'),
                'contribution_cycle'    => $request->input('contribution_cycle'),
                'status'                => 'active',
                'created_by'            => auth()->id(),
            ]);

            return redirect()->route('groups.show', $client->group)->with('success', 'Group client registered successfully.');
        }

        $data = $request->validate([
            // Personal
            'first_name'               => 'required|string|max:100',
            'middle_name'              => 'nullable|string|max:100',
            'last_name'                => 'required|string|max:100',
            'gender'                   => 'required|in:male,female,other',
            'date_of_birth'            => 'required|date',
            'marital_status'           => 'required|in:single,married,divorced,widowed',
            'nationality'              => 'required|string|max:100',
            'id_number'                => 'required|string|max:50',
            'photo'                    => 'nullable|image|max:2048',
            // Contact
            'phone'                    => 'required|string|max:20',
            'alt_phone'                => 'nullable|string|max:20',
            'email'                    => 'required|email|max:255',
            'address'                  => 'required|string',
            'district'                 => 'required|string|max:100',
            'village'                  => 'required|string|max:100',
            // Employment & membership
            'employment_status'        => 'required|in:employed,self_employed,business_owner,farmer,student,unemployed',
            'purpose_of_joining'       => 'required|string|max:100',
            'expected_monthly_savings' => 'required|numeric|min:0',
            'loan_interest'            => 'required|boolean',
            // Next of kin
            'next_of_kin_name'         => 'required|string|max:150',
            'next_of_kin_relationship' => 'required|string|max:80',
            'next_of_kin_phone'        => 'required|string|max:20',
            'next_of_kin_address'      => 'required|string|max:255',
            // Preferences
            'preferred_communication'  => 'required|in:sms,email,whatsapp,phone_call',
            'branch_id'                => 'nullable|exists:branches,id',
            'status'                   => 'required|in:active,inactive,blacklisted',
            'joining_date'             => 'required|date',
        ]);

        $data['client_type']   = 'individual';
        $data['name']          = trim($data['first_name'] . ' ' . ($data['middle_name'] ? $data['middle_name'] . ' ' : '') . $data['last_name']);
        $data['client_number'] = $this->generateClientNumber();
        $data['created_by']    = auth()->id();
        $data['loan_interest'] = $request->boolean('loan_interest');

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->storeClientPhoto($request->file('photo'));
        }

        $data['membership_fee']        = 50000;
        $data['membership_fee_paid']   = 0;
        $data['membership_fee_status'] = 'unpaid';

        $client = Client::create($data);

        MemberShare::create([
            'client_id'    => $client->id,
            'share_number' => $this->generateShareNumber(),
            'share_value'  => 100000,
            'amount_paid'  => 0,
            'status'       => 'unpaid',
            'created_by'   => auth()->id(),
        ]);

        return redirect()->route('clients.index')->with('success', 'Client created successfully.');
    }

    public function show(Client $client)
    {
        $client->load([
            'loans.product',
            'savingsAccounts.product',
            'fixedDeposits.product',
            'shares',
            'createdBy',
            'branch',
            'group',
        ]);

        $paymentSourceAccounts = \App\Models\Account::where('is_payment_source', true)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return view('clients.show', compact('client', 'paymentSourceAccounts'));
    }

    public function edit(Client $client)
    {
        $branches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get();
        return view('clients.edit', compact('client', 'branches'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            // Personal
            'first_name'               => 'required|string|max:100',
            'middle_name'              => 'nullable|string|max:100',
            'last_name'                => 'required|string|max:100',
            'gender'                   => 'nullable|in:male,female,other',
            'date_of_birth'            => 'nullable|date',
            'marital_status'           => 'nullable|in:single,married,divorced,widowed',
            'nationality'              => 'nullable|string|max:100',
            'id_number'                => 'nullable|string|max:50',
            'photo'                    => 'nullable|image|max:2048',
            // Contact
            'phone'                    => 'nullable|string|max:20',
            'alt_phone'                => 'nullable|string|max:20',
            'email'                    => 'nullable|email|max:255',
            'address'                  => 'nullable|string',
            'district'                 => 'nullable|string|max:100',
            'village'                  => 'nullable|string|max:100',
            'postal_address'           => 'nullable|string|max:200',
            // Employment & membership
            'employment_status'        => 'nullable|in:employed,self_employed,business_owner,farmer,student,unemployed',
            'purpose_of_joining'       => 'nullable|string|max:100',
            'expected_monthly_savings' => 'nullable|numeric|min:0',
            'loan_interest'            => 'nullable|boolean',
            // Next of kin
            'next_of_kin_name'         => 'nullable|string|max:150',
            'next_of_kin_relationship' => 'nullable|string|max:80',
            'next_of_kin_phone'        => 'nullable|string|max:20',
            'next_of_kin_address'      => 'nullable|string|max:255',
            // Preferences
            'preferred_communication'  => 'nullable|in:sms,email,whatsapp,phone_call',
            'branch_id'                => 'nullable|exists:branches,id',
            'status'                   => 'required|in:active,inactive,blacklisted',
            'joining_date'             => 'nullable|date',
        ]);

        $data['name']         = trim($data['first_name'] . ' ' . ($data['middle_name'] ? $data['middle_name'] . ' ' : '') . $data['last_name']);
        $data['loan_interest'] = $request->boolean('loan_interest');

        if ($request->hasFile('photo')) {
            if ($client->photo && file_exists(public_path($client->photo))) {
                @unlink(public_path($client->photo));
            }
            $data['photo'] = $this->storeClientPhoto($request->file('photo'));
        }

        $client->update($data);

        if ($client->group) {
            $client->group->update(['name' => $data['name']]);
        }

        return redirect()->route('clients.show', $client)->with('success', 'Client updated successfully.');
    }

    public function invite(Client $client)
    {
        if (!$client->email) {
            return back()->with('error', 'This client has no email address. Add an email first.');
        }

        // Find or create a portal user for this email
        $user = User::firstOrCreate(
            ['email' => $client->email],
            [
                'name'     => $client->name,
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
            ]
        );

        // Assign client role if not already set
        if (!$user->hasRole('client')) {
            $user->assignRole('client');
        }

        // Link ALL clients sharing this email to the same portal user
        $allClientIds = \App\Models\Client::where('email', $client->email)->pluck('id')->toArray();
        $user->portalClients()->syncWithoutDetaching($allClientIds);

        // Send password reset / invitation link
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $msg = "Portal invitation sent to {$client->email}.";
            return request()->wantsJson()
                ? response()->json(['success' => true, 'message' => $msg])
                : back()->with('success', $msg);
        }

        $err = 'Could not send invitation email. Please check mail configuration.';
        return request()->wantsJson()
            ? response()->json(['success' => false, 'message' => $err], 422)
            : back()->with('error', $err);
    }

    public function inviteGroupMembers(Client $client)
    {
        $group = $client->group;

        if (!$group) {
            return response()->json(['success' => false, 'message' => 'This client has no linked group.'], 422);
        }

        $members = $group->activeMembers()->whereNotNull('user_id')->with('user')->get();

        if ($members->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No group members have portal accounts set up.'], 422);
        }

        $sent    = 0;
        $failed  = 0;

        foreach ($members as $member) {
            if (!$member->user?->email) {
                $failed++;
                continue;
            }
            $status = Password::sendResetLink(['email' => $member->user->email]);
            $status === Password::RESET_LINK_SENT ? $sent++ : $failed++;
        }

        if ($sent > 0) {
            $msg = "Portal invitations sent to {$sent} member(s)" . ($failed > 0 ? " ({$failed} failed)." : '.');
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return response()->json(['success' => false, 'message' => 'Could not send invitations. Check mail configuration.'], 422);
    }

    public function destroy(Client $client)
    {
        $blocks = [];

        $loans = $client->loans()->whereIn('status', ['active', 'pending', 'defaulted'])->count();
        if ($loans) $blocks[] = "{$loans} active/pending loan(s)";

        $savings = $client->savingsAccounts()->whereIn('status', ['active', 'dormant'])->count();
        if ($savings) $blocks[] = "{$savings} savings account(s)";

        $fds = $client->fixedDeposits()->where('status', 'active')->count();
        if ($fds) $blocks[] = "{$fds} active fixed deposit(s)";

        $shares = $client->shares()->whereNotIn('status', ['liquidated'])->where('amount_paid', '>', 0)->count();
        if ($shares) $blocks[] = "{$shares} share record(s) with paid-in capital";

        if ($blocks) {
            return back()->with('error',
                'Cannot delete ' . $client->name . '. Please close or transfer: ' . implode(', ', $blocks) . '.'
            );
        }

        // If this client is a group, block deletion if the group has active members or transactions
        if ($client->isGroup()) {
            $group = $client->group;
            if ($group) {
                $activeMembers = $group->activeMembers()->count();
                if ($activeMembers) {
                    return back()->with('error',
                        'Cannot delete this group client — the group still has ' . $activeMembers . ' active member(s). Remove all members first.'
                    );
                }
                $groupTxns = $group->transactions()->count();
                if ($groupTxns) {
                    return back()->with('error',
                        'Cannot delete this group client — the group has ' . $groupTxns . ' transaction(s) on record.'
                    );
                }
                // Delete group members and the group itself
                $group->members()->delete();
                $group->delete();
            }
        }

        // Remove group memberships before deletion (when client is a member of another group)
        \App\Models\GroupMember::where('client_id', $client->id)->delete();

        // Delete zero-value unpaid share placeholders (auto-created on client registration)
        $client->shares()->where('amount_paid', '<=', 0)->delete();

        $client->delete();
        return redirect()->route('clients.index')->with('success', 'Client deleted.');
    }

    /**
     * Stores directly under public/uploads/clients rather than the
     * storage/app/public disk, matching the org logo upload - this host
     * blocks the web server from following the storage symlink, so anything
     * served via the disk never resolves. NOTE: must not be named "clients" -
     * that collides with the /clients route itself (LiteSpeed tries to list
     * the real directory instead of routing to Laravel, returning a 403).
     */
    private function storeClientPhoto($file): string
    {
        $dir = public_path('uploads/clients');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'uploads/clients/' . uniqid('client_') . '.' . $file->getClientOriginalExtension();
        $file->move($dir, basename($filename));

        return $filename;
    }

    private function generateClientNumber(): string
    {
        $last = Client::withTrashed()->count() + 1;
        return 'CLT-' . str_pad($last, 6, '0', STR_PAD_LEFT);
    }

    private function generateShareNumber(): string
    {
        $year = now()->format('Y');
        $last = MemberShare::whereYear('created_at', $year)->count() + 1;
        return 'SHR-' . $year . '-' . str_pad($last, 5, '0', STR_PAD_LEFT);
    }

    private function generateGroupNumber(): string
    {
        $last = Group::count() + 1;

        return 'GRP-' . str_pad((string) $last, 6, '0', STR_PAD_LEFT);
    }
}
