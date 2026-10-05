<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.app-install-head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My Portal') — {{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #0f2444;
            --accent:  #2563eb;
            --sidebar-w: 230px;
            --topbar-h: 52px;
            --body-bg: #f0f2f5;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background: var(--body-bg); font-family: 'Segoe UI', system-ui, sans-serif; font-size: .875rem; }

        /* ── Top bar ─────────────────────────────────────────── */
        .cp-top {
            position: fixed; top: 0; left: 0; right: 0; height: var(--topbar-h); z-index: 200;
            background: var(--primary); color: #fff;
            display: flex; align-items: center; gap: 1rem; padding: 0 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
        }
        .cp-top .brand { font-weight: 700; font-size: .95rem; line-height: 1.1; }
        .cp-top .sub   { font-size: .68rem; opacity: .6; }
        .cp-top .avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--accent); display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .8rem; flex-shrink: 0;
        }

        /* ── Sidebar ─────────────────────────────────────────── */
        .cp-sidebar {
            position: fixed; top: var(--topbar-h); left: 0; bottom: 0;
            width: var(--sidebar-w); background: #fff;
            border-right: 1px solid #e5e7eb;
            overflow-y: auto; z-index: 100;
            display: flex; flex-direction: column;
        }
        .cp-sidebar .user-panel {
            padding: 1rem 1rem .75rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .cp-sidebar .user-panel .uname { font-weight: 700; font-size: .85rem; color: var(--primary); }
        .cp-sidebar .user-panel .urole { font-size: .7rem; color: #9ca3af; }
        .cp-sidebar .user-panel .linked-count {
            display: inline-block; margin-top: .35rem;
            font-size: .68rem; background: #eff6ff; color: var(--accent);
            border-radius: 999px; padding: .1rem .5rem; font-weight: 600;
        }

        .cp-sidebar .nav-section { padding: .6rem 1rem .2rem; font-size: .63rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; }

        .cp-sidebar a.cp-nav-item {
            display: flex; align-items: center; gap: .65rem;
            padding: .5rem 1rem; color: #374151; text-decoration: none;
            font-size: .82rem; transition: background .12s;
        }
        .cp-sidebar a.cp-nav-item i { font-size: 1rem; width: 18px; text-align: center; flex-shrink: 0; }
        .cp-sidebar a.cp-nav-item:hover  { background: #f9fafb; }
        .cp-sidebar a.cp-nav-item.active {
            background: #eff6ff; color: var(--accent); font-weight: 600;
            border-right: 3px solid var(--accent);
        }
        .cp-sidebar a.cp-nav-item .badge-dot {
            margin-left: auto; width: 8px; height: 8px; border-radius: 50%; background: #ef4444;
        }

        .cp-sidebar .sidebar-footer {
            margin-top: auto; padding: .75rem 1rem;
            border-top: 1px solid #f3f4f6;
        }

        /* ── Main content ────────────────────────────────────── */
        .cp-body { margin-left: var(--sidebar-w); margin-top: var(--topbar-h); min-height: calc(100vh - var(--topbar-h)); }
        .cp-main { padding: 1.5rem; max-width: 1050px; }

        /* ── Cards & misc ────────────────────────────────────── */
        .card { border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .card-header { font-weight: 600; font-size: .875rem; background: #fff; border-bottom: 1px solid #f3f4f6; }
        .table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; }

        .stat-card {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 1.1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        .stat-card .sc-label { font-size: .7rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .stat-card .sc-value { font-size: 1.45rem; font-weight: 700; line-height: 1.2; margin: .2rem 0; }
        .stat-card .sc-sub   { font-size: .72rem; color: #9ca3af; }
        .stat-card .sc-icon  {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
        }

        .badge-status-active    { background:#d1fae5; color:#065f46; }
        .badge-status-closed,
        .badge-status-matured   { background:#fee2e2; color:#991b1b; }
        .badge-status-pending   { background:#fef9c3; color:#854d0e; }
        .badge-status-disbursed { background:#dbeafe; color:#1e40af; }
        .badge-status-overdue   { background:#fee2e2; color:#991b1b; }
        .badge-status-written_off { background:#f3f4f6; color:#6b7280; }

        /* Switch account button */
        .btn-switch-account {
            background: #eff6ff; border: 1px solid #bfdbfe; color: var(--accent);
            font-size: .78rem; font-weight: 600; border-radius: 8px; padding: .4rem .75rem;
            transition: background .12s;
        }
        .btn-switch-account:hover { background: #dbeafe; color: var(--accent); }

        /* Account switcher modal cards */
        .account-card {
            border: 2px solid #e5e7eb; border-radius: 12px; padding: .9rem 1rem;
            cursor: pointer; transition: border-color .15s, box-shadow .15s;
            display: flex; align-items: center; gap: .85rem; position: relative;
            background: #fff;
        }
        .account-card:hover { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.08); }
        .account-card.selected { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .account-card .ac-avatar {
            width: 44px; height: 44px; border-radius: 50%; background: var(--primary);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 1rem; flex-shrink: 0;
        }
        .account-card .ac-name  { font-weight: 700; font-size: .88rem; color: #111827; }
        .account-card .ac-num   { font-size: .72rem; color: #6b7280; }
        .account-card .ac-badge {
            position: absolute; top: .5rem; right: .6rem;
            font-size: .6rem; background: var(--accent); color: #fff;
            border-radius: 999px; padding: .1rem .45rem; font-weight: 700;
        }

        /* Mobile */
        @media(max-width:767px){
            .cp-sidebar { transform: translateX(-100%); transition: transform .2s; }
            .cp-sidebar.open { transform: translateX(0); box-shadow: 4px 0 16px rgba(0,0,0,.15); }
            .cp-body { margin-left: 0; }
        }
    </style>
    @stack('styles')
</head>
<body>

@php
    // Resolve active client — include same-email clients so switcher always works
    $portalClients = auth()->user()->portalClients;
    if (auth()->user()->email) {
        $byEmail = \App\Models\Client::where('email', auth()->user()->email)->get();
        if ($byEmail->isNotEmpty()) {
            auth()->user()->portalClients()->syncWithoutDetaching($byEmail->pluck('id')->toArray());
            $portalClients = $portalClients->merge($byEmail)->unique('id')->values();
        }
    }
    $activeClientId     = session('portal_client_id') ?? $portalClients->first()?->id;
    $activePortalClient = $portalClients->firstWhere('id', $activeClientId) ?? $portalClients->first();
@endphp

{{-- ── Top bar ──────────────────────────────────────────────────── --}}
<header class="cp-top">
    <button class="btn btn-sm btn-outline-light d-md-none me-1 p-1 lh-1" id="sidebarToggle">
        <i class="bi bi-list fs-5"></i>
    </button>
    <i class="bi bi-bank2 fs-5 opacity-75"></i>
    <div>
        <div class="brand">{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</div>
        <div class="sub">Member Portal</div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <div class="avatar d-none d-sm-flex">{{ strtoupper(substr($activePortalClient?->name ?? auth()->user()->name, 0, 2)) }}</div>
        <div class="d-none d-sm-block text-end">
            <div class="small" style="line-height:1.2">{{ $activePortalClient?->name ?? auth()->user()->name }}</div>
            <div style="font-size:.68rem;opacity:.6">{{ $activePortalClient?->client_number }}</div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="d-inline">@csrf
            <button type="submit" class="btn btn-sm btn-outline-light px-2">
                <i class="bi bi-box-arrow-right me-1"></i><span class="d-none d-sm-inline">Sign out</span>
            </button>
        </form>
    </div>
</header>

{{-- ── Sidebar ──────────────────────────────────────────────────── --}}
<nav class="cp-sidebar" id="cpSidebar">
    <div class="user-panel">
        <div class="uname">{{ $activePortalClient?->name ?? auth()->user()->name }}</div>
        <div class="urole">{{ $activePortalClient?->client_number }}</div>
    </div>
    @if($portalClients->count() > 1)
    <div class="px-3 pt-2 pb-1">
        <button class="btn btn-sm w-100 btn-switch-account" data-bs-toggle="modal" data-bs-target="#switchAccountModal">
            <i class="bi bi-arrow-repeat me-1"></i>Switch Account
        </button>
    </div>
    @endif

    <div class="nav-section">Overview</div>
    <a href="{{ route('client-portal.dashboard') }}"
       class="cp-nav-item {{ request()->routeIs('client-portal.dashboard') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <div class="nav-section">My Finance</div>
    <a href="{{ route('client-portal.accounts') }}"
       class="cp-nav-item {{ request()->routeIs('client-portal.accounts') ? 'active' : '' }}">
        <i class="bi bi-wallet2"></i> My Statements
    </a>

    @if(auth()->user()->hasAnyRole(['group_member', 'group_leader']) || auth()->user()->hasAnyRole(['super_admin','admin','cashier']))
    <div class="nav-section">Other Portals</div>
    <a href="{{ route('choose-portal') }}" class="cp-nav-item">
        <i class="bi bi-arrow-left-right"></i> Switch Portal
    </a>
    @endif

    <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                <i class="bi bi-box-arrow-right me-1"></i>Sign Out
            </button>
        </form>
    </div>
</nav>

{{-- ── Body ─────────────────────────────────────────────────────── --}}
<div class="cp-body">
<main class="cp-main">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible py-2 mb-3 small">
            <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible py-2 mb-3 small">
            <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2 mb-3 small">{{ $errors->first() }}</div>
    @endif
    @yield('content')
</main>
</div>

{{-- ── Account switcher modal ──────────────────────────────────── --}}
@if($portalClients->count() > 1)
<div class="modal fade" id="switchAccountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Select Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-2">
                <p class="text-muted small mb-3">Choose the account you want to view.</p>
                <div class="row g-3" id="accountCards">
                    @foreach($portalClients as $pc)
                    <div class="col-6">
                        <div class="account-card {{ $pc->id == $activeClientId ? 'selected' : '' }}"
                             data-client-id="{{ $pc->id }}"
                             data-action="{{ route('client-portal.switch', $pc) }}">
                            @if($pc->id == $activeClientId)
                            <span class="ac-badge">Current</span>
                            @endif
                            <div class="ac-avatar">{{ strtoupper(substr($pc->name, 0, 2)) }}</div>
                            <div>
                                <div class="ac-name">{{ $pc->name }}</div>
                                <div class="ac-num">{{ $pc->client_number }}</div>
                                <div class="ac-num">{{ ucfirst($pc->status) }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="switchAccountForm" action="">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm px-4" id="confirmSwitchBtn" disabled>Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('cpSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', e => { e.stopPropagation(); sidebar.classList.toggle('open'); });
        document.addEventListener('click', e => {
            if (!sidebar.contains(e.target) && e.target !== toggle) sidebar.classList.remove('open');
        });
    }

    // Account switcher modal
    const cards      = document.querySelectorAll('.account-card');
    const switchForm = document.getElementById('switchAccountForm');
    const confirmBtn = document.getElementById('confirmSwitchBtn');
    let selectedAction = null;

    cards.forEach(card => {
        card.addEventListener('click', () => {
            cards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedAction = card.dataset.action;
            if (switchForm) switchForm.action = selectedAction;
            if (confirmBtn) confirmBtn.disabled = false;
        });
    });
</script>
@stack('scripts')
<script>
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.tagName !== 'FORM') return;
    if (form.dataset.noBlock === '1') return;
    form.querySelectorAll('button:not([type="button"]):not([type="reset"]), input[type="submit"]').forEach(function (btn) {
        btn.disabled = true;
        if (btn.tagName === 'BUTTON') {
            btn.dataset.orig = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving…';
        }
    });
});
</script>
</body>
</html>
