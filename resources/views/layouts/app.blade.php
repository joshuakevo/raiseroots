<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
    <style>
        :root {
            --sidebar-w: 255px;
            --topbar-h: 56px;
            --primary: #0f2444;
            --primary-light: #1a3a6e;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --sidebar-text: rgba(255,255,255,.72);
            --body-bg: #f0f2f5;
            --card-shadow: 0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);
        }
        *,*::before,*::after{box-sizing:border-box}
        body{background:var(--body-bg);font-family:'Segoe UI',system-ui,sans-serif;font-size:.875rem;overflow-x:hidden}

        /* SIDEBAR */
        .sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w);background:var(--primary);display:flex;flex-direction:column;z-index:1050;transition:transform .25s ease}
        .sidebar.collapsed{transform:translateX(-100%)}
        .sidebar-brand{padding:.9rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.07);flex-shrink:0;display:flex;align-items:center;gap:.65rem}
        .sidebar-brand .brand-icon{width:34px;height:34px;border-radius:8px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:1rem;color:#fff;flex-shrink:0}
        .sidebar-brand .brand-text{color:#fff;font-weight:700;font-size:.9rem;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .sidebar-brand .brand-sub{color:rgba(255,255,255,.4);font-size:.67rem}
        .sidebar-scroll{flex:1;overflow-y:auto;overflow-x:hidden;padding:.4rem 0 1rem}
        .sidebar-scroll::-webkit-scrollbar{width:3px}
        .sidebar-scroll::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:3px}
        .sidebar-section{font-size:.63rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.28);padding:1rem 1.1rem .3rem}
        .nav-link-item{display:flex;align-items:center;gap:.6rem;padding:.44rem 1.1rem;margin:.04rem .45rem;border-radius:6px;color:var(--sidebar-text);text-decoration:none;font-size:.81rem;transition:background .15s,color .15s;white-space:nowrap}
        .nav-link-item i{font-size:.92rem;opacity:.8;flex-shrink:0;width:16px;text-align:center}
        .nav-link-item:hover{background:rgba(255,255,255,.08);color:#fff}
        .nav-link-item.active{background:var(--accent);color:#fff}
        .nav-link-item.active i{opacity:1}
        .nav-collapse-btn{display:flex;align-items:center;gap:.6rem;padding:.44rem 1.1rem;margin:.04rem .45rem;border-radius:6px;color:var(--sidebar-text);background:none;border:none;width:calc(100% - .9rem);cursor:pointer;font-size:.81rem;text-align:left;transition:background .15s,color .15s}
        .nav-collapse-btn:hover{background:rgba(255,255,255,.08);color:#fff}
        .nav-collapse-btn .chevron{margin-left:auto;font-size:.68rem;transition:transform .2s}
        .nav-collapse-btn[aria-expanded="true"] .chevron{transform:rotate(90deg)}
        .nav-collapse-btn i:first-child{font-size:.92rem;opacity:.8;flex-shrink:0;width:16px;text-align:center}
        .nav-sub{padding-left:1.4rem}
        .nav-sub .nav-link-item{font-size:.78rem;padding:.35rem .9rem}
        .sidebar-footer{border-top:1px solid rgba(255,255,255,.07);padding:.7rem 1.1rem;flex-shrink:0}

        /* TOPBAR */
        .topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:#fff;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;padding:0 1.25rem;gap:.75rem;z-index:1040;transition:left .25s ease}
        .topbar.full{left:0}
        .topbar-toggle{background:none;border:none;padding:.3rem;border-radius:6px;color:#6b7280;cursor:pointer;font-size:1.2rem;line-height:1;flex-shrink:0}
        .topbar-toggle:hover{background:#f3f4f6;color:#111}
        .breadcrumb{margin:0;font-size:.78rem}
        .breadcrumb-item a{color:#6b7280;text-decoration:none}
        .breadcrumb-item.active{color:#111;font-weight:500}
        .topbar-right{margin-left:auto;display:flex;align-items:center;gap:.6rem}
        .topbar-date{font-size:.72rem;color:#9ca3af}
        .topbar-user{display:flex;align-items:center;gap:.45rem;padding:.28rem .65rem;border-radius:6px;background:#f9fafb;border:1px solid #e5e7eb;cursor:pointer;font-size:.81rem;font-weight:500;color:#374151}
        .avatar{width:26px;height:26px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.67rem;font-weight:700;flex-shrink:0}

        /* MAIN */
        .main-wrap{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh;transition:margin-left .25s ease}
        .main-wrap.full{margin-left:0}
        .page-content{padding:1.4rem}

        /* OVERLAY */
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1049}
        .sidebar-overlay.show{display:block}

        /* CARDS */
        .card{border:1px solid #e5e7eb;border-radius:10px;box-shadow:var(--card-shadow);background:#fff}
        .card-header{background:#fff;border-bottom:1px solid #f3f4f6;border-radius:10px 10px 0 0!important;padding:.8rem 1.2rem;font-weight:600;font-size:.875rem;color:#111}
        .card-footer{background:#fafafa;border-top:1px solid #f3f4f6;border-radius:0 0 10px 10px!important}

        /* STAT CARDS */
        .stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:1.15rem;box-shadow:var(--card-shadow);transition:box-shadow .2s,transform .2s}
        .stat-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.09);transform:translateY(-1px)}
        .stat-icon{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
        .stat-label{font-size:.72rem;color:#6b7280;font-weight:500}
        .stat-value{font-size:1.3rem;font-weight:700;color:#111;line-height:1.2;margin-top:.15rem}
        .stat-sub{font-size:.7rem;color:#9ca3af;margin-top:.1rem}

        /* TABLE */
        .table{font-size:.81rem}
        .table th{font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;background:#f9fafb;border-bottom:1px solid #e5e7eb;padding:.6rem 1rem}
        .table td{padding:.65rem 1rem;color:#374151;vertical-align:middle;border-color:#f3f4f6}
        .table-hover tbody tr:hover{background:#fafbff}

        /* BADGES */
        .badge-status-active   {background:#d1fae5;color:#065f46;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-inactive {background:#f3f4f6;color:#6b7280;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-pending  {background:#fef3c7;color:#92400e;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-approved {background:#dbeafe;color:#1e40af;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-closed   {background:#ede9fe;color:#5b21b6;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-defaulted{background:#fee2e2;color:#991b1b;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-matured  {background:#fef3c7;color:#92400e;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-dormant  {background:#e0f2fe;color:#0369a1;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-broken   {background:#fee2e2;color:#b91c1c;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-paid     {background:#065f46;color:#fff;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-partial  {background:#fef9c3;color:#854d0e;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-overdue  {background:#dc2626;color:#fff;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-blacklisted{background:#1f2937;color:#fff;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-processed{background:#065f46;color:#fff;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-status-draft    {background:#fef3c7;color:#92400e;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-membership-unpaid     {background:#fee2e2;color:#b91c1c;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-membership-partial    {background:#fef3c7;color:#92400e;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-membership-paid       {background:#d1fae5;color:#065f46;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}
        .badge-membership-liquidated {background:#e5e7eb;color:#374151;border-radius:20px;padding:.22em .7em;font-size:.7rem;font-weight:600}

        /* STEP FORM */
        .step-wizard{margin-bottom:1.5rem}
        .step-circles{display:flex;align-items:center}
        .step-item{display:flex;flex-direction:column;align-items:center;flex:1;min-width:0}
        .step-connector{flex:1;height:2px;background:#e5e7eb;margin-top:15px;transition:background .3s}
        .step-connector.done{background:#10b981}
        .step-circle{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;border:2px solid #d1d5db;background:#fff;color:#9ca3af;transition:all .2s;position:relative}
        .step-circle span{transition:opacity .2s}
        .step-circle i{position:absolute;opacity:0;transition:opacity .2s;font-size:.8rem}
        .step-circle.active{border-color:var(--accent);background:var(--accent);color:#fff}
        .step-circle.done{border-color:#10b981;background:#10b981;color:#fff}
        .step-circle.done span{opacity:0}
        .step-circle.done i{opacity:1}
        .step-label{font-size:.7rem;color:#6b7280;margin-top:.3rem;white-space:nowrap;text-align:center}
        .step-label.active{color:var(--accent);font-weight:600}
        .step-pane{display:none}
        .step-pane.active{display:block}

        /* FORMS */
        .form-control,.form-select{border-radius:7px;border-color:#d1d5db;font-size:.82rem;padding:.42rem .7rem}
        .form-control:focus,.form-select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.11)}
        .form-label{font-weight:500;font-size:.8rem;color:#374151;margin-bottom:.28rem}
        .input-group-text{background:#f9fafb;border-color:#d1d5db;font-size:.82rem}

        /* BUTTONS */
        .btn{border-radius:7px;font-size:.81rem;font-weight:500;padding:.42rem .85rem}
        .btn-primary{background:var(--accent);border-color:var(--accent)}
        .btn-primary:hover{background:var(--accent-hover);border-color:var(--accent-hover)}
        .btn-sm{padding:.28rem .6rem;font-size:.76rem}
        .btn-icon{width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:6px}

        /* PAGE HEADER */
        .page-header{margin-bottom:1.1rem;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.6rem}
        .page-header h4{margin:0;font-weight:700;font-size:1.05rem;color:#111}
        .page-header .sub{font-size:.75rem;color:#9ca3af;margin-top:.1rem}

        /* ALERTS */
        .alert{border-radius:8px;font-size:.82rem;border:none}
        .alert-success{background:#d1fae5;color:#065f46}
        .alert-danger{background:#fee2e2;color:#991b1b}
        .alert-info{background:#e0f2fe;color:#0369a1}
        .alert-warning{background:#fef3c7;color:#92400e}

        /* TELLER */
        .teller-card{background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);color:#fff;border-radius:12px;padding:1.4rem;margin-bottom:1rem}
        .teller-amount{font-size:2rem;font-weight:700;letter-spacing:-.5px}
        .teller-label{font-size:.72rem;opacity:.6;text-transform:uppercase;letter-spacing:.08em}
        .teller-btn{border-radius:10px;padding:.7rem 1.2rem;font-size:.875rem;font-weight:600;border:none;cursor:pointer;transition:all .15s}
        .teller-btn-deposit{background:#10b981;color:#fff}
        .teller-btn-deposit:hover{background:#059669}
        .teller-btn-withdraw{background:#ef4444;color:#fff}
        .teller-btn-withdraw:hover{background:#dc2626}

        /* MISC */
        .font-mono{font-family:'Consolas','Courier New',monospace}
        .text-muted{color:#9ca3af!important}
        a{color:var(--accent)}
        .divider{border-top:1px solid #f3f4f6;margin:.65rem 0}

        /* MOBILE */
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%)}
            .sidebar.open{transform:translateX(0)}
            .topbar,.main-wrap{left:0;margin-left:0}
            .topbar{left:0!important}
            .main-wrap{margin-left:0!important}
            .page-content{padding:1rem}
            .hide-sm{display:none!important}
        }
    </style>
    @stack('styles')
    <script>
    /* Step-form helper — in <head> so content scripts can call SF.init() immediately.
       All comparisons use > only (no <) to avoid HTML parser ambiguity. */
    window.SF={
        cur:1,tot:0,
        init:function(t){
            this.tot=t;
            var self=this;
            if(document.readyState==='loading'){
                document.addEventListener('DOMContentLoaded',function(){self.go(1);},{once:true});
            }else{
                this.go(1);
            }
        },
        go:function(n){
            this.cur=n;
            document.querySelectorAll('.step-pane').forEach(function(p,i){p.classList.toggle('active',i+1===n);});
            document.querySelectorAll('.step-circle').forEach(function(c,i){
                c.classList.remove('active','done');
                if(i+1===n){c.classList.add('active');}
                else if(n>i+1){c.classList.add('done');}
            });
            document.querySelectorAll('.step-label').forEach(function(l,i){l.classList.toggle('active',i+1===n);});
            document.querySelectorAll('.step-connector').forEach(function(l,i){l.classList.toggle('done',n>i+1);});
            var pb=document.getElementById('sfProgress');
            if(pb&&this.tot>1){pb.style.width=Math.round((n-1)/(this.tot-1)*100)+'%';}
        },
        next:function(){if(this.tot>this.cur){this.go(this.cur+1);}},
        prev:function(){if(this.cur>1){this.go(this.cur-1);}}
    };
    </script>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

{{-- SIDEBAR --}}
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        @php $orgLogo = \App\Models\SystemSetting::get('org_logo'); @endphp
        @if($orgLogo)
            <img src="{{ asset($orgLogo) }}" alt="Logo"
                 style="height:40px;max-width:140px;object-fit:contain;flex-shrink:0">
        @else
            <div class="brand-icon"><i class="bi bi-bank2"></i></div>
            <div style="overflow:hidden">
                <div class="brand-text">{{ \App\Models\SystemSetting::get('org_name','ElTech Finance') }}</div>
                <div class="brand-sub">Financial Management</div>
            </div>
        @endif
    </div>

    <div class="sidebar-scroll">

        <a href="{{ route('dashboard') }}" class="nav-link-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        @can('view clients')
        <div class="sidebar-section">Clients</div>
        <a href="{{ route('clients.index') }}" class="nav-link-item {{ request()->routeIs('clients.*') && !request()->routeIs('clients.shares.*') ? 'active' : '' }}">
            <i class="bi bi-people-fill"></i> Clients
        </a>
        @endcan

        @can('manage shares')
        @if(\App\Models\SystemSetting::get('shares_module_enabled', '1'))
        <a href="{{ route('shares.index') }}" class="nav-link-item {{ request()->routeIs('shares.*') ? 'active' : '' }}">
            <i class="bi bi-pie-chart-fill"></i> Member Shares
        </a>
        @endif
        @endcan

        @can('view groups')
        <a href="{{ route('groups.index') }}" class="nav-link-item {{ request()->routeIs('groups.*') ? 'active' : '' }}">
            <i class="bi bi-collection-fill"></i> Groups
        </a>
        @endcan

        @canany(['view savings-products', 'view savings'])
        <div class="sidebar-section">Savings</div>
        @can('view savings-products')
        <a href="{{ route('savings-products.index') }}" class="nav-link-item {{ request()->routeIs('savings-products.*') ? 'active' : '' }}">
            <i class="bi bi-grid-3x3-gap"></i> Savings Products
        </a>
        @endcan
        @can('view savings')
        <a href="{{ route('savings.index') }}" class="nav-link-item {{ request()->routeIs('savings.*') ? 'active' : '' }}">
            <i class="bi bi-piggy-bank-fill"></i> Savings Accounts
        </a>
        @endcan
        @endcanany

        @canany(['view loan-products', 'view loans'])
        <div class="sidebar-section">Loans</div>
        @can('view loan-products')
        <a href="{{ route('loan-products.index') }}" class="nav-link-item {{ request()->routeIs('loan-products.*') ? 'active' : '' }}">
            <i class="bi bi-grid-3x3-gap"></i> Loan Products
        </a>
        @endcan
        @can('view loans')
        <a href="{{ route('loans.index') }}" class="nav-link-item {{ request()->routeIs('loans.*') ? 'active' : '' }}">
            <i class="bi bi-cash-stack"></i> Loans
            @php $pendingLoans = \App\Models\Loan::where('status','pending')->count(); @endphp
            @if($pendingLoans > 0)
                <span class="badge bg-warning text-dark ms-auto rounded-pill" style="font-size:.62rem">{{ $pendingLoans }}</span>
            @endif
        </a>
        @endcan
        @endcanany

        @canany(['view fd-products', 'view fixed-deposits'])
        <div class="sidebar-section">Fixed Deposits</div>
        @can('view fd-products')
        <a href="{{ route('fd-products.index') }}" class="nav-link-item {{ request()->routeIs('fd-products.*') ? 'active' : '' }}">
            <i class="bi bi-grid-3x3-gap"></i> FD Products
        </a>
        @endcan
        @can('view fixed-deposits')
        <a href="{{ route('fixed-deposits.index') }}" class="nav-link-item {{ request()->routeIs('fixed-deposits.*') ? 'active' : '' }}">
            <i class="bi bi-safe-fill"></i> Fixed Deposits
            @php $maturedFDs = \App\Models\FixedDeposit::where('status','active')->where('maturity_date','<=',now()->toDateString())->count(); @endphp
            @if($maturedFDs > 0)
                <span class="badge bg-warning text-dark ms-auto rounded-pill" style="font-size:.62rem">{{ $maturedFDs }}</span>
            @endif
        </a>
        @endcan
        @endcanany

        <div class="sidebar-section">HR &amp; Payroll</div>
        @can('view employees')
        <a href="{{ route('employees.index') }}" class="nav-link-item {{ request()->routeIs('employees.*') ? 'active' : '' }}">
            <i class="bi bi-person-badge me-1"></i> Employees
        </a>
        @endcan
        @can('view payroll')
        <a href="{{ route('payroll.index') }}" class="nav-link-item {{ request()->routeIs('payroll.*') ? 'active' : '' }}">
            <i class="bi bi-cash-coin me-1"></i> Payroll
        </a>
        @endcan

        @canany(['view accounts', 'view transactions'])
        <div class="sidebar-section">Accounting</div>
        @can('view accounts')
        <a href="{{ route('accounts.index') }}" class="nav-link-item {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text"></i> Chart of Accounts
        </a>
        @endcan
        @can('view transactions')
        <a href="{{ route('transactions.index') }}" class="nav-link-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-left-right"></i> Journal Entries
        </a>
        @endcan
        @can('manage settings')
        <a href="{{ route('periods.index') }}" class="nav-link-item {{ request()->routeIs('periods.*') ? 'active' : '' }}">
            <i class="bi bi-calendar2-check"></i> Financial Periods
        </a>
        @endcan
        @endcanany

        @can('view reports')
        <div class="sidebar-section">Reports</div>
        <button class="nav-collapse-btn" data-bs-toggle="collapse" data-bs-target="#reportsMenu"
            aria-expanded="{{ request()->routeIs('reports.*') ? 'true' : 'false' }}">
            <i class="bi bi-bar-chart-line-fill"></i> Reports
            <i class="bi bi-chevron-right chevron"></i>
        </button>
        <div class="collapse nav-sub {{ request()->routeIs('reports.*') ? 'show' : '' }}" id="reportsMenu">
            <a href="{{ route('reports.trial-balance') }}"     class="nav-link-item {{ request()->routeIs('reports.trial-balance') ? 'active' : '' }}"><i class="bi bi-check2-square"></i> Trial Balance</a>
            <a href="{{ route('reports.income-statement') }}"  class="nav-link-item {{ request()->routeIs('reports.income-statement') ? 'active' : '' }}"><i class="bi bi-graph-up"></i> Income Statement</a>
            <a href="{{ route('reports.balance-sheet') }}"     class="nav-link-item {{ request()->routeIs('reports.balance-sheet') ? 'active' : '' }}"><i class="bi bi-building"></i> Balance Sheet</a>
            <a href="{{ route('reports.general-ledger') }}"    class="nav-link-item {{ request()->routeIs('reports.general-ledger') ? 'active' : '' }}"><i class="bi bi-journal-text"></i> General Ledger</a>
            <a href="{{ route('reports.loan-portfolio') }}"    class="nav-link-item {{ request()->routeIs('reports.loan-portfolio') ? 'active' : '' }}"><i class="bi bi-cash-stack"></i> Loan Portfolio</a>
            <a href="{{ route('reports.loan-aging') }}"        class="nav-link-item {{ request()->routeIs('reports.loan-aging') ? 'active' : '' }}"><i class="bi bi-clock-history"></i> Loan Aging</a>
            <a href="{{ route('reports.savings-balances') }}"  class="nav-link-item {{ request()->routeIs('reports.savings-balances') ? 'active' : '' }}"><i class="bi bi-piggy-bank"></i> Savings Balances</a>
            <a href="{{ route('reports.fd-maturity') }}"       class="nav-link-item {{ request()->routeIs('reports.fd-maturity') ? 'active' : '' }}"><i class="bi bi-safe"></i> FD Maturity</a>
            <a href="{{ route('reports.member-summary') }}"    class="nav-link-item {{ request()->routeIs('reports.member-summary') ? 'active' : '' }}"><i class="bi bi-people-fill"></i> Member Summary</a>
        </div>
        @endcan

        @canany(['manage branches', 'manage users', 'manage settings', 'manage backup'])
        <div class="sidebar-section">Administration</div>
        @can('manage branches')
        <a href="{{ route('branches.index') }}" class="nav-link-item {{ request()->routeIs('branches.*') ? 'active' : '' }}">
            <i class="bi bi-diagram-3-fill"></i> Branches
        </a>
        @endcan
        @can('manage users')
        <a href="{{ route('users.index') }}" class="nav-link-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <i class="bi bi-person-badge-fill"></i> Users & Roles
        </a>
        @endcan
        @role('super_admin')
        <a href="{{ route('roles.index') }}" class="nav-link-item {{ request()->routeIs('roles.*') ? 'active' : '' }}">
            <i class="bi bi-shield-shaded"></i> Roles &amp; Permissions
        </a>
        @endrole
        @can('manage settings')
        <a href="{{ route('settings.index') }}" class="nav-link-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <i class="bi bi-gear-fill"></i> System Settings
        </a>
        <a href="{{ route('audit.index') }}" class="nav-link-item {{ request()->routeIs('audit.*') ? 'active' : '' }}">
            <i class="bi bi-shield-lock-fill"></i> Audit Log
        </a>
        @endcan
        @can('manage backup')
        <a href="{{ route('backup.index') }}" class="nav-link-item {{ request()->routeIs('backup.*') ? 'active' : '' }}">
            <i class="bi bi-database-fill-down"></i> Database Backup
        </a>
        @endcan
        @endcanany

    </div>

    <div class="sidebar-footer">
        @auth
        @if(auth()->user()->hasAnyRole(['client','group_leader','group_member']))
        <a href="{{ route('choose-portal') }}" class="nav-link-item mb-1" style="color:rgba(255,255,255,.6);font-size:.75rem">
            <i class="bi bi-arrow-left-right"></i> Switch Portal
        </a>
        @endif
        <div class="d-flex align-items-center gap-2">
            <div style="width:28px;height:28px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:.67rem;font-weight:700;color:#fff;flex-shrink:0">
                {{ strtoupper(substr(auth()->user()->name,0,2)) }}
            </div>
            <div style="overflow:hidden;flex:1">
                <div style="color:#fff;font-size:.73rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ auth()->user()->name }}</div>
                <div style="color:rgba(255,255,255,.35);font-size:.66rem">{{ auth()->user()->role_name }}</div>
            </div>
        </div>
        @endauth
    </div>
</aside>

{{-- TOPBAR --}}
<header class="topbar" id="topbar">
    <button class="topbar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">@yield('breadcrumb')</ol>
    </nav>
    <div class="topbar-right">
        <span class="topbar-date hide-sm">{{ now()->format('D, d M Y') }}</span>
        @if(auth()->user()?->branch)
            <span class="badge bg-primary bg-opacity-10 text-primary hide-sm" style="font-size:.7rem">
                <i class="bi bi-diagram-3 me-1"></i>{{ auth()->user()->branch->name }}
            </span>
        @endif
        <div class="dropdown">
            <div class="topbar-user" data-bs-toggle="dropdown">
                <div class="avatar">{{ strtoupper(substr(auth()->user()?->name ?? 'U',0,2)) }}</div>
                <span class="hide-sm">{{ auth()->user()?->name }}</span>
                <i class="bi bi-chevron-down" style="font-size:.62rem;color:#9ca3af"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow" style="font-size:.82rem;min-width:180px">
                <li><span class="dropdown-item-text text-muted small">{{ auth()->user()?->role_name }}</span></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item" href="{{ route('settings.index') }}"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" data-no-block="1">
                        @csrf
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

{{-- MAIN --}}
<div class="main-wrap" id="mainWrap">
    <div class="page-content">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-3" role="alert">
                <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger mb-3">
                <div class="d-flex align-items-center gap-2 mb-1"><i class="bi bi-exclamation-triangle-fill"></i><strong>Please fix the following errors:</strong></div>
                <ul class="mb-0 ps-3 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
function toggleSidebar(){
    const s=document.getElementById('sidebar'),o=document.getElementById('sidebarOverlay');
    if(window.innerWidth<=768){s.classList.toggle('open');o.classList.toggle('show');}
    else{
        const w=document.getElementById('mainWrap'),t=document.getElementById('topbar');
        s.classList.toggle('collapsed');w.classList.toggle('full');t.classList.toggle('full');
        localStorage.setItem('sc',s.classList.contains('collapsed')?'1':'0');
    }
}
(function(){
    if(window.innerWidth>768&&localStorage.getItem('sc')==='1'){
        document.getElementById('sidebar').classList.add('collapsed');
        document.getElementById('mainWrap').classList.add('full');
        document.getElementById('topbar').classList.add('full');
    }
})();

// SF is defined in <head> — no duplicate needed here
</script>
@stack('scripts')
<script>
// Prevent duplicate form submissions — disable submit buttons on submit
document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.tagName !== 'FORM') return;
        if (form.dataset.noBlock === '1') return;
        // Catch explicit type="submit" AND buttons with no type (HTML default = submit)
        // Exclude type="button" (JS helpers) and type="reset"
        form.querySelectorAll(
            'button:not([type="button"]):not([type="reset"]), input[type="submit"]'
        ).forEach(function (btn) {
            btn.disabled = true;
            if (btn.tagName === 'BUTTON') {
                btn.dataset.orig = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving…';
            }
        });
    });
});
</script>
<script>
// Auto-init Tom Select on all .ts-select elements
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select.ts-select').forEach(function(el) {
        if (!el.tomselect) {
            new TomSelect(el, { allowEmptyOption: true, maxOptions: null });
        }
    });
});
// Helper so dynamic rows can also init a single element
function initTomSelect(el) {
    if (el && !el.tomselect) new TomSelect(el, { allowEmptyOption: true, maxOptions: null });
}
</script>
</body>
</html>
