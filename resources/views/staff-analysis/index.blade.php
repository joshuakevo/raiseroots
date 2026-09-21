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
    $d = fn ($iso) => \Illuminate\Support\Carbon::parse($iso)->format('j M Y');
    $isAll = $period['key'] === 'all';

    // Up/down against the previous window of the same length. Amounts show % change; rates show percentage points.
    $delta = function ($cur, $prev) {
        if ($prev === null) return '';
        if ($prev == 0) return $cur > 0 ? '<span class="badge bg-info-subtle text-info-emphasis" style="font-size:.6rem">new</span>' : '';
        $c = ($cur - $prev) / $prev * 100;
        if (abs($c) < 0.05) return '<span class="text-muted" style="font-size:.66rem">— 0%</span>';
        return '<span class="' . ($c > 0 ? 'text-success' : 'text-danger') . '" style="font-size:.66rem">' . ($c > 0 ? '▲' : '▼') . ' ' . number_format(abs($c), 0) . '%</span>';
    };
    $deltaPts = function ($cur, $prev) {
        if ($cur === null || $prev === null) return '';
        $c = $cur - $prev;
        if (abs($c) < 0.05) return '<span class="text-muted" style="font-size:.66rem">— 0 pts</span>';
        return '<span class="' . ($c > 0 ? 'text-success' : 'text-danger') . '" style="font-size:.66rem">' . ($c > 0 ? '▲' : '▼') . ' ' . number_format(abs($c), 1) . ' pts</span>';
    };
    $prevOf = fn ($r) => $previous === null ? null : ($previous[$r['id'] ?? 'none'] ?? null);
    $prevT  = $previous['total'] ?? null;
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Staff Analysis</h4>
        <div class="text-muted small">How each Loan Officer's clients are performing. Loans are credited to the client's Loan Officer.</div>
    </div>
    <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
</div>

{{-- Period picker --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="small fw-semibold text-muted me-1"><i class="bi bi-calendar3 me-1"></i>Period</span>
            <div class="d-flex flex-wrap gap-1">
                @foreach($periods as $key => $label)
                    <a href="{{ route('staff-analysis.index', ['period' => $key]) }}"
                       class="btn btn-sm {{ $period['key'] === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
                <button type="button" class="btn btn-sm {{ $period['key'] === 'custom' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        data-bs-toggle="collapse" data-bs-target="#customRange" aria-expanded="{{ $period['key'] === 'custom' ? 'true' : 'false' }}">
                    <i class="bi bi-sliders me-1"></i>Custom
                </button>
            </div>
        </div>

        <div class="collapse {{ $period['key'] === 'custom' ? 'show' : '' }} mt-3" id="customRange">
            {{-- data-no-block: the layout normally disables a submitted form's buttons, which strands this one on back/refresh --}}
            <form method="GET" action="{{ route('staff-analysis.index') }}" class="row g-2 align-items-end" data-no-block="1">
                <input type="hidden" name="period" value="custom">
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-0">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ $period['from'] }}" required>
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-0">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ $period['to'] }}" required>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Apply range</button></div>
            </form>
        </div>

        <div class="small text-muted mt-2">
            Showing <strong class="text-body">{{ $period['label'] }}</strong>:
            {{ $isAll ? 'everything to date' : ($period['from'] === $period['to'] ? $d($period['from']) : $d($period['from']) . ' – ' . $d($period['to'])) }}
            @if(!$isAll)
                · compared with the previous {{ $period['days'] }} {{ Str::plural('day', $period['days']) }} ({{ $d($period['prev_from']) }} – {{ $d($period['prev_to']) }})
            @endif
        </div>
    </div>
</div>

{{-- Activity in the period (this is the part that follows the picker) --}}
<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge text-bg-primary">{{ $period['label'] }}</span>
    <span class="fw-semibold">Activity in this period</span>
</div>
<div class="row g-3 mb-3">
    @foreach([
        ['Disbursed', $fmt($t['disbursed_period_amount']), 'bi-cash-stack', 'primary', $t['disbursed_period_count'] . ' loans', $delta($t['disbursed_period_amount'], $prevT['disbursed_period_amount'] ?? null)],
        ['Collected', $fmt($t['collected_period']), 'bi-piggy-bank', 'success', $fmt($t['interest_collected_period']) . ' of it interest', $delta($t['collected_period'], $prevT['collected_period'] ?? null)],
        ['Instalments due', $fmt($t['due_period']), 'bi-calendar-check', 'info', 'fell due in this period', $delta($t['due_period'], $prevT['due_period'] ?? null)],
        ['Collection rate', $pct($t['period_efficiency']), 'bi-bullseye', $t['period_efficiency'] === null ? 'secondary' : ($t['period_efficiency'] < $thresholds['risk_eff'] ? 'danger' : ($t['period_efficiency'] < $thresholds['strong_eff'] ? 'warning' : 'success')), $fmt($t['paid_period']) . ' of ' . $fmt($t['due_period']) . ' paid', $deltaPts($t['period_efficiency'], $prevT['period_efficiency'] ?? null)],
    ] as [$label, $value, $icon, $color, $sub, $dl])
    <div class="col-6 col-lg-3">
        <div class="card h-100 border-primary-subtle">
            <div class="card-body py-3">
                <div class="text-muted small"><i class="bi {{ $icon }} text-{{ $color }} me-1"></i>{{ $label }}</div>
                <div class="d-flex align-items-baseline gap-2 flex-wrap"><span class="fs-5 fw-bold">{{ $value }}</span>{!! $dl !!}</div>
                <div class="text-muted" style="font-size:.7rem">{{ $sub }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Position as of today (not affected by the picker) --}}
<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge text-bg-secondary">As of today</span>
    <span class="fw-semibold">Portfolio position</span>
    <span class="text-muted small">— a snapshot, doesn't change with the period</span>
</div>
<div class="row g-3 mb-3">
    @foreach([
        ['Loan Officers', $officers->where('id', '!=', null)->count(), 'bi-person-badge', 'primary', $fmt($t['clients']) . ' clients'],
        ['Loans Disbursed', $fmt($t['issued_count']), 'bi-cash-stack', 'primary', $fmt($t['issued_amount']) . ' all time'],
        ['Outstanding', $fmt($t['outstanding_principal']), 'bi-wallet2', 'info', $fmt($t['active_count']) . ' active loans'],
        ['PAR 30', $pct($t['par30_pct']), 'bi-exclamation-triangle', $t['par30_pct'] === null ? 'secondary' : ($t['par30_pct'] >= $thresholds['risk_par'] ? 'danger' : ($t['par30_pct'] >= $thresholds['strong_par'] ? 'warning' : 'success')), $fmt($t['par30_amount']) . ' at risk'],
        ['Collection Efficiency', $pct($t['collection_efficiency']), 'bi-bullseye', $t['collection_efficiency'] === null ? 'secondary' : ($t['collection_efficiency'] < $thresholds['risk_eff'] ? 'danger' : ($t['collection_efficiency'] < $thresholds['strong_eff'] ? 'warning' : 'success')), $fmt($t['paid_to_date']) . ' of ' . $fmt($t['due_to_date']) . ' due to date'],
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
        ['Best collections to date', 'bi-bullseye', 'success', $insights['best_collection'], fn ($r) => $pct($r['collection_efficiency']) . ' of instalments due collected'],
        ['Top disburser (period)', 'bi-rocket-takeoff', 'info', $insights['top_disburser'], fn ($r) => $fmt($r['disbursed_period_amount']) . ' across ' . $r['disbursed_period_count'] . ' loans'],
        ['Top collector (period)', 'bi-piggy-bank', 'info', $insights['top_collector'], fn ($r) => $fmt($r['collected_period']) . ' collected'],
        ['Needs attention', 'bi-exclamation-octagon', 'danger', $insights['needs_attention'], fn ($r) => 'PAR30 ' . $pct($r['par30_pct']) . ' · ' . $fmt($r['par30_amount']) . ' at risk'],
    ];
@endphp
<div class="row g-3 mb-3">
    @foreach($cards as [$label, $icon, $color, $row, $detail])
    <div class="col-md-6 col-xl-2">
        <div class="card h-100 border-{{ $color }}-subtle">
            <div class="card-body py-3">
                <div class="small text-{{ $color }} fw-semibold mb-1"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</div>
                @if($row)
                    <div class="fw-bold">{{ $row['name'] }}</div>
                    <div class="text-muted small">{{ $detail($row) }}</div>
                @else
                    <div class="text-muted small">{{ $label === 'Needs attention' ? 'No one is at risk — nice.' : 'Nothing in this period.' }}</div>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Main table: activity (follows the period) then position (snapshot) --}}
<div class="card mb-3">
    <div class="card-header fw-semibold"><i class="bi bi-table text-primary me-2"></i>Loan Officer scorecard</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
            <thead>
                <tr class="text-center small">
                    <th class="table-light"></th>
                    <th colspan="5" class="bg-primary-subtle text-primary-emphasis border-start border-end">Activity · {{ $period['label'] }}</th>
                    <th colspan="15" class="table-light text-secondary">Position · as of today</th>
                </tr>
                <tr class="table-light">
                    <th class="ps-3">Loan Officer</th>
                    <th class="text-end bg-primary-subtle bg-opacity-25 border-start">Disbursed</th>
                    <th class="text-end bg-primary-subtle bg-opacity-25">Collected</th>
                    <th class="text-end bg-primary-subtle bg-opacity-25" title="Interest part of what was collected">Interest</th>
                    <th class="text-end bg-primary-subtle bg-opacity-25" title="Instalments that fell due in the period">Due</th>
                    <th class="text-end bg-primary-subtle bg-opacity-25 border-end" title="Paid ÷ due on instalments that fell due in the period">Coll. rate</th>
                    <th class="text-end">Clients</th>
                    <th class="text-end" title="Clients with a loan currently out">Borrowers</th>
                    <th class="text-end" title="Loans disbursed, all time">Loans</th>
                    <th class="text-end">Active</th>
                    <th class="text-end">Closed</th>
                    <th class="text-end">Defaulted</th>
                    <th class="text-end" title="Pending approval or awaiting disbursement">Pipeline</th>
                    <th class="text-end">Outstanding</th>
                    <th class="text-end">Share</th>
                    <th class="text-end" title="Outstanding on loans with an instalment 30+ days overdue">PAR30</th>
                    <th class="text-end" title="Defaulted ÷ disbursed loans">Default rate</th>
                    <th class="text-end" title="Paid ÷ due on every instalment that has fallen due, to date">Coll. eff.</th>
                    <th class="text-end">Avg loan</th>
                    <th class="text-end" title="Borrowers who have taken more than one loan">Repeat</th>
                    <th class="pe-3">Rating</th>
                </tr>
            </thead>
            <tbody>
            @forelse($officers as $r)
                @php $p = $prevOf($r); @endphp
                <tr>
                    <td class="ps-3 fw-semibold text-nowrap">
                        {{ $r['name'] }}
                        @if($r['id'] && !$r['listed'])<span class="badge bg-light text-muted border ms-1" title="Not switched on under Users > Loan Officer, but still has clients assigned">not listed</span>@endif
                        @if($r['id'] && !$r['active'])<span class="badge bg-light text-danger border ms-1">inactive</span>@endif
                    </td>
                    <td class="text-end border-start">{{ $fmt($r['disbursed_period_amount']) }}<div class="text-muted" style="font-size:.68rem">{{ $r['disbursed_period_count'] }} loans {!! $delta($r['disbursed_period_amount'], $p['disbursed_period_amount'] ?? null) !!}</div></td>
                    <td class="text-end">{{ $fmt($r['collected_period']) }}<div>{!! $delta($r['collected_period'], $p['collected_period'] ?? null) !!}</div></td>
                    <td class="text-end">{{ $fmt($r['interest_collected_period']) }}</td>
                    <td class="text-end">{{ $fmt($r['due_period']) }}</td>
                    <td class="text-end border-end fw-semibold {{ $effClass($r['period_efficiency']) }}">{{ $pct($r['period_efficiency']) }}<div class="fw-normal">{!! $deltaPts($r['period_efficiency'], $p['period_efficiency'] ?? null) !!}</div></td>
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
                    <td class="text-end">{{ $fmt($r['avg_loan']) }}</td>
                    <td class="text-end">{{ $pct($r['repeat_rate']) }}</td>
                    <td class="pe-3">@include('staff-analysis._rating', ['rating' => $r['rating']])</td>
                </tr>
            @empty
                <tr><td colspan="21" class="text-center text-muted py-4">No Loan Officers yet. Switch users on under Users → “Appear in Loan Officer lists”.</td></tr>
            @endforelse
            </tbody>
            @if($officers->count() > 1)
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td class="ps-3">All Loan Officers</td>
                    <td class="text-end border-start">{{ $fmt($t['disbursed_period_amount']) }}<div class="text-muted fw-normal" style="font-size:.68rem">{{ $t['disbursed_period_count'] }} loans {!! $delta($t['disbursed_period_amount'], $prevT['disbursed_period_amount'] ?? null) !!}</div></td>
                    <td class="text-end">{{ $fmt($t['collected_period']) }}<div class="fw-normal">{!! $delta($t['collected_period'], $prevT['collected_period'] ?? null) !!}</div></td>
                    <td class="text-end">{{ $fmt($t['interest_collected_period']) }}</td>
                    <td class="text-end">{{ $fmt($t['due_period']) }}</td>
                    <td class="text-end border-end {{ $effClass($t['period_efficiency']) }}">{{ $pct($t['period_efficiency']) }}<div class="fw-normal">{!! $deltaPts($t['period_efficiency'], $prevT['period_efficiency'] ?? null) !!}</div></td>
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
                <strong>The period picker</strong> drives the <em>Activity</em> block: what was disbursed and collected, and the collection rate on instalments that fell due in that window
                (▲/▼ compares with the previous window of the same length). The <em>Position</em> block is a snapshot as of today.
                <strong>Rating:</strong> <em>Strong</em> = PAR30 and collection efficiency both meet the strong bar. <em>At risk</em> = either breaches the risk line. <em>Watch</em> = in between.
                Read growth alongside quality: a big portfolio with a poor rating is a risk, not a win. Loans are credited to the Loan Officer currently assigned to the client.
            </div>
        </div>
    </div>
</div>
@endsection
