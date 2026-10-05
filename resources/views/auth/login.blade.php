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
    </style>
</head>
<body>
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
document.addEventListener('submit', function (e) {
    var form = e.target;
    form.querySelectorAll('button:not([type="button"]):not([type="reset"]), input[type="submit"]').forEach(function (btn) {
        btn.disabled = true;
        if (btn.tagName === 'BUTTON') {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Signing in…';
        }
    });
});
</script>
</body>
</html>
