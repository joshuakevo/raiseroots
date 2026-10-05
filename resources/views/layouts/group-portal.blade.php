<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.app-install-head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Group Portal') — {{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary: #0f2444; --accent: #2563eb; --body-bg: #f0f2f5; --sidebar-w: 220px; }
        * { box-sizing: border-box; }
        body { background: var(--body-bg); font-family: 'Segoe UI', system-ui, sans-serif; font-size: .875rem; margin: 0; }

        /* Top bar */
        .gp-top { background: var(--primary); color: #fff; padding: .75rem 1.25rem;
                  display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; position: sticky; top: 0; z-index: 100; }
        .gp-top .brand { font-weight: 700; font-size: .95rem; }
        .gp-top .sub   { font-size: .7rem; opacity: .65; }

        /* Sidebar */
        .gp-sidebar { width: var(--sidebar-w); min-height: calc(100vh - 48px); background: #fff;
                      border-right: 1px solid #e5e7eb; padding: 1rem 0; flex-shrink: 0; }
        .gp-sidebar .group-info { padding: .75rem 1rem 1rem; border-bottom: 1px solid #f3f4f6; margin-bottom: .5rem; }
        .gp-sidebar .group-info .gname { font-weight: 700; font-size: .875rem; color: var(--primary); }
        .gp-sidebar .group-info .gnum  { font-size: .7rem; color: #9ca3af; }
        .gp-sidebar .nav-label { font-size: .65rem; font-weight: 700; text-transform: uppercase;
                                  letter-spacing: .08em; color: #9ca3af; padding: .5rem 1rem .25rem; }
        .gp-sidebar a.nav-item { display: flex; align-items: center; gap: .6rem; padding: .5rem 1rem;
                                  color: #374151; text-decoration: none; font-size: .8rem; border-radius: 0; transition: background .15s; }
        .gp-sidebar a.nav-item:hover  { background: #f3f4f6; }
        .gp-sidebar a.nav-item.active { background: #eff6ff; color: var(--accent); font-weight: 600;
                                         border-right: 3px solid var(--accent); }
        .gp-sidebar a.nav-item i { font-size: .95rem; width: 16px; text-align: center; }

        /* Body layout */
        .gp-body { display: flex; min-height: calc(100vh - 48px); }
        .gp-main { flex: 1; padding: 1.25rem; min-width: 0; }

        /* Cards */
        .card { border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .card-header { font-weight: 600; font-size: .875rem; border-bottom: 1px solid #f3f4f6; background: #fff; }
        .table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; }

        /* Hamburger toggle for mobile */
        @media(max-width:767px) {
            .gp-sidebar { display: none; position: fixed; top: 48px; left: 0; height: calc(100vh - 48px);
                          z-index: 99; box-shadow: 2px 0 8px rgba(0,0,0,.15); }
            .gp-sidebar.open { display: block; }
        }
    </style>
    @stack('styles')
</head>
<body>

{{-- Top bar --}}
<header class="gp-top">
    @auth
    <button class="btn btn-sm btn-outline-light d-md-none" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>
    @endauth
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-collection-fill fs-5"></i>
        <div>
            <div class="brand">{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</div>
            <div class="sub">Group savings portal</div>
        </div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="small opacity-75 d-none d-sm-inline">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}" class="d-inline">@csrf
            <button type="submit" class="btn btn-sm btn-outline-light">Sign out</button>
        </form>
    </div>
</header>

<div class="gp-body">

{{-- Sidebar --}}
@auth
<nav class="gp-sidebar" id="gpSidebar">
    @isset($group)
    <div class="group-info">
        <div class="gname">{{ $group->name }}</div>
        <div class="gnum">{{ $group->group_number }}</div>
    </div>
    @endisset

    @if(auth()->user()->hasRole('group_leader'))
    <div class="nav-label">Navigation</div>
    <a href="{{ route('group-portal.leader') }}" class="nav-item {{ request()->routeIs('group-portal.leader') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="{{ route('group-portal.leader.members') }}" class="nav-item {{ request()->routeIs('group-portal.leader.members') ? 'active' : '' }}">
        <i class="bi bi-people-fill"></i> All Members
    </a>
    @elseif(auth()->user()->hasRole('group_member'))
    <div class="nav-label">Navigation</div>
    <a href="{{ route('group-portal.member') }}" class="nav-item {{ request()->routeIs('group-portal.member') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="{{ route('group-portal.member.statement') }}" class="nav-item {{ request()->routeIs('group-portal.member.statement') ? 'active' : '' }}">
        <i class="bi bi-receipt"></i> My Statements
    </a>
    @endif

    @php
        $portalCount = 0;
        if(auth()->user()->hasAnyRole(['super_admin','admin','cashier'])) $portalCount++;
        if(auth()->user()->hasRole('client')) $portalCount++;
        if(auth()->user()->hasRole('group_leader')) $portalCount++;
        if(auth()->user()->hasRole('group_member')) $portalCount++;
    @endphp
    @if($portalCount > 1)
    <div class="nav-label">Other Portals</div>
    <a href="{{ route('choose-portal') }}" class="nav-item">
        <i class="bi bi-arrow-left-right"></i> Switch Portal
    </a>
    @endif
</nav>
@endauth

{{-- Main content --}}
<main class="gp-main">
    @if(session('success'))
        <div class="alert alert-success py-2 mb-3">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2 mb-3 small">{{ $errors->first() }}</div>
    @endif
    @yield('content')
</main>

</div>{{-- end gp-body --}}

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('gpSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', e => {
            if (!sidebar.contains(e.target) && e.target !== toggle) sidebar.classList.remove('open');
        });
    }
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
