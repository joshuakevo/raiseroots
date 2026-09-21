@extends('layouts.app')
@section('title', 'Staff Analysis')
@section('breadcrumb')
    <li class="breadcrumb-item">HR &amp; Payroll</li>
    <li class="breadcrumb-item active">Staff Analysis</li>
@endsection
@section('content')
@php
    $fmt = fn ($v) => number_format($v, 0);
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1) . '%';
    $parClass = fn ($v) => $v === null ? 'text-muted' : ($v >= $thresholds['risk_par'] ? 'text-danger' : ($v >= $thresholds['strong_par'] ? 'text-warning-emphasis' : 'text-success'));
    $effClass = fn ($v) => $v === null ? 'text-muted' : ($v < $thresholds['risk_eff'] ? 'text-danger' : ($v < $thresholds['strong_eff'] ? 'text-warning-emphasis' : 'text-success'));
    $officers = $officers->values();
    $t = $totals;
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Staff Analysis</h4>
        <div class="text-muted small">How each Loan Officer's clients are performing. Loans are credited to the client's Loan Officer.</div>
    </div>
    <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-0">Period from</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-0">to</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div>
            <div class="col-auto"><a href="{{ route('staff-analysis.index') }}" class="btn btn-sm btn-outline-secondary">This year</a></div>
            <div class="col text-muted small text-md-end">The period drives “Disbursed” and “Collected”. Everything else is the position as of today.</div>
        </form>
    </div>
</div>

{{-- Team totals --}}
<div class="row g-3 mb-3">
    @foreach([
        ['Loan Officers', $officers->where('id', '!=', null)->count(), 'bi-person-badge', 'primary', $fmt($t['clients']) . ' clients'],
        ['Loans Disbursed', $fmt($t['issued_count']), 'bi-cash-stack', 'primary', $fmt($t['issued_amount'])],
        ['Outstanding', $fmt($t['outstanding_principal']), 'bi-wallet2', 'info', $fmt($t['active_count']) . ' active loans'],
        ['PAR 30', $pct($t['par30_pct']), 'bi-exclamation-triangle', $t['par30_pct'] === null ? 'secondary' : ($t['par30_pct'] >= $thresholds['risk_par'] ? 'danger' : ($t['par30_pct'] >= $thresholds['strong_par'] ? 'warning' : 'success')), $fmt($t['par30_amount']) . ' at risk'],
        ['Collection Efficiency', $pct($t['collection_efficiency']), 'bi-bullseye', $t['collection_efficiency'] === null ? 'secondary' : ($t['collection_efficiency'] < $thresholds['risk_eff'] ? 'danger' : ($t['collection_efficiency'] < $thresholds['strong_eff'] ? 'warning' : 'success')), $fmt($t['paid_to_date']) . ' of ' . $fmt($t['due_to_date']) . ' due'],
        ['Defaulted Loans', $fmt($t['defaulted_count']), 'bi-x-octagon', $t['defaulted_count'] > 0 ? 'danger' : 'success', $fmt($t['defaulted_amount']) . ' principal'],
    ] as [$label, $value, $icon, $color, $sub])
    <div class="col-6 col-lg-2">
        <div class="card h-100">
            <div class="card-body py-3">
                <div class="text-muted small"><i class="bi {{ $icon }} text-{{ $color }} me-1"></i>{{ $label }}</div>
                <div class="fs-5 fw-bold">{{ $value }}</div>
                <div class="text-muted" style="font-size:.7rem">{{ $sub }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Standouts --}}
@php
    $cards = [
        ['Largest portfolio', 'bi-trophy', 'primary', $insights['largest_portfolio'], fn ($r) => $fmt($r['outstanding_principal']) . ' outstanding · ' . $r['portfolio_share'] . '% of book'],
        ['Healthiest book', 'bi-shield-check', 'success', $insights['lowest_par'], fn ($r) => 'PAR30 ' . $pct($r['par30_pct']) . ' on ' . $fmt($r['outstanding_principal'])],
        ['Best collections', 'bi-bullseye', 'success', $insights['best_collection'], fn ($r) => $pct($r['collection_efficiency']) . ' of instalments due collected'],
        ['Top disburser (period)', 'bi-rocket-takeoff', 'info', $insights['top_disburser'], fn ($r) => $fmt($r['disbursed_period_amount']) . ' across ' . $r['disbursed_period_count'] . ' loans'],
        ['Needs attention', 'bi-exclamation-octagon', 'danger', $insights['needs_attention'], fn ($r) => 'PAR30 ' . $pct($r['par30_pct']) . ' · ' . $fmt($r['par30_amount']) . ' at risk'],
    ];
@endphp
<div class="row g-3 mb-3">
    @foreach($cards as [$label, $icon, $color, $row, $detail])
    <div class="col-md-6 col-xl">
        <div class="card h-100 border-{{ $color }}-subtle">
            <div class="card-body py-3">
                <div class="small text-{{ $color }} fw-semibold mb-1"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</div>
                @if($row)
                    <div class="fw-bold">{{ $row['name'] }}</div>
                    <div class="text-muted small">{{ $detail($row) }}</div>
                @else
                    <div class="text-muted small">{{ $label === 'Needs attention' ? 'No one is at risk — nice.' : 'Not enough data yet.' }}</div>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Main table --}}
<div class="card mb-3">
    <div class="card-header fw-semibold"><i class="bi bi-table text-primary me-2"></i>Loan Officer scorecard</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Loan Officer</th>
                    <th class="text-end">Clients</th>
                    <th class="text-end" title="Clients with a loan currently out">Borrowers</th>
                    <th class="text-end" title="Loans disbursed, all time">Disbursed</th>
                    <th class="text-end">Active</th>
                    <th class="text-end">Closed</th>
                    <th class="text-end">Defaulted</th>
                    <th class="text-end" title="Pending approval or awaiting disbursement">Pipeline</th>
                    <th class="text-end">Outstanding</th>
                    <th class="text-end">Share</th>
                    <th class="text-end" title="Outstanding on loans with an instalment 30+ days overdue">PAR30</th>
                    <th class="text-end" title="Defaulted ÷ disbursed loans">Default rate</th>
                    <th class="text-end" title="Paid ÷ due on every instalment that has fallen due">Collection eff.</th>
                    <th class="text-end">Disbursed (period)</th>
                    <th class="text-end">Collected (period)</th>
                    <th class="text-end">Avg loan</th>
                    <th class="text-end" title="Borrowers who have taken more than one loan">Repeat</th>
                    <th class="pe-3">Rating</th>
                </tr>
            </thead>
            <tbody>
            @forelse($officers as $r)
                <tr>
                    <td class="ps-3 fw-semibold text-nowrap">
                        {{ $r['name'] }}
                        @if($r['id'] && !$r['listed'])<span class="badge bg-light text-muted border ms-1" title="Not switched on under Users > Loan Officer, but still has clients assigned">not listed</span>@endif
                        @if($r['id'] && !$r['active'])<span class="badge bg-light text-danger border ms-1">inactive</span>@endif
                    </td>
                    <td class="text-end">{{ $fmt($r['clients']) }}</td>
                    <td class="text-end">{{ $fmt($r['borrowers']) }}</td>
                    <td class="text-end">{{ $fmt($r['issued_count']) }}<div class="text-muted" style="font-size:.68rem">{{ $fmt($r['issued_amount']) }}</div></td>
                    <td class="text-end">{{ $fmt($r['active_count']) }}</td>
                    <td class="text-end">{{ $fmt($r['closed_count']) }}</td>
                    <td class="text-end {{ $r['defaulted_count'] > 0 ? 'text-danger' : '' }}">{{ $fmt($r['defaulted_count']) }}@if($r['defaulted_count'])<div style="font-size:.68rem">{{ $fmt($r['defaulted_amount']) }}</div>@endif</td>
                    <td class="text-end">{{ $fmt($r['pipeline_count']) }}</td>
                    <td class="text-end fw-semibold">{{ $fmt($r['outstanding_principal']) }}</td>
                    <td class="text-end" style="min-width:90px">
                        {{ $r['portfolio_share'] }}%
                        <div class="progress" style="height:3px"><div class="progress-bar" style="width:{{ min(100, $r['portfolio_share']) }}%"></div></div>
                    </td>
                    <td class="text-end fw-semibold {{ $parClass($r['par30_pct']) }}">{{ $pct($r['par30_pct']) }}</td>
                    <td class="text-end">{{ $pct($r['default_rate']) }}</td>
                    <td class="text-end fw-semibold {{ $effClass($r['collection_efficiency']) }}">{{ $pct($r['collection_efficiency']) }}</td>
                    <td class="text-end">{{ $fmt($r['disbursed_period_amount']) }}<div class="text-muted" style="font-size:.68rem">{{ $r['disbursed_period_count'] }} loans</div></td>
                    <td class="text-end">{{ $fmt($r['collected_period']) }}</td>
                    <td class="text-end">{{ $fmt($r['avg_loan']) }}</td>
                    <td class="text-end">{{ $pct($r['repeat_rate']) }}</td>
                    <td class="pe-3">@include('staff-analysis._rating', ['rating' => $r['rating']])</td>
                </tr>
            @empty
                <tr><td colspan="18" class="text-center text-muted py-4">No Loan Officers yet. Switch users on under Users → “Appear in Loan Officer lists”.</td></tr>
            @endforelse
            </tbody>
            @if($officers->count() > 1)
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td class="ps-3">All Loan Officers</td>
                    <td class="text-end">{{ $fmt($t['clients']) }}</td>
                    <td class="text-end">{{ $fmt($t['borrowers']) }}</td>
                    <td class="text-end">{{ $fmt($t['issued_count']) }}<div class="text-muted fw-normal" style="font-size:.68rem">{{ $fmt($t['issued_amount']) }}</div></td>
                    <td class="text-end">{{ $fmt($t['active_count']) }}</td>
                    <td class="text-end">{{ $fmt($t['closed_count']) }}</td>
                    <td class="text-end">{{ $fmt($t['defaulted_count']) }}</td>
                    <td class="text-end">{{ $fmt($t['pipeline_count']) }}</td>
                    <td class="text-end">{{ $fmt($t['outstanding_principal']) }}</td>
                    <td class="text-end">100%</td>
                    <td class="text-end {{ $parClass($t['par30_pct']) }}">{{ $pct($t['par30_pct']) }}</td>
                    <td class="text-end">{{ $pct($t['default_rate']) }}</td>
                    <td class="text-end {{ $effClass($t['collection_efficiency']) }}">{{ $pct($t['collection_efficiency']) }}</td>
                    <td class="text-end">{{ $fmt($t['disbursed_period_amount']) }}</td>
                    <td class="text-end">{{ $fmt($t['collected_period']) }}</td>
                    <td class="text-end">{{ $fmt($t['avg_loan']) }}</td>
                    <td class="text-end">{{ $pct($t['repeat_rate']) }}</td>
                    <td class="pe-3">@include('staff-analysis._rating', ['rating' => $t['rating']])</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- How to read this --}}
<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-lightbulb text-warning me-2"></i>What makes a good Loan Officer</div>
    <div class="card-body small">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="fw-semibold mb-1">Quality of the book (most important)</div>
                <ul class="mb-0 ps-3">
                    <li><strong>PAR30</strong> — share of the outstanding book with an instalment 30+ days overdue. Under {{ $thresholds['strong_par'] }}% is strong; {{ $thresholds['risk_par'] }}% or more is at risk.</li>
                    <li><strong>Collection efficiency</strong> — of everything that has fallen due, how much was actually paid. {{ $thresholds['strong_eff'] }}%+ is strong; under {{ $thresholds['risk_eff'] }}% is at risk.</li>
                    <li><strong>Default rate</strong> — defaulted ÷ disbursed loans. Lower is better.</li>
                </ul>
            </div>
            <div class="col-md-6">
                <div class="fw-semibold mb-1">Growth &amp; relationships</div>
                <ul class="mb-0 ps-3">
                    <li><strong>Disbursed / Outstanding</strong> — how much business is being brought in and carried. Growth only counts if PAR30 stays low.</li>
                    <li><strong>Repeat borrowers</strong> — clients coming back for another loan signal trust and good service.</li>
                    <li><strong>Pipeline</strong> — pending or approved loans not yet disbursed; a steady pipeline means future growth.</li>
                </ul>
            </div>
            <div class="col-12 text-muted">
                <strong>Rating:</strong> <em>Strong</em> = PAR30 and collection efficiency both meet the strong bar. <em>At risk</em> = either breaches the risk line. <em>Watch</em> = in between.
                Read growth alongside quality: a big portfolio with a poor rating is a risk, not a win. Loans are credited to the Loan Officer currently assigned to the client.
            </div>
        </div>
    </div>
</div>
@endsection
