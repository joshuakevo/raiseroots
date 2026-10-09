<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.app-install-head')
    <title>Sign In — {{ \App\Models\SystemSetting::get('org_name', 'Eltech Systems') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary:#0f2444; --accent:#2563eb; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; }

        /* Two halves, full height */
        .login-split { display: flex; min-height: 100vh; }
        .brand-side { flex: 1 1 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1.5rem; text-align: center; }
        .form-side  { flex: 1 1 50%; background: linear-gradient(135deg, var(--primary) 0%, #1a3a6e 100%); display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .brand-mark { width: 130px; height: auto; }
        .brand-name { font-size: 2rem; font-weight: 700; letter-spacing: .02em; color: #2b2f36; margin-top: 1rem; line-height: 1.15; }
        .brand-tagline { font-size: .9rem; font-weight: 700; letter-spacing: .14em; color: #1565c0; margin-top: .4rem; }
        .install-pill { margin-top: 2rem; background: transparent; border: 1px solid #dbe3ef; border-radius: 999px; color: var(--primary);
                        font-size: .8rem; font-weight: 600; padding: .5rem 1.1rem; transition: background .15s; }
        .install-pill:hover { background: #f1f5fb; color: var(--primary); }
        .brand-footer { color: #9ca3af; font-size: .72rem; margin-top: 3rem; }

        /* Sign-in card */
        .login-wrap { width: 100%; max-width: 420px; }
        .login-card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.35); overflow: hidden; }
        .login-header { background: var(--primary); padding: 1.25rem 2rem; text-align: center; }
        .login-logo { width: 46px; height: 46px; background: var(--accent); border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #fff; margin-bottom: .5rem; }
        .login-title { color: #fff; font-weight: 700; margin-bottom: 0; font-size: 1.1rem; }
        .login-subtitle { color: rgba(255,255,255,.55); font-size: .78rem; }
        .login-body { background: #fff; padding: 1.5rem 2rem; }
        .section-title { color: #6b7280; font-size: .8rem; font-weight: 600; text-align: center; margin-bottom: .85rem; }
        .form-label { font-size: .78rem; font-weight: 600; }
        .form-control { border-radius: 8px; border-color: #d1d5db; font-size: .875rem; }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .input-group-text { background: #f9fafb; border-color: #d1d5db; }
        .form-check-label { font-size: .8rem; }
        .btn-login { background: var(--accent); border: none; border-radius: 8px; padding: .65rem 1rem; font-weight: 600; font-size: .875rem; width: 100%; transition: background .15s; }
        .btn-login:hover { background: #1d4ed8; }

        /* Phones / narrow screens: stack, brand side compact */
        @media (max-width: 991.98px) {
            .login-split { flex-direction: column; }
            .brand-side { flex: 0 0 auto; padding: 1.75rem 1rem 1.5rem; }
            .brand-mark { width: 80px; }
            .brand-name { font-size: 1.4rem; margin-top: .6rem; }
            .install-pill { margin-top: 1rem; }
            .brand-footer { display: none; }
            .form-side { flex: 1 0 auto; }
        }

        /* Full-screen "signing in" loader */
        .login-loader { position: fixed; inset: 0; z-index: 2000; display: flex; flex-direction: column; align-items: center; justify-content: center;
                        background: rgba(15, 36, 68, .82); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
                        opacity: 0; visibility: hidden; transition: opacity .25s ease, visibility .25s; }
        .login-loader.show { opacity: 1; visibility: visible; }
        .loader-rings { position: relative; width: 128px; height: 128px; display: flex; align-items: center; justify-content: center; }
        .loader-rings::before, .loader-rings::after { content: ""; position: absolute; border-radius: 50%; border: 3px solid transparent; }
        .loader-rings::before { inset: 0; border-top-color: #60a5fa; border-right-color: #60a5fa; animation: loader-spin 1.1s linear infinite; }
        .loader-rings::after  { inset: 10px; border-bottom-color: rgba(255,255,255,.7); border-left-color: rgba(255,255,255,.7); animation: loader-spin-rev 1.6s linear infinite; }
        .loader-badge { width: 80px; height: 80px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center;
                        overflow: hidden; box-shadow: 0 6px 24px rgba(0,0,0,.25); animation: loader-pulse 1.6s ease-in-out infinite; }
        .loader-badge img { max-width: 72%; max-height: 72%; object-fit: contain; }
        .loader-badge i { font-size: 1.9rem; color: var(--accent); }
        .loader-text { color: #fff; font-weight: 600; margin-top: 1.4rem; font-size: 1rem; }
        .loader-sub { color: rgba(255,255,255,.65); font-size: .8rem; margin-top: .35rem; min-height: 1.2em; text-align: center; padding: 0 1rem; transition: opacity .3s; }
        .loader-dots::after { content: ""; animation: loader-dots 1.4s steps(4, end) infinite; }
        @keyframes loader-spin     { to { transform: rotate(360deg); } }
        @keyframes loader-spin-rev { to { transform: rotate(-360deg); } }
        @keyframes loader-pulse    { 0%, 100% { transform: scale(1); } 50% { transform: scale(.94); } }
        @keyframes loader-dots     { 0% { content: ""; } 25% { content: "."; } 50% { content: ".."; } 75% { content: "..."; } }
        @media (prefers-reduced-motion: reduce) {
            .loader-rings::before, .loader-rings::after, .loader-badge, .loader-dots::after { animation: none; }
        }
    </style>
</head>
<body>
@php
    $orgName   = \App\Models\SystemSetting::get('org_name', 'Eltech Systems');
    $brandMark = file_exists(public_path('images/eltech-mark.png')) ? asset('images/eltech-mark.png') : null;
@endphp

<div class="login-loader" id="loginLoader" role="status" aria-live="polite" aria-hidden="true">
    <div class="loader-rings">
        <div class="loader-badge">
            @if($brandMark)<img src="{{ $brandMark }}" alt="">@else<i class="bi bi-bank"></i>@endif
        </div>
    </div>
    <div class="loader-text">Signing you in<span class="loader-dots"></span></div>
    <div class="loader-sub" id="loaderSub">Checking your details</div>
</div>

<div class="login-split">
    {{-- Brand side --}}
    <div class="brand-side">
        @if($brandMark)<img src="{{ $brandMark }}" alt="ElTech Systems" class="brand-mark">@endif
        <div class="brand-name">ELTECH SYSTEMS</div>
        <div class="brand-tagline">ENGINEERED FOR IMPACT</div>
        <button type="button" class="install-pill" data-bs-toggle="modal" data-bs-target="#installAppModal">
            <i class="bi bi-qr-code me-1"></i>Install the app · scan QR code
        </button>
        <div class="brand-footer">Financial management for SACCOs &amp; microfinance</div>
    </div>

    {{-- Sign-in side --}}
    <div class="form-side">
        <div class="login-wrap">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo"><i class="bi bi-bank"></i></div>
                <div class="login-title">{{ $orgName }}</div>
                <div class="login-subtitle">Financial Management System</div>
            </div>
            <div class="login-body">
                <p class="section-title">Sign in to your account</p>

                @if($errors->any())
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label mb-1">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}"
                                   placeholder="you@example.com" required autofocus>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3 mt-2">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-login text-white">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </button>
                </form>
            </div>
        </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.install-app-modal')
<script>
(function () {
    var loader = document.getElementById('loginLoader');
    var sub    = document.getElementById('loaderSub');
    var timers = [];
    var buttonHtml = {};

    function setSub(text) {
        sub.style.opacity = 0;
        setTimeout(function () { sub.textContent = text; sub.style.opacity = 1; }, 200);
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches('form[action="{{ route('login') }}"]')) return;
        form.querySelectorAll('button:not([type="button"]):not([type="reset"]), input[type="submit"]').forEach(function (btn, i) {
            buttonHtml[i] = btn.innerHTML;
            btn.disabled = true;
            if (btn.tagName === 'BUTTON') {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Signing in…';
            }
        });
        loader.classList.add('show');
        loader.setAttribute('aria-hidden', 'false');
        sub.textContent = 'Checking your details';
        timers.push(setTimeout(function () { setSub('Loading your dashboard'); }, 2500));
        timers.push(setTimeout(function () { setSub('Almost there — the connection is a little slow'); }, 7000));
    });

    // Coming back with the Back button restores the page from cache - don't leave it stuck behind the loader.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        timers.forEach(clearTimeout);
        timers = [];
        loader.classList.remove('show');
        loader.setAttribute('aria-hidden', 'true');
        document.querySelectorAll('form button:not([type="button"]):not([type="reset"]), form input[type="submit"]').forEach(function (btn, i) {
            btn.disabled = false;
            if (buttonHtml[i] !== undefined) btn.innerHTML = buttonHtml[i];
        });
    });
})();
</script>
</body>
</html>
