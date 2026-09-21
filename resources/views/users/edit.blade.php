@extends('layouts.app')
@section('title','Edit User')
@section('content')
<div class="mb-4">
    <a href="{{ route('users.index') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>Users
    </a>
    <h4 class="mb-0 fw-semibold mt-1">Edit User — {{ $user->name }}</h4>
</div>

<div class="row justify-content-center">
<div class="col-lg-9">
<div class="card shadow-sm">
<div class="card-body p-4">
<form method="POST" action="{{ route('users.update', $user) }}">
@csrf
@method('PUT')
<div class="row g-3">
    <div class="col-12">
        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $user->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $user->email) }}" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">New Password</label>
        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
               placeholder="Leave blank to keep current">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Confirm New Password</label>
        <input type="password" name="password_confirmation" class="form-control" placeholder="Leave blank to keep current">
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
            <option value="">— Select Role —</option>
            @foreach($roles as $role)
                <option value="{{ $role->name }}"
                    {{ old('role', $user->role_name) == $role->name ? 'selected' : '' }}>
                    {{ ucfirst(str_replace('_',' ',$role->name)) }}
                </option>
            @endforeach
        </select>
        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Branch</label>
        <select name="branch_id" class="form-select">
            <option value="">— All Branches —</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}"
                    {{ old('branch_id', $user->branch_id) == $branch->id ? 'selected' : '' }}>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12" id="client-field" style="{{ old('role', $user->role_name) === 'client' ? '' : 'display:none' }}">
        <label class="form-label fw-semibold">Linked Client</label>
        <select name="client_id" class="form-select @error('client_id') is-invalid @enderror" id="client_id_select">
            <option value="">— Select Client —</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ old('client_id', $user->client_id) == $c->id ? 'selected' : '' }}>
                    {{ $c->name }} ({{ $c->client_number }})
                </option>
            @endforeach
        </select>
        @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_loan_officer" id="is_loan_officer"
                   value="1" {{ old('is_loan_officer', $user->is_loan_officer) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_loan_officer">Appear in Loan Officer lists</label>
        </div>
        <div class="form-text">When on, this user can be picked as a client's Loan Officer.</div>
    </div>
</div>
<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">Update User</button>
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
</form>
</div>
</div>

{{-- Direct Permissions --}}
<div class="card shadow-sm mt-4">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <div>
            <span class="fw-semibold"><i class="bi bi-shield-lock me-2 text-muted"></i>Direct Permissions</span>
            <div class="text-muted small fw-normal mt-1">
                Extra permissions on top of their role.
                Role: <span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ',$user->role_name)) }}</span>
                &nbsp;<span class="text-muted small fst-italic">Green = already via role</span>
            </div>
        </div>
    </div>
    <form method="POST" action="{{ route('users.permissions', $user) }}">
    @csrf
    @php
        $categories  = \App\Http\Controllers\RoleController::permissionCategories();
        $directPerms = $user->getDirectPermissions()->pluck('name')->toArray();
        $rolePerms   = $user->getPermissionsViaRoles()->pluck('name')->toArray();
    @endphp
    <div class="card-body">
        <div class="row g-3">
        @foreach($categories as $label => $cat)
        <div class="col-md-4">
            <div class="card border h-100">
                <div class="card-header py-2 small fw-semibold" style="background:#f8f9fa">
                    <i class="bi {{ $cat['icon'] }} me-1 text-primary"></i>{{ $label }}
                </div>
                <div class="card-body py-2 px-3">
                @foreach($cat['permissions'] as $perm)
                @php
                    $viaRole = in_array($perm, $rolePerms);
                    $parts   = explode(' ', $perm);
                    $action  = $parts[0];
                    $subject = implode(' ', array_slice($parts, 1));
                    $actionBadge = match($action) {
                        'view'    => 'bg-success-subtle text-success',
                        'create'  => 'bg-primary-subtle text-primary',
                        'edit'    => 'bg-warning-subtle text-warning',
                        'delete'  => 'bg-danger-subtle text-danger',
                        'manage'  => 'bg-info-subtle text-info',
                        'use'     => 'bg-secondary-subtle text-secondary',
                        'process' => 'bg-info-subtle text-info',
                        default   => 'bg-secondary-subtle text-secondary',
                    };
                @endphp
                <div class="form-check d-flex align-items-center gap-2 py-1 border-bottom">
                    <input class="form-check-input direct-perm-check flex-shrink-0 mt-0"
                           type="checkbox"
                           name="direct_permissions[]"
                           value="{{ $perm }}"
                           id="dp_{{ Str::slug($perm) }}"
                           {{ in_array($perm, $directPerms) ? 'checked' : '' }}
                           {{ $viaRole ? 'disabled' : '' }}>
                    <label class="form-check-label d-flex align-items-center gap-2 w-100 mb-0 {{ $viaRole ? 'text-muted' : '' }}"
                           for="dp_{{ Str::slug($perm) }}" style="cursor:pointer">
                        <span class="badge {{ $viaRole ? 'bg-success-subtle text-success' : $actionBadge }}"
                              style="min-width:58px;font-size:10px;font-weight:600">
                            {{ $viaRole ? 'via role' : ucfirst($action) }}
                        </span>
                        <span class="small">{{ ucfirst(str_replace('-',' ',$subject)) }}</span>
                    </label>
                </div>
                @endforeach
                </div>
            </div>
        </div>
        @endforeach
        </div>
    </div>
    <div class="card-footer d-flex align-items-center gap-3">
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-check-lg me-1"></i>Save Direct Permissions
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllDirect(true)">Select All</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllDirect(false)">Deselect All</button>
    </div>
    </form>
</div>

</div>
</div>
@push('scripts')
<script>
const roleSelect = document.querySelector('select[name="role"]');
const clientField = document.getElementById('client-field');
function toggleClientField() {
    clientField.style.display = roleSelect.value === 'client' ? '' : 'none';
}
roleSelect.addEventListener('change', toggleClientField);

function toggleAllDirect(state) {
    document.querySelectorAll('.direct-perm-check:not(:disabled)').forEach(cb => cb.checked = state);
}
</script>
@endpush
@endsection
