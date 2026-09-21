@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')

@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $userBranch = auth()->user()->branch;
@endphp
<div class="branch-hero">
    <div class="branch-hero-inner d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="branch-hero-icon">
                <i class="bi {{ $userBranch ? 'bi-geo-alt-fill' : 'bi-globe-americas' }}"></i>
            </div>
            <div>
                <div class="branch-hero-eyebrow">{{ $userBranch ? "You're viewing" : 'Organization-wide view' }}</div>
                <div class="branch-hero-name">{{ $userBranch->name ?? 'All Branches' }}</div>
                <div class="branch-hero-sub">{{ $greeting }}, {{ explode(' ', auth()->user()->name)[0] }} — here's how things stand{{ $userBranch ? ' at your branch' : '' }} today.</div>
            </div>
        </div>
        <div class="d-flex">
            <div class="branch-hero-stat">
                <div class="branch-hero-stat-val">{{ number_format($totalClients) }}</div>
                <div class="branch-hero-stat-label">Clients</div>
            </div>
            <div class="branch-hero-stat">
                <div class="branch-hero-stat-val">{{ number_format($stats['active_loans']) }}</div>
                <div class="branch-hero-stat-label">Active Loans</div>
            </div>
            <div class="branch-hero-stat">
                <div class="branch-hero-stat-val">{{ number_format($stats['total_outstanding'], 0) }}</div>
                <div class="branch-hero-stat-label">Outstanding</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Stat Cards ─────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <a href="{{ route('loans.index', ['status' => 'issued']) }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="text-muted small">Loans Disbursed</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['total_loans_issued']) }}</div>
                    <div class="text-muted" style="font-size:.7rem">{{ number_format($stats['total_loans_issued_amount'], $dp) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="{{ route('loans.index', ['status' => 'active']) }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="text-muted small">Active Loans</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['active_loans']) }}</div>
                    <div class="text-muted" style="font-size:.7rem">{{ number_format($stats['active_loans_amount'], $dp) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="{{ route('loans.index', ['status' => 'defaulted']) }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-circle"></i></div>
                <div>
                    <div class="text-muted small">Defaulted Loans</div>
                    <div class="fw-bold fs-5 {{ $stats['overdue_loans'] > 0 ? 'text-danger' : '' }}">{{ number_format($stats['overdue_loans']) }}</div>
                    <div class="text-muted" style="font-size:.7rem">{{ number_format($stats['overdue_loans_amount'], $dp) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="{{ route('loans.index', ['status' => 'pending']) }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="text-muted small">Pending Loans — Awaiting Approval</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['pending_loans']) }}</div>
                    <div class="text-muted" style="font-size:.7rem">{{ number_format($stats['pending_loans_amount'], $dp) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="{{ route('loans.index', ['status' => 'approved']) }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-clipboard-check"></i></div>
                <div>
                    <div class="text-muted small">Approved — Awaiting Disbursement</div>
                    <div class="fw-bold fs-5 {{ $stats['approved_loans'] > 0 ? 'text-info' : '' }}">{{ number_format($stats['approved_loans']) }}</div>
                    <div class="text-muted" style="font-size:.7rem">{{ number_format($stats['approved_loans_amount'], $dp) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="{{ route('loans.index', ['status' => 'closed']) }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-archive-fill"></i></div>
                <div>
                    <div class="text-muted small">Closed Loans</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['closed_loans']) }}</div>
                    <div class="text-muted" style="font-size:.7rem">{{ number_format($stats['closed_loans_amount'], $dp) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="{{ route('reports.loan-portfolio') }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="text-muted small">Outstanding Principal</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['total_outstanding'], $dp) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="{{ route('reports.loan-portfolio') }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-percent"></i></div>
                <div>
                    <div class="text-muted small">Outstanding Interest</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['outstanding_interest'], $dp) }}</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="{{ route('reports.interest-income') }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <div class="text-muted small">Total Interest Earned</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['total_interest_earned'], $dp) }}</div>
                </div>
            </div>
        </a>
    </div>
</div>

{{-- ── Row 1: Profitability + Loan Portfolio Risk ─────────────────────────── --}}
<div class="row g-3 mb-3">
    {{-- Profitability Analysis --}}
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold"><i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Profitability Analysis</span>
                <span class="text-muted small">Last 6 months</span>
            </div>
            <div class="card-body">
                @php
                    $totalIncome   = $monthlyIncome->sum();
                    $totalExpenses = $monthlyExpenses->sum();
                    $totalProfit   = $totalIncome - $totalExpenses;
                @endphp
                <div class="row g-3 mb-3">
                    <div class="col-4 text-center">
                        <div class="text-muted small mb-1">Total Income</div>
                        <div class="fw-bold text-success">{{ number_format($totalIncome, $dp) }}</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="text-muted small mb-1">Total Expenses</div>
                        <div class="fw-bold text-danger">{{ number_format($totalExpenses, $dp) }}</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="text-muted small mb-1">Net Profit</div>
                        <div class="fw-bold {{ $totalProfit >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($totalProfit, $dp) }}</div>
                    </div>
                </div>
                <canvas id="profitabilityChart" height="180"></canvas>
            </div>
        </div>
    </div>

    {{-- Loan Portfolio Risk --}}
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-shield-check text-info me-2"></i>Loan Portfolio Risk
            </div>
            <div class="card-body">
                @php
                    $parColor = $par30 == 0 ? 'success' : ($par30 <= 5 ? 'warning' : 'danger');
                    $parLabel = $par30 == 0 ? 'Low Risk' : ($par30 <= 5 ? 'Moderate' : 'High Risk');
                    $drColor  = $defaultRate == 0 ? 'success' : ($defaultRate <= 10 ? 'warning' : 'danger');
                    $drLabel  = $defaultRate == 0 ? 'Low Risk' : ($defaultRate <= 10 ? 'Moderate' : 'High Risk');
                @endphp
                <div class="mb-3 p-3 rounded border border-{{ $parColor }} bg-{{ $parColor }} bg-opacity-10">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <div class="small text-muted">Portfolio at Risk (PAR30)</div>
                            <div class="fw-bold fs-4 text-{{ $parColor }}">{{ $par30 }}%</div>
                        </div>
                        <span class="badge bg-{{ $parColor }}">{{ $parLabel }}</span>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-{{ $parColor }}" style="width: {{ min(100, $par30 * 5) }}%"></div>
                    </div>
                </div>
                <div class="p-3 rounded border border-{{ $drColor }} bg-{{ $drColor }} bg-opacity-10">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <div class="small text-muted">Default Rate</div>
                            <div class="fw-bold fs-4 text-{{ $drColor }}">{{ $defaultRate }}%</div>
                        </div>
                        <span class="badge bg-{{ $drColor }}">{{ $drLabel }}</span>
                    </div>
                    <div class="text-muted" style="font-size:.72rem">Share of all loans ever issued that are currently defaulted</div>
                </div>
                <div class="mt-3">
                    <canvas id="riskDonutChart" height="140"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Row: Assets & Liabilities Analysis ──────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold"><i class="bi bi-bank2 text-primary me-2"></i>Assets &amp; Liabilities</span>
                <span class="text-muted small">As of today</span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-4 text-center">
                        <div class="text-muted small mb-1">Total Assets</div>
                        <div class="fw-bold text-success">{{ number_format($totalAssets, $dp) }}</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="text-muted small mb-1">Total Liabilities</div>
                        <div class="fw-bold text-danger">{{ number_format($totalLiabilities, $dp) }}</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="text-muted small mb-1">Net Position</div>
                        <div class="fw-bold {{ $netPosition >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($netPosition, $dp) }}</div>
                    </div>
                </div>
                <canvas id="assetsLiabilitiesChart" height="180"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-list-ul text-secondary me-2"></i>Top Accounts
            </div>
            <div class="card-body">
                <div class="small fw-semibold text-success mb-2"><i class="bi bi-arrow-up-circle me-1"></i>Assets</div>
                @forelse($topAssetAccounts as $row)
                <div class="d-flex justify-content-between mb-2">
                    <span class="small text-truncate me-2">{{ $row['account']->account_name }}</span>
                    <span class="small fw-semibold text-nowrap">{{ number_format($row['balance'], 0) }}</span>
                </div>
                @empty
                <div class="text-muted small mb-2">No asset balances yet.</div>
                @endforelse
                <hr class="my-2">
                <div class="small fw-semibold text-danger mb-2"><i class="bi bi-arrow-down-circle me-1"></i>Liabilities</div>
                @forelse($topLiabilityAccounts as $row)
                <div class="d-flex justify-content-between mb-2">
                    <span class="small text-truncate me-2">{{ $row['account']->account_name }}</span>
                    <span class="small fw-semibold text-nowrap">{{ number_format($row['balance'], 0) }}</span>
                </div>
                @empty
                <div class="text-muted small mb-2">No liability balances yet.</div>
                @endforelse
                <a href="{{ route('reports.balance-sheet') }}" class="d-block text-center small mt-2">View full Balance Sheet →</a>
            </div>
        </div>
    </div>
</div>

{{-- ── Row: Loan Officer Performance ────────────────────────────────────────── --}}
@if($officerPerformance)
@php
    $opRows   = $officerPerformance['officers']->filter(fn ($r) => $r['id'] !== null)->take(6)->values();
    $opTotals = $officerPerformance['totals'];
    $opInsight = $officerPerformance['insights'];
    $opPct    = fn ($v) => $v === null ? '—' : number_format($v, 1) . '%';
    $opChart  = $opRows->filter(fn ($r) => $r['outstanding_principal'] > 0)->values();
@endphp
<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold"><i class="bi bi-person-badge text-primary me-2"></i>Loan Officer Performance</span>
                <a href="{{ route('staff-analysis.index') }}" class="small">Full analysis →</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size:.82rem">
                    <thead class="table-light"><tr>
                        <th class="ps-3">Officer</th>
                        <th class="text-end">Outstanding</th>
                        <th class="text-end" title="Outstanding with an instalment 30+ days overdue">PAR30</th>
                        <th class="text-end" title="Paid ÷ due on instalments that have fallen due">Collection</th>
                        <th class="text-end">Defaulted</th>
                        <th class="pe-3">Rating</th>
                    </tr></thead>
                    <tbody>
                    @forelse($opRows as $r)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $r['name'] }}<div class="text-muted fw-normal" style="font-size:.68rem">{{ $r['clients'] }} clients · {{ $r['active_count'] }} active loans</div></td>
                            <td class="text-end">{{ number_format($r['outstanding_principal'], 0) }}</td>
                            <td class="text-end">{{ $opPct($r['par30_pct']) }}</td>
                            <td class="text-end">{{ $opPct($r['collection_efficiency']) }}</td>
                            <td class="text-end {{ $r['defaulted_count'] ? 'text-danger' : '' }}">{{ $r['defaulted_count'] }}</td>
                            <td class="pe-3">@include('staff-analysis._rating', ['rating' => $r['rating']])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No Loan Officers yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted small">
                Team: {{ number_format($opTotals['outstanding_principal'], 0) }} outstanding ·
                PAR30 {{ $opPct($opTotals['par30_pct']) }} · collection {{ $opPct($opTotals['collection_efficiency']) }}
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-shield-check text-success me-2"></i>Portfolio Quality by Officer</div>
            <div class="card-body">
                @if($opChart->isNotEmpty())
                    <canvas id="officerQualityChart" height="{{ max(140, 46 * $opChart->count()) }}"></canvas>
                @else
                    <div class="text-muted small">No outstanding loans assigned to a Loan Officer yet.</div>
                @endif
                <hr class="my-3">
                @if($opInsight['lowest_par'])
                    <div class="small mb-1"><i class="bi bi-shield-check text-success me-1"></i><strong>Healthiest book:</strong> {{ $opInsight['lowest_par']['name'] }} ({{ $opPct($opInsight['lowest_par']['par30_pct']) }} PAR30)</div>
                @endif
                @if($opInsight['best_collection'])
                    <div class="small mb-1"><i class="bi bi-bullseye text-success me-1"></i><strong>Best collections:</strong> {{ $opInsight['best_collection']['name'] }} ({{ $opPct($opInsight['best_collection']['collection_efficiency']) }})</div>
                @endif
                @if($opInsight['needs_attention'])
                    <div class="small"><i class="bi bi-exclamation-octagon text-danger me-1"></i><strong>Needs attention:</strong> {{ $opInsight['needs_attention']['name'] }} ({{ $opPct($opInsight['needs_attention']['par30_pct']) }} PAR30)</div>
                @else
                    <div class="small text-muted"><i class="bi bi-check-circle text-success me-1"></i>No officer is currently rated at risk.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Row 2: Client Activity + Loan Status + Portfolio Insights ──────────── --}}
<div class="row g-3 mb-3">
    {{-- Client Activity --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-people-fill text-primary me-2"></i>Client Activity
            </div>
            <div class="card-body">
                @php $totalClientsCount = $totalClients ?: 1; @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small">Active Borrowers</span>
                        <span class="small fw-semibold">{{ $activeBorrowers }}</span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-primary" style="width: {{ min(100, round($activeBorrowers / $totalClientsCount * 100)) }}%"></div>
                    </div>
                    <div class="text-muted" style="font-size:.72rem">{{ round($activeBorrowers / $totalClientsCount * 100, 1) }}% of total clients</div>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Total Clients</span>
                    <span class="badge bg-secondary">{{ $totalClients }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="small text-muted">Pending Loans (awaiting approval)</span>
                    <span class="badge {{ $stats['pending_loans'] > 0 ? 'bg-warning text-dark' : 'bg-success' }}">{{ $stats['pending_loans'] }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Loan Portfolio by Status --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-pie-chart-fill text-success me-2"></i>Loan Portfolio by Status
            </div>
            <div class="card-body d-flex flex-column">
                <canvas id="loanStatusChart" height="180"></canvas>
                <div class="row g-2 mt-2 text-center">
                    <div class="col-3"><div class="small text-muted">Active</div><div class="fw-semibold">{{ $loanStatusBreakdown['active'] }}</div></div>
                    <div class="col-3"><div class="small text-muted">Defaulted</div><div class="fw-semibold text-danger">{{ $loanStatusBreakdown['defaulted'] }}</div></div>
                    <div class="col-3"><div class="small text-muted">Closed</div><div class="fw-semibold">{{ $loanStatusBreakdown['closed'] }}</div></div>
                    <div class="col-3"><div class="small text-muted">Pending</div><div class="fw-semibold">{{ $loanStatusBreakdown['pending'] }}</div></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Portfolio Insights --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-briefcase-fill text-warning me-2"></i>Portfolio Insights
            </div>
            <div class="card-body">
                <div class="mb-3">
                    @php
                        $portfolioStatus = $par30 > 5 ? ['label'=>'Attention Needed','color'=>'danger']
                            : ($par30 > 0 ? ['label'=>'Moderate','color'=>'warning']
                            : ['label'=>'Healthy','color'=>'success']);
                    @endphp
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">Portfolio Health</span>
                        <span class="badge bg-{{ $portfolioStatus['color'] }}">{{ $portfolioStatus['label'] }}</span>
                    </div>
                    <div class="text-muted small">Based on PAR30: {{ $par30 }}%</div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">Loan Growth</span>
                        <span class="badge {{ $loanGrowth >= 0 ? 'bg-primary' : 'bg-secondary' }}">
                            {{ $loanGrowth >= 0 ? '+' : '' }}{{ $loanGrowth }}%
                        </span>
                    </div>
                    <div class="text-muted small">vs last month disbursements</div>
                </div>
                <hr class="my-2">
                <div class="small fw-semibold mb-2">Strategic Recommendations</div>
                @foreach($recommendations as $rec)
                <div class="d-flex gap-2 mb-2 p-2 rounded bg-{{ $rec['type'] }} bg-opacity-10">
                    <i class="bi {{ $rec['icon'] }} text-{{ $rec['type'] }} mt-1 flex-shrink-0"></i>
                    <span style="font-size:.75rem">{{ $rec['text'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ── Row 3: Monthly Disbursements + Upcoming Installments ───────────────── --}}
<div class="row g-3">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-graph-up text-success me-2"></i>Monthly Loan Disbursements
            </div>
            <div class="card-body">
                <canvas id="trendsChart" height="180"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar-event text-warning me-2"></i>Upcoming Installments Due</span>
                <span class="text-muted small">Next 30 days</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr>
                        <th class="ps-3">Loan #</th><th>Client</th><th>Due</th><th class="text-end pe-3">Amount</th>
                    </tr></thead>
                    <tbody>
                    @forelse($upcomingInstallments as $schedule)
                        <tr>
                            <td class="ps-3"><a href="{{ route('loans.show', $schedule->loan) }}" class="text-decoration-none">{{ $schedule->loan->loan_number }}</a></td>
                            <td class="small">{{ $schedule->loan->client->name ?? '—' }}</td>
                            <td class="small text-warning">{{ \Illuminate\Support\Carbon::parse($schedule->due_date)->format('d M Y') }}</td>
                            <td class="text-end pe-3 small">{{ number_format(($schedule->principal_due - $schedule->principal_paid) + ($schedule->interest_due - $schedule->interest_paid), $dp) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3 small">No upcoming installments.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 4: Top Repeat Borrowers ─────────────────────────────────────────── --}}
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-trophy-fill text-warning me-2"></i>Top Repeat Borrowers</span>
                <span class="text-muted small">By number of loans taken</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr>
                        <th class="ps-3">Client</th>
                        <th class="text-end">Loans Taken</th>
                        <th class="text-end">Total Borrowed</th>
                        <th class="text-end pe-3">Current Outstanding</th>
                    </tr></thead>
                    <tbody>
                    @forelse($topBorrowers as $row)
                        <tr>
                            <td class="ps-3">
                                @if($row->client)
                                    <a href="{{ route('clients.show', $row->client) }}" class="text-decoration-none">{{ $row->client->name }}</a>
                                @else
                                    <span class="text-muted fst-italic">Deleted client</span>
                                @endif
                            </td>
                            <td class="text-end"><span class="badge bg-primary">{{ $row->loan_count }}</span></td>
                            <td class="text-end">{{ number_format($row->total_borrowed, $dp) }}</td>
                            <td class="text-end pe-3 {{ $row->current_outstanding > 0 ? 'text-warning fw-semibold' : 'text-success' }}">{{ number_format($row->current_outstanding, $dp) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3 small">No loans issued yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const labels  = @json($monthLabels);
const income   = @json($monthlyIncome->values());
const expenses = @json($monthlyExpenses->values());
const profit   = @json($monthlyProfit->values());
const loans    = @json($monthlyLoanDisbursements->values());

const gridColor = 'rgba(0,0,0,.05)';
const font = { family: "'Segoe UI', system-ui, sans-serif", size: 11 };

// ── Profitability Chart ──────────────────────────────────────────────────────
new Chart(document.getElementById('profitabilityChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label: 'Income',   data: income,   backgroundColor: 'rgba(34,197,94,.7)',  borderRadius: 4 },
            { label: 'Expenses', data: expenses, backgroundColor: 'rgba(239,68,68,.7)',  borderRadius: 4 },
            { label: 'Profit',   data: profit,   backgroundColor: 'rgba(59,130,246,.7)', borderRadius: 4, type: 'line',
              borderColor: 'rgba(59,130,246,.9)', tension: 0.4, fill: false, pointRadius: 3 },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { labels: { font } } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { font } },
            y: { grid: { color: gridColor }, ticks: { font, callback: v => v.toLocaleString() } }
        }
    }
});

@if(!empty($opChart) && $opChart->isNotEmpty())
// ── Loan Officer portfolio quality ──────────────────────────────────────────
new Chart(document.getElementById('officerQualityChart'), {
    type: 'bar',
    data: {
        labels: @json($opChart->pluck('name')),
        datasets: [
            { label: 'Performing', data: @json($opChart->map(fn ($r) => round($r['outstanding_principal'] - $r['par30_amount'], 2))), backgroundColor: 'rgba(34,197,94,.75)', borderRadius: 3 },
            { label: 'PAR30 (at risk)', data: @json($opChart->pluck('par30_amount')), backgroundColor: 'rgba(239,68,68,.8)', borderRadius: 3 },
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { font } } },
        scales: {
            x: { stacked: true, grid: { color: gridColor }, ticks: { font, callback: v => v.toLocaleString() } },
            y: { stacked: true, grid: { display: false }, ticks: { font } }
        }
    }
});
@endif

// ── Assets & Liabilities Chart ──────────────────────────────────────────────
new Chart(document.getElementById('assetsLiabilitiesChart'), {
    type: 'bar',
    data: {
        labels: ['Assets', 'Liabilities'],
        datasets: [{
            data: [{{ $totalAssets }}, {{ $totalLiabilities }}],
            backgroundColor: ['rgba(34,197,94,.75)', 'rgba(239,68,68,.75)'],
            borderRadius: 4,
            barThickness: 60,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { font, callback: v => v.toLocaleString() } },
            y: { grid: { display: false }, ticks: { font } }
        }
    }
});

// ── Risk Donut ───────────────────────────────────────────────────────────────
new Chart(document.getElementById('riskDonutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Performing Loans', 'At Risk (PAR30)'],
        datasets: [{
            data: [Math.max(0, 100 - {{ $par30 }}), {{ $par30 }}],
            backgroundColor: ['rgba(34,197,94,.8)', 'rgba(239,68,68,.8)'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true, cutout: '70%',
        plugins: {
            legend: { position: 'bottom', labels: { font, boxWidth: 12 } },
            tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.raw + '%' } }
        }
    }
});

// ── Loan Status Pie ──────────────────────────────────────────────────────────
new Chart(document.getElementById('loanStatusChart'), {
    type: 'pie',
    data: {
        labels: ['Active', 'Defaulted', 'Closed', 'Pending'],
        datasets: [{
            data: [
                {{ $loanStatusBreakdown['active'] }},
                {{ $loanStatusBreakdown['defaulted'] }},
                {{ $loanStatusBreakdown['closed'] }},
                {{ $loanStatusBreakdown['pending'] }}
            ],
            backgroundColor: ['rgba(34,197,94,.8)', 'rgba(239,68,68,.8)', 'rgba(107,114,128,.8)', 'rgba(245,158,11,.8)'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { font, boxWidth: 12 } }
        }
    }
});

// ── Monthly Disbursements Chart ──────────────────────────────────────────────
new Chart(document.getElementById('trendsChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label: 'Loan Disbursements', data: loans, backgroundColor: 'rgba(245,158,11,.7)', borderRadius: 4 },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { font } } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { font } },
            y: { grid: { color: gridColor }, ticks: { font, callback: v => v.toLocaleString() } }
        }
    }
});
</script>
@endpush
@endsection
