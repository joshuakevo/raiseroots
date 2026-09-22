@extends('layouts.app')
@section('title', 'Loan Cycle Run')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item active">Loan Cycle Run</li>
@endsection
@section('content')

<h4 class="fw-bold mb-4">Loan Cycle Run</h4>

{{-- Stat cards --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="text-muted small text-uppercase" style="letter-spacing:.04em;font-size:.68rem">Principal Balance</div>
            <div class="fw-bold fs-4 text-warning">{{ number_format($totals['principal'], 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="text-muted small text-uppercase" style="letter-spacing:.04em;font-size:.68rem">Interest Balance</div>
            <div class="fw-bold fs-4 text-warning">{{ number_format($totals['interest'], 0) }}</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <div class="text-muted small text-uppercase" style="letter-spacing:.04em;font-size:.68rem">Loans Due — {{ \Carbon\Carbon::parse($anniversaryDate)->format('jS') }}</div>
            <div class="fw-bold fs-4">{{ $totals['count'] }}</div>
        </div>
    </div>
</div>

{{-- Filter bar --}}
<div class="card mb-2">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Anniversary date</label>
                <div class="d-flex align-items-center gap-1">
                    <a class="btn btn-outline-secondary btn-sm"
                       href="{{ request()->fullUrlWithQuery(['date' => \Carbon\Carbon::parse($anniversaryDate)->subDay()->toDateString()]) }}">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <input type="date" name="date" class="form-control form-control-sm" style="width:160px" value="{{ $anniversaryDate }}">
                    <a class="btn btn-outline-secondary btn-sm"
                       href="{{ request()->fullUrlWithQuery(['date' => \Carbon\Carbon::parse($anniversaryDate)->addDay()->toDateString()]) }}">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-auto flex-grow-1" style="min-width:220px">
                <label class="form-label small fw-semibold mb-1">Loan # or client name</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Loan # or client name…" value="{{ request('search') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">View</button>
                <a href="{{ route('loans.cycle-run') }}" class="btn btn-outline-secondary btn-sm">Today</a>
            </div>
        </form>
        <div class="text-muted small mt-2">
            Active loans whose disbursement day-of-month is the {{ \Carbon\Carbon::parse($anniversaryDate)->format('jS') }}
            — every installment for these loans falls due on that day each month.
        </div>
    </div>
</div>

{{-- Main table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0 small">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Loan #</th>
                    <th>Client</th>
                    <th class="text-end">Outstanding Principal</th>
                    <th class="text-end">Outstanding Interest</th>
                    <th class="text-end">Amount Due</th>
                    <th>Maturity Date</th>
                    <th class="pe-3 text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
            <tr>
                <td class="ps-3">
                    <a href="{{ route('loans.show', $row->loan) }}" class="text-decoration-none fw-semibold">{{ $row->loan->loan_number }}</a>
                </td>
                <td>
                    <a href="{{ route('clients.show', $row->loan->client) }}" class="text-decoration-none text-dark">{{ $row->loan->client->name }}</a>
                </td>
                <td class="text-end">{{ number_format($row->loan->outstanding_principal, $dp) }}</td>
                <td class="text-end">{{ number_format($row->loan->outstanding_interest, $dp) }}</td>
                <td class="text-end fw-semibold {{ $row->amount_due > 0 ? 'text-danger' : '' }}">{{ number_format($row->amount_due, $dp) }}</td>
                @php
                    $maturity = $row->loan->maturity_date;
                    $daysOverdue = $maturity && $maturity->isPast() ? $maturity->diffInDays(now()->startOfDay()) : 0;
                @endphp
                <td class="{{ $daysOverdue > 0 ? 'text-danger fw-semibold' : '' }}">
                    {{ $maturity ? $maturity->format('d M Y') : '—' }}
                    @if($daysOverdue > 0)
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1" title="Past maturity">{{ $daysOverdue }}d overdue</span>
                    @endif
                </td>
                <td class="pe-3 text-end">
                    <a href="{{ route('loans.show', $row->loan) }}" class="btn btn-sm btn-outline-primary" title="View loan">
                        <i class="bi bi-eye"></i>
                    </a>
                    @can('repay loans')
                    <a href="{{ route('loans.repay-form', $row->loan) }}" class="btn btn-sm btn-success" title="Record repayment">
                        <i class="bi bi-cash-coin"></i>
                    </a>
                    @endcan
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No active loans have their anniversary on this day.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
