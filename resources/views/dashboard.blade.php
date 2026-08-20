@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<h4 class="fw-bold mb-4">Dashboard</h4>

{{-- ── Stat Cards ─────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="text-muted small">Total Loans Issued</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['total_loans_issued']) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="text-muted small">Outstanding Principal</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['total_outstanding'], $dp) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-percent"></i></div>
                <div>
                    <div class="text-muted small">Outstanding Interest</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['outstanding_interest'], $dp) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <div class="text-muted small">Total Interest Earned</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['total_interest_earned'], $dp) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-circle"></i></div>
                <div>
                    <div class="text-muted small">Overdue Loans</div>
                    <div class="fw-bold fs-5 {{ $stats['overdue_loans'] > 0 ? 'text-danger' : '' }}">{{ number_format($stats['overdue_loans']) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-piggy-bank"></i></div>
                <div>
                    <div class="text-muted small">Total Savings Balance</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['total_savings_balance'], $dp) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-safe"></i></div>
                <div>
                    <div class="text-muted small">FD Principal</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['total_fd_principal'], $dp) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                <div>
                    <div class="text-muted small">Active Clients</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['active_clients']) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="text-muted small">Pending Loans</div>
                    <div class="fw-bold fs-5">{{ number_format($stats['pending_loans']) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 1: Profitability + Monthly Trends ──────────────────────────────── --}}
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

    {{-- Liquidity & Risk Metrics --}}
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-shield-check text-info me-2"></i>Liquidity &amp; Risk Metrics
            </div>
            <div class="card-body">
                {{-- Loan-to-Savings Ratio --}}
                @php
                    $ltsColor = $loanToSavingsRatio <= 0.7 ? 'success' : ($loanToSavingsRatio <= 1 ? 'warning' : 'danger');
                    $ltsLabel = $loanToSavingsRatio <= 0.7 ? 'Excellent Liquidity' : ($loanToSavingsRatio <= 1 ? 'Moderate' : 'High Risk');
                    $parColor = $par30 == 0 ? 'success' : ($par30 <= 5 ? 'warning' : 'danger');
                    $parLabel = $par30 == 0 ? 'Low Risk' : ($par30 <= 5 ? 'Moderate' : 'High Risk');
                @endphp
                <div class="mb-3 p-3 rounded border border-{{ $ltsColor }} bg-{{ $ltsColor }} bg-opacity-10">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <div class="small text-muted">Loan-to-Savings Ratio</div>
                            <div class="fw-bold fs-4 text-{{ $ltsColor }}">{{ $loanToSavingsRatio }}</div>
                        </div>
                        <span class="badge bg-{{ $ltsColor }}">{{ $ltsLabel }}</span>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-{{ $ltsColor }}" style="width: {{ min(100, $loanToSavingsRatio * 100) }}%"></div>
                    </div>
                </div>
                <div class="p-3 rounded border border-{{ $parColor }} bg-{{ $parColor }} bg-opacity-10">
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
                <div class="mt-3">
                    <canvas id="riskDonutChart" height="140"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 2: Client Activity + Savings Insights ──────────────────────────── --}}
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
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small">Active Savers</span>
                        <span class="small fw-semibold">{{ $activeSavers }}</span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-success" style="width: {{ min(100, round($activeSavers / $totalClientsCount * 100)) }}%"></div>
                    </div>
                    <div class="text-muted" style="font-size:.72rem">{{ round($activeSavers / $totalClientsCount * 100, 1) }}% of total clients</div>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Dormant Accounts <span style="font-size:.7rem">(6+ months)</span></span>
                    <span class="badge {{ $dormantAccounts > 0 ? 'bg-warning text-dark' : 'bg-success' }}">{{ $dormantAccounts }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="small text-muted">Total Clients</span>
                    <span class="badge bg-secondary">{{ $totalClients }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Savings Insights --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-piggy-bank-fill text-success me-2"></i>Savings Insights
                <span class="text-muted fw-normal small ms-1">— This month</span>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 rounded bg-success bg-opacity-10 text-center">
                            <div class="text-muted small">New Accounts</div>
                            <div class="fw-bold fs-5 text-success">{{ $newSavingsThisMonth }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded bg-primary bg-opacity-10 text-center">
                            <div class="text-muted small">Net Cash Flow</div>
                            <div class="fw-bold text-{{ $netCashFlow >= 0 ? 'success' : 'danger' }}" style="font-size:.95rem">
                                {{ $netCashFlow >= 0 ? '+' : '' }}{{ number_format($netCashFlow, $dp) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Deposits</span>
                        <span class="fw-semibold text-success">{{ number_format($depositsThisMonth, $dp) }}</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Withdrawals</span>
                        <span class="fw-semibold text-danger">{{ number_format($withdrawalsThisMonth, $dp) }}</span>
                    </div>
                </div>
                <hr class="my-2">
                <canvas id="savingsTrendChart" height="100"></canvas>
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
                        $portfolioStatus = $loanToSavingsRatio > 1 ? ['label'=>'Attention Needed','color'=>'danger']
                            : ($loanToSavingsRatio > 0.7 ? ['label'=>'Moderate','color'=>'warning']
                            : ['label'=>'Healthy','color'=>'success']);
                    @endphp
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">Portfolio Health</span>
                        <span class="badge bg-{{ $portfolioStatus['color'] }}">{{ $portfolioStatus['label'] }}</span>
                    </div>
                    <div class="text-muted small">Ratio: {{ $loanToSavingsRatio }}</div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">Savings Growth</span>
                        <span class="badge {{ $savingsGrowth >= 0 ? 'bg-success' : 'bg-danger' }}">
                            {{ $savingsGrowth >= 0 ? '+' : '' }}{{ $savingsGrowth }}%
                        </span>
                    </div>
                    <div class="text-muted small">vs last month deposits</div>
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

{{-- ── Row 3: Cumulative Trends + FD Maturities ───────────────────────────── --}}
<div class="row g-3">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-graph-up text-success me-2"></i>Monthly Trends — Savings &amp; Loan Disbursements
            </div>
            <div class="card-body">
                <canvas id="trendsChart" height="180"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar-event text-warning me-2"></i>Upcoming FD Maturities</span>
                <span class="text-muted small">Next 30 days</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr>
                        <th class="ps-3">Deposit</th><th>Client</th><th>Maturity</th><th class="text-end pe-3">Amount</th>
                    </tr></thead>
                    <tbody>
                    @forelse($upcomingMaturities as $fd)
                        <tr>
                            <td class="ps-3"><a href="{{ route('fixed-deposits.show', $fd) }}" class="text-decoration-none">{{ $fd->deposit_number }}</a></td>
                            <td class="small">{{ $fd->client->name }}</td>
                            <td class="small text-warning">{{ $fd->maturity_date->format('d M Y') }}</td>
                            <td class="text-end pe-3 small">{{ number_format($fd->maturity_amount, $dp) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3 small">No upcoming maturities.</td></tr>
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
const deposits = @json($monthlySavingsDeposits->values());
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

// ── Savings Trend Mini Chart ─────────────────────────────────────────────────
new Chart(document.getElementById('savingsTrendChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: 'Deposits', data: deposits,
            borderColor: 'rgba(34,197,94,.9)', backgroundColor: 'rgba(34,197,94,.1)',
            tension: 0.4, fill: true, pointRadius: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font } },
            y: { grid: { color: gridColor }, ticks: { font, callback: v => v >= 1000 ? (v/1000)+'k' : v } }
        }
    }
});

// ── Trends Chart ─────────────────────────────────────────────────────────────
new Chart(document.getElementById('trendsChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label: 'Savings Deposits',     data: deposits, backgroundColor: 'rgba(34,197,94,.7)',  borderRadius: 4 },
            { label: 'Loan Disbursements',   data: loans,    backgroundColor: 'rgba(245,158,11,.7)', borderRadius: 4 },
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
