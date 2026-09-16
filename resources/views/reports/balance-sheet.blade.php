@extends('layouts.app')
@section('title', 'Balance Sheet')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Balance Sheet</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Balance Sheet</h4>
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
            <div class="col-md-3"><label class="form-label small fw-semibold">As of Date</label><input type="date" name="as_of" class="form-control" value="{{ $asOf }}"></div>
            @include('reports.partials.branch-filter')
            <div class="col-auto align-self-end"><button class="btn btn-primary">Run Report</button></div>
        </form>
    </div>
</div>
<div class="row g-3">
    <!-- Assets -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header fw-semibold">Assets</div>
            <table class="table mb-0">
                <tbody>
                @foreach($data['asset']['rows'] as $row)
                    <tr><td class="ps-3">{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td><td class="text-end pe-3">{{ number_format($row['balance'], $dp) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold"><tr><td class="ps-3">Total Assets</td><td class="text-end pe-3 text-primary">{{ number_format($data['asset']['total'], $dp) }}</td></tr></tfoot>
            </table>
        </div>
    </div>
    <!-- Liabilities + Equity -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Liabilities</div>
            <table class="table mb-0">
                <tbody>
                @foreach($data['liability']['rows'] as $row)
                    <tr><td class="ps-3">{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td><td class="text-end pe-3">{{ number_format($row['balance'], $dp) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold"><tr><td class="ps-3">Total Liabilities</td><td class="text-end pe-3 text-danger">{{ number_format($data['liability']['total'], $dp) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Equity</div>
            <table class="table mb-0">
                <tbody>
                @foreach($data['equity']['rows'] as $row)
                    <tr><td class="ps-3">{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td><td class="text-end pe-3">{{ number_format($row['balance'], $dp) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold"><tr><td class="ps-3">Total Equity</td><td class="text-end pe-3 text-success">{{ number_format($data['equity']['total'], $dp) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="card mt-2">
            <div class="card-body text-center">
                <div class="fw-bold">Liabilities + Equity = {{ number_format($data['liability']['total'] + $data['equity']['total'], $dp) }}</div>
                @if(abs($data['asset']['total'] - ($data['liability']['total'] + $data['equity']['total'])) < 0.01)
                    <div class="text-success small"><i class="bi bi-check-circle me-1"></i>Balance sheet is balanced.</div>
                @else
                    <div class="text-danger small"><i class="bi bi-x-circle me-1"></i>Balance sheet is NOT balanced.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
