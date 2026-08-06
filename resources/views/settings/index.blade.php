@extends('layouts.app')
@section('title','System Settings')
@section('content')
<div class="mb-4">
    <h4 class="mb-0 fw-semibold">System Settings</h4>
    <p class="text-muted small mb-0">Configure application-wide parameters</p>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('settings.update') }}">
@csrf

<div class="row g-4">
@php
$groupIcons = [
    'general'  => ['icon'=>'bi-building','label'=>'Organisation','color'=>'primary'],
    'financial'=> ['icon'=>'bi-currency-dollar','label'=>'Financial','color'=>'success'],
    'mail'     => ['icon'=>'bi-envelope-fill','label'=>'Email / Mail','color'=>'info'],
    'modules'  => ['icon'=>'bi-toggles','label'=>'Modules','color'=>'warning'],
    'system'   => ['icon'=>'bi-gear','label'=>'System','color'=>'secondary'],
];
@endphp

@foreach($settings as $group => $items)
@php $meta = $groupIcons[$group] ?? ['icon'=>'bi-sliders','label'=>ucfirst($group),'color'=>'secondary'] @endphp
<div class="col-12">
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center gap-2 fw-semibold">
            <i class="bi {{ $meta['icon'] }} text-{{ $meta['color'] }}"></i>
            {{ $meta['label'] }} Settings
        </div>
        <div class="card-body">
            <div class="row g-3">
            @foreach($items as $setting)
            <div class="{{ in_array($setting->type,['textarea']) ? 'col-12' : 'col-sm-6 col-lg-4' }}">
                <label class="form-label fw-semibold">{{ $setting->label }}</label>

                @if($setting->type === 'boolean')
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox"
                               name="settings[{{ $setting->key }}]"
                               id="s_{{ $setting->key }}" value="1"
                               {{ $setting->value ? 'checked' : '' }}>
                        <label class="form-check-label text-muted" for="s_{{ $setting->key }}">
                            {{ $setting->value ? 'Enabled' : 'Disabled' }}
                        </label>
                    </div>
                @elseif($setting->type === 'textarea')
                    <textarea name="settings[{{ $setting->key }}]" class="form-control" rows="2">{{ $setting->value }}</textarea>
                @elseif($setting->type === 'number')
                    <input type="number" name="settings[{{ $setting->key }}]"
                           class="form-control" value="{{ $setting->value }}" step="any">
                @else
                    <input type="text" name="settings[{{ $setting->key }}]"
                           class="form-control" value="{{ $setting->value }}">
                @endif
            </div>
            @endforeach
            </div>
        </div>
    </div>
</div>
@endforeach

</div>

<div class="mt-4 d-flex align-items-center gap-3 flex-wrap">
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-floppy me-2"></i>Save All Settings
    </button>
</div>
</form>

{{-- Organisation Logo --}}
@php $logoPath = \App\Models\SystemSetting::get('org_logo'); @endphp
<div class="card mt-4 shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 fw-semibold">
        <i class="bi bi-image text-primary"></i> Organisation Logo
    </div>
    <div class="card-body">
        <div class="row align-items-center g-4">
            <div class="col-auto">
                @if($logoPath)
                    <img src="{{ asset($logoPath) }}" alt="Logo"
                         class="rounded border" style="height:80px;max-width:200px;object-fit:contain">
                @else
                    <div class="rounded border d-flex align-items-center justify-content-center bg-light text-muted"
                         style="height:80px;width:160px;font-size:12px">
                        <i class="bi bi-image me-1"></i> No logo set
                    </div>
                @endif
            </div>
            <div class="col">
                <form method="POST" action="{{ route('settings.logo') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <input type="file" name="logo" id="logoInput" class="form-control form-control-sm @error('logo') is-invalid @enderror"
                               accept=".png,.jpg,.jpeg,.svg,.webp" style="max-width:280px">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-upload me-1"></i>Upload Logo
                        </button>
                    </div>
                    <div class="form-text mt-1">PNG, JPG, SVG or WebP. Max 2 MB. Recommended: transparent PNG, at least 200×60 px.</div>
                    @error('logo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </form>
                @if($logoPath)
                <form method="POST" action="{{ route('settings.logo.remove') }}" class="mt-2"
                      onsubmit="return confirm('Remove the logo?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Remove Logo
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Database Migrations --}}
<div class="card mt-4 border-danger">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-database-gear text-danger"></i>
        <span>Database Migrations</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Runs <code>php artisan migrate</code> to apply any pending schema changes after a code deploy.
            This host has no shell access and the cPanel deploy button doesn't run migrations, so this is
            the only way to apply them. Safe to run any time — already-applied migrations are skipped.
        </p>
        <form method="POST" action="{{ route('settings.migrate') }}"
              onsubmit="return confirm('Run pending database migrations now?')">
            @csrf
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-play-fill me-2"></i>Run Migrations
            </button>
        </form>

        @if(session('migrateOutput'))
        <pre class="bg-dark text-light p-3 rounded mt-3 mb-0 small">{{ session('migrateOutput') }}</pre>
        @endif

        <hr class="my-3">
        <p class="text-muted small mb-3">
            Re-runs the Roles &amp; Permissions seeder — adds any new permissions/roles introduced by a code
            deploy (e.g. a new <code>manager</code> role) without touching existing users' role assignments.
            Safe to run more than once.
        </p>
        <form method="POST" action="{{ route('settings.run-roles-seeder') }}"
              onsubmit="return confirm('Re-run the Roles & Permissions seeder now?')">
            @csrf
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-person-lock me-2"></i>Run Roles &amp; Permissions Seeder
            </button>
        </form>

        @if(session('seederOutput'))
        <pre class="bg-dark text-light p-3 rounded mt-3 mb-0 small">{{ session('seederOutput') }}</pre>
        @endif
    </div>
</div>

{{-- Data Integrity --}}
<div class="card mt-4 border-warning">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-shield-check text-warning"></i>
        <span>Data Integrity &amp; Reconciliation</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Recalculates all denormalized/cached fields from their source of truth — savings balances,
            loan outstanding amounts, schedule statuses, share statuses, and membership fee statuses.
            Run this if you suspect any figures are out of sync.
        </p>
        <form method="POST" action="{{ route('settings.reconcile') }}"
              onsubmit="return confirm('Run reconciliation now? This will fix any inconsistencies across the database.')">
            @csrf
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-arrow-repeat me-2"></i>Run Reconciliation
            </button>
        </form>
    </div>
</div>

{{-- Storage Diagnostics --}}
<div class="card mt-4 border-info">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-hdd-network text-info"></i>
        <span>Storage Diagnostics</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Recreates the <code>public/storage</code> symlink and reports on the server's file/storage
            configuration. Run this if uploaded photos or files aren't displaying.
        </p>
        <form method="POST" action="{{ route('settings.storage-diagnostics') }}">
            @csrf
            <button type="submit" class="btn btn-info text-white">
                <i class="bi bi-search me-2"></i>Run Storage Diagnostics
            </button>
        </form>

        @if(session('storageReport'))
        <div class="mt-3">
            <table class="table table-sm table-bordered mb-0">
                <tbody>
                @foreach(session('storageReport') as $label => $value)
                    <tr>
                        <th class="text-nowrap" style="width:280px">{{ $label }}</th>
                        <td class="text-break"><code>{{ $value }}</code></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <hr class="my-3">
        <p class="text-muted small mb-3">
            One-time helper for the raiseroots_clone → raiseroots_clone_update migration: copies over any
            client photos or logos that exist on the old live folder but weren't carried across. Safe to run
            more than once &mdash; it skips files that already exist here.
        </p>
        <form method="POST" action="{{ route('settings.sync-legacy-uploads') }}">
            @csrf
            <button type="submit" class="btn btn-outline-info">
                <i class="bi bi-arrow-left-right me-2"></i>Sync Missing Uploads from raiseroots_clone
            </button>
        </form>
    </div>
</div>
@endsection
