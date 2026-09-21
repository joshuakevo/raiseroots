@extends('layouts.app')
@section('title', 'Loans')
@section('breadcrumb')
    <li class="breadcrumb-item active">Loans</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Loans</h4>
    @can('create loans')
    <a href="{{ route('loans.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Loan</a>
    @endcan
</div>
<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search loan # or client name..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="issued" @selected(request('status')=='issued')>Issued (Active/Closed/Defaulted)</option>
                    <option value="pending" @selected(request('status')=='pending')>Pending</option>
                    <option value="approved" @selected(request('status')=='approved')>Approved</option>
                    <option value="active" @selected(request('status')=='active')>Active</option>
                    <option value="closed" @selected(request('status')=='closed')>Closed</option>
                    <option value="defaulted" @selected(request('status')=='defaulted')>Defaulted</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Loan Officer</label>
                <select name="relationship_manager_id" class="form-select">
                    <option value="">All Loan Officers</option>
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
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
            <div class="col-auto"><a href="{{ route('loans.index') }}" class="btn btn-outline-secondary">Clear</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Loan #</th><th>Client</th><th>Product</th>
                <th class="text-end">Outstanding</th>
                <th>Disbursed</th><th>Loan Officer</th><th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($loans as $loan)
                @php
                    // Pending = waiting for approval; approved = waiting to be disbursed.
                    $needsAction = in_array($loan->status, ['pending', 'approved']);
                    $rowTint     = $loan->status === 'pending' ? 'table-warning' : ($loan->status === 'approved' ? 'table-info' : '');
                    $accent      = $loan->status === 'pending' ? '#ffc107' : '#0dcaf0';
                @endphp
                <tr class="{{ $rowTint }}" @if($needsAction) style="box-shadow: inset 4px 0 0 {{ $accent }}" @endif>
                    <td class="ps-3 font-monospace">{{ $loan->loan_number }}</td>
                    <td>
                        @if($loan->client)
                            <a href="{{ route('clients.show', $loan->client) }}" class="text-decoration-none">{{ $loan->client->name }}</a>
                        @else
                            <span class="text-muted fst-italic">Deleted client</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $loan->product->name }}</td>
                    <td class="text-end fw-semibold {{ $loan->outstanding_principal > 0 ? 'text-warning' : 'text-success' }}">
                        {{ number_format($loan->outstanding_principal, $dp) }}
                    </td>
                    <td class="small text-muted">{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</td>
                    <td class="small text-muted">{{ $loan->client?->relationshipManager?->name ?? '—' }}</td>
                    <td>
                        <span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span>
                        @if($needsAction)
                            <div class="fw-semibold" style="font-size:.68rem">{{ $loan->status === 'pending' ? 'Awaiting approval' : 'Awaiting disbursement' }}</div>
                        @endif
                    </td>
                    <td class="pe-3">
                        <a href="{{ route('loans.show', $loan) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('repay loans')
                        @if(in_array($loan->status, ['active', 'defaulted']))
                            <a href="{{ route('loans.repay-form', $loan) }}" class="btn btn-sm btn-success"><i class="bi bi-cash"></i></a>
                        @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No loans found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $loans->withQueryString()->links() }}</div>
</div>
@endsection
