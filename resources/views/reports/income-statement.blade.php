@extends('layouts.app')
@section('title', 'Income Statement')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Income Statement</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Income Statement</h4>
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
            <div class="col-auto align-self-end"><button class="btn btn-primary">Run Report</button></div>
        </form>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Total Revenue</div><div class="fw-bold fs-5 text-success">{{ number_format($data['total_revenue'], $dp) }}</div></div></div>
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Total Expenses</div><div class="fw-bold fs-5 text-danger">{{ number_format($data['total_expense'], $dp) }}</div></div></div>
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Net Income</div><div class="fw-bold fs-5 {{ $data['net_income'] >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($data['net_income'], $dp) }}</div></div></div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Revenue</div>
            <table class="table mb-0">
                <tbody>
                @forelse($data['revenue_rows'] as $row)
                    <tr>
                        <td class="ps-3">{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td>
                        <td class="text-end pe-3 text-success fw-semibold">{{ number_format($row['balance'], $dp) }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-muted py-3">No revenue.</td></tr>
                @endforelse
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr><td class="ps-3">Total Revenue</td><td class="text-end pe-3 text-success">{{ number_format($data['total_revenue'], $dp) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Expenses</div>
            <table class="table mb-0">
                <tbody>
                @forelse($data['expense_groups'] as $group)
                    <tr class="table-light">
                        <td class="ps-3 small fw-semibold text-uppercase text-muted">{{ $group['label'] }}</td>
                        <td class="text-end pe-3 small fw-semibold text-muted">{{ number_format($group['subtotal'], $dp) }}</td>
                    </tr>
                    @foreach($group['rows'] as $row)
                    <tr>
                        <td class="ps-4">{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td>
                        <td class="text-end pe-3 text-danger fw-semibold">{{ number_format($row['balance'], $dp) }}</td>
                    </tr>
                    @endforeach
                @empty
                    <tr><td class="text-center text-muted py-3">No expenses.</td></tr>
                @endforelse
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr><td class="ps-3">Total Expenses</td><td class="text-end pe-3 text-danger">{{ number_format($data['total_expense'], $dp) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
