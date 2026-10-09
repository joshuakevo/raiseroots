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
        body { background: linear-gradient(135deg, var(--primary) 0%, #1a3a6e 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', system-ui, sans-serif; }
        .login-wrap { width: 100%; max-width: 420px; padding: 1rem; }
        .login-card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.35); overflow: hidden; }
        .login-header { background: var(--primary); padding: 1.25rem 2rem; text-align: center; }
        .login-logo { width: 46px; height: 46px; background: var(--accent); border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem; color: #fff; margin-bottom: .5rem; }
        .login-body { background: #fff; padding: 1.5rem 2rem; }
        .login-title { color: #fff; font-weight: 700; margin-bottom: 0; font-size: 1.1rem; }
        .login-subtitle { color: rgba(255,255,255,.55); font-size: .78rem; }
        .section-title { color: #6b7280; font-size: .8rem; font-weight: 600; text-align: center; margin-bottom: .85rem; }
        .form-label { font-size: .78rem; font-weight: 600; }
        .form-control { border-radius: 8px; border-color: #d1d5db; font-size: .875rem; }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .input-group-text { background: #f9fafb; border-color: #d1d5db; }
        .form-check-label { font-size: .8rem; }
        .btn-login { background: var(--accent); border: none; border-radius: 8px; padding: .65rem 1rem; font-weight: 600; font-size: .875rem; width: 100%; transition: background .15s; }
        .btn-login:hover { background: #1d4ed8; }
        .hint-text { color: #9ca3af; font-size: .73rem; text-align: center; }

        /* Full-screen "signing in" loader, shown on submit */
        .login-loader { position: fixed; inset: 0; z-index: 2000; display: flex; flex-direction: column; align-items: center; justify-content: center;
                        background: rgba(15, 36, 68, .72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
                        opacity: 0; visibility: hidden; transition: opacity .25s ease, visibility .25s; }
        .login-loader.show { opacity: 1; visibility: visible; }
        .loader-ring { position: relative; width: 104px; height: 104px; display: flex; align-items: center; justify-content: center; }
        .loader-ring::before { content: ""; position: absolute; inset: 0; border-radius: 50%;
                               border: 4px solid rgba(255,255,255,.15); border-top-color: #60a5fa; border-right-color: #60a5fa;
                               animation: loader-spin 1s linear infinite; }
        .loader-badge { width: 72px; height: 72px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center;
                        overflow: hidden; box-shadow: 0 6px 24px rgba(0,0,0,.25); animation: loader-pulse 1.6s ease-in-out infinite; }
        .loader-badge img { max-width: 78%; max-height: 78%; object-fit: contain; }
        .loader-badge i { font-size: 1.9rem; color: var(--accent); }
        .loader-text { color: #fff; font-weight: 600; margin-top: 1.25rem; font-size: 1rem; letter-spacing: .01em; }
        .loader-sub { color: rgba(255,255,255,.65); font-size: .8rem; margin-top: .35rem; min-height: 1.2em; text-align: center; padding: 0 1rem; transition: opacity .3s; }
        .loader-dots::after { content: ""; animation: loader-dots 1.4s steps(4, end) infinite; }
        @keyframes loader-spin  { to { transform: rotate(360deg); } }
        @keyframes loader-pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(.94); } }
        @keyframes loader-dots  { 0% { content: ""; } 25% { content: "."; } 50% { content: ".."; } 75% { content: "..."; } }
        @media (prefers-reduced-motion: reduce) {
            .loader-ring::before, .loader-badge, .loader-dots::after { animation: none; }
        }
    </style>
</head>
<body>
@php $loginLogo = \App\Models\SystemSetting::get('org_logo'); @endphp
<div class="login-loader" id="loginLoader" role="status" aria-live="polite" aria-hidden="true">
    <div class="loader-ring">
        <div class="loader-badge">
            @if($loginLogo && file_exists(public_path($loginLogo)))
                <img src="{{ asset($loginLogo) }}" alt="">
            @else
                <i class="bi bi-bank"></i>
            @endif
        </div>
    </div>
    <div class="loader-text">Signing you in<span class="loader-dots"></span></div>
    <div class="loader-sub" id="loaderSub">Checking your details</div>
</div>

<div class="login-wrap">
<div class="login-card">
    <div class="login-header">
        <div class="login-logo"><i class="bi bi-bank"></i></div>
        <div class="login-title">{{ \App\Models\SystemSetting::get('org_name', 'Eltech Systems') }}</div>
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
        timers.push(setTimeout(function () { setSub('Still working — this can take a moment on a slow connection'); }, 7000));
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
