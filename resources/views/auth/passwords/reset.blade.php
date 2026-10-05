<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.app-install-head')
    <title>Set New Password — {{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        :root { --primary:#0f2444; --accent:#2563eb; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background: linear-gradient(135deg, var(--primary) 0%, #1a3a6e 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', system-ui, sans-serif; }
        .wrap { width: 100%; max-width: 420px; padding: 1rem; }
        .card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.35); overflow: hidden; }
        .card-header { background: var(--primary); padding: 1.25rem 2rem; text-align: center; }
        .card-body { background: #fff; padding: 1.75rem 2rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card border-0">
        <div class="card-header">
            <div style="color:#fff;font-weight:700;font-size:1.1rem;">{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</div>
            <div style="color:rgba(255,255,255,.55);font-size:.78rem;">Set your new password</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ $email ?? old('email') }}" required autofocus>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">New Password</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Set Password</button>
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
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving…';
        }
    });
});
</script>
</body>
</html>
