@extends('layouts.app')
@section('title', 'Staff Reimbursements')
@section('breadcrumb')
    <li class="breadcrumb-item active">Staff Reimbursements</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0">Staff Reimbursements</h6>
    @can('process payroll')
    <a href="{{ route('staff-reimbursements.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Reimbursement</a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Staff</label>
                <select name="employee_id" class="form-select ts-select">
                    <option value="">All staff</option>
                    @foreach($employees as $e)
                        <option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->name }} ({{ $e->employee_number }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="paid" @selected(request('status') === 'paid')>Paid</option>
                    <option value="reversed" @selected(request('status') === 'reversed')>Reversed</option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small fw-semibold">From</label><input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold">To</label><input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}"></div>
            <div class="col-auto">
                <button class="btn btn-primary">Filter</button>
                <a href="{{ route('staff-reimbursements.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="text-muted small">Reimbursements</div><div class="fw-bold fs-5">{{ number_format($totals->count) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="text-muted small">Amount Claimed</div><div class="fw-bold fs-5">{{ number_format($totals->amount, $dp) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="text-muted small">Deductions</div><div class="fw-bold fs-5 text-danger">{{ number_format($totals->deduction, $dp) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card text-center"><div class="text-muted small">Net Paid</div><div class="fw-bold fs-5 text-success">{{ number_format($totals->net, $dp) }}</div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Reference</th><th>Date</th><th>Staff</th><th>Description</th>
                <th class="text-end">Amount</th><th class="text-end">Deduction</th><th class="text-end">Net Paid</th>
                <th>Paid To</th><th class="pe-3">Status</th>
            </tr></thead>
            <tbody>
            @forelse($reimbursements as $r)
                <tr>
                    <td class="ps-3"><a href="{{ route('staff-reimbursements.show', $r) }}" class="font-monospace text-decoration-none">{{ $r->reference }}</a></td>
                    <td class="small text-nowrap">{{ $r->date->format('d M Y') }}</td>
                    <td>
                        <div class="fw-semibold">{{ $r->employee->name }}</div>
                        <div class="text-muted" style="font-size:.72rem">{{ $r->employee->employee_number }}</div>
                    </td>
                    <td class="small">{{ $r->description }}<div class="text-muted" style="font-size:.72rem">{{ $r->expenseAccount?->account_name }}</div></td>
                    <td class="text-end text-nowrap">{{ number_format($r->amount, $dp) }}</td>
                    <td class="text-end text-nowrap text-danger" @if($r->deduction_reason) title="{{ $r->deduction_reason }}" @endif>{{ $r->deduction > 0 ? number_format($r->deduction, $dp) : '—' }}</td>
                    <td class="text-end text-nowrap fw-semibold">{{ number_format($r->net_amount, $dp) }}</td>
                    <td class="small">{{ $r->payment_method === 'savings' ? 'Savings' : 'Cash / Bank' }}</td>
                    <td class="pe-3">
                        <span class="badge {{ $r->status === 'paid' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($r->status) }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No staff reimbursements yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($reimbursements->hasPages())
        <div class="card-footer">{{ $reimbursements->links() }}</div>
    @endif
</div>
@endsection
