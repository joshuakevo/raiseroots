@extends('layouts.client-portal')
@section('title', $loan->loan_number)
@section('content')

<div class="mb-3">
    <a href="{{ route('client-portal.accounts') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>My Statements
    </a>
</div>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0">{{ $loan->loan_number }}</h5>
        <span class="text-muted small">{{ $loan->product->name }}</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge badge-status-{{ $loan->status }} px-3 py-2 rounded-pill">
            {{ ucfirst($loan->status) }}
        </span>
        <a href="{{ route('client-portal.loan.pdf', $loan) }}" class="btn btn-sm btn-outline-danger" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Loan Amount</div>
            <div class="fw-bold fs-5">{{ number_format($loan->principal, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Outstanding Balance</div>
            <div class="fw-bold fs-5 text-primary">{{ number_format($loan->outstanding_principal, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Outstanding Interest</div>
            <div class="fw-bold fs-5 text-info">{{ number_format($loan->outstanding_interest, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Accrued Penalty</div>
            <div class="fw-bold fs-5 {{ $currentPenalty > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($currentPenalty, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Total Owing</div>
            @php $totalOwing = $loan->outstanding_principal + $loan->outstanding_interest + $currentPenalty + $loan->outstanding_admin_fee; @endphp
            <div class="fw-bold fs-5 text-danger">{{ number_format($totalOwing, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Total Paid</div>
            <div class="fw-bold fs-5 text-success">{{ number_format($repayments->sum('amount'), 2) }}</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Interest Rate</div>
            <div class="fw-semibold">{{ $loan->interest_rate }}% / {{ $loan->repayment_frequency ?? 'month' }}</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Disbursement Date</div>
            <div class="fw-semibold">{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</div>
        </div>
    </div>
</div>

@if($currentPenalty > 0)
<div class="alert alert-danger d-flex align-items-start gap-2 mb-4 py-2 small">
    <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
    <div>
        <strong>Penalty accrued: {{ number_format($currentPenalty, 2) }}</strong> — You have overdue installments attracting
        {{ $loan->product->penalty_rate }}%/day penalty. Please make a repayment as soon as possible to stop penalty from growing.
    </div>
</div>
@endif

{{-- Repayment History --}}
<div class="card mb-4">
    <div class="card-header bg-white py-3">Repayment History</div>
    <div class="card-body p-0">
        @if($repayments->isEmpty())
            <p class="text-muted p-3 mb-0 small">No repayments recorded yet.</p>
        @else
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th class="text-end">Amount Paid</th>
                    <th class="text-end">Principal</th>
                    <th class="text-end">Interest</th>
                    <th class="text-end">Penalty</th>
                    <th>Method</th>
                    <th>Reference</th>
                </tr>
            </thead>
            <tbody>
            @foreach($repayments as $rp)
                <tr>
                    <td>{{ $rp->payment_date->format('d M Y') }}</td>
                    <td class="text-end fw-semibold text-success">{{ number_format($rp->amount, 2) }}</td>
                    <td class="text-end">{{ number_format($rp->principal_paid, 2) }}</td>
                    <td class="text-end">{{ number_format($rp->interest_paid, 2) }}</td>
                    <td class="text-end">{{ number_format($rp->penalty_paid, 2) }}</td>
                    <td class="text-muted small">{{ ucfirst($rp->payment_method ?? '—') }}</td>
                    <td class="text-muted small">{{ $rp->reference ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td>Total Paid</td>
                    <td class="text-end text-success">{{ number_format($repayments->sum('amount'), 2) }}</td>
                    <td class="text-end">{{ number_format($repayments->sum('principal_paid'), 2) }}</td>
                    <td class="text-end">{{ number_format($repayments->sum('interest_paid'), 2) }}</td>
                    <td class="text-end">{{ number_format($repayments->sum('penalty_paid'), 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        </div>
        @endif
    </div>
</div>

{{-- Repayment Schedule --}}
@if($schedule->isNotEmpty())
<div class="card">
    <div class="card-header bg-white py-3">Repayment Schedule</div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Due Date</th>
                    <th class="text-end">Principal</th>
                    <th class="text-end">Interest</th>
                    <th class="text-end">Penalty</th>
                    <th class="text-end">Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($schedule as $row)
            @php $rowPenalty = $penaltyBreakdown[$row->id] ?? 0; @endphp
                <tr class="{{ $row->status === 'paid' ? 'table-success bg-opacity-10 text-muted' : ($rowPenalty > 0 ? 'table-danger bg-opacity-10' : '') }}">
                    <td>{{ $row->installment_no }}</td>
                    <td>{{ $row->due_date->format('d M Y') }}</td>
                    <td class="text-end">{{ number_format($row->principal_due, 2) }}</td>
                    <td class="text-end">{{ number_format($row->interest_due, 2) }}</td>
                    <td class="text-end {{ $rowPenalty > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                        {{ $rowPenalty > 0 ? number_format($rowPenalty, 2) : '—' }}
                    </td>
                    <td class="text-end">{{ number_format($row->balance_after, 2) }}</td>
                    <td>
                        @if($row->status === 'paid')
                            <span class="badge bg-success-subtle text-success small">Paid</span>
                        @elseif($row->status === 'partial')
                            <span class="badge bg-warning-subtle text-warning small">Partial</span>
                        @elseif($row->due_date->isPast())
                            <span class="badge bg-danger-subtle text-danger small">Overdue</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary small">Upcoming</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endif

@endsection
