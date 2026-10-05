{{-- Lets phones add the system to the home screen with the organisation's logo and name (AppManifestController). --}}
<link rel="manifest" href="{{ route('app-manifest') }}">
<link rel="apple-touch-icon" href="{{ route('app-icon', 180) }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ route('app-icon', 192) }}">
<meta name="theme-color" content="#0f2444">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}">
