<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.app-install-head')
    <title>Install — {{ $orgName }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        html, body { min-height: 100%; margin: 0; }
        body { background: linear-gradient(160deg, #0f2444 0%, #1a3a6e 100%); min-height: 100vh; display: flex; flex-direction: column;
               align-items: center; justify-content: center; padding: 24px 16px; font-family: 'Segoe UI', system-ui, sans-serif; }
        .install-card { background: #fff; border-radius: 22px; max-width: 380px; width: 100%; padding: 2rem 1.6rem 1.6rem; text-align: center;
                        box-shadow: 0 20px 60px rgba(0,0,0,.35); }
        .app-icon { width: 96px; height: 96px; border-radius: 22px; box-shadow: 0 8px 24px rgba(15,36,68,.3); }
        .org { font-weight: 700; font-size: 1.25rem; color: #0f2444; margin-top: 1rem; }
        .sub { color: #6b7280; font-size: .85rem; }
        .btn-install { background: #2563eb; color: #fff; border: none; border-radius: 14px; padding: .85rem 1rem; font-weight: 600; font-size: 1rem; width: 100%; }
        .btn-install:hover { background: #1d4ed8; color: #fff; }
        .btn-install:disabled { background: #93b4f5; color: #fff; }
        .steps { text-align: left; margin: 0; padding: 0; list-style: none; }
        .steps li { display: flex; gap: .75rem; align-items: flex-start; padding: .55rem 0; font-size: .9rem; color: #374151; }
        .steps li + li { border-top: 1px solid #f1f5f9; }
        .num { flex: 0 0 26px; height: 26px; border-radius: 50%; background: #e8effc; color: #2563eb; font-weight: 700; font-size: .8rem;
               display: inline-flex; align-items: center; justify-content: center; }
        .powered { color: rgba(255,255,255,.55); font-size: .75rem; margin-top: 1.25rem; }
        .continue { font-size: .85rem; }
    </style>
</head>
<body>
<div class="install-card">
    <img src="{{ route('app-icon', 192) }}?v={{ \App\Http\Controllers\AppManifestController::iconVersion() }}" alt="" class="app-icon">
    <div class="org">{{ $orgName }}</div>
    <div class="sub mb-4">Financial Management System</div>

    {{-- Android / Chrome / Edge: one-tap install once the browser offers it --}}
    <div id="androidBox" class="d-none">
        <button type="button" id="installBtn" class="btn-install" disabled>
            <span class="spinner-border spinner-border-sm me-2" id="installSpin"></span><span id="installLabel">Preparing install…</span>
        </button>
        <ol class="steps mt-1 d-none" id="androidSteps">
            <li><span class="num">1</span><span>Open your browser menu <i class="bi bi-three-dots-vertical"></i></span></li>
            <li><span class="num">2</span><span>Tap <strong>Install app</strong> or <strong>Add to Home screen</strong></span></li>
        </ol>
    </div>

    {{-- iPhone / iPad: Safari only, manual steps --}}
    <div id="iosBox" class="d-none">
        <div id="iosWarn" class="alert alert-warning small py-2 d-none"><i class="bi bi-exclamation-triangle me-1"></i>Open this page in <strong>Safari</strong> to install it on iPhone.</div>
        <ol class="steps">
            <li><span class="num">1</span><span>Tap the <strong>Share</strong> button <i class="bi bi-box-arrow-up"></i> at the bottom of Safari</span></li>
            <li><span class="num">2</span><span>Scroll down and tap <strong>Add to Home Screen</strong> <i class="bi bi-plus-square"></i></span></li>
            <li><span class="num">3</span><span>Tap <strong>Add</strong> in the top corner</span></li>
        </ol>
    </div>

    <div id="installedBox" class="alert alert-success small mb-0 d-none">
        <i class="bi bi-check-circle me-1"></i>Installed. Open {{ $orgName }} from your home screen or apps.
    </div>

    <div class="mt-4"><a href="{{ route('login') }}" class="continue">Continue in the browser instead</a></div>
</div>
<div class="powered">Powered by ElTech Systems</div>

<script>
(function () {
    // Already running as the installed app - nothing to do here.
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        window.location.replace('/');
        return;
    }

    var ua  = navigator.userAgent;
    var ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var show = function (id) { document.getElementById(id).classList.remove('d-none'); };
    var hide = function (id) { document.getElementById(id).classList.add('d-none'); };

    function installed() {
        hide('androidBox'); hide('iosBox'); show('installedBox');
    }
    window.addEventListener('appinstalled', installed);

    if (ios) {
        show('iosBox');
        if (/CriOS|FxiOS|EdgiOS/.test(ua)) show('iosWarn');
        return;
    }

    show('androidBox');
    var btn = document.getElementById('installBtn');

    function ready() {
        btn.disabled = false;
        document.getElementById('installSpin').classList.add('d-none');
        document.getElementById('installLabel').innerHTML = '<i class="bi bi-download me-2"></i>Install app';
    }
    if (window.__installPrompt) ready();
    document.addEventListener('install-available', ready);

    // No prompt from the browser? Fall back to the manual steps.
    setTimeout(function () {
        if (!window.__installPrompt) { btn.classList.add('d-none'); show('androidSteps'); }
    }, 3500);

    btn.addEventListener('click', function () {
        var p = window.__installPrompt;
        if (!p) return;
        p.prompt();
        p.userChoice.then(function (choice) {
            window.__installPrompt = null;
            if (choice.outcome === 'accepted') installed();
        });
    });
})();
</script>
</body>
</html>
