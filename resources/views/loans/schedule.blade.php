@extends('layouts.app')
@section('title', 'Repayment Schedule')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item"><a href="{{ route('loans.show', $loan) }}">{{ $loan->loan_number }}</a></li>
    <li class="breadcrumb-item active">Schedule</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Repayment Schedule</h4>
        <span class="text-muted">{{ $loan->loan_number }} — {{ $loan->client->name }}</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('loans.schedule-pdf', $loan) }}" class="btn btn-outline-danger btn-sm" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
        <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary btn-sm">Back to Loan</a>
    </div>
</div>

@php $isPreview = in_array($loan->status, ['pending', 'approved']); @endphp

@if($isPreview)
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span>This is a <strong>projected schedule</strong> based on disbursement today ({{ today()->format('d M Y') }}). Actual dates will be set when you disburse the loan.</span>
</div>
@endif

@php
    $rows = $isPreview ? $schedulePreview : $loan->schedules->toArray();
    $totalInterestSum = $isPreview
        ? array_sum(array_column($schedulePreview, 'interest_due'))
        : $loan->schedules->sum('interest_due');
@endphp

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3 text-center"><div class="text-muted small">Principal</div><div class="fw-bold">{{ number_format($loan->principal, $dp) }}</div></div>
            <div class="col-md-3 text-center"><div class="text-muted small">Rate / Method</div><div class="fw-bold">{{ $loan->interest_rate }}% {{ ucfirst($loan->interest_method) }}</div></div>
            <div class="col-md-3 text-center"><div class="text-muted small">Term</div><div class="fw-bold">{{ $loan->term_months }} months</div></div>
            <div class="col-md-3 text-center"><div class="text-muted small">Total Interest</div><div class="fw-bold">{{ number_format($totalInterestSum, $dp) }}</div></div>
            @if($currentPenalty > 0)
            <div class="col-12">
                <div class="alert alert-danger py-2 mb-0 small d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><strong>Total Accrued Penalty: {{ number_format($currentPenalty, $dp) }}</strong> — Overdue installments are attracting {{ $loan->product->penalty_rate }}%/day penalty.</span>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">#</th>
                <th>Due Date</th>
                <th class="text-end">Principal</th>
                <th class="text-end">Interest</th>
                @if(!$isPreview)<th class="text-end">Penalty</th>@endif
                <th class="text-end">Total Due</th>
                @if(!$isPreview)
                <th class="text-end">Paid</th>
                @endif
                <th class="text-end pe-3">Balance After</th>
                @if(!$isPreview)<th>Status</th>@endif
            </tr></thead>
            <tbody>
            @php $totalPrincipal = 0; $totalInterest = 0; $totalDue = 0; $totalPenalty = 0; @endphp
            @if($isPreview)
                @forelse($schedulePreview as $s)
                @php $totalPrincipal += $s['principal_due']; $totalInterest += $s['interest_due']; $totalDue += $s['total_due']; @endphp
                <tr>
                    <td class="ps-3">{{ $s['installment_no'] }}</td>
                    <td>{{ $s['due_date'] }}</td>
                    <td class="text-end">{{ number_format($s['principal_due'], $dp) }}</td>
                    <td class="text-end">{{ number_format($s['interest_due'], $dp) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($s['total_due'], $dp) }}</td>
                    <td class="text-end pe-3">{{ number_format($s['balance_after'], $dp) }}</td>
                </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No preview available.</td></tr>
                @endforelse
            @else
                @forelse($loan->schedules as $s)
                @php
                    $rowPenalty = $penaltyBreakdown[$s->id] ?? 0;
                    $totalPrincipal += $s->principal_due;
                    $totalInterest  += $s->interest_due;
                    $totalDue       += $s->total_due;
                    $totalPenalty   += $rowPenalty;
                @endphp
                <tr class="{{ $s->isOverdue() ? 'table-danger' : ($s->status==='paid' ? 'table-success bg-opacity-25' : '') }}">
                    <td class="ps-3">{{ $s->installment_no }}</td>
                    <td>{{ $s->due_date->format('d M Y') }}</td>
                    <td class="text-end">{{ number_format($s->principal_due, $dp) }}</td>
                    <td class="text-end">{{ number_format($s->interest_due, $dp) }}</td>
                    <td class="text-end {{ $rowPenalty > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                        {{ $rowPenalty > 0 ? number_format($rowPenalty, $dp) : '—' }}
                    </td>
                    <td class="text-end fw-semibold">{{ number_format($s->total_due + $rowPenalty, $dp) }}</td>
                    <td class="text-end text-success">{{ number_format($s->principal_paid + $s->interest_paid, $dp) }}</td>
                    <td class="text-end pe-3">{{ number_format($s->balance_after, $dp) }}</td>
                    <td><span class="badge badge-status-{{ $s->status }}">{{ ucfirst($s->status) }}</span></td>
                </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No schedule generated.</td></tr>
                @endforelse
            @endif
            </tbody>
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td colspan="2" class="ps-3">Totals</td>
                    <td class="text-end">{{ number_format($totalPrincipal, $dp) }}</td>
                    <td class="text-end">{{ number_format($totalInterest, $dp) }}</td>
                    @if(!$isPreview)
                    <td class="text-end text-danger">{{ $totalPenalty > 0 ? number_format($totalPenalty, $dp) : '—' }}</td>
                    @endif
                    <td class="text-end">{{ number_format($totalDue + $totalPenalty, $dp) }}</td>
                    <td colspan="{{ $isPreview ? 2 : 3 }}"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
