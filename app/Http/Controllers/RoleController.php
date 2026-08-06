<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private const SYSTEM_ROLES = ['super_admin', 'admin', 'manager', 'cashier', 'staff', 'client', 'group_leader', 'group_member'];
    public function index()
    {
        $roles = Role::withCount('permissions')->orderBy('name')->get();
        return view('roles.index', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required', 'string', 'max:50',
                'regex:/^[a-z0-9_]+$/',
                'unique:roles,name',
            ],
        ], [
            'name.regex' => 'Role name must be lowercase letters, numbers, and underscores only (e.g. hr_manager).',
        ]);

        Role::create(['name' => $request->name, 'guard_name' => 'web']);

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$request->name}\" created. Now assign permissions to it.");
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, self::SYSTEM_ROLES)) {
            return back()->with('error', "The \"{$role->name}\" role is a system role and cannot be deleted.");
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', "Cannot delete role \"{$role->name}\" — it is assigned to {$role->users()->count()} user(s). Reassign them first.");
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" deleted.");
    }

    public function edit(Role $role)
    {
        $categories      = self::permissionCategories();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('roles.edit', compact('role', 'categories', 'rolePermissions'));
    }

    /**
     * Returns permissions grouped into labelled categories with icons.
     * Each category: ['label' => '...', 'icon' => 'bi-...', 'permissions' => [...]]
     */
    public static function permissionCategories(): array
    {
        $all = Permission::orderBy('name')->pluck('name')->toArray();

        $map = [
            'Dashboard' => [
                'icon'  => 'bi-speedometer2',
                'perms' => ['view dashboard'],
            ],
            'Clients & Members' => [
                'icon'  => 'bi-people-fill',
                'perms' => ['view clients', 'create clients', 'edit clients', 'delete clients', 'manage shares'],
            ],
            'Loans' => [
                'icon'  => 'bi-cash-coin',
                'perms' => ['view loan-products', 'create loan-products', 'edit loan-products',
                            'view loans', 'create loans', 'approve loans', 'disburse loans', 'repay loans'],
            ],
            'Savings' => [
                'icon'  => 'bi-piggy-bank-fill',
                'perms' => ['view savings-products', 'create savings-products', 'edit savings-products',
                            'view savings', 'create savings', 'deposit savings', 'withdraw savings', 'transfer savings'],
            ],
            'Fixed Deposits' => [
                'icon'  => 'bi-safe-fill',
                'perms' => ['view fd-products', 'create fd-products', 'edit fd-products',
                            'view fixed-deposits', 'create fixed-deposits', 'mature fixed-deposits'],
            ],
            'Teller' => [
                'icon'  => 'bi-cash-stack',
                'perms' => ['use teller'],
            ],
            'Journal Entries' => [
                'icon'  => 'bi-journal-bookmark-fill',
                'perms' => ['view transactions', 'create transactions', 'reverse transactions'],
            ],
            'Chart of Accounts' => [
                'icon'  => 'bi-diagram-3-fill',
                'perms' => ['view accounts', 'create accounts', 'edit accounts'],
            ],
            'Groups' => [
                'icon'  => 'bi-collection-fill',
                'perms' => ['view groups', 'manage groups'],
            ],
            'Employees' => [
                'icon'  => 'bi-person-badge-fill',
                'perms' => ['view employees', 'create employees', 'edit employees'],
            ],
            'Payroll' => [
                'icon'  => 'bi-receipt-cutoff',
                'perms' => ['view payroll', 'create payroll', 'process payroll', 'delete payroll'],
            ],
            'Reports' => [
                'icon'  => 'bi-bar-chart-fill',
                'perms' => ['view reports'],
            ],
            'Administration' => [
                'icon'  => 'bi-gear-fill',
                'perms' => ['manage branches', 'manage users', 'manage settings', 'manage backup'],
            ],
        ];

        // Build categories — only include permissions that actually exist in DB.
        // Any DB permission not in the map above goes into "Other".
        $categorised = [];
        $placed       = [];

        foreach ($map as $label => $def) {
            $existing = array_values(array_intersect($def['perms'], $all));
            if (empty($existing)) continue;
            $categorised[$label] = ['icon' => $def['icon'], 'permissions' => $existing];
            array_push($placed, ...$existing);
        }

        $other = array_values(array_diff($all, $placed));
        if (!empty($other)) {
            $categorised['Other'] = ['icon' => 'bi-shield', 'permissions' => $other];
        }

        return $categorised;
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->syncPermissions($request->input('permissions', []));

        // Flush permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('roles.index')
            ->with('success', "Permissions for role \"{$role->name}\" updated.");
    }

    public function updateUserPermissions(Request $request, \App\Models\User $user)
    {
        $request->validate([
            'direct_permissions'   => 'nullable|array',
            'direct_permissions.*' => 'exists:permissions,name',
        ]);

        // Sync only direct permissions (does not touch roles)
        $user->syncPermissions($request->input('direct_permissions', []));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', 'Direct permissions updated for ' . $user->name . '.');
    }
}
