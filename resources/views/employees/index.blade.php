@extends('layouts.app')
@section('title', 'Employees')
@section('breadcrumb')
    <li class="breadcrumb-item active">Employees</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0">Employees</h6>
    <a href="{{ route('employees.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Add Employee
    </a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Emp #</th>
                <th>Name</th>
                <th>Position</th>
                <th>Department</th>
                <th class="text-end">Basic Salary</th>
                <th>Salary Payout</th>
                <th>Status</th>
                <th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($employees as $emp)
                <tr>
                    <td class="ps-3 font-monospace small">{{ $emp->employee_number }}</td>
                    <td class="fw-semibold">{{ $emp->name }}</td>
                    <td>{{ $emp->position ?? '—' }}</td>
                    <td>{{ $emp->department ?? '—' }}</td>
                    <td class="text-end">{{ number_format($emp->basic_salary, 0) }}</td>
                    <td class="small">
                        @if($emp->payment_method === 'savings' && $emp->savingsAccount)
                            <span class="font-monospace">{{ $emp->savingsAccount->account_number }}</span>
                        @elseif($emp->payment_method === 'savings')
                            <span class="text-danger small">Savings account not linked</span>
                        @elseif($emp->paymentSourceAccount)
                            <span>{{ $emp->paymentSourceAccount->account_name }}</span>
                        @else
                            <span class="text-danger small">Payout account not set</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $emp->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                            {{ ucfirst($emp->status) }}
                        </span>
                    </td>
                    <td class="pe-3">
                        <a href="{{ route('employees.show', $emp) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('employees.edit', $emp) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No employees found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $employees->links() }}</div>
</div>
@endsection
