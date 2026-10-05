<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.app-install-head')
    <title>Choose Portal — {{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; }
        .portal-card {
            border: 2px solid #e5e7eb; border-radius: 16px; padding: 1.75rem 1.5rem;
            text-align: center; cursor: pointer; transition: all .18s;
            text-decoration: none; color: inherit; display: block; background: #fff;
            position: relative;
        }
        .portal-card:hover {
            border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,.1);
            transform: translateY(-2px); color: inherit;
        }
        .portal-card.active-portal {
            border-color: #2563eb; background: #eff6ff;
            box-shadow: 0 0 0 4px rgba(37,99,235,.12);
        }
        .portal-card.active-portal:hover { transform: translateY(-2px); }
        .active-badge {
            position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
            background: #2563eb; color: #fff; font-size: .65rem; font-weight: 700;
            padding: 2px 10px; border-radius: 20px; white-space: nowrap;
            letter-spacing: .04em;
        }
        .portal-card .icon {
            width: 60px; height: 60px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; margin: 0 auto 1rem;
        }
        .portal-card .title { font-weight: 700; font-size: 1rem; margin-bottom: .25rem; }
        .portal-card .sub   { font-size: .78rem; color: #6b7280; }
    </style>
</head>
<body>
<div style="width:100%;max-width:680px;padding:1rem">
    <div class="text-center mb-4">
        <div class="fw-bold fs-5" style="color:#0f2444">{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</div>
        <div class="text-muted small mt-1">Welcome back, <strong>{{ auth()->user()->name }}</strong>. Choose a portal to continue.</div>
    </div>

    @php
        $activePortal = session('active_portal');
    @endphp

    <div class="row g-3 justify-content-center">

        @if(auth()->user()->hasAnyRole(['super_admin', 'admin', 'cashier']))
        <div class="col-6 col-md-4">
            <a href="{{ route('dashboard') }}" class="portal-card {{ $activePortal === 'staff' ? 'active-portal' : '' }}">
                @if($activePortal === 'staff')<span class="active-badge"><i class="bi bi-check-circle-fill me-1"></i>Currently Here</span>@endif
                <div class="icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-speedometer2"></i></div>
                <div class="title">Staff Dashboard</div>
                <div class="sub">Admin &amp; operations</div>
            </a>
        </div>
        @endif

        @if(auth()->user()->hasRole('client'))
        <div class="col-6 col-md-4">
            <a href="{{ route('client-portal.dashboard') }}" class="portal-card {{ $activePortal === 'client' ? 'active-portal' : '' }}">
                @if($activePortal === 'client')<span class="active-badge"><i class="bi bi-check-circle-fill me-1"></i>Currently Here</span>@endif
                <div class="icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-circle"></i></div>
                <div class="title">Client Portal</div>
                <div class="sub">My savings, loans &amp; FDs</div>
            </a>
        </div>
        @endif

        @if(auth()->user()->hasRole('group_leader'))
        <div class="col-6 col-md-4">
            <a href="{{ route('group-portal.leader') }}" class="portal-card {{ $activePortal === 'leader' ? 'active-portal' : '' }}">
                @if($activePortal === 'leader')<span class="active-badge"><i class="bi bi-check-circle-fill me-1"></i>Currently Here</span>@endif
                <div class="icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-people-fill"></i></div>
                <div class="title">Group Leader</div>
                <div class="sub">Manage your group</div>
            </a>
        </div>
        @endif

        @if(auth()->user()->hasRole('group_member'))
        <div class="col-6 col-md-4">
            <a href="{{ route('group-portal.member') }}" class="portal-card {{ $activePortal === 'member' ? 'active-portal' : '' }}">
                @if($activePortal === 'member')<span class="active-badge"><i class="bi bi-check-circle-fill me-1"></i>Currently Here</span>@endif
                <div class="icon bg-info bg-opacity-10 text-info"><i class="bi bi-person-badge"></i></div>
                <div class="title">Group Member</div>
                <div class="sub">My group balance &amp; activity</div>
            </a>
        </div>
        @endif

    </div>

    <div class="text-center mt-4">
        <form method="POST" action="{{ route('logout') }}" class="d-inline" data-no-block="1">@csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-arrow-right me-1"></i>Sign out
            </button>
        </form>
    </div>
</div>
</body>
</html>
