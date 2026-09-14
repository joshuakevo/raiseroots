@extends('layouts.app')
@section('title', 'Reports')
@section('breadcrumb')
    <li class="breadcrumb-item active">Reports</li>
@endsection
@section('content')
<h4 class="fw-bold mb-4">Reports</h4>
<div class="row g-3">
    @php
    $reports = [
        ['title'=>'Trial Balance','icon'=>'bi-journal-check','desc'=>'View debit/credit totals for all accounts','route'=>'reports.trial-balance','color'=>'primary'],
        ['title'=>'Income Statement','icon'=>'bi-graph-up','desc'=>'Revenue vs expenses for a period','route'=>'reports.income-statement','color'=>'success'],
        ['title'=>'Balance Sheet','icon'=>'bi-building','desc'=>'Assets, liabilities, and equity snapshot','route'=>'reports.balance-sheet','color'=>'info'],
        ['title'=>'General Ledger','icon'=>'bi-journal-text','desc'=>'All transactions for a specific account','route'=>'reports.general-ledger','color'=>'secondary'],
        ['title'=>'Loan Portfolio','icon'=>'bi-cash-stack','desc'=>'Full overview of all loans','route'=>'reports.loan-portfolio','color'=>'warning'],
        ['title'=>'Loan Aging','icon'=>'bi-clock-history','desc'=>'Overdue loans by age bucket','route'=>'reports.loan-aging','color'=>'danger'],
        ['title'=>'Repayment Schedule','icon'=>'bi-calendar3','desc'=>'Full schedule for a selected loan','route'=>'reports.repayment-schedule','color'=>'primary'],
        ['title'=>'Interest Income','icon'=>'bi-currency-dollar','desc'=>'Interest earned over a period','route'=>'reports.interest-income','color'=>'success'],
        ['title'=>'Savings Balances','icon'=>'bi-piggy-bank','desc'=>'All active savings account balances','route'=>'reports.savings-balances','color'=>'info'],
        ['title'=>'FD Maturity','icon'=>'bi-safe','desc'=>'Fixed deposits maturing in a period','route'=>'reports.fd-maturity','color'=>'secondary'],
        ['title'=>'Member Summary','icon'=>'bi-people-fill','desc'=>'Outstanding loans & relationship manager per member','route'=>'reports.member-summary','color'=>'primary'],
    ];
    @endphp
    @foreach($reports as $r)
    <div class="col-md-4">
        <a href="{{ route($r['route']) }}" class="text-decoration-none">
            <div class="card h-100 hover-shadow" style="transition:.2s">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-{{ $r['color'] }} bg-opacity-10 text-{{ $r['color'] }}">
                        <i class="bi {{ $r['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="fw-semibold text-dark">{{ $r['title'] }}</div>
                        <div class="text-muted small">{{ $r['desc'] }}</div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>
@endsection
