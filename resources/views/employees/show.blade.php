@extends('layouts.app')
@section('title', 'Employee — ' . $employee->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Employees</a></li>
    <li class="breadcrumb-item active">{{ $employee->name }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0">{{ $employee->name }} <span class="text-muted font-monospace small">{{ $employee->employee_number }}</span></h6>
    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-pencil me-1"></i>Edit
    </a>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header small fw-semibold py-2">Employee Details</div>
            <div class="card-body py-2">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted w-40">Name</td><td class="fw-semibold">{{ $employee->name }}</td></tr>
                    <tr><td class="text-muted">Phone</td><td>{{ $employee->phone ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Email</td><td>{{ $employee->email ?? '—' }}</td></tr>
                    <tr><td class="text-muted">ID Number</td><td>{{ $employee->id_number ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Position</td><td>{{ $employee->position ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Department</td><td>{{ $employee->department ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Basic Salary</td><td class="fw-semibold">{{ number_format($employee->basic_salary, 0) }}</td></tr>
                    <tr><td class="text-muted">Status</td><td>
                        <span class="badge {{ $employee->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($employee->status) }}</span>
                    </td></tr>
                    <tr><td class="text-muted">Salary Payout</td><td>
                        @if($employee->payment_method === 'savings')
                            <span class="font-monospace">{{ $employee->savingsAccount?->account_number ?? 'Not linked' }}</span>
                            <span class="text-muted">({{ $employee->client?->name }})</span>
                        @else
                            {{ $employee->paymentSourceAccount?->account_name ?? 'Not set' }}
                        @endif
                    </td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-header small fw-semibold py-2">Payroll History</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 small">
            <thead class="table-light"><tr>
                <th>Run #</th><th>Period</th><th class="text-end">Basic</th><th class="text-end">Allowances</th><th class="text-end">Deductions</th><th class="text-end">Net</th><th>Status</th>
            </tr></thead>
            <tbody>
            @forelse($employee->payrollItems as $item)
            <tr>
                <td class="font-monospace"><a href="{{ route('payroll.show', $item->payroll_run_id) }}">{{ $item->payrollRun->run_number }}</a></td>
                <td>{{ $item->payrollRun->period_label }}</td>
                <td class="text-end">{{ number_format($item->basic_salary, 0) }}</td>
                <td class="text-end text-success">{{ number_format($item->allowances, 0) }}</td>
                <td class="text-end text-danger">{{ number_format($item->deductions, 0) }}</td>
                <td class="text-end fw-semibold">{{ number_format($item->net_salary, 0) }}</td>
                <td><span class="badge {{ $item->payrollRun->status === 'processed' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($item->payrollRun->status) }}</span></td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-3">No payroll history.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
