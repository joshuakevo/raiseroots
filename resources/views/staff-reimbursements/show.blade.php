@extends('layouts.app')
@section('title', 'Reimbursement Run — ' . $run->run_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('staff-reimbursements.index') }}">Staff Reimbursements</a></li>
    <li class="breadcrumb-item active">{{ $run->run_number }}</li>
@endsection
@section('content')
@if($errors->has('payment_date'))
<div class="alert alert-danger small mb-3"><i class="bi bi-exclamation-triangle me-1"></i>{{ $errors->first('payment_date') }}</div>
@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h6 class="fw-semibold mb-0">{{ $run->run_number }} — {{ $run->period_label }}</h6>
        <small class="text-muted">
            {{ $run->description ? $run->description . ' · ' : '' }}Expense: {{ $run->expenseAccount?->account_code }} — {{ $run->expenseAccount?->account_name }}
            @if($run->total_deduction > 0)
                · Deductions to: {{ $run->deductionAccount ? $run->deductionAccount->account_code . ' — ' . $run->deductionAccount->account_name : 'the expense account' }}
            @endif
        </small>
    </div>
    @if($run->status === 'draft')
    <div class="d-flex gap-2">
        @can('process payroll')
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#processModal"><i class="bi bi-check-circle me-1"></i>Process Reimbursements</button>
        @endcan
        @can('create payroll')
        <a href="{{ route('staff-reimbursements.edit', $run) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endcan
        @can('delete payroll')
        <form method="POST" action="{{ route('staff-reimbursements.destroy', $run) }}" onsubmit="return confirm('Delete this draft run? This cannot be undone.')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete Draft</button>
        </form>
        @endcan
    </div>
    @else
    <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Processed</span>
    @endif
</div>

<div class="row g-3 mb-3">
    <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="small text-muted">Staff</div><div class="fw-bold fs-6">{{ $run->items->count() }}</div></div></div></div>
    <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="small text-muted">Amount</div><div class="fw-bold fs-6">{{ number_format($run->total_amount, 0) }}</div></div></div></div>
    <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="small text-muted">Deductions</div><div class="fw-bold fs-6 text-danger">{{ number_format($run->total_deduction, 0) }}</div></div></div></div>
    <div class="col"><div class="card text-center border-success"><div class="card-body py-2"><div class="small text-muted">Net to Pay</div><div class="fw-bold fs-6 text-success">{{ number_format($run->total_net, 0) }}</div></div></div></div>
    @if($run->status === 'processed')
    <div class="col"><div class="card text-center"><div class="card-body py-2"><div class="small text-muted">Paid On</div><div class="fw-bold fs-6">{{ $run->payment_date?->format('d M Y') }}</div>
        @if($run->transaction)<a href="{{ route('transactions.show', $run->transaction) }}" class="small font-monospace">{{ $run->transaction->reference }}</a>@endif
    </div></div></div>
    @endif
</div>

<div class="card">
    <div class="card-header small fw-semibold py-2">Staff Reimbursements</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Staff</th>
                    <th>Paid To</th>
                    <th>What For</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Deduction</th>
                    <th>Deduction Reason</th>
                    <th class="text-end fw-semibold">Net Paid</th>
                </tr>
            </thead>
            <tbody class="small">
            @foreach($run->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    <div class="fw-semibold">{{ $item->employee->name }}</div>
                    <div class="text-muted" style="font-size:.72rem">{{ $item->employee->position }} · {{ $item->employee->employee_number }}</div>
                </td>
                <td>
                    @if($item->employee?->payment_method === 'cash')
                        {{ $item->employee->paymentSourceAccount?->account_name ?? 'Not set' }}
                    @else
                        {!! $item->savingsAccount ? '<span class="font-monospace">' . e($item->savingsAccount->account_number) . '</span>' : '<span class="text-danger">Not linked</span>' !!}
                    @endif
                </td>
                <td>{{ $item->description ?? '—' }}</td>
                <td class="text-end">{{ number_format($item->amount, 0) }}</td>
                <td class="text-end text-danger">{{ $item->deduction > 0 ? number_format($item->deduction, 0) : '—' }}</td>
                <td class="text-muted">{{ $item->deduction_reason ?? '' }}</td>
                <td class="text-end fw-semibold">{{ number_format($item->net_amount, 0) }}</td>
            </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold small">
                <tr>
                    <td colspan="4">Totals</td>
                    <td class="text-end">{{ number_format($run->total_amount, 0) }}</td>
                    <td class="text-end text-danger">{{ number_format($run->total_deduction, 0) }}</td>
                    <td></td>
                    <td class="text-end">{{ number_format($run->total_net, 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @if($run->status === 'processed')
    <div class="card-footer small text-muted">
        <i class="bi bi-info-circle me-1"></i>Made a mistake? Open the journal entry and <strong>Reverse</strong> it. That undoes the payments
        (including savings credits) and puts this run back to Draft so you can edit and process it again.
    </div>
    @endif
</div>

@if($run->status === 'draft')
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-check-circle me-1"></i>Process Reimbursements</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('staff-reimbursements.process', $run) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        This pays <strong>{{ number_format($run->total_net, 0) }}</strong> to {{ $run->items->count() }} staff (each to their usual salary payout) and posts a journal entry.
                    </div>
                    <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Confirm &amp; Pay</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
