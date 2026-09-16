@extends('layouts.app')
@section('title', 'General Ledger')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">General Ledger</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">General Ledger</h4>
    @if(isset($account))
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
    @endif
</div>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Account <span class="text-danger">*</span></label>
                <select name="account_id" class="form-select ts-select" required>
                    <option value="">Select account...</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" @selected(isset($account) && $account->id==$a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small fw-semibold">From Date</label><input type="date" name="from_date" class="form-control" value="{{ $fromDate }}"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold">To Date</label><input type="date" name="to_date" class="form-control" value="{{ $toDate }}"></div>
            @include('reports.partials.branch-filter')
            <div class="col-auto align-self-end"><button class="btn btn-primary">Run Report</button></div>
        </form>
    </div>
</div>
@if(isset($account))
<div class="card">
    <div class="card-header">{{ $account->account_code }} — {{ $account->account_name }}</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Date</th><th>Reference</th><th>Description</th>
                <th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end pe-3">Balance</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="ps-3">{{ $row['line']->transaction->date->format('d M Y') }}</td>
                    <td><a href="{{ route('transactions.show', $row['line']->transaction) }}" class="font-monospace text-decoration-none">{{ $row['line']->transaction->reference }}</a></td>
                    <td class="small">{{ $row['line']->description ?? $row['line']->transaction->description }}</td>
                    <td class="text-end">{{ $row['line']->debit > 0 ? number_format($row['line']->debit, $dp) : '' }}</td>
                    <td class="text-end">{{ $row['line']->credit > 0 ? number_format($row['line']->credit, $dp) : '' }}</td>
                    <td class="text-end pe-3 fw-semibold {{ $row['balance'] < 0 ? 'text-danger' : '' }}">{{ number_format($row['balance'], $dp) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No ledger entries for this account.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
