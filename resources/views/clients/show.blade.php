@extends('layouts.app')
@section('title', $client->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">Clients</a></li>
    <li class="breadcrumb-item active">{{ $client->name }}</li>
@endsection
@section('content')
@if($client->isGroup() && $client->group)
<div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div><i class="bi bi-collection-fill me-2"></i> This client is a <strong>group</strong>. Member savings and postings are managed under Groups.</div>
    @can('view groups')
    <a href="{{ route('groups.show', $client->group) }}" class="btn btn-sm btn-primary">Open group</a>
    @endcan
</div>
@endif
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        @if($client->photo)
        <img src="{{ asset($client->photo) }}" alt="Photo"
             class="rounded-circle" style="height:56px;width:56px;object-fit:cover;border:2px solid #dee2e6;cursor:pointer"
             role="button" data-bs-toggle="modal" data-bs-target="#clientPhotoModal">
        <div class="modal fade" id="clientPhotoModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-transparent border-0">
                    <button type="button" class="btn-close btn-close-white ms-auto mb-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    <img src="{{ asset($client->photo) }}" alt="Photo" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
        @else
        <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center"
             style="height:56px;width:56px;font-size:1.4rem;color:#6c757d">
            <i class="bi bi-person-fill"></i>
        </div>
        @endif
        <div>
            <h4 class="fw-bold mb-0">{{ $client->name }}</h4>
            <span class="font-monospace text-muted">{{ $client->client_number }}</span>
        </div>
    </div>
    <div class="d-flex gap-2">
        @can('create loans')
        <a href="{{ route('loans.create', ['client_id'=>$client->id]) }}" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> New Loan</a>
        @endcan
        @can('create savings')
        <a href="{{ route('savings.create', ['client_id'=>$client->id]) }}" class="btn btn-info btn-sm text-white"><i class="bi bi-plus"></i> Savings</a>
        @endcan
        @can('create fixed-deposits')
        <a href="{{ route('fixed-deposits.create', ['client_id'=>$client->id]) }}" class="btn btn-secondary btn-sm"><i class="bi bi-plus"></i> Fixed Deposit</a>
        @endcan
        @can('edit clients')
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        @endcan
        @can('delete clients')
        <button type="button" class="btn btn-outline-danger btn-sm"
                data-bs-toggle="modal" data-bs-target="#deleteClientModal">
            <i class="bi bi-trash"></i> Delete
        </button>
        @endcan
    </div>
</div>

@can('delete clients')
@php
    $deleteBlocks = [];
    $activeLoansCount = $client->loans()->whereIn('status', ['active','pending','defaulted'])->count();
    if ($activeLoansCount) $deleteBlocks[] = "{$activeLoansCount} active/pending loan(s)";
    $activeSavingsCount = $client->savingsAccounts()->whereIn('status', ['active','dormant'])->count();
    if ($activeSavingsCount) $deleteBlocks[] = "{$activeSavingsCount} savings account(s)";
    $activeFdCount = $client->fixedDeposits()->where('status','active')->count();
    if ($activeFdCount) $deleteBlocks[] = "{$activeFdCount} active fixed deposit(s)";
    $activeSharesCount = $client->shares()->whereNotIn('status',['liquidated'])->where('amount_paid', '>', 0)->count();
    if ($activeSharesCount) $deleteBlocks[] = "{$activeSharesCount} share record(s) with paid-in capital";
@endphp
<div class="modal fade" id="deleteClientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if($deleteBlocks)
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-lock-fill me-2"></i><strong>Cannot delete this client.</strong>
                        <p class="mb-1 mt-1 small">Please close or transfer the following first:</p>
                        <ul class="mb-0 small ps-3">
                            @foreach($deleteBlocks as $block)
                                <li>{{ $block }}</li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p>Are you sure you want to delete <strong>{{ $client->name }}</strong> ({{ $client->client_number }})?</p>
                    <p class="text-muted small mb-0">This will permanently remove the client record.</p>
                @endif
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                @if(!$deleteBlocks)
                <form method="POST" action="{{ route('clients.destroy', $client) }}" data-no-block="1">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Yes, Delete
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endcan

{{-- Summary stats --}}
<div class="row g-2 mb-4">
    <div class="col-4">
        <div class="stat-card text-center">
            <div class="text-muted small">Active Loans</div>
            <div class="fw-bold fs-4 text-primary">{{ $client->activeLoans->count() }}</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card text-center">
            <div class="text-muted small">Savings Balance</div>
            <div class="fw-bold fs-5">{{ number_format($client->savingsAccounts->sum('balance'), $dp) }}</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card text-center">
            <div class="text-muted small">Fixed Deposits</div>
            <div class="fw-bold fs-4">{{ $client->fixedDeposits->where('status','active')->count() }}</div>
        </div>
    </div>
</div>

{{-- Details Cards --}}
<div class="row g-3 mb-4">
    {{-- Personal --}}
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Personal Information</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    @if($client->gender)
                    <dt class="col-5 fw-normal text-muted">Gender</dt><dd class="col-7">{{ ucfirst($client->gender) }}</dd>
                    @endif
                    @if($client->date_of_birth)
                    <dt class="col-5 fw-normal text-muted">Date of Birth</dt><dd class="col-7">{{ $client->date_of_birth->format('d M Y') }}</dd>
                    @endif
                    @if($client->marital_status)
                    <dt class="col-5 fw-normal text-muted">Marital Status</dt><dd class="col-7">{{ ucfirst($client->marital_status) }}</dd>
                    @endif
                    @if($client->nationality)
                    <dt class="col-5 fw-normal text-muted">Nationality</dt><dd class="col-7">{{ $client->nationality }}</dd>
                    @endif
                    @if($client->id_number)
                    <dt class="col-5 fw-normal text-muted">ID / Passport</dt><dd class="col-7 font-monospace">{{ $client->id_number }}</dd>
                    @endif
                    <dt class="col-5 fw-normal text-muted">Status</dt>
                    <dd class="col-7"><span class="badge badge-status-{{ $client->status }}">{{ ucfirst($client->status) }}</span></dd>
                    <dt class="col-5 fw-normal text-muted">Member Since</dt>
                    <dd class="col-7">{{ $client->joining_date ? $client->joining_date->format('d M Y') : $client->created_at->format('d M Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Contact --}}
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Contact Information</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 fw-normal text-muted">Phone</dt><dd class="col-7">{{ $client->phone ?? '—' }}</dd>
                    @if($client->alt_phone)
                    <dt class="col-5 fw-normal text-muted">Alt. Phone</dt><dd class="col-7">{{ $client->alt_phone }}</dd>
                    @endif
                    <dt class="col-5 fw-normal text-muted">Email</dt><dd class="col-7 text-break">{{ $client->email ?? '—' }}</dd>
                    <dt class="col-5 fw-normal text-muted">Address</dt><dd class="col-7">{{ $client->address ?? '—' }}</dd>
                    @if($client->district)
                    <dt class="col-5 fw-normal text-muted">District</dt><dd class="col-7">{{ $client->district }}</dd>
                    @endif
                    @if($client->village)
                    <dt class="col-5 fw-normal text-muted">Village/Parish</dt><dd class="col-7">{{ $client->village }}</dd>
                    @endif
                    @if($client->postal_address)
                    <dt class="col-5 fw-normal text-muted">Postal</dt><dd class="col-7">{{ $client->postal_address }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    {{-- Employment & Membership --}}
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Employment &amp; Membership</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    @if($client->employment_status)
                    <dt class="col-6 fw-normal text-muted">Employment</dt>
                    <dd class="col-6">{{ ucfirst(str_replace('_',' ',$client->employment_status)) }}</dd>
                    @endif
                    @if($client->purpose_of_joining)
                    <dt class="col-6 fw-normal text-muted">Purpose</dt>
                    <dd class="col-6">{{ ucfirst(str_replace('_',' ',$client->purpose_of_joining)) }}</dd>
                    @endif
                    @if($client->expected_monthly_savings)
                    <dt class="col-6 fw-normal text-muted">Expected Savings</dt>
                    <dd class="col-6">{{ number_format($client->expected_monthly_savings, $dp) }}/mo</dd>
                    @endif
                    @if($client->loan_interest !== null)
                    <dt class="col-6 fw-normal text-muted">Loan Interest</dt>
                    <dd class="col-6">{{ $client->loan_interest ? 'Yes' : 'No' }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    {{-- Next of Kin --}}
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Next of Kin</div>
            <div class="card-body">
                @if($client->next_of_kin_name)
                <dl class="row mb-0 small">
                    <dt class="col-5 fw-normal text-muted">Name</dt><dd class="col-7">{{ $client->next_of_kin_name }}</dd>
                    @if($client->next_of_kin_relationship)
                    <dt class="col-5 fw-normal text-muted">Relationship</dt><dd class="col-7">{{ $client->next_of_kin_relationship }}</dd>
                    @endif
                    @if($client->next_of_kin_phone)
                    <dt class="col-5 fw-normal text-muted">Phone</dt><dd class="col-7">{{ $client->next_of_kin_phone }}</dd>
                    @endif
                    @if($client->next_of_kin_address)
                    <dt class="col-5 fw-normal text-muted">Address</dt><dd class="col-7">{{ $client->next_of_kin_address }}</dd>
                    @endif
                </dl>
                @else
                <span class="text-muted small">No next of kin recorded.</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Preferences & System Info --}}
<div class="card mb-4">
    <div class="card-header">Preferences &amp; Registration Info</div>
    <div class="card-body">
        <div class="row small">
            <div class="col-md-3">
                <span class="text-muted">Preferred Communication</span><br>
                <strong>{{ $client->preferred_communication ? ucfirst(str_replace('_',' ',$client->preferred_communication)) : '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted">Date Joined</span><br>
                <strong>{{ $client->joining_date ? $client->joining_date->format('d M Y') : $client->created_at->format('d M Y') }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted">Registered By</span><br>
                <strong>{{ $client->createdBy->name ?? '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted">Relationship Manager</span><br>
                <strong>{{ $client->relationship_manager_name ?? '—' }}</strong>
                @if(!$client->relationship_manager_id)
                    <span class="badge bg-light text-muted border ms-1" style="font-size:.65rem">default</span>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Membership Fee & Shares -->
@php
    $_sharesOn    = \App\Models\SystemSetting::get('shares_module_enabled', '1');
    $_membershipOn = \App\Models\SystemSetting::get('membership_fee_module_enabled', '1');
@endphp
@if($_membershipOn || $_sharesOn)
<div class="row g-3 mb-3">

    {{-- Membership Fee Card --}}
    @if($_membershipOn)
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge me-1"></i> Membership Fee</span>
                <span class="badge badge-membership-{{ $client->membership_fee_status }}">{{ ucfirst($client->membership_fee_status) }}</span>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <div class="text-muted small">Required</div>
                        <div class="fw-bold">{{ number_format($client->membership_fee, 0) }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">Paid</div>
                        <div class="fw-bold text-success">{{ number_format($client->membership_fee_paid, 0) }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">Balance</div>
                        <div class="fw-bold {{ $client->membership_fee_balance > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($client->membership_fee_balance, 0) }}
                        </div>
                    </div>
                </div>
                @if($client->membership_fee_status !== 'paid')
                <button class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#membershipModal">
                    <i class="bi bi-cash me-1"></i>Record Payment
                </button>
                @else
                <div class="text-center text-success small"><i class="bi bi-check-circle-fill me-1"></i>Fully Paid</div>
                @endif
            </div>
        </div>
    </div>
    @endif {{-- membership_fee_module_enabled --}}

    {{-- Shares Card --}}
    @if($_sharesOn)
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-1">
                <span><i class="bi bi-pie-chart me-1"></i> Member Shares</span>
                <div class="d-flex gap-1">
                    @can('manage shares')
                    <a href="{{ route('clients.shares.statement', $client) }}" class="btn btn-sm btn-outline-secondary py-0" title="Share Statement">
                        <i class="bi bi-file-text"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addShareModal">
                        <i class="bi bi-plus"></i> Add Share
                    </button>
                    @endcan
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead><tr>
                        <th class="ps-3">Share #</th>
                        <th class="text-end">Subscribed</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th class="pe-3"></th>
                    </tr></thead>
                    <tbody>
                    @forelse($client->shares as $share)
                    <tr>
                        <td class="ps-3 font-monospace">{{ $share->share_number }}</td>
                        <td class="text-end">{{ number_format($share->share_value, 0) }}</td>
                        <td class="text-end text-success">{{ number_format($share->amount_paid, 0) }}</td>
                        <td class="text-end {{ $share->balance > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($share->balance, 0) }}</td>
                        <td><span class="badge badge-membership-{{ $share->status }}">{{ ucfirst($share->status) }}</span></td>
                        <td class="pe-3">
                            @can('manage shares')
                            @if(!$share->isLiquidated())
                            <div class="d-flex gap-1">
                                @if($share->status !== 'paid')
                                <button class="btn btn-sm btn-outline-success py-0"
                                    data-bs-toggle="modal"
                                    data-bs-target="#sharePayModal-{{ $share->id }}">Pay</button>
                                @endif
                                <button class="btn btn-sm btn-outline-warning py-0"
                                    data-bs-toggle="modal"
                                    data-bs-target="#revalueModal-{{ $share->id }}" title="Revalue">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                @if($share->amount_paid > 0)
                                <button class="btn btn-sm btn-outline-danger py-0"
                                    data-bs-toggle="modal"
                                    data-bs-target="#liquidateModal-{{ $share->id }}" title="Liquidate">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                                @endif
                            </div>
                            @else
                            <span class="text-muted small">Liquidated</span>
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No shares recorded.</td></tr>
                    @endforelse
                    </tbody>
                    @if($client->shares->count())
                    <tfoot class="table-light fw-semibold small">
                        <tr>
                            <td class="ps-3">Total</td>
                            <td class="text-end">{{ number_format($client->shares->whereNotIn('status',['liquidated'])->sum('share_value'), 0) }}</td>
                            <td class="text-end text-success">{{ number_format($client->shares->whereNotIn('status',['liquidated'])->sum('amount_paid'), 0) }}</td>
                            <td class="text-end text-danger">{{ number_format($client->shares->whereNotIn('status',['liquidated'])->sum(fn($s) => $s->balance), 0) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
    @endif {{-- shares_module_enabled --}}

</div>
@endif {{-- either module enabled --}}

{{-- Membership Fee Payment Modal --}}
@if($_membershipOn)
<div class="modal fade" id="membershipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-person-badge me-1"></i>Membership Fee Payment</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('clients.membership-payment', $client) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Outstanding: <strong>UGX {{ number_format($client->membership_fee_balance, 0) }}</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Source <span class="text-danger">*</span></label>
                        <select name="payment_source_account_id" class="form-select" id="memSrcSelect" onchange="toggleMemSavings(this)" required>
                            <option value="">— Select source —</option>
                            @foreach($paymentSourceAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->account_name }}</option>
                            @endforeach
                            <option value="savings">— Deduct from Savings Account —</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="savingsAccountGroup">
                        <label class="form-label fw-semibold">Savings Account <span class="text-danger">*</span></label>
                        @php $activeSavings = $client->savingsAccounts->where('status', 'active'); @endphp
                        @if($activeSavings->isEmpty())
                            <div class="text-danger small">No active savings accounts found.</div>
                            <input type="hidden" name="savings_account_id" value="">
                        @else
                            <select name="savings_account_id" id="savingsAccountSelect" class="form-select form-select-sm">
                                @foreach($activeSavings as $sa)
                                    <option value="{{ $sa->id }}">
                                        {{ $sa->account_number }} — {{ $sa->product->name }} (Bal: UGX {{ number_format($sa->balance, 0) }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (UGX) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="1" min="1"
                               max="{{ $client->membership_fee_balance }}"
                               value="{{ $client->membership_fee_balance }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="mb-3" id="membershipRefGroup">
                        <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                        <input type="text" name="reference" class="form-control" placeholder="Cheque no., receipt no., etc." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endif {{-- membership_fee_module_enabled --}}

{{-- Share Pay Modals (one per share) --}}
@if($_sharesOn)
@foreach($client->shares as $share)
@if($share->status !== 'paid')
<div class="modal fade" id="sharePayModal-{{ $share->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-pie-chart me-1"></i>Share Payment — {{ $share->share_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('clients.shares.payment', [$client, $share]) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Outstanding: <strong>UGX {{ number_format($share->balance, 0) }}</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Source <span class="text-danger">*</span></label>
                        <select name="payment_source_account_id" class="form-select share-src-select" data-share="{{ $share->id }}" onchange="toggleShareSavings({{ $share->id }}, this)" required>
                            <option value="">— Select source —</option>
                            @foreach($paymentSourceAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->account_name }}</option>
                            @endforeach
                            <option value="savings">— Deduct from Savings Account —</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="shareSavingsGroup-{{ $share->id }}">
                        <label class="form-label fw-semibold">Savings Account <span class="text-danger">*</span></label>
                        @php $activeSavings = $client->savingsAccounts->where('status', 'active'); @endphp
                        @if($activeSavings->isEmpty())
                            <div class="text-danger small">No active savings accounts found.</div>
                            <input type="hidden" name="savings_account_id" value="">
                        @else
                            <select name="savings_account_id" class="form-select form-select-sm">
                                @foreach($activeSavings as $sa)
                                    <option value="{{ $sa->id }}">{{ $sa->account_number }} — {{ $sa->product->name }} (Bal: UGX {{ number_format($sa->balance, 0) }})</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (UGX) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="1" min="1"
                               max="{{ $share->balance }}" value="{{ $share->balance }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="mb-3" id="shareRefGroup-{{ $share->id }}">
                        <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                        <input type="text" name="reference" class="form-control" placeholder="Cheque no., receipt no., etc." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endforeach

{{-- Revalue Modals (one per share) --}}
@can('manage shares')
@foreach($client->shares as $share)
@if(!$share->isLiquidated())
<div class="modal fade" id="revalueModal-{{ $share->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-1"></i>Revalue Share — {{ $share->share_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('clients.shares.revalue', [$client, $share]) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Current subscribed value: <strong>UGX {{ number_format($share->share_value, 0) }}</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Share Value (UGX) <span class="text-danger">*</span></label>
                        <input type="number" name="new_share_value" class="form-control" step="1000" min="1"
                               value="{{ $share->share_value }}" required>
                        <div class="form-text">Enter the new subscribed value for this share.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Effective Date <span class="text-danger">*</span></label>
                        <input type="date" name="effective_date" class="form-control"
                               value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Reason for revaluation…">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-warning">Save Revaluation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endforeach

{{-- Liquidate Modals (one per eligible share) --}}
@foreach($client->shares as $share)
@if(!$share->isLiquidated() && $share->amount_paid > 0)
<div class="modal fade" id="liquidateModal-{{ $share->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger-subtle">
                <h6 class="modal-title text-danger"><i class="bi bi-x-circle me-1"></i>Liquidate Share — {{ $share->share_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('clients.shares.liquidate', [$client, $share]) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        This will pay back <strong>UGX {{ number_format($share->amount_paid, 0) }}</strong> to the member and mark the share as liquidated. This cannot be undone.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payout Method <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payout_method"
                                    id="liqCash-{{ $share->id }}" value="cash" checked
                                    onchange="toggleLiqSavings({{ $share->id }}, this)">
                                <label class="form-check-label" for="liqCash-{{ $share->id }}"><i class="bi bi-cash me-1"></i>Cash</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payout_method"
                                    id="liqSavings-{{ $share->id }}" value="savings"
                                    onchange="toggleLiqSavings({{ $share->id }}, this)">
                                <label class="form-check-label" for="liqSavings-{{ $share->id }}"><i class="bi bi-piggy-bank me-1"></i>Credit to Savings</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 d-none" id="liqSavingsGroup-{{ $share->id }}">
                        <label class="form-label fw-semibold">Savings Account <span class="text-danger">*</span></label>
                        @php $activeSavings = $client->savingsAccounts->where('status', 'active'); @endphp
                        @if($activeSavings->isEmpty())
                            <div class="text-danger small">No active savings accounts found.</div>
                            <input type="hidden" name="savings_account_id" value="">
                        @else
                            <select name="savings_account_id" id="liqSavingsSelect-{{ $share->id }}" class="form-select form-select-sm">
                                @foreach($activeSavings as $sa)
                                    <option value="{{ $sa->id }}">{{ $sa->account_number }} — {{ $sa->product->name }} (Bal: UGX {{ number_format($sa->balance, 0) }})</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Liquidation Date <span class="text-danger">*</span></label>
                        <input type="date" name="liquidation_date" class="form-control"
                               value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="liquidation_notes" class="form-control" placeholder="Reason for liquidation…">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-danger">Confirm Liquidation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endforeach
@endcan

{{-- Add Share Modal --}}
<div class="modal fade" id="addShareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Add Share Subscription</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('clients.shares.add', $client) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Share Value (UGX) <span class="text-danger">*</span></label>
                        <input type="number" name="share_value" class="form-control" step="1000" min="1000" value="100000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-primary">Add Share</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif {{-- $_sharesOn modals --}}

<!-- Loans -->
<div class="card mb-3">
    <div class="card-header">Loans</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th class="ps-3">Loan #</th><th>Product</th><th>Principal</th><th>Outstanding</th><th>Status</th><th class="pe-3">Actions</th></tr></thead>
            <tbody>
            @forelse($client->loans as $loan)
                <tr>
                    <td class="ps-3 font-monospace">{{ $loan->loan_number }}</td>
                    <td>{{ $loan->product->name }}</td>
                    <td>{{ number_format($loan->principal, $dp) }}</td>
                    <td>{{ number_format($loan->outstanding_principal, $dp) }}</td>
                    <td><span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td>
                    <td class="pe-3"><a href="{{ route('loans.show', $loan) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">No loans.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Savings -->
<div class="card mb-3">
    <div class="card-header">Savings Accounts</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th class="ps-3">Account #</th><th>Product</th><th>Balance</th><th>Status</th><th class="pe-3">Actions</th></tr></thead>
            <tbody>
            @forelse($client->savingsAccounts as $sa)
                <tr>
                    <td class="ps-3 font-monospace">{{ $sa->account_number }}</td>
                    <td>{{ $sa->product->name }}</td>
                    <td class="fw-semibold">{{ number_format($sa->balance, $dp) }}</td>
                    <td><span class="badge badge-status-{{ $sa->status }}">{{ ucfirst($sa->status) }}</span></td>
                    <td class="pe-3"><a href="{{ route('savings.show', $sa) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">No savings accounts.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Fixed Deposits -->
<div class="card">
    <div class="card-header">Fixed Deposits</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th class="ps-3">Deposit #</th><th>Product</th><th>Principal</th><th>Maturity</th><th>Status</th><th class="pe-3">Actions</th></tr></thead>
            <tbody>
            @forelse($client->fixedDeposits as $fd)
                <tr>
                    <td class="ps-3 font-monospace">{{ $fd->deposit_number }}</td>
                    <td>{{ $fd->product->name }}</td>
                    <td>{{ number_format($fd->principal, $dp) }}</td>
                    <td>{{ $fd->maturity_date->format('d M Y') }}</td>
                    <td><span class="badge badge-status-{{ $fd->status }}">{{ ucfirst($fd->status) }}</span></td>
                    <td class="pe-3"><a href="{{ route('fixed-deposits.show', $fd) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">No fixed deposits.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
// Membership fee modal: toggle savings dropdown
function toggleMemSavings(sel) {
    var group  = document.getElementById('savingsAccountGroup');
    var select = document.getElementById('savingsAccountSelect');
    if (sel.value === 'savings') {
        group.classList.remove('d-none');
        if (select) select.setAttribute('required', 'required');
    } else {
        group.classList.add('d-none');
        if (select) select.removeAttribute('required');
    }
}

// Liquidation modals: toggle savings dropdown
function toggleLiqSavings(shareId, radio) {
    var savingsGrp = document.getElementById('liqSavingsGroup-' + shareId);
    var sel        = document.getElementById('liqSavingsSelect-' + shareId);
    if (radio.value === 'savings') {
        if (savingsGrp) savingsGrp.classList.remove('d-none');
        if (sel) sel.setAttribute('required','required');
    } else {
        if (savingsGrp) savingsGrp.classList.add('d-none');
        if (sel) sel.removeAttribute('required');
    }
}

// Share pay modals: toggle savings dropdown
function toggleShareSavings(shareId, sel) {
    var savingsGrp = document.getElementById('shareSavingsGroup-' + shareId);
    var selects    = savingsGrp ? savingsGrp.querySelectorAll('select') : [];
    if (sel.value === 'savings') {
        if (savingsGrp) savingsGrp.classList.remove('d-none');
        selects.forEach(function(s){ s.setAttribute('required','required'); });
    } else {
        if (savingsGrp) savingsGrp.classList.add('d-none');
        selects.forEach(function(s){ s.removeAttribute('required'); });
    }
}
</script>
@endpush
@endsection
