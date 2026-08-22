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

        <hr class="my-3">
        <p class="text-muted small mb-3">
            Clears cached config, routes, and views. If you've just edited <code>.env</code> (e.g. new API keys)
            and the app still isn't picking up the change, run this — a cached config from an earlier deploy
            can otherwise keep serving the old values indefinitely.
        </p>
        <form method="POST" action="{{ route('settings.clear-cache') }}">
            @csrf
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-eraser-fill me-2"></i>Clear Config Cache
            </button>
        </form>

        @if(session('cacheOutput'))
        <pre class="bg-dark text-light p-3 rounded mt-3 mb-0 small">{{ session('cacheOutput') }}</pre>
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

{{-- SMS Free Trial --}}
<div class="card mt-4 border-success">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-gift text-success"></i>
        <span>SMS Free Trial</span>
    </div>
    <div class="card-body">
        @php $usedTrial = (int) \App\Models\SystemSetting::get('sms_trial_used_count', 0); @endphp
        <p class="text-muted small mb-3">
            Every account gets {{ \App\Services\SmsSubscriptionService::TRIAL_LIMIT }} free SMS before a subscription
            is required. Currently used: <strong>{{ $usedTrial }} of {{ \App\Services\SmsSubscriptionService::TRIAL_LIMIT }}</strong>.
            Resetting sets this back to 0 — use for testing or to grant another free trial.
        </p>
        <form method="POST" action="{{ route('settings.reset-sms-trial') }}"
              onsubmit="return confirm('Reset the free SMS trial back to {{ \App\Services\SmsSubscriptionService::TRIAL_LIMIT }} SMS?')">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="bi bi-arrow-counterclockwise me-2"></i>Reset Free SMS Trial
            </button>
        </form>

        <hr class="my-3">
        <p class="text-muted small mb-3">
            Checks which MarzSMS/MarzPay <code>.env</code> keys are actually loaded (present or missing only —
            never shows the values). Use this before assuming the gateway rejected a request; if a key shows
            "MISSING" here, it either wasn't added to the server's <code>.env</code> or the config cache is
            stale — try Clear Config Cache above afterwards.
        </p>
        <form method="POST" action="{{ route('settings.sms-config-check') }}">
            @csrf
            <button type="submit" class="btn btn-outline-success">
                <i class="bi bi-clipboard2-check me-2"></i>Check MarzSMS / MarzPay Config
            </button>
        </form>

        @if(session('smsConfigReport'))
        <div class="mt-3">
            <table class="table table-sm table-bordered mb-0">
                <tbody>
                @foreach(session('smsConfigReport') as $key => $status)
                    <tr>
                        <th class="text-nowrap" style="width:280px"><code>{{ $key }}</code></th>
                        <td>
                            <span class="badge bg-{{ $status === 'SET' ? 'success' : 'danger' }}">{{ $status }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <hr class="my-3">
        <p class="text-muted small mb-3">
            Times a plain request to MarzPay's API from this server — no payment is triggered. Use this to
            check whether outbound calls to MarzPay are slow/timing out, or whether this host's
            <code>max_execution_time</code> would kill a real payment request before MarzPay responds.
        </p>
        <form method="POST" action="{{ route('settings.test-marzpay-connectivity') }}">
            @csrf
            <button type="submit" class="btn btn-outline-success">
                <i class="bi bi-hdd-network me-2"></i>Test MarzPay Connectivity
            </button>
        </form>

        @if(session('marzpayConnReport'))
        <div class="mt-3">
            <table class="table table-sm table-bordered mb-0">
                <tbody>
                @foreach(session('marzpayConnReport') as $label => $value)
                    <tr>
                        <th class="text-nowrap" style="width:280px">{{ $label }}</th>
                        <td class="text-break"><code>{{ $value }}</code></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Loan Collateral Categories --}}
<div class="card mt-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-shield-lock text-secondary"></i>
        <span>Loan Collateral Categories</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Options offered on the "Add Collateral" dropdown when creating or updating a loan. Hiding a category
            keeps it visible on loans that already used it — only new collateral won't be able to pick it anymore.
        </p>

        <table class="table table-sm align-middle mb-3">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-end">Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse(\App\Models\LoanCollateralCategory::orderBy('label')->get() as $cat)
                <tr class="{{ $cat->is_active ? '' : 'text-muted' }}">
                    <td>{{ $cat->label }}</td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('settings.collateral-categories.toggle', $cat) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $cat->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                {{ $cat->is_active ? 'Active' : 'Hidden' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" class="text-center text-muted py-3">No categories yet.</td></tr>
            @endforelse
            </tbody>
        </table>

        <form method="POST" action="{{ route('settings.collateral-categories.store') }}" class="d-flex gap-2">
            @csrf
            <input type="text" name="label" class="form-control form-control-sm" placeholder="e.g. Livestock" required maxlength="255">
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                <i class="bi bi-plus-lg me-1"></i>Add Category
            </button>
        </form>
    </div>
</div>

@role('super_admin')
{{-- One-time: fix loan disbursements mis-posted to the wrong receivable account --}}
<div class="card mt-4 border-danger">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-octagon text-danger"></i>
        <span>Fix Loan Receivable Postings</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            One-time corrective fix for loan disbursements that posted their receivable leg to the wrong
            GL account (Current Assets 1000, Loans Receivable — Emergency 1103, or Cash On Hand 1001) instead of
            Loan Receivables (1100). Corrects the account directly on each affected journal line — no reversal
            entry is created, nothing changes in Journal Entries, and the loan's schedule/history is untouched.
            <strong>Preview first</strong> to confirm exactly what will change.
        </p>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('settings.loan-receivable-fix-preview') }}">
                @csrf
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-eye me-2"></i>Preview
                </button>
            </form>
            <form method="POST" action="{{ route('settings.loan-receivable-fix-apply') }}"
                  onsubmit="return confirm('This will reverse 3 loan disbursement journals and re-post them to the correct account. Only proceed after reviewing the Preview output. Continue?')">
                @csrf
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-check2-circle me-2"></i>Apply Fix
                </button>
            </form>
        </div>

        @if(session('loanFixOutput'))
        <pre class="bg-dark text-light p-3 rounded mt-3 mb-0 small">{{ session('loanFixOutput') }}</pre>
        @endif
    </div>
</div>
@endrole
@endsection
