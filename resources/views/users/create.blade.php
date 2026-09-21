@extends('layouts.app')
@section('title','New User')
@section('content')
<div class="mb-4">
    <a href="{{ route('users.index') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>Users
    </a>
    <h4 class="mb-0 fw-semibold mt-1">New User</h4>
</div>

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card shadow-sm">
<div class="card-body p-4">
<form method="POST" action="{{ route('users.store') }}">
@csrf
<div class="row g-3">
    <div class="col-12">
        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email') }}" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
            <option value="">— Select Role —</option>
            @foreach($roles as $role)
                <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                    {{ ucfirst(str_replace('_',' ',$role->name)) }}
                </option>
            @endforeach
        </select>
        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Branch</label>
        <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
            <option value="">— All Branches —</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12" id="client-field" style="display:none">
        <label class="form-label fw-semibold">Linked Client <span class="text-danger">*</span></label>
        <select name="client_id" class="form-select @error('client_id') is-invalid @enderror" id="client_id_select">
            <option value="">— Select Client —</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ old('client_id') == $c->id ? 'selected' : '' }}>
                    {{ $c->name }} ({{ $c->client_number }})
                </option>
            @endforeach
        </select>
        @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Required when role is Client.</div>
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_loan_officer" id="is_loan_officer"
                   value="1" {{ old('is_loan_officer') ? 'checked' : '' }}>
            <label class="form-check-label" for="is_loan_officer">Appear in Loan Officer lists</label>
        </div>
        <div class="form-text">When on, this user can be picked as a client's Loan Officer.</div>
    </div>
</div>

@push('scripts')
<script>
const roleSelect = document.querySelector('select[name="role"]');
const clientField = document.getElementById('client-field');
function toggleClientField() {
    const show = roleSelect.value === 'client';
    clientField.style.display = show ? '' : 'none';
    document.getElementById('client_id_select').required = show;
}
roleSelect.addEventListener('change', toggleClientField);
toggleClientField();
</script>
@endpush

<div class="alert alert-info mt-3 small">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Role permissions:</strong><br>
    <span class="text-muted">
        <b>Super Admin</b> — Full access &nbsp;|&nbsp;
        <b>Admin</b> — Full access except backup &nbsp;|&nbsp;
        <b>Cashier</b> — Teller, savings transactions, loan repayments &nbsp;|&nbsp;
        <b>Staff</b> — Read-only + create clients and applications
    </span>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">Create User</button>
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
</form>
</div>
</div>
</div>
</div>
@endsection
