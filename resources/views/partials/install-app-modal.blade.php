{{-- "Install app" pop-up: QR code to /install for phones, plus one-tap install on this device when the browser allows it. --}}
@php
    $iaOrg = \App\Models\SystemSetting::get('org_name', 'ElTech Finance');
    $iaUrl = route('app-install');
    $iaV   = \App\Http\Controllers\AppManifestController::iconVersion();
@endphp
<div class="modal fade" id="installAppModal" tabindex="-1" aria-labelledby="installAppTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 overflow-hidden" style="border-radius:16px">
            <div class="d-flex align-items-center gap-3 px-4 py-3" style="background:#0f2444">
                <img src="{{ route('app-icon', 192) }}?v={{ $iaV }}" alt="" width="44" height="44" style="border-radius:10px">
                <div class="flex-grow-1">
                    <div class="fw-bold text-white" id="installAppTitle">Install {{ $iaOrg }}</div>
                    <div style="color:rgba(255,255,255,.6);font-size:.78rem">Open it like an app from your phone or computer</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4 align-items-start">
                    <div class="col-sm-5 text-center">
                        <div id="installQr" class="d-inline-block p-2 bg-white border rounded-3" style="width:168px;height:168px"></div>
                        <div class="text-muted mt-2" style="font-size:.75rem">Scan with your phone camera to install</div>
                    </div>
                    <div class="col-sm-7">
                        <button type="button" id="installHereBtn" class="btn w-100 text-white fw-semibold mb-2 d-none" style="background:#2563eb;border-radius:10px">
                            <i class="bi bi-download me-2"></i>Install on this device
                        </button>
                        <div id="installDone" class="alert alert-success py-2 small mb-2 d-none"><i class="bi bi-check-circle me-1"></i>Installed</div>
                        <div class="small" style="line-height:1.5">
                            <div class="mb-2"><i class="bi bi-android2 me-1 text-success"></i><strong>Android:</strong> Chrome menu <i class="bi bi-three-dots-vertical"></i> → <em>Install app</em> / <em>Add to Home screen</em></div>
                            <div class="mb-2"><i class="bi bi-apple me-1"></i><strong>iPhone:</strong> Safari <i class="bi bi-box-arrow-up"></i> Share → <em>Add to Home Screen</em></div>
                            <div><i class="bi bi-laptop me-1 text-primary"></i><strong>Computer:</strong> click the install icon <i class="bi bi-window-plus"></i> in the address bar</div>
                        </div>
                    </div>
                </div>
                <div class="input-group input-group-sm mt-4">
                    <input type="text" class="form-control" id="installUrl" value="{{ $iaUrl }}" readonly>
                    <button class="btn btn-outline-secondary" type="button" id="installCopyBtn"><i class="bi bi-clipboard me-1"></i>Copy link</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var modal = document.getElementById('installAppModal');
    var btn   = document.getElementById('installHereBtn');
    var done  = document.getElementById('installDone');
    var qrDrawn = false;

    function refreshInstallButton() { btn.classList.toggle('d-none', !window.__installPrompt); }
    document.addEventListener('install-available', refreshInstallButton);

    modal.addEventListener('show.bs.modal', function () {
        refreshInstallButton();
        if (!qrDrawn && window.QRCode) {
            new QRCode(document.getElementById('installQr'), {
                text: @json($iaUrl), width: 150, height: 150, colorDark: '#0f2444', colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
            qrDrawn = true;
        }
    });

    function markInstalled() { done.classList.remove('d-none'); btn.classList.add('d-none'); }
    btn.addEventListener('click', function () {
        var p = window.__installPrompt;
        if (!p) return;
        p.prompt();
        p.userChoice.then(function (choice) {
            if (choice.outcome === 'accepted') markInstalled();
            window.__installPrompt = null;
            refreshInstallButton();
        });
    });
    window.addEventListener('appinstalled', markInstalled);

    document.getElementById('installCopyBtn').addEventListener('click', function () {
        var input = document.getElementById('installUrl'), b = this;
        var ok = function () { b.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied'; setTimeout(function () { b.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy link'; }, 1800); };
        if (navigator.clipboard) navigator.clipboard.writeText(input.value).then(ok, function () { input.select(); document.execCommand('copy'); ok(); });
        else { input.select(); document.execCommand('copy'); ok(); }
    });
})();
</script>
