@extends('layouts.app')
@section('title', 'Reimbursement ' . $r->reference)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('staff-reimbursements.index') }}">Staff Reimbursements</a></li>
    <li class="breadcrumb-item active">{{ $r->reference }}</li>
@endsection
@section('content')
<div class="card" style="max-width:760px">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>Reimbursement <span class="font-monospace">{{ $r->reference }}</span></span>
        <span class="badge {{ $r->status === 'paid' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($r->status) }}</span>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4 fw-normal text-muted">Staff</dt>
            <dd class="col-sm-8 fw-semibold">{{ $r->employee->name }} <span class="badge bg-light text-secondary border font-monospace fw-normal">{{ $r->employee->employee_number }}</span></dd>
            <dt class="col-sm-4 fw-normal text-muted">Date</dt><dd class="col-sm-8">{{ $r->date->format('d M Y') }}</dd>
            <dt class="col-sm-4 fw-normal text-muted">For</dt><dd class="col-sm-8">{{ $r->description }}</dd>
            <dt class="col-sm-4 fw-normal text-muted">Expense Account</dt><dd class="col-sm-8">{{ $r->expenseAccount?->account_code }} — {{ $r->expenseAccount?->account_name }}</dd>

            <dt class="col-sm-4 fw-normal text-muted border-top pt-2 mt-2">Amount Claimed</dt><dd class="col-sm-8 border-top pt-2 mt-2">{{ number_format($r->amount, $dp) }}</dd>
            <dt class="col-sm-4 fw-normal text-muted">Deduction</dt>
            <dd class="col-sm-8 text-danger">
                {{ $r->deduction > 0 ? number_format($r->deduction, $dp) : '—' }}
                @if($r->deduction > 0)
                    <div class="text-muted small">{{ $r->deduction_reason }} · to {{ $r->deductionAccount?->account_code }} — {{ $r->deductionAccount?->account_name }}</div>
                @endif
            </dd>
            <dt class="col-sm-4 fw-semibold">Net Paid</dt><dd class="col-sm-8 fw-bold fs-5 text-success">{{ number_format($r->net_amount, $dp) }}</dd>
            <dt class="col-sm-4 fw-normal text-muted">Paid To</dt>
            <dd class="col-sm-8">
                @if($r->payment_method === 'savings')
                    Savings account <span class="font-monospace">{{ $r->savingsAccount?->account_number }}</span>
                @else
                    {{ $r->paymentAccount?->account_code }} — {{ $r->paymentAccount?->account_name }}
                @endif
            </dd>
            <dt class="col-sm-4 fw-normal text-muted border-top pt-2 mt-2">Journal Entry</dt>
            <dd class="col-sm-8 border-top pt-2 mt-2">
                @if($r->transaction)
                    <a href="{{ route('transactions.show', $r->transaction) }}" class="font-monospace">{{ $r->transaction->reference }}</a>
                @else — @endif
            </dd>
            <dt class="col-sm-4 fw-normal text-muted">Recorded By</dt><dd class="col-sm-8 small">{{ $r->createdBy?->name ?? '—' }} · {{ $r->created_at->format('d M Y H:i') }}</dd>
        </dl>
    </div>
    @if($r->status === 'paid' && $r->transaction)
    <div class="card-footer small text-muted">
        <i class="bi bi-info-circle me-1"></i>Made a mistake? Open the journal entry and <strong>Reverse</strong> it. That undoes the payment
        (including the savings credit) and marks this reimbursement Reversed. Then record it again correctly.
    </div>
    @endif
</div>
@endsection
