@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<h4 class="fw-bold mb-4">Dashboard</h4>

{{-- ── Stat Cards ─────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <a href="{{ route('loans.index', ['status' => 'issued']) }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="text-muted small">Loans Disbursed</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['total_loans_issued']) }}</div>
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
    <div class="col-6 col-md-4">
        <a href="{{ route('reports.loan-portfolio') }}" class="stat-card text-decoration-none text-reset d-block">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calculator"></i></div>
                <div>
                    <div class="text-muted small">Average Loan Size</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['average_loan_size'], $dp) }}</div>
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
