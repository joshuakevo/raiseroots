@extends('layouts.app')
@section('title', 'Edit Client')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">Clients</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clients.show', $client) }}">{{ $client->name }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection
@section('content')
<div class="row justify-content-center">
<div class="col-xl-8 col-lg-10">

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-3">
    <i class="bi bi-exclamation-circle me-2"></i>Please correct the highlighted errors below.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('clients.update', $client) }}" enctype="multipart/form-data" id="clientForm">
@csrf @method('PUT')

{{-- Step Wizard Header --}}
<div class="step-wizard">
    <div class="step-circles">
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

<div class="card">
<div class="card-body p-4">

{{-- ── Step 1: Personal ── --}}
<div class="step-pane active" id="step1">
    <h6 class="fw-semibold mb-3">Personal Information</h6>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror"
                   value="{{ old('first_name', $client->first_name) }}" required>
            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Middle Name</label>
            <input type="text" name="middle_name" class="form-control"
                   value="{{ old('middle_name', $client->middle_name) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Last Name <span class="text-danger">*</span></label>
            <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror"
                   value="{{ old('last_name', $client->last_name) }}" required>
            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select">
                <option value="">— Select —</option>
                <option value="male"   @selected(old('gender',$client->gender)=='male')>Male</option>
                <option value="female" @selected(old('gender',$client->gender)=='female')>Female</option>
                <option value="other"  @selected(old('gender',$client->gender)=='other')>Other</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control"
                   value="{{ old('date_of_birth', $client->date_of_birth?->toDateString()) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Marital Status</label>
            <select name="marital_status" class="form-select">
                <option value="">— Select —</option>
                <option value="single"   @selected(old('marital_status',$client->marital_status)=='single')>Single</option>
                <option value="married"  @selected(old('marital_status',$client->marital_status)=='married')>Married</option>
                <option value="divorced" @selected(old('marital_status',$client->marital_status)=='divorced')>Divorced</option>
                <option value="widowed"  @selected(old('marital_status',$client->marital_status)=='widowed')>Widowed</option>
            </select>
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Nationality</label>
            <input type="text" name="nationality" class="form-control"
                   value="{{ old('nationality', $client->nationality) }}" placeholder="e.g. Kenyan">
        </div>
        <div class="col-md-6">
            <label class="form-label">National ID / Passport Number</label>
            <input type="text" name="id_number" class="form-control"
                   value="{{ old('id_number', $client->id_number) }}">
        </div>
    </div>
    <div class="mb-1">
        <label class="form-label">Profile Photo</label>
        @if($client->photo)
        <div class="mb-2">
            <img src="{{ asset($client->photo) }}" alt="Photo"
                 class="rounded" style="height:70px;width:70px;object-fit:cover;border:2px solid #dee2e6">
            <span class="text-muted small ms-2">Current photo</span>
        </div>
        @endif
        <input type="file" name="photo" class="form-control @error('photo') is-invalid @enderror" accept="image/*">
        <div class="form-text">Upload a new photo to replace the current one. Max 2 MB.</div>
        @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

{{-- ── Step 2: Contact ── --}}
<div class="step-pane" id="step2">
    <h6 class="fw-semibold mb-3">Contact Information</h6>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Phone Number (Primary)</label>
            <input type="text" name="phone" class="form-control"
                   value="{{ old('phone', $client->phone) }}" placeholder="+254...">
        </div>
        <div class="col-md-6">
            <label class="form-label">Alternative Phone Number</label>
            <input type="text" name="alt_phone" class="form-control"
                   value="{{ old('alt_phone', $client->alt_phone) }}" placeholder="+254...">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $client->email) }}">
    </div>
    <div class="mb-3">
        <label class="form-label">Physical Address</label>
        <textarea name="address" class="form-control" rows="2">{{ old('address', $client->address) }}</textarea>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">District</label>
            <input type="text" name="district" class="form-control" value="{{ old('district', $client->district) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Village / Parish</label>
            <input type="text" name="village" class="form-control" value="{{ old('village', $client->village) }}">
        </div>
    </div>
    <div class="mb-1">
        <label class="form-label">Postal Address</label>
        <input type="text" name="postal_address" class="form-control"
               value="{{ old('postal_address', $client->postal_address) }}" placeholder="P.O. Box 123, Town">
    </div>
</div>

{{-- ── Step 3: Employment ── --}}
<div class="step-pane" id="step3">
    <h6 class="fw-semibold mb-3">Employment &amp; Membership</h6>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Employment Status</label>
            <select name="employment_status" class="form-select">
                <option value="">— Select —</option>
                <option value="employed"       @selected(old('employment_status',$client->employment_status)=='employed')>Employed</option>
                <option value="self_employed"  @selected(old('employment_status',$client->employment_status)=='self_employed')>Self-Employed</option>
                <option value="business_owner" @selected(old('employment_status',$client->employment_status)=='business_owner')>Business Owner</option>
                <option value="farmer"         @selected(old('employment_status',$client->employment_status)=='farmer')>Farmer</option>
                <option value="student"        @selected(old('employment_status',$client->employment_status)=='student')>Student</option>
                <option value="unemployed"     @selected(old('employment_status',$client->employment_status)=='unemployed')>Unemployed</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Main Purpose of Joining</label>
            <select name="purpose_of_joining" class="form-select">
                <option value="">— Select —</option>
                <option value="savings"            @selected(old('purpose_of_joining',$client->purpose_of_joining)=='savings')>Savings</option>
                <option value="loan"               @selected(old('purpose_of_joining',$client->purpose_of_joining)=='loan')>Loan</option>
                <option value="investment"         @selected(old('purpose_of_joining',$client->purpose_of_joining)=='investment')>Investment</option>
                <option value="business_financing" @selected(old('purpose_of_joining',$client->purpose_of_joining)=='business_financing')>Business Financing</option>
            </select>
        </div>
    </div>
    <div class="row g-3 mb-1">
        <div class="col-md-6">
            <label class="form-label">Expected Monthly Savings</label>
            <div class="input-group">
                <span class="input-group-text text-muted">{{ \App\Models\SystemSetting::get('currency','KES') }}</span>
                <input type="number" name="expected_monthly_savings" class="form-control" step="0.01" min="0"
                       value="{{ old('expected_monthly_savings', $client->expected_monthly_savings) }}" placeholder="0.00">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label d-block">Interested in Loans?</label>
            <div class="d-flex gap-4 mt-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="loan_interest" id="li_yes" value="1"
                           @checked(old('loan_interest', $client->loan_interest ? '1' : ($client->loan_interest === false ? '0' : null))=='1')>
                    <label class="form-check-label" for="li_yes">Yes</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="loan_interest" id="li_no" value="0"
                           @checked(old('loan_interest', $client->loan_interest === false ? '0' : null)=='0')>
                    <label class="form-check-label" for="li_no">No</label>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Step 4: Next of Kin ── --}}
<div class="step-pane" id="step4">
    <h6 class="fw-semibold mb-3">Next of Kin</h6>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input type="text" name="next_of_kin_name" class="form-control"
                   value="{{ old('next_of_kin_name', $client->next_of_kin_name) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Relationship</label>
            <input type="text" name="next_of_kin_relationship" class="form-control"
                   value="{{ old('next_of_kin_relationship', $client->next_of_kin_relationship) }}"
                   placeholder="e.g. Spouse, Parent, Sibling">
        </div>
    </div>
    <div class="row g-3 mb-1">
        <div class="col-md-6">
            <label class="form-label">Phone Number</label>
            <input type="text" name="next_of_kin_phone" class="form-control"
                   value="{{ old('next_of_kin_phone', $client->next_of_kin_phone) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Address</label>
            <input type="text" name="next_of_kin_address" class="form-control"
                   value="{{ old('next_of_kin_address', $client->next_of_kin_address) }}">
        </div>
    </div>
</div>

{{-- ── Step 5: Preferences ── --}}
<div class="step-pane" id="step5">
    <h6 class="fw-semibold mb-3">Preferences &amp; Account Settings</h6>
    <div class="mb-3">
        <label class="form-label">Preferred Communication Method</label>
        <div class="d-flex flex-wrap gap-3 mt-1">
            @foreach(['sms'=>'SMS','email'=>'Email','whatsapp'=>'WhatsApp','phone_call'=>'Phone Call'] as $val=>$label)
            <div class="form-check">
                <input class="form-check-input" type="radio" name="preferred_communication"
                       id="pc_{{ $val }}" value="{{ $val }}"
                       @checked(old('preferred_communication',$client->preferred_communication)==$val)>
                <label class="form-check-label" for="pc_{{ $val }}">{{ $label }}</label>
            </div>
            @endforeach
        </div>
    </div>
    <div class="row g-3 mb-3">
        @if($branches->count())
        <div class="col-md-6">
            <label class="form-label">Branch</label>
            <select name="branch_id" class="form-select">
                <option value="">— Select Branch —</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id',$client->branch_id)==$branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-md-6">
            <label class="form-label">Account Status</label>
            <select name="status" class="form-select">
                <option value="active"      @selected(old('status',$client->status)=='active')>Active</option>
                <option value="inactive"    @selected(old('status',$client->status)=='inactive')>Inactive</option>
                <option value="blacklisted" @selected(old('status',$client->status)=='blacklisted')>Blacklisted</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Joining Date</label>
            <input type="date" name="joining_date" class="form-control"
                   value="{{ old('joining_date', $client->joining_date?->toDateString()) }}">
        </div>
    </div>
</div>

</div>{{-- card-body --}}

<div class="card-footer d-flex justify-content-between align-items-center px-4 py-3">
    <button type="button" class="btn btn-outline-secondary" id="btnPrev" onclick="stepNav(-1)" style="display:none">
        <i class="bi bi-arrow-left me-1"></i> Previous
    </button>
    <span></span>
    <div class="d-flex gap-2">
        <a href="{{ route('clients.show', $client) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="button" class="btn btn-primary" id="btnNext" onclick="stepNav(1)">
            Next <i class="bi bi-arrow-right ms-1"></i>
        </button>
        <button type="submit" class="btn btn-success" id="btnSubmit" style="display:none">
            <i class="bi bi-check-lg me-1"></i> Update Client
        </button>
    </div>
</div>

</div>{{-- card --}}
</form>
</div>
</div>

<script>
let currentStep = 1;
const totalSteps = 5;

function stepNav(dir) {
    if (dir === 1 && !validateStep(currentStep)) return;
    goToStep(currentStep + dir);
}

function goToStep(n) {
    if (n < 1 || n > totalSteps) return;
    for (let i = 1; i <= totalSteps; i++) {
        const sc = document.getElementById('sc' + i);
        const sl = document.getElementById('sl' + i);
        sc.classList.remove('active', 'done');
        sl.classList.remove('active');
        if (i < n) { sc.classList.add('done'); }
        else if (i === n) { sc.classList.add('active'); sl.classList.add('active'); }
        if (i < totalSteps) {
            document.getElementById('cn' + i).classList.toggle('done', i < n);
        }
    }
    document.querySelectorAll('.step-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('step' + n).classList.add('active');
    document.getElementById('btnPrev').style.display  = n > 1 ? '' : 'none';
    document.getElementById('btnNext').style.display   = n < totalSteps ? '' : 'none';
    document.getElementById('btnSubmit').style.display = n === totalSteps ? '' : 'none';
    currentStep = n;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep(step) {
    const pane = document.getElementById('step' + step);
    let valid = true;
    pane.querySelectorAll('[required]').forEach(el => {
        el.classList.remove('is-invalid');
        if (!el.value.trim()) { el.classList.add('is-invalid'); valid = false; }
    });
    if (!valid) pane.querySelector('.is-invalid')?.focus();
    return valid;
}

document.addEventListener('DOMContentLoaded', function () {
    const firstInvalid = document.querySelector('.is-invalid');
    if (firstInvalid) {
        const pane = firstInvalid.closest('.step-pane');
        if (pane) goToStep(parseInt(pane.id.replace('step', '')));
    }
});
</script>
@endsection
