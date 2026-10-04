@extends('layouts.app')
@section('title', 'Outstanding Balances')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Outstanding Balances</li>
@endsection
@section('content')
@php
    $statusLabel = match (request('status')) {
        'active'    => 'Active Loans',
        'defaulted' => 'Defaulted Loans',
        default     => 'Active & Defaulted Loans',
    };
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Outstanding Balances <span class="text-muted fs-6 fw-normal">— {{ $statusLabel }}</span></h4>
    <a href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-excel me-1 text-success"></i>Export Excel (CSV)</a>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">Active &amp; Defaulted</option>
                    <option value="active" @selected(request('status')=='active')>Active</option>
                    <option value="defaulted" @selected(request('status')=='defaulted')>Defaulted</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Relationship Manager</label>
                <select name="relationship_manager_id" class="form-select">
                    <option value="">All Relationship Managers</option>
                    @foreach($relationshipManagers as $user)
                        <option value="{{ $user->id }}" @selected(request('relationship_manager_id')==$user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary">Filter</button>
                <a href="{{ route('reports.outstanding-balances') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-md"><div class="stat-card text-center"><div class="text-muted small">Loans</div><div class="fw-bold fs-4">{{ number_format($loans->count()) }}</div></div></div>
    <div class="col-6 col-md"><div class="stat-card text-center"><div class="text-muted small">Outstanding Principal</div><div class="fw-bold fs-5">{{ number_format($totals['principal'], $dp) }}</div></div></div>
    <div class="col-6 col-md"><div class="stat-card text-center"><div class="text-muted small">Outstanding Interest</div><div class="fw-bold fs-5">{{ number_format($totals['interest'], $dp) }}</div></div></div>
    <div class="col-6 col-md"><div class="stat-card text-center"><div class="text-muted small">Outstanding Admin Costs</div><div class="fw-bold fs-5">{{ number_format($totals['admin_fee'], $dp) }}</div></div></div>
    <div class="col-12 col-md"><div class="stat-card text-center"><div class="text-muted small">Total Outstanding</div><div class="fw-bold fs-5 text-warning">{{ number_format($totals['total'], $dp) }}</div></div></div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Loan #</th><th>Client</th><th>Relationship Manager</th><th>Product</th><th>Status</th>
                <th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Admin Fee</th><th class="text-end pe-3">Total</th>
            </tr></thead>
            <tbody>
            @forelse($loans as $loan)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $loan) }}" class="text-decoration-none">{{ $loan->loan_number }}</a></td>
                    <td>{{ $loan->client->name }}</td>
                    <td class="small text-muted">{{ $loan->client->relationshipManager?->name ?? '—' }}</td>
                    <td class="small text-muted">{{ $loan->product->name }}</td>
                    <td><span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td>
                    <td class="text-end">{{ number_format($loan->outstanding_principal, $dp) }}</td>
                    <td class="text-end">{{ number_format($loan->outstanding_interest, $dp) }}</td>
                    <td class="text-end">{{ number_format($loan->outstanding_admin_fee, $dp) }}</td>
                    <td class="text-end pe-3 fw-semibold">{{ number_format($loan->outstanding_principal + $loan->outstanding_interest + $loan->outstanding_admin_fee, $dp) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No loans found.</td></tr>
            @endforelse
            </tbody>
            @if($loans->isNotEmpty())
            <tfoot class="table-light fw-bold">
                <tr>
                    <td class="ps-3" colspan="5">Totals</td>
                    <td class="text-end">{{ number_format($totals['principal'], $dp) }}</td>
                    <td class="text-end">{{ number_format($totals['interest'], $dp) }}</td>
                    <td class="text-end">{{ number_format($totals['admin_fee'], $dp) }}</td>
                    <td class="text-end pe-3">{{ number_format($totals['total'], $dp) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
