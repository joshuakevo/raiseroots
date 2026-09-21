<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'branch'])
            ->whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['group_member', 'group_leader', 'client']))
            ->orderBy('name')
            ->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles    = Role::orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $clients  = Client::where('status', 'active')->orderBy('name')->get();
        return view('users.create', compact('roles', 'branches', 'clients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'phone'     => 'nullable|string|max:20',
            'branch_id' => 'nullable|exists:branches,id',
            'client_id' => 'nullable|exists:clients,id',
            'role'      => 'required|exists:roles,name',
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'phone'     => $data['phone'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ] + $this->loanOfficerData($request));

        $user->assignRole($data['role']);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $user->load(['roles', 'branch']);
        return view('users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $roles    = Role::orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $clients  = Client::where('status', 'active')->orderBy('name')->get();
        return view('users.edit', compact('user', 'roles', 'branches', 'clients'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email,' . $user->id,
            'password'  => 'nullable|string|min:8|confirmed',
            'phone'     => 'nullable|string|max:20',
            'branch_id' => 'nullable|exists:branches,id',
            'client_id' => 'nullable|exists:clients,id',
            'role'      => 'required|exists:roles,name',
            'is_active' => 'boolean',
        ]);

        $updateData = [
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ] + $this->loanOfficerData($request);

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function toggleStatus(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "User {$status} successfully.");
    }

    public function toggleLoanOfficer(User $user)
    {
        if (!User::loanOfficerFlagAvailable()) {
            return back()->with('error', 'Run migrations first (Settings) to enable Loan Officer lists.');
        }

        $user->update(['is_loan_officer' => !$user->is_loan_officer]);
        $state = $user->is_loan_officer ? 'now appears' : 'no longer appears';

        return back()->with('success', "{$user->name} {$state} in Loan Officer lists.");
    }

    /** Skipped until the is_loan_officer migration has run, so saving a user never 500s on a missing column. */
    private function loanOfficerData(Request $request): array
    {
        return User::loanOfficerFlagAvailable()
            ? ['is_loan_officer' => $request->boolean('is_loan_officer')]
            : [];
    }

    public function assignRole(Request $request, User $user)
    {
        $request->validate(['role' => 'required|exists:roles,name']);
        $user->syncRoles([$request->role]);
        return back()->with('success', 'Role assigned successfully.');
    }
}
