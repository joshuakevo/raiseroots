@extends('layouts.app')
@section('title', 'Trial Balance')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Trial Balance</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Trial Balance</h4>
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
        <form class="row g-2" method="GET">
            <div class="col-md-3"><label class="form-label small fw-semibold">From Date</label><input type="date" name="from_date" class="form-control" value="{{ $fromDate }}"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">To Date</label><input type="date" name="to_date" class="form-control" value="{{ $toDate }}"></div>
            @include('reports.partials.branch-filter')
            <div class="col-auto align-self-end"><button class="btn btn-primary">Run Report</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>Trial Balance {{ $fromDate ? "from $fromDate" : '' }} to {{ $toDate }}</span>
        @if($data['balanced'])
            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Balanced</span>
        @else
            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Unbalanced</span>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Code</th><th>Account Name</th><th>Type</th>
                <th class="text-end">Debit</th><th class="text-end pe-3">Credit</th>
            </tr></thead>
            <tbody>
            @foreach($data['rows'] as $row)
                <tr>
                    <td class="ps-3 font-monospace">{{ $row['account_code'] }}</td>
                    <td>{{ $row['account_name'] }}</td>
                    <td>@php
                        $typeStyle = match($row['account_type']) {
                            'asset'     => 'background:#2563eb;color:#fff',
                            'liability' => 'background:#dc2626;color:#fff',
                            'equity'    => 'background:#7c3aed;color:#fff',
                            'income'    => 'background:#059669;color:#fff',
                            'expense'   => 'background:#d97706;color:#fff',
                            default     => 'background:#6b7280;color:#fff',
                        };
                    @endphp
                    <span class="badge" style="{{ $typeStyle }}">{{ ucfirst($row['account_type']) }}</span></td>
                    <td class="text-end">{{ $row['debit'] > 0 ? number_format($row['debit'], $dp) : '' }}</td>
                    <td class="text-end pe-3">{{ $row['credit'] > 0 ? number_format($row['credit'], $dp) : '' }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td colspan="3" class="ps-3 text-end">Totals</td>
                    <td class="text-end">{{ number_format($data['total_debit'], $dp) }}</td>
                    <td class="text-end pe-3">{{ number_format($data['total_credit'], $dp) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
