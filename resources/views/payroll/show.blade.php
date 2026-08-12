@extends('layouts.app')
@section('title', 'Payroll Run — ' . $payroll->run_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('payroll.index') }}">Payroll</a></li>
    <li class="breadcrumb-item active">{{ $payroll->run_number }}</li>
@endsection
@section('content')
@if($errors->has('payment_date'))
<div class="alert alert-danger small mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>{{ $errors->first('payment_date') }}
</div>
@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h6 class="fw-semibold mb-0">{{ $payroll->run_number }} — {{ $payroll->period_label }}</h6>
        @if($payroll->description)
            <small class="text-muted">{{ $payroll->description }}</small>
        @endif
    </div>
    @if($payroll->status === 'draft')
    <div class="d-flex gap-2">
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#processModal">
            <i class="bi bi-check-circle me-1"></i>Process Payroll
        </button>
        <form method="POST" action="{{ route('payroll.destroy', $payroll) }}"
              onsubmit="return confirm('Delete this draft run? This cannot be undone.')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete Draft</button>
        </form>
    </div>
    @else
    <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Processed</span>
    @endif
</div>

<div class="row g-3 mb-3">
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="small text-muted">Employees</div>
                <div class="fw-bold fs-6">{{ $payroll->items->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-success">
            <div class="card-body py-2">
                <div class="small text-muted">Total Net Salary</div>
                <div class="fw-bold fs-6 text-success">{{ number_format($payroll->total_gross, 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="small text-muted">Status</div>
                <div class="fw-bold fs-6">
                    @if($payroll->status === 'processed')
                        <span class="text-success">Processed</span>
                    @else
                        <span class="text-warning">Draft</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @if($payroll->processed_at)
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="small text-muted">Processed On</div>
                <div class="fw-bold fs-6">{{ $payroll->processed_at->format('d M Y') }}</div>
            </div>
        </div>
    </div>
    @endif
</div>

<div class="card">
    <div class="card-header small fw-semibold py-2">Payroll Items</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Salary Payout</th>
                    <th class="text-end">Basic</th>
                    <th class="text-end">Allowances</th>
                    <th class="text-end">Deductions</th>
                    <th class="text-end fw-semibold">Net Salary</th>
                </tr>
            </thead>
            <tbody class="small">
            @foreach($payroll->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    <div class="fw-semibold">{{ $item->employee->name }}</div>
                    <div class="text-muted" style="font-size:.72rem">{{ $item->employee->position }} · {{ $item->employee->employee_number }}</div>
                </td>
                <td class="font-monospace">
                    @if($item->employee?->payment_method === 'cash')
                        <span class="font-sans">{{ $item->employee->paymentSourceAccount?->account_name ?? 'Not set' }}</span>
                    @else
                        {!! $item->savingsAccount?->account_number ?? '<span class="text-danger">Not linked</span>' !!}
                    @endif
                </td>
                <td class="text-end">{{ number_format($item->basic_salary, 0) }}</td>
                <td class="text-end text-success">{{ number_format($item->allowances, 0) }}</td>
                <td class="text-end text-danger">{{ number_format($item->deductions, 0) }}</td>
                <td class="text-end fw-semibold">{{ number_format($item->net_salary, 0) }}</td>
            </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold small">
                <tr>
                    <td colspan="3">Totals</td>
                    <td class="text-end">{{ number_format($payroll->items->sum('basic_salary'), 0) }}</td>
                    <td class="text-end text-success">{{ number_format($payroll->items->sum('allowances'), 0) }}</td>
                    <td class="text-end text-danger">{{ number_format($payroll->items->sum('deductions'), 0) }}</td>
                    <td class="text-end">{{ number_format($payroll->items->sum('net_salary'), 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@if($payroll->status === 'draft')
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-check-circle me-1"></i>Process Payroll</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('payroll.process', $payroll) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        This will credit <strong>{{ number_format($payroll->total_gross, 0) }}</strong> total to employee savings accounts and post a journal entry.
                    </div>
                    @error('payment_date')
                        <div class="alert alert-danger small py-2 mb-3">{{ $message }}</div>
                    @enderror
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror"
                               value="{{ old('payment_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Confirm &amp; Process</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
