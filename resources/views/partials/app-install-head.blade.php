{{-- Makes the system installable as an app (AppManifestController, public/sw.js). Included in every page <head>. --}}
@php
    $pwaOrg = \App\Models\SystemSetting::get('org_name', 'ElTech Finance');
    $pwaV   = \App\Http\Controllers\AppManifestController::iconVersion();
@endphp
<link rel="manifest" href="{{ route('app-manifest') }}">
<meta name="theme-color" content="#0f2444">
<link rel="icon" type="image/png" sizes="192x192" href="{{ route('app-icon', 192) }}?v={{ $pwaV }}">
<link rel="apple-touch-icon" href="{{ route('app-icon', 180) }}?v={{ $pwaV }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $pwaOrg }}">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
    }
    // Keep the browser's install prompt so our own "Install" buttons can trigger it.
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        window.__installPrompt = e;
        document.dispatchEvent(new Event('install-available'));
    });
</script>
