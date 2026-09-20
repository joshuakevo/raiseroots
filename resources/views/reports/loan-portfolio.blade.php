@extends('layouts.app')
@section('title', 'Loan Portfolio')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Loan Portfolio</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Loan Portfolio Report</h4>
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach(['pending','active','closed','defaulted'] as $s)
                        <option value="{{ $s }}" @selected(request('status')==$s)>{{ ucfirst($s) }}</option>
                    @endforeach
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
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Disbursed From</label>
                <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Disbursed To</label>
                <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary">Filter</button>
                <a href="{{ route('reports.loan-portfolio') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="stat-card text-center"><div class="text-muted small">Total Loans</div><div class="fw-bold fs-4">{{ $summary['total_loans'] }}</div></div></div>
    <div class="col-md-2"><div class="stat-card text-center"><div class="text-muted small">Active</div><div class="fw-bold fs-4 text-success">{{ $summary['active_loans'] }}</div></div></div>
    <div class="col-md-2"><div class="stat-card text-center"><div class="text-muted small">Closed</div><div class="fw-bold fs-4">{{ $summary['closed_loans'] }}</div></div></div>
    <div class="col-md-2"><div class="stat-card text-center"><div class="text-muted small">Defaulted</div><div class="fw-bold fs-4 text-danger">{{ $summary['defaulted_loans'] }}</div></div></div>
    <div class="col-md-2"><div class="stat-card text-center"><div class="text-muted small">Disbursed</div><div class="fw-bold fs-5">{{ number_format($summary['total_disbursed'], $dp) }}</div></div></div>
    <div class="col-md-2"><div class="stat-card text-center"><div class="text-muted small">Outstanding</div><div class="fw-bold fs-5 text-warning">{{ number_format($summary['total_outstanding'], $dp) }}</div></div></div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Loan #</th><th>Client</th><th>Relationship Manager</th><th>Product</th><th>Method</th>
                <th class="text-end">Principal</th><th class="text-end">Outstanding</th>
                <th>Disbursed</th><th>Maturity</th><th>Status</th>
            </tr></thead>
            <tbody>
            @forelse($loans as $loan)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $loan) }}" class="text-decoration-none">{{ $loan->loan_number }}</a></td>
                    <td>{{ $loan->client->name }}</td>
                    <td class="small text-muted">{{ $loan->client->relationshipManager?->name ?? '—' }}</td>
                    <td class="small text-muted">{{ $loan->product->name }}</td>
                    <td><span class="badge bg-light text-dark">{{ ucfirst($loan->interest_method) }}</span></td>
                    <td class="text-end">{{ number_format($loan->principal, $dp) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($loan->outstanding_principal, $dp) }}</td>
                    <td class="small">{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</td>
                    <td class="small">{{ $loan->maturity_date?->format('d M Y') ?? '—' }}</td>
                    <td><span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No loans found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
