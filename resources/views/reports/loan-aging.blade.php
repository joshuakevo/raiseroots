@extends('layouts.app')
@section('title', 'Loan Aging')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Loan Aging</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Loan Aging Report</h4>
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
                <label class="form-label small fw-semibold">As of Date</label>
                <input type="date" name="as_of" class="form-control" value="{{ $asOf }}">
            </div>
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
                <button class="btn btn-primary">Run Report</button>
                <a href="{{ route('reports.loan-aging') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

@php
$colors = ['1-30'=>'success','31-60'=>'info','61-90'=>'warning','91-180'=>'orange','181+'=>'danger'];
$labels = ['1-30'=>'1-30 Days','31-60'=>'31-60 Days','61-90'=>'61-90 Days','91-180'=>'91-180 Days','181+'=>'181+ Days'];
@endphp

@foreach($buckets as $bucket => $items)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-{{ $colors[$bucket] ?? 'secondary' }}">{{ $labels[$bucket] }} Overdue</span>
        <span class="badge bg-{{ $colors[$bucket] ?? 'secondary' }}">{{ count($items) }} loans | {{ number_format(array_sum(array_column($items, 'outstanding')), $dp) }}</span>
    </div>
    @if(count($items) > 0)
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Loan #</th><th>Client</th><th>Due Date</th>
                <th class="text-end">Days Overdue</th><th class="text-end pe-3">Outstanding</th>
            </tr></thead>
            <tbody>
            @foreach($items as $item)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $item['schedule']->loan) }}" class="text-decoration-none">{{ $item['schedule']->loan->loan_number }}</a></td>
                    <td>{{ $item['schedule']->loan->client->name }}</td>
                    <td>{{ $item['schedule']->due_date->format('d M Y') }}</td>
                    <td class="text-end text-{{ $colors[$bucket] ?? 'secondary' }} fw-semibold">{{ $item['days'] }}</td>
                    <td class="text-end pe-3">{{ number_format($item['outstanding'], $dp) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
        <div class="card-body text-muted text-center small">No overdue loans in this bracket.</div>
    @endif
</div>
@endforeach
@endsection
