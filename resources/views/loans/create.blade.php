@extends('layouts.app')
@section('title', 'New Loan Application')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <a href="{{ route('loans.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Loans
        </a>
        <h5 class="mb-0 fw-semibold mt-1">New Loan Application</h5>
    </div>
</div>

<form method="POST" action="{{ route('loans.store') }}" id="loanForm" enctype="multipart/form-data">
@csrf

<div class="card">

{{-- Step wizard header --}}
<div class="step-wizard border-bottom px-3 pt-2 pb-0" style="background:var(--bs-body-bg)">
    <div class="step-circles" id="stepCircles">
        <div class="step-item">
            <div class="step-circle active" id="sc1"><span>1</span><i class="bi bi-check-lg"></i></div>
            <div class="step-label active" id="sl1">Client &amp; Product</div>
        </div>
        <div class="step-connector" id="cn1"></div>
        <div class="step-item">
            <div class="step-circle" id="sc2"><span>2</span><i class="bi bi-check-lg"></i></div>
            <div class="step-label" id="sl2">Loan Terms</div>
        </div>
        <div class="step-connector" id="cn2"></div>
        <div class="step-item">
            <div class="step-circle" id="sc3"><span>3</span><i class="bi bi-check-lg"></i></div>
            <div class="step-label" id="sl3">Guarantors</div>
        </div>
        <div class="step-connector" id="cn3"></div>
        <div class="step-item">
            <div class="step-circle" id="sc4"><span>4</span><i class="bi bi-check-lg"></i></div>
            <div class="step-label" id="sl4">Collateral</div>
        </div>
    </div>
</div>

<div class="card-body p-3">

{{-- STEP 1: Client & Product --}}
<div class="step-pane active" id="step-1">
    <h6 class="fw-semibold mb-2"><i class="bi bi-person me-1 text-primary"></i>Client &amp; Product</h6>
    <div class="row g-2 mb-2">
        <div class="col-md-6">
            <label class="form-label mb-1">Client <span class="text-danger">*</span></label>
            <select name="client_id" class="form-select form-select-sm ts-select @error('client_id') is-invalid @enderror" required>
                <option value="">— Select Client —</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" @selected(old('client_id', $selectedClient?->id)==$c->id)>
                        {{ $c->name }} ({{ $c->client_number }})
                    </option>
                @endforeach
            </select>
            @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label mb-1">Loan Product <span class="text-danger">*</span></label>
            <select name="loan_product_id" class="form-select form-select-sm ts-select @error('loan_product_id') is-invalid @enderror"
                    id="productSelect" required>
                <option value="">— Select Loan Product —</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}"
                        data-rate="{{ $p->interest_rate }}"
                        data-method="{{ $p->interest_method }}"
                        data-months="{{ $p->term_months }}"
                        @selected(old('loan_product_id')==$p->id)>
                        {{ $p->name }} ({{ $p->interest_rate }}% {{ ucfirst($p->interest_method) }}, {{ $p->term_months }}m)
                    </option>
                @endforeach
            </select>
            @error('loan_product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label mb-1">Principal Amount <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm">
                <span class="input-group-text text-muted">{{ \App\Models\SystemSetting::get('currency', 'UGX') }}</span>
                <input type="number" name="principal"
                       class="form-control @error('principal') is-invalid @enderror"
                       step="0.01" min="1" value="{{ old('principal') }}" required>
            </div>
            @error('principal')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8">
            <label class="form-label mb-1">Application Notes</label>
            <input type="text" name="notes" class="form-control form-control-sm" value="{{ old('notes') }}" placeholder="Optional">
        </div>
    </div>
</div>

{{-- STEP 2: Loan Terms --}}
<div class="step-pane" id="step-2">
    <h6 class="fw-semibold mb-2"><i class="bi bi-calculator me-1 text-primary"></i>Loan Terms</h6>
    <div class="alert alert-info py-2 small mb-2">
        <i class="bi bi-info-circle me-1"></i>Leave fields blank to use the defaults from the selected loan product.
    </div>
    <div class="row g-2 mb-2">
        <div class="col-sm-4">
            <label class="form-label mb-1">Interest Rate (%)</label>
            <input type="number" name="interest_rate" id="interestRate" class="form-control form-control-sm"
                   step="0.01" min="0" value="{{ old('interest_rate') }}" placeholder="From product">
        </div>
        <div class="col-sm-4">
            <label class="form-label mb-1">Interest Method</label>
            <select name="interest_method" id="interestMethod" class="form-select form-select-sm">
                <option value="">From product</option>
                <option value="flat"     @selected(old('interest_method')=='flat')>Flat</option>
                <option value="reducing" @selected(old('interest_method')=='reducing')>Reducing Balance</option>
            </select>
        </div>
        <div class="col-sm-4">
            <label class="form-label mb-1">Term (months)</label>
            <input type="number" name="term_months" id="termMonths" class="form-control form-control-sm"
                   min="1" value="{{ old('term_months') }}" placeholder="From product">
        </div>
    </div>
    <div id="productSummary" class="p-2 border rounded bg-light small" style="display:none">
        <span class="fw-semibold me-3">Product defaults:</span>
        <span class="me-3">Rate: <strong id="pRate">—</strong>%</span>
        <span class="me-3">Method: <strong id="pMethod">—</strong></span>
        <span>Term: <strong id="pTerm">—</strong> months</span>
    </div>
</div>

{{-- STEP 3: Guarantors --}}
<div class="step-pane" id="step-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-people me-1 text-primary"></i>Guarantors <span class="text-muted fw-normal small">(optional)</span></h6>
        <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="addGuarantor()">
            <i class="bi bi-plus-lg me-1"></i>Add Guarantor
        </button>
    </div>
    <div id="guarantorList"></div>
    <div id="noGuarantorsMsg" class="text-center text-muted py-3 small">
        <i class="bi bi-people d-block mb-1" style="font-size:1.8rem;opacity:.3"></i>
        No guarantors added. Click "Add Guarantor" to add one.
    </div>
</div>

{{-- STEP 4: Collateral --}}
<div class="step-pane" id="step-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-shield-check me-1 text-primary"></i>Collateral <span class="text-muted fw-normal small">(optional)</span></h6>
        <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="addCollateral()">
            <i class="bi bi-plus-lg me-1"></i>Add Collateral
        </button>
    </div>
    <div id="collateralList"></div>
    <div id="noCollateralMsg" class="text-center text-muted py-3 small">
        <i class="bi bi-shield-check d-block mb-1" style="font-size:1.8rem;opacity:.3"></i>
        No collateral added. Click "Add Collateral" to add one.
    </div>
</div>

</div>{{-- card-body --}}

{{-- Navigation --}}
<div class="card-footer d-flex justify-content-between align-items-center py-2 px-3">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPrev" onclick="loanStepNav(-1)" style="display:none">
        <i class="bi bi-arrow-left me-1"></i>Back
    </button>
    <span></span>
    <div class="d-flex gap-2">
        <a href="{{ route('loans.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
        <button type="button" class="btn btn-sm btn-primary" id="btnNext" onclick="loanStepNav(1)">
            Next <i class="bi bi-arrow-right ms-1"></i>
        </button>
        <button type="submit" class="btn btn-sm btn-success" id="btnSubmit" style="display:none">
            <i class="bi bi-check-lg me-1"></i>Submit Application
        </button>
    </div>
</div>

</div>{{-- card --}}
</form>

<script>
// ── Step navigation ───────────────────────────────────────────────────
let loanStep = 1;
const loanTotalSteps = 4;

function loanStepNav(dir) {
    if (dir === 1 && !loanValidateStep(loanStep)) return;
    loanGoToStep(loanStep + dir);
}

function loanGoToStep(n) {
    if (n < 1 || n > loanTotalSteps) return;

    for (let i = 1; i <= loanTotalSteps; i++) {
        const sc = document.getElementById('sc' + i);
        const sl = document.getElementById('sl' + i);
        sc.classList.remove('active', 'done');
        sl.classList.remove('active');
        if (i < n) sc.classList.add('done');
        else if (i === n) { sc.classList.add('active'); sl.classList.add('active'); }
        if (i < loanTotalSteps) {
            document.getElementById('cn' + i).classList.toggle('done', i < n);
        }
    }

    document.querySelectorAll('.step-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('step-' + n).classList.add('active');

    document.getElementById('btnPrev').style.display   = n > 1 ? '' : 'none';
    document.getElementById('btnNext').style.display   = n < loanTotalSteps ? '' : 'none';
    document.getElementById('btnSubmit').style.display = n === loanTotalSteps ? '' : 'none';

    loanStep = n;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function loanValidateStep(step) {
    const pane = document.getElementById('step-' + step);
    const required = pane.querySelectorAll('[required]');
    let valid = true;
    required.forEach(el => {
        el.classList.remove('is-invalid');
        if (!el.value.trim()) { el.classList.add('is-invalid'); valid = false; }
    });
    if (!valid) pane.querySelector('.is-invalid')?.focus();
    return valid;
}

// ── Product select fills step-2 defaults ─────────────────────────────
document.getElementById('productSelect').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const r = opt.dataset.rate, m = opt.dataset.method, t = opt.dataset.months;
    document.getElementById('interestRate').placeholder = r ? r + '%' : 'From product';
    document.getElementById('termMonths').placeholder   = t ?? 'From product';
    const sel = document.getElementById('interestMethod');
    if (m) [...sel.options].forEach(o => { o.selected = o.value === m; });
    const summary = document.getElementById('productSummary');
    if (r || m || t) {
        document.getElementById('pRate').textContent   = r ?? '—';
        document.getElementById('pMethod').textContent = m ? (m === 'flat' ? 'Flat' : 'Reducing') : '—';
        document.getElementById('pTerm').textContent   = t ?? '—';
        summary.style.display = '';
    } else {
        summary.style.display = 'none';
    }
});

// ── Guarantors ────────────────────────────────────────────────────────
let gIndex = 0;
function addGuarantor() {
    const i = gIndex++;
    document.getElementById('noGuarantorsMsg').style.display = 'none';
    const div = document.createElement('div');
    div.className = 'border rounded p-2 mb-2 position-relative';
    div.id = `g-${i}`;
    div.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-1 py-0"
                onclick="removeGuarantor(${i})" title="Remove"><i class="bi bi-trash"></i></button>
        <div class="fw-semibold mb-2 small text-primary">Guarantor #${i + 1}</div>
        <div class="row g-2">
            <div class="col-sm-3">
                <label class="form-label small mb-1">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="guarantors[${i}][name]" class="form-control form-control-sm" required>
            </div>
            <div class="col-sm-3">
                <label class="form-label small mb-1">Phone</label>
                <input type="text" name="guarantors[${i}][phone]" class="form-control form-control-sm">
            </div>
            <div class="col-sm-3">
                <label class="form-label small mb-1">ID / Passport No.</label>
                <input type="text" name="guarantors[${i}][id_number]" class="form-control form-control-sm">
            </div>
            <div class="col-sm-3">
                <label class="form-label small mb-1">Relationship</label>
                <input type="text" name="guarantors[${i}][relationship]" class="form-control form-control-sm" placeholder="e.g. Spouse">
            </div>
            <div class="col-sm-4">
                <label class="form-label small mb-1">Monthly Income</label>
                <input type="number" name="guarantors[${i}][monthly_income]" class="form-control form-control-sm" step="0.01">
            </div>
            <div class="col-sm-4">
                <label class="form-label small mb-1">Employer</label>
                <input type="text" name="guarantors[${i}][employer]" class="form-control form-control-sm">
            </div>
            <div class="col-sm-4">
                <label class="form-label small mb-1">Address</label>
                <input type="text" name="guarantors[${i}][address]" class="form-control form-control-sm">
            </div>
            <div class="col-sm-4">
                <label class="form-label small mb-1">Photo</label>
                <input type="file" name="guarantors[${i}][photo]" class="form-control form-control-sm" accept="image/*">
            </div>
        </div>`;
    document.getElementById('guarantorList').appendChild(div);
}

function removeGuarantor(i) {
    document.getElementById(`g-${i}`)?.remove();
    if (!document.getElementById('guarantorList').children.length) {
        document.getElementById('noGuarantorsMsg').style.display = '';
    }
}

// ── Collateral ────────────────────────────────────────────────────────
const collateralCategories = @json(\App\Models\LoanCollateralCategory::activeOptions());
let cIndex = 0;
function addCollateral() {
    const i = cIndex++;
    document.getElementById('noCollateralMsg').style.display = 'none';
    const options = Object.entries(collateralCategories)
        .map(([key, label]) => `<option value="${key}">${label}</option>`).join('');
    const div = document.createElement('div');
    div.className = 'border rounded p-2 mb-2 position-relative';
    div.id = `c-${i}`;
    div.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-1 py-0"
                onclick="removeCollateral(${i})" title="Remove"><i class="bi bi-trash"></i></button>
        <div class="fw-semibold mb-2 small text-primary">Collateral #${i + 1}</div>
        <div class="row g-2">
            <div class="col-sm-4">
                <label class="form-label small mb-1">Category <span class="text-danger">*</span></label>
                <select name="collaterals[${i}][category]" class="form-select form-select-sm ts-select" required>
                    <option value="">— Select category —</option>${options}
                </select>
            </div>
            <div class="col-sm-4">
                <label class="form-label small mb-1">Description</label>
                <input type="text" name="collaterals[${i}][description]" class="form-control form-control-sm" placeholder="e.g. Reg. no, plot no, cheque no...">
            </div>
            <div class="col-sm-4">
                <label class="form-label small mb-1">Attach Image or Document</label>
                <input type="file" name="collaterals[${i}][attachment]" class="form-control form-control-sm" accept="image/*,.pdf,.doc,.docx">
            </div>
        </div>`;
    document.getElementById('collateralList').appendChild(div);
    div.querySelectorAll('select').forEach(s => initTomSelect(s));
}

function removeCollateral(i) {
    document.getElementById(`c-${i}`)?.remove();
    if (!document.getElementById('collateralList').children.length) {
        document.getElementById('noCollateralMsg').style.display = '';
    }
}
</script>
@endsection
