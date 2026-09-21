@extends('layouts.app')
@section('title','Users')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-semibold">Users</h4>
        <p class="text-muted small mb-0">Manage system users and their roles</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> New User
    </a>
</div>

@php
    $roleColors = ['super_admin'=>'danger','admin'=>'warning','cashier'=>'info','staff'=>'secondary'];
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" title="Appears in the Loan Officer dropdown on Clients">Loan Officer</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-semibold"
                                 style="width:36px;height:36px;font-size:14px;flex-shrink:0">
                                {{ strtoupper(substr($user->name,0,1)) }}
                            </div>
                            <span class="fw-semibold">{{ $user->name }}</span>
                        </div>
                    </td>
                    <td class="text-muted">{{ $user->email }}</td>
                    <td>{{ $user->phone ?? '—' }}</td>
                    <td>
                        <span class="badge bg-{{ $roleColors[$user->role_name] ?? 'secondary' }}-subtle text-{{ $roleColors[$user->role_name] ?? 'secondary' }}">
                            {{ ucfirst(str_replace('_',' ',$user->role_name)) }}
                        </span>
                    </td>
                    <td>{{ $user->branch?->name ?? '—' }}</td>
                    <td class="text-center">
                        @if($user->is_active)
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger">Inactive</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <form method="POST" action="{{ route('users.toggle-loan-officer', $user) }}" class="d-inline-block">
                            @csrf
                            <div class="form-check form-switch d-flex justify-content-center m-0 p-0">
                                <input class="form-check-input m-0" type="checkbox" role="switch"
                                       @checked($user->is_loan_officer) onchange="this.form.submit()"
                                       aria-label="Appear in Loan Officer lists: {{ $user->name }}">
                            </div>
                        </form>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('users.toggle-status', $user) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-{{ $user->is_active ? 'warning' : 'success' }}"
                                        title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                    <i class="bi bi-{{ $user->is_active ? 'pause' : 'play' }}"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-4 text-muted">No system users found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
