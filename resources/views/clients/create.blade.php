@extends('layouts.app')
@section('title', 'New Client')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">Clients</a></li>
    <li class="breadcrumb-item active">New Client</li>
@endsection
@section('content')
<div class="row justify-content-center">
<div class="col-xl-9 col-lg-11">

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-3">
    <i class="bi bi-exclamation-circle me-2"></i>
    Please correct the highlighted errors below.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('clients.store') }}" enctype="multipart/form-data" id="clientForm" novalidate>
@csrf
@php $defaultType = old('client_type', request('type', 'individual')); @endphp

{{-- Single type selector --}}
<div class="d-flex align-items-center gap-3 mb-3">
    <span class="text-muted small fw-semibold text-nowrap">Client type:</span>
    <select name="client_type" id="clientType" class="form-select form-select-sm" style="max-width:220px">
        <option value="individual" {{ $defaultType==='individual'?'selected':'' }}>Individual member</option>
        <option value="group"      {{ $defaultType==='group'?'selected':'' }}>Group</option>
    </select>
</div>

<div id="groupForm" class="card mb-3" style="display:none">
    <div class="card-header py-2">
        <span class="fw-semibold">Group registration</span>
    </div>
    <div class="card-body p-3">

        {{-- Group type selector --}}
        <div class="mb-3">
            <label class="form-label mb-1 fw-semibold">Group Type <span class="text-danger">*</span></label>
            <div class="d-flex gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="group_type" id="gt_savings" value="savings"
                           {{ old('group_type','savings') === 'savings' ? 'checked' : '' }}>
                    <label class="form-check-label" for="gt_savings">
                        <i class="bi bi-piggy-bank-fill text-primary me-1"></i>
                        <strong>Savings Group</strong>
                        <span class="text-muted small d-block">Each member has their own balance</span>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="group_type" id="gt_pooled" value="pooled"
                           {{ old('group_type') === 'pooled' ? 'checked' : '' }}>
                    <label class="form-check-label" for="gt_pooled">
                        <i class="bi bi-safe2-fill text-success me-1"></i>
                        <strong>Pooled Group</strong>
                        <span class="text-muted small d-block">Members contribute to a shared pot</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <label class="form-label mb-1">Group name <span class="text-danger">*</span></label>
                <input type="text" name="group_name" class="form-control form-control-sm" value="{{ old('group_name') }}" placeholder="e.g. Westside Women Group">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Phone</label>
                <input type="text" name="phone" class="form-control form-control-sm" value="{{ old('phone') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Email</label>
                <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email') }}">
            </div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <label class="form-label mb-1">Branch</label>
                @if(auth()->user()->isBranchScoped())
                <input type="text" class="form-control form-control-sm" value="{{ auth()->user()->branch?->name ?? 'No branch assigned' }}" disabled>
                <div class="form-text small">New clients are assigned to your branch automatically.</div>
                @else
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ (string)old('branch_id')===(string)$b->id?'selected':'' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
                @endif
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select form-select-sm">
                    <option value="active" {{ old('status','active')==='active'?'selected':'' }}>Active</option>
                    <option value="inactive" {{ old('status')==='inactive'?'selected':'' }}>Inactive</option>
                    <option value="blacklisted" {{ old('status')==='blacklisted'?'selected':'' }}>Blacklisted</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Joining date</label>
                <input type="date" name="joining_date" class="form-control form-control-sm" value="{{ old('joining_date') }}">
            </div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <label class="form-label mb-1">Membership fee <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="membership_fee" class="form-control form-control-sm" value="{{ old('membership_fee', 0) }}">
            </div>

            {{-- Savings group: interest rate --}}
            <div class="col-md-4" id="grp_interest_wrap">
                <label class="form-label mb-1">Monthly interest rate (%) <span class="text-danger">*</span></label>
                <input type="number" step="1" min="0" max="100" name="monthly_interest_rate" id="grp_interest" class="form-control form-control-sm" value="{{ old('monthly_interest_rate', 0) }}">
            </div>

            {{-- Pooled group: expected contribution --}}
            <div class="col-md-4" id="grp_contribution_wrap" style="display:none">
                <label class="form-label mb-1">Expected contribution <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="expected_contribution" class="form-control form-control-sm" value="{{ old('expected_contribution') }}" placeholder="Amount per cycle">
            </div>

            <div class="col-md-4" id="grp_cycle_wrap" style="display:none">
                <label class="form-label mb-1">Contribution cycle <span class="text-danger">*</span></label>
                <select name="contribution_cycle" class="form-select form-select-sm">
                    <option value="">— Select —</option>
                    <option value="weekly"    {{ old('contribution_cycle')==='weekly'    ?'selected':'' }}>Weekly</option>
                    <option value="biweekly"  {{ old('contribution_cycle')==='biweekly'  ?'selected':'' }}>Bi-weekly</option>
                    <option value="monthly"   {{ old('contribution_cycle')==='monthly'   ?'selected':'' }}>Monthly</option>
                    <option value="quarterly" {{ old('contribution_cycle')==='quarterly' ?'selected':'' }}>Quarterly</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label mb-1">Address</label>
                <input type="text" name="address" class="form-control form-control-sm" value="{{ old('address') }}" placeholder="Optional">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end align-items-center gap-2 py-2 px-3">
        <a href="{{ route('clients.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Save Group</button>
    </div>
</div>

<div id="individualWizard">

<div class="card">

{{-- Step Wizard Header --}}
<div class="step-wizard border-bottom px-3 pt-2 pb-0" style="background:var(--bs-body-bg)">
    <div class="step-circles" id="stepCircles">
        <div class="step-item">
            <div class="step-circle active" id="sc1"><span>1</span><i class="bi bi-check-lg"></i></div>
            <div class="step-label active" id="sl1">Personal</div>
        </div>
        <div class="step-connector" id="cn1"></div>
        <div class="step-item">
            <div class="step-circle" id="sc2"><span>2</span><i class="bi bi-check-lg"></i></div>
            <div class="step-label" id="sl2">Contact</div>
        </div>
        <div class="step-connector" id="cn2"></div>
        <div class="step-item">
            <div class="step-circle" id="sc3"><span>3</span><i class="bi bi-check-lg"></i></div>
            <div class="step-label" id="sl3">Employment</div>
        </div>
        <div class="step-connector" id="cn3"></div>
        <div class="step-item">
            <div class="step-circle" id="sc4"><span>4</span><i class="bi bi-check-lg"></i></div>
            <div class="step-label" id="sl4">Next of Kin</div>
        </div>
        <div class="step-connector" id="cn4"></div>
        <div class="step-item">
            <div class="step-circle" id="sc5"><span>5</span><i class="bi bi-check-lg"></i></div>
            <div class="step-label" id="sl5">Preferences</div>
        </div>
    </div>
</div>

<div class="card-body p-3">

{{-- ── Step 1: Personal Information ── --}}
<div class="step-pane active" id="step1">
    <h6 class="fw-semibold mb-2">Personal Information</h6>
    <div class="row g-2 mb-2">
        <div class="col-md-4">
            <label class="form-label mb-1">First Name <span class="text-danger">*</span></label>
            <input type="text" name="first_name" class="form-control form-control-sm @error('first_name') is-invalid @enderror"
                   value="{{ old('first_name') }}" required>
            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Middle Name</label>
            <input type="text" name="middle_name" class="form-control form-control-sm @error('middle_name') is-invalid @enderror"
                   value="{{ old('middle_name') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Last Name <span class="text-danger">*</span></label>
            <input type="text" name="last_name" class="form-control form-control-sm @error('last_name') is-invalid @enderror"
                   value="{{ old('last_name') }}" required>
            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="row g-2 mb-2">
        <div class="col-md-4">
            <label class="form-label mb-1">Gender <span class="text-danger">*</span></label>
            <select name="gender" class="form-select form-select-sm @error('gender') is-invalid @enderror" required>
                <option value="">— Select —</option>
                <option value="male"   @selected(old('gender')=='male')>Male</option>
                <option value="female" @selected(old('gender')=='female')>Female</option>
                <option value="other"  @selected(old('gender')=='other')>Other</option>
            </select>
            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Date of Birth <span class="text-danger">*</span></label>
            <input type="date" name="date_of_birth" class="form-control form-control-sm @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth') }}" required>
            @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Marital Status <span class="text-danger">*</span></label>
            <select name="marital_status" class="form-select form-select-sm @error('marital_status') is-invalid @enderror" required>
                <option value="">— Select —</option>
                <option value="single"   @selected(old('marital_status')=='single')>Single</option>
                <option value="married"  @selected(old('marital_status')=='married')>Married</option>
                <option value="divorced" @selected(old('marital_status')=='divorced')>Divorced</option>
                <option value="widowed"  @selected(old('marital_status')=='widowed')>Widowed</option>
            </select>
            @error('marital_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="row g-2 mb-2">
        <div class="col-md-4">
            <label class="form-label mb-1">Nationality <span class="text-danger">*</span></label>
            <input type="text" name="nationality" class="form-control form-control-sm @error('nationality') is-invalid @enderror" value="{{ old('nationality') }}" placeholder="e.g. Ugandan" required>
            @error('nationality')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">National ID / Passport No. <span class="text-danger">*</span></label>
            <input type="text" name="id_number" class="form-control form-control-sm @error('id_number') is-invalid @enderror" value="{{ old('id_number') }}" required>
            @error('id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Profile Photo</label>
            <input type="file" name="photo" class="form-control form-control-sm @error('photo') is-invalid @enderror" accept="image/*">
            @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ── Step 2: Contact Information ── --}}
<div class="step-pane" id="step2">
    <h6 class="fw-semibold mb-2">Contact Information</h6>
    <div class="row g-2 mb-2">
        <div class="col-md-4">
            <label class="form-label mb-1">Phone (Primary) <span class="text-danger">*</span></label>
            <input type="text" name="phone" class="form-control form-control-sm @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="+256..." required>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Alternative Phone</label>
            <input type="text" name="alt_phone" class="form-control form-control-sm" value="{{ old('alt_phone') }}" placeholder="+256...">
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="row g-2 mb-2">
        <div class="col-md-6">
            <label class="form-label mb-1">District <span class="text-danger">*</span></label>
            <input type="text" name="district" class="form-control form-control-sm @error('district') is-invalid @enderror" value="{{ old('district') }}" required>
            @error('district')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label mb-1">Village / Parish <span class="text-danger">*</span></label>
            <input type="text" name="village" class="form-control form-control-sm @error('village') is-invalid @enderror" value="{{ old('village') }}" required>
            @error('village')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="row g-2">
        <div class="col-md-12">
            <label class="form-label mb-1">Physical Address <span class="text-danger">*</span></label>
            <textarea name="address" class="form-control form-control-sm @error('address') is-invalid @enderror" rows="2" placeholder="Street / Estate / Building" required>{{ old('address') }}</textarea>
            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ── Step 3: Employment & Membership ── --}}
<div class="step-pane" id="step3">
    <h6 class="fw-semibold mb-2">Employment &amp; Membership</h6>
    <div class="row g-2 mb-2">
        <div class="col-md-4">
            <label class="form-label mb-1">Employment Status <span class="text-danger">*</span></label>
            <select name="employment_status" class="form-select form-select-sm @error('employment_status') is-invalid @enderror" required>
                <option value="">— Select —</option>
                <option value="employed"        @selected(old('employment_status')=='employed')>Employed</option>
                <option value="self_employed"   @selected(old('employment_status')=='self_employed')>Self-Employed</option>
                <option value="business_owner"  @selected(old('employment_status')=='business_owner')>Business Owner</option>
                <option value="farmer"          @selected(old('employment_status')=='farmer')>Farmer</option>
                <option value="student"         @selected(old('employment_status')=='student')>Student</option>
                <option value="unemployed"      @selected(old('employment_status')=='unemployed')>Unemployed</option>
            </select>
            @error('employment_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Purpose of Joining <span class="text-danger">*</span></label>
            <select name="purpose_of_joining" class="form-select form-select-sm @error('purpose_of_joining') is-invalid @enderror" required>
                <option value="">— Select —</option>
                <option value="savings"             @selected(old('purpose_of_joining')=='savings')>Savings</option>
                <option value="loan"                @selected(old('purpose_of_joining')=='loan')>Loan</option>
                <option value="investment"          @selected(old('purpose_of_joining')=='investment')>Investment</option>
                <option value="business_financing"  @selected(old('purpose_of_joining')=='business_financing')>Business Financing</option>
            </select>
            @error('purpose_of_joining')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Expected Monthly Savings <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm">
                <span class="input-group-text text-muted">{{ \App\Models\SystemSetting::get('currency','UGX') }}</span>
                <input type="number" name="expected_monthly_savings" class="form-control @error('expected_monthly_savings') is-invalid @enderror" step="0.01" min="0"
                       value="{{ old('expected_monthly_savings') }}" placeholder="0.00" required>
                @error('expected_monthly_savings')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label mb-1">Interested in Loans? <span class="text-danger">*</span></label>
            <div class="d-flex gap-4 mt-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="loan_interest" id="li_yes" value="1" @checked(old('loan_interest')=='1') required>
                    <label class="form-check-label" for="li_yes">Yes</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="loan_interest" id="li_no" value="0" @checked(old('loan_interest')=='0')>
                    <label class="form-check-label" for="li_no">No</label>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Step 4: Next of Kin ── --}}
<div class="step-pane" id="step4">
    <h6 class="fw-semibold mb-2">Next of Kin</h6>
    <div class="row g-2">
        <div class="col-md-3">
            <label class="form-label mb-1">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="next_of_kin_name" class="form-control form-control-sm @error('next_of_kin_name') is-invalid @enderror" value="{{ old('next_of_kin_name') }}" required>
            @error('next_of_kin_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label mb-1">Relationship <span class="text-danger">*</span></label>
            <input type="text" name="next_of_kin_relationship" class="form-control form-control-sm @error('next_of_kin_relationship') is-invalid @enderror"
                   value="{{ old('next_of_kin_relationship') }}" placeholder="e.g. Spouse, Parent" required>
            @error('next_of_kin_relationship')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label mb-1">Phone Number <span class="text-danger">*</span></label>
            <input type="text" name="next_of_kin_phone" class="form-control form-control-sm @error('next_of_kin_phone') is-invalid @enderror" value="{{ old('next_of_kin_phone') }}" required>
            @error('next_of_kin_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label mb-1">Address <span class="text-danger">*</span></label>
            <input type="text" name="next_of_kin_address" class="form-control form-control-sm @error('next_of_kin_address') is-invalid @enderror" value="{{ old('next_of_kin_address') }}" required>
            @error('next_of_kin_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ── Step 5: Preferences & Account ── --}}
<div class="step-pane" id="step5">
    <h6 class="fw-semibold mb-2">Preferences &amp; Account Settings</h6>
    <div class="row g-2 mb-2">
        @if(auth()->user()->isBranchScoped())
        <div class="col-md-3">
            <label class="form-label mb-1">Branch</label>
            <input type="text" class="form-control form-control-sm" value="{{ auth()->user()->branch?->name ?? 'No branch assigned' }}" disabled>
            <div class="form-text small">Assigned to your branch automatically.</div>
        </div>
        @elseif($branches->count())
        <div class="col-md-3">
            <label class="form-label mb-1">Branch</label>
            <select name="branch_id" class="form-select form-select-sm">
                <option value="">— Select —</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id')==$branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-md-3">
            <label class="form-label mb-1">Account Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="active"      @selected(old('status','active')=='active')>Active</option>
                <option value="inactive"    @selected(old('status')=='inactive')>Inactive</option>
                <option value="blacklisted" @selected(old('status')=='blacklisted')>Blacklisted</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label mb-1">Date Joined <span class="text-danger">*</span></label>
            <input type="date" name="joining_date" class="form-control form-control-sm @error('joining_date') is-invalid @enderror" value="{{ old('joining_date', now()->toDateString()) }}" required>
            @error('joining_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="mb-2">
        <label class="form-label mb-1">Preferred Communication <span class="text-danger">*</span></label>
        <div class="d-flex flex-wrap gap-3">
            @foreach(['sms'=>'SMS','email'=>'Email','whatsapp'=>'WhatsApp','phone_call'=>'Phone Call'] as $val=>$lbl)
            <div class="form-check">
                <input class="form-check-input" type="radio" name="preferred_communication"
                       id="pc_{{ $val }}" value="{{ $val }}" @checked(old('preferred_communication')==$val)
                       {{ $loop->first ? 'required' : '' }}>
                <label class="form-check-label small" for="pc_{{ $val }}">{{ $lbl }}</label>
            </div>
            @endforeach
        </div>
        @error('preferred_communication')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
    <div class="alert alert-info py-2 d-flex gap-2 align-items-center" style="font-size:.82rem">
        <i class="bi bi-info-circle-fill flex-shrink-0"></i>
        <span><strong>System will automatically record</strong> the officer who registered this client.</span>
    </div>
</div>

</div>{{-- card-body --}}

{{-- Navigation Buttons --}}
<div class="card-footer d-flex justify-content-between align-items-center px-3 py-2">
    <button type="button" class="btn btn-outline-secondary" id="btnPrev" onclick="stepNav(-1)" style="display:none">
        <i class="bi bi-arrow-left me-1"></i> Previous
    </button>
    <span></span>
    <div class="d-flex gap-2">
        <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="button" class="btn btn-primary" id="btnNext" onclick="stepNav(1)">
            Next <i class="bi bi-arrow-right ms-1"></i>
        </button>
        <button type="submit" class="btn btn-success" id="btnSubmit" style="display:none">
            <i class="bi bi-check-lg me-1"></i> Save Client
        </button>
    </div>
</div>

</div>{{-- card (individualWizard) --}}

</div>{{-- #individualWizard --}}

</form>
</div>
</div>

<script>
function toggleClientType() {
    const t = document.getElementById('clientType').value;
    const isGroup = t === 'group';

    document.getElementById('groupForm').style.display        = isGroup ? '' : 'none';
    document.getElementById('individualWizard').style.display = isGroup ? 'none' : '';

    document.querySelectorAll('#groupForm input, #groupForm select, #groupForm textarea, #groupForm button').forEach(el => {
        if (el.id === 'clientType') return;
        el.disabled = !isGroup;
    });
    document.querySelectorAll('#individualWizard input, #individualWizard select, #individualWizard textarea, #individualWizard button').forEach(el => {
        if (el.id === 'clientType') return;
        el.disabled = isGroup;
    });
}
document.getElementById('clientType').addEventListener('change', toggleClientType);
document.addEventListener('DOMContentLoaded', toggleClientType);

function toggleGroupType() {
    const isPooled = document.getElementById('gt_pooled').checked;
    document.getElementById('grp_interest_wrap').style.display    = isPooled ? 'none' : '';
    document.getElementById('grp_contribution_wrap').style.display = isPooled ? '' : 'none';
    document.getElementById('grp_cycle_wrap').style.display        = isPooled ? '' : 'none';
    document.getElementById('grp_interest').disabled               = isPooled;
}
document.getElementById('gt_savings').addEventListener('change', toggleGroupType);
document.getElementById('gt_pooled').addEventListener('change', toggleGroupType);
document.addEventListener('DOMContentLoaded', toggleGroupType);

let currentStep = {{ $errors->any() ? 1 : 1 }};
const totalSteps = 5;

function stepNav(dir) {
    if (dir === 1 && !validateStep(currentStep)) return;
    goToStep(currentStep + dir);
}

function goToStep(n) {
    if (n < 1 || n > totalSteps) return;

    // Mark previous steps done, update circles & connectors
    for (let i = 1; i <= totalSteps; i++) {
        const sc = document.getElementById('sc' + i);
        const sl = document.getElementById('sl' + i);
        sc.classList.remove('active', 'done');
        sl.classList.remove('active');
        if (i < n) { sc.classList.add('done'); }
        else if (i === n) { sc.classList.add('active'); sl.classList.add('active'); }
        if (i < totalSteps) {
            const cn = document.getElementById('cn' + i);
            cn.classList.toggle('done', i < n);
        }
    }

    // Show/hide panes
    document.querySelectorAll('.step-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('step' + n).classList.add('active');

    // Buttons
    document.getElementById('btnPrev').style.display  = n > 1 ? '' : 'none';
    document.getElementById('btnNext').style.display   = n < totalSteps ? '' : 'none';
    document.getElementById('btnSubmit').style.display = n === totalSteps ? '' : 'none';

    currentStep = n;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep(step) {
    const pane = document.getElementById('step' + step);
    let valid = true;
    const seenRadioGroups = new Set();

    pane.querySelectorAll('[required]').forEach(el => {
        if (el.type === 'radio') {
            if (seenRadioGroups.has(el.name)) return;
            seenRadioGroups.add(el.name);
            const anyChecked = pane.querySelectorAll(`[name="${el.name}"]:checked`).length > 0;
            pane.querySelectorAll(`[name="${el.name}"]`).forEach(r => r.classList.remove('is-invalid'));
            if (!anyChecked) {
                el.classList.add('is-invalid');
                valid = false;
            }
        } else {
            el.classList.remove('is-invalid');
            if (!el.value.trim()) {
                el.classList.add('is-invalid');
                valid = false;
            }
        }
    });

    if (!valid) {
        pane.querySelector('.is-invalid')?.focus();
    }
    return valid;
}

document.getElementById('clientForm').addEventListener('submit', function (e) {
    if (document.getElementById('clientType').value !== 'individual') return;
    if (!validateStep(currentStep)) {
        e.preventDefault();
    }
});

// On page load with validation errors, jump to first step with errors
document.addEventListener('DOMContentLoaded', function () {
    const firstInvalid = document.querySelector('.is-invalid');
    if (firstInvalid) {
        const pane = firstInvalid.closest('.step-pane');
        if (pane) {
            const stepNum = parseInt(pane.id.replace('step', ''));
            goToStep(stepNum);
        }
    }
});
</script>
@endsection
