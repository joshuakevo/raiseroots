@extends('layouts.app')
@section('title', 'Loan Statement — ' . $loan->loan_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item"><a href="{{ route('loans.show', $loan) }}">{{ $loan->loan_number }}</a></li>
    <li class="breadcrumb-item active">Statement</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0">Loan Statement — <span class="font-monospace">{{ $loan->loan_number }}</span></h6>
    <a href="{{ route('loans.statement-pdf', $loan) }}" class="btn btn-danger btn-sm" target="_blank">
        <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header small fw-semibold py-2">Client Information</div>
            <div class="card-body py-2">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted w-40">Full Name</td><td class="fw-semibold">{{ $loan->client->name }}</td></tr>
                    <tr><td class="text-muted">Client No.</td><td class="fw-semibold">{{ $loan->client->client_number }}</td></tr>
                    @if($loan->client->phone)
                    <tr><td class="text-muted">Phone</td><td>{{ $loan->client->phone }}</td></tr>
                    @endif
                    @if($loan->client->email)
                    <tr><td class="text-muted">Email</td><td>{{ $loan->client->email }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header small fw-semibold py-2">Loan Information</div>
            <div class="card-body py-2">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted w-40">Loan Number</td><td class="fw-semibold font-monospace">{{ $loan->loan_number }}</td></tr>
                    <tr><td class="text-muted">Product</td><td>{{ $loan->product->name }}</td></tr>
                    <tr><td class="text-muted">Principal</td><td>{{ number_format($loan->principal, $dp) }}</td></tr>
                    <tr><td class="text-muted">Rate / Method</td><td>{{ $loan->interest_rate }}% — {{ ucfirst($loan->interest_method) }}</td></tr>
                    <tr><td class="text-muted">Term</td><td>{{ $loan->term_months }} months</td></tr>
                    <tr><td class="text-muted">Disbursed</td><td>{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Maturity</td><td>{{ $loan->maturity_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Status</td><td><span class="badge bg-{{ $loan->status === 'active' ? 'success' : ($loan->status === 'closed' ? 'secondary' : 'warning') }}">{{ strtoupper($loan->status) }}</span></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Summary Stats --}}
<div class="row g-3 mb-3">
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="small text-muted">Outstanding Principal</div>
                <div class="fw-bold fs-6">{{ number_format($loan->outstanding_principal, $dp) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="small text-muted">Outstanding Interest</div>
                <div class="fw-bold fs-6">{{ number_format($loan->outstanding_interest, $dp) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center {{ $currentPenalty > 0 ? 'border-danger' : '' }}">
            <div class="card-body py-2">
                <div class="small text-muted">Accrued Penalty</div>
                <div class="fw-bold fs-6 text-danger">{{ number_format($currentPenalty, $dp) }}</div>
                @if($currentPenalty > 0)<div style="font-size:.68rem" class="text-muted">{{ $loan->product->penalty_rate }}%/day on overdue</div>@endif
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-danger">
            <div class="card-body py-2">
                <div class="small text-muted">Total Outstanding</div>
                @php $liveTotal = $loan->outstanding_principal + $loan->outstanding_interest + $currentPenalty + $loan->outstanding_admin_fee; @endphp
                <div class="fw-bold fs-6 text-danger">{{ number_format($liveTotal, $dp) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-success">
            <div class="card-body py-2">
                <div class="small text-muted">Total Paid</div>
                <div class="fw-bold fs-6 text-success">{{ number_format($loan->total_paid, $dp) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Repayment History --}}
<div class="card">
    <div class="card-header small fw-semibold py-2">Repayment History</div>
    <div class="card-body p-0">
        @if($loan->repayments->isEmpty())
            <p class="text-muted small p-3 mb-0">No repayments recorded.</p>
        @else
        @php $runningBalance = $loan->principal; @endphp
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th class="text-end">Principal</th>
                    <th class="text-end">Interest</th>
                    <th class="text-end">Penalty</th>
                    <th class="text-end">Total Paid</th>
                    <th class="text-end fw-semibold">Balance</th>
                </tr>
            </thead>
            <tbody class="small">
            @foreach($loan->repayments as $i => $r)
            @php $runningBalance -= $r->principal_paid; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->payment_date->format('d M Y') }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $r->payment_method)) }}</td>
                    <td class="text-muted">{{ $r->reference ?? '—' }}</td>
                    <td class="text-end">{{ number_format($r->principal_paid, $dp) }}</td>
                    <td class="text-end">{{ number_format($r->interest_paid, $dp) }}</td>
                    <td class="text-end">{{ number_format($r->penalty_paid, $dp) }}</td>
                    <td class="text-end">{{ number_format($r->amount, $dp) }}</td>
                    <td class="text-end fw-semibold {{ $runningBalance <= 0 ? 'text-success' : '' }}">
                        {{ number_format(max($runningBalance, 0), $dp) }}
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold small">
                <tr>
                    <td colspan="4">Totals</td>
                    <td class="text-end">{{ number_format($loan->repayments->sum('principal_paid'), $dp) }}</td>
                    <td class="text-end">{{ number_format($loan->repayments->sum('interest_paid'), $dp) }}</td>
                    <td class="text-end">{{ number_format($loan->repayments->sum('penalty_paid'), $dp) }}</td>
                    <td class="text-end">{{ number_format($loan->repayments->sum('amount'), $dp) }}</td>
                    <td class="text-end text-{{ $loan->outstanding_principal <= 0 ? 'success' : 'danger' }}">
                        {{ number_format($loan->outstanding_principal, $dp) }}
                    </td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>
</div>
{{-- Repayment Schedule --}}
@if($loan->schedules->isNotEmpty())
<div class="card mt-3">
    <div class="card-header small fw-semibold py-2 d-flex justify-content-between align-items-center">
        <span>Repayment Schedule</span>
        @if($currentPenalty > 0)
        <span class="text-danger small"><i class="bi bi-exclamation-triangle-fill me-1"></i>Accrued Penalty: {{ number_format($currentPenalty, $dp) }}</span>
        @endif
    </div>
    <div class="card-body p-0">
        @php $schTotalPenalty = 0; @endphp
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th><th>Due Date</th>
                    <th class="text-end">Principal</th><th class="text-end">Interest</th>
                    <th class="text-end">Penalty</th><th class="text-end">Total Due</th>
                    <th class="text-end">Paid</th><th>Status</th>
                </tr>
            </thead>
            <tbody class="small">
            @foreach($loan->schedules as $s)
            @php $rowPenalty = $penaltyBreakdown[$s->id] ?? 0; $schTotalPenalty += $rowPenalty; @endphp
            <tr class="{{ $s->isOverdue() ? 'table-danger' : ($s->status === 'paid' ? 'text-muted' : '') }}">
                <td>{{ $s->installment_no }}</td>
                <td>{{ $s->due_date->format('d M Y') }}</td>
                <td class="text-end">{{ number_format($s->principal_due, $dp) }}</td>
                <td class="text-end">{{ number_format($s->interest_due, $dp) }}</td>
                <td class="text-end {{ $rowPenalty > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                    {{ $rowPenalty > 0 ? number_format($rowPenalty, $dp) : '—' }}
                </td>
                <td class="text-end fw-semibold">{{ number_format($s->total_due + $rowPenalty, $dp) }}</td>
                <td class="text-end text-success">{{ number_format($s->principal_paid + $s->interest_paid, $dp) }}</td>
                <td><span class="badge badge-status-{{ $s->status }}">{{ ucfirst($s->status) }}</span></td>
            </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold small">
                <tr>
                    <td colspan="2">Totals</td>
                    <td class="text-end">{{ number_format($loan->schedules->sum('principal_due'), $dp) }}</td>
                    <td class="text-end">{{ number_format($loan->schedules->sum('interest_due'), $dp) }}</td>
                    <td class="text-end text-danger">{{ $schTotalPenalty > 0 ? number_format($schTotalPenalty, $dp) : '—' }}</td>
                    <td class="text-end">{{ number_format($loan->schedules->sum('total_due') + $schTotalPenalty, $dp) }}</td>
                    <td class="text-end">{{ number_format($loan->schedules->sum('principal_paid') + $loan->schedules->sum('interest_paid'), $dp) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

<p class="text-muted small mt-2 mb-0">Statement generated: {{ now()->format('d M Y H:i') }}</p>
@endsection
