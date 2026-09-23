<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Group;
use App\Models\MemberShare;
use App\Models\User;
use App\Services\ClientNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ClientController extends Controller
{
    public function __construct(protected ClientNotificationService $clientNotifier) {}

    public function index(Request $request)
    {
        $query = Client::query()
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', "%{$request->search}%")
                    ->orWhere('client_number', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        if ($request->format === 'pdf') {
            $clients = (clone $query)->with('branch')->latest()->get();

            $summary = [
                'total'       => $clients->count(),
                'active'      => $clients->where('status', 'active')->count(),
                'inactive'    => $clients->where('status', 'inactive')->count(),
                'blacklisted' => $clients->where('status', 'blacklisted')->count(),
                'groups'      => $clients->where('client_type', 'group')->count(),
            ];

            $pdf = Pdf::loadView('pdf.clients-list', compact('clients', 'summary'))
                ->setPaper('a4', 'landscape');

            return $pdf->download('clients-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'csv') {
            $clients = (clone $query)->with('branch', 'relationshipManager', 'createdBy')->latest()->get();
            $showMembership = \App\Models\SystemSetting::get('membership_fee_module_enabled', '1');

            return response()->streamDownload(function () use ($clients, $showMembership) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 names correctly

                $header = ['#', 'Client Number', 'Name', 'Type', 'Loan Officer', 'Phone', 'Email', 'Status', 'Branch', 'Joining Date'];
                if ($showMembership) {
                    $header[] = 'Membership';
                }
                fputcsv($out, $header);

                foreach ($clients as $i => $client) {
                    $row = [
                        $i + 1,
                        $client->client_number,
                        $client->name,
                        ucfirst($client->client_type ?? 'individual'),
                        $client->relationship_manager_name,
                        $client->phone,
                        $client->email,
                        ucfirst($client->status),
                        $client->branch?->name,
                        $client->joining_date?->format('Y-m-d'),
                    ];
                    if ($showMembership) {
                        $row[] = ucfirst($client->membership_fee_status);
                    }
                    fputcsv($out, $row);
                }

                fclose($out);
            }, 'clients-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $relationshipManagers = $this->relationshipManagerOptions();

        $clients = $query->withCount('shares')
            ->with([
                'shares' => fn($q) => $q->select('client_id', 'share_value', 'amount_paid', 'status'),
                'relationshipManager', 'createdBy',
            ])
            ->latest()
            ->paginate(20);

        return view('clients.index', compact('clients', 'relationshipManagers'));
    }

    /** Relationship managers (shown as "Loan Officer") are the staff users switched on under Users > Edit. */
    private function relationshipManagerOptions()
    {
        return \App\Models\User::loanOfficers()->orderBy('name')->get();
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

            if (auth()->user()?->isBranchScoped()) {
                $data['branch_id'] = auth()->user()->branch_id;
            }

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
                'branch_id'             => $client->branch_id,
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

            $this->clientNotifier->clientWelcomed($client);

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

        if (auth()->user()?->isBranchScoped()) {
            $data['branch_id'] = auth()->user()->branch_id;
        }

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

        $this->clientNotifier->clientWelcomed($client);

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
            'relationshipManager',
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

        $relationshipManagers = $this->relationshipManagerOptions();

        return view('clients.edit', compact('client', 'branches', 'relationshipManagers'));
    }

    public function update(Request $request, Client $client)
    {
        $rules = [
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
            'relationship_manager_id'  => 'nullable|exists:users,id',
            'status'                   => 'required|in:active,inactive,blacklisted',
            'joining_date'             => 'nullable|date',
        ];

        // Client Number is locked down separately from the rest of the profile - it's printed
        // on every receipt/statement a member holds, so changing it needs its own permission.
        // Ignored entirely (not just hidden in the form) for anyone without it, even if submitted.
        $canEditNumber = auth()->user()?->can('edit client number');
        if ($canEditNumber) {
            $rules['client_number'] = ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique('clients', 'client_number')->ignore($client->id)];
        }

        $data = $request->validate($rules);

        $data['name']         = trim($data['first_name'] . ' ' . ($data['middle_name'] ? $data['middle_name'] . ' ' : '') . $data['last_name']);
        $data['loan_interest'] = $request->boolean('loan_interest');

        if (auth()->user()?->isBranchScoped()) {
            unset($data['branch_id']);
        }

        if ($request->hasFile('photo')) {
            if ($client->photo && file_exists(public_path($client->photo))) {
                @unlink(public_path($client->photo));
            }
            $data['photo'] = $this->storeClientPhoto($request->file('photo'));
        }

        $originalClientNumber = $client->client_number;

        $client->update($data);

        if ($client->group) {
            $client->group->update(['name' => $data['name']]);
        }

        // The blanket "Updated client" audit entry (from AuditActivity middleware) doesn't
        // capture field values - record the old/new number explicitly since this one matters.
        if ($canEditNumber && isset($data['client_number']) && $data['client_number'] !== $originalClientNumber) {
            \App\Models\AuditLog::record(
                'update',
                "Changed Client Number for {$client->name} from '{$originalClientNumber}' to '{$data['client_number']}'",
                'Clients'
            );
        }

        return redirect()->route('clients.show', $client)->with('success', 'Client updated successfully.');
    }

    public function updateRelationshipManager(Request $request, Client $client)
    {
        $request->validate(['relationship_manager_id' => 'nullable|exists:users,id']);

        $client->update(['relationship_manager_id' => $request->relationship_manager_id ?: null]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Loan Officer updated for ' . $client->name . '.',
            ]);
        }

        return back()->with('success', 'Loan Officer updated for ' . $client->name . '.');
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

    /**
     * These generate org-wide unique sequential numbers, so they must always
     * count across every branch — never scoped to the acting user's own
     * branch — or two branches will hand out the same number.
     */
    /**
     * Prefix/padding are configurable (Settings > Client Number Prefix) since
     * this codebase serves multiple orgs with different legacy numbering
     * (e.g. sipmart's imported "SIP/255" vs the default "CLT-000255").
     * Uses the raw table (not Eloquent) so it's immune to branch scoping and
     * takes MAX(suffix)+1 rather than a row count, since legacy-imported
     * numbers have gaps — a count would collide with an already-used number.
     */
    private function generateClientNumber(): string
    {
        $prefix = \App\Models\SystemSetting::get('client_number_prefix', 'CLT-');
        $pad    = (int) \App\Models\SystemSetting::get('client_number_padding', 6);

        $max = \Illuminate\Support\Facades\DB::table('clients')
            ->where('client_number', 'like', $prefix . '%')
            ->max(\Illuminate\Support\Facades\DB::raw("CAST(SUBSTRING(client_number, " . (strlen($prefix) + 1) . ") AS UNSIGNED)"));

        return $prefix . str_pad((int) $max + 1, $pad, '0', STR_PAD_LEFT);
    }

    private function generateShareNumber(): string
    {
        $year = now()->format('Y');
        $last = MemberShare::withoutGlobalScope('branch')->whereYear('created_at', $year)->count() + 1;
        return 'SHR-' . $year . '-' . str_pad($last, 5, '0', STR_PAD_LEFT);
    }

    private function generateGroupNumber(): string
    {
        $last = Group::withoutGlobalScope('branch')->count() + 1;

        return 'GRP-' . str_pad((string) $last, 6, '0', STR_PAD_LEFT);
    }
}
