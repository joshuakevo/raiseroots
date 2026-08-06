@extends('layouts.app')
@section('title', 'New Loan Product')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loan-products.index') }}">Loan Products</a></li>
    <li class="breadcrumb-item active">New Product</li>
@endsection
@section('content')
<div class="row g-4">
    {{-- Form --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">New Loan Product</div>
            <div class="card-body">
                <form method="POST" action="{{ route('loan-products.store') }}" id="productForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Interest Rate (% p.a.) <span class="text-danger">*</span></label>
                            <input type="number" name="interest_rate" id="pv_rate" class="form-control" step="0.01" min="0" value="{{ old('interest_rate') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Interest Method <span class="text-danger">*</span></label>
                            <select name="interest_method" id="pv_method" class="form-select" required>
                                <option value="flat"     @selected(old('interest_method')=='flat')>Flat Rate</option>
                                <option value="reducing" @selected(old('interest_method')=='reducing')>Reducing Balance</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Repayment Frequency <span class="text-danger">*</span></label>
                            <select name="repayment_frequency" id="pv_freq" class="form-select" required>
                                <option value="daily"     @selected(old('repayment_frequency')==='daily')>Daily</option>
                                <option value="weekly"    @selected(old('repayment_frequency')==='weekly')>Weekly</option>
                                <option value="monthly"   @selected(old('repayment_frequency','monthly')==='monthly')>Monthly</option>
                                <option value="quarterly" @selected(old('repayment_frequency')==='quarterly')>Quarterly (every 3 months)</option>
                                <option value="annually"  @selected(old('repayment_frequency')==='annually')>Annually (every 12 months)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Term (months) <span class="text-danger">*</span></label>
                            <input type="number" name="term_months" id="pv_term" class="form-control" min="1" value="{{ old('term_months') }}" required>
                            <div class="form-text" id="termHint"></div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Penalty Rate (%/day)</label>
                            <input type="number" name="penalty_rate" class="form-control" step="0.001" min="0" value="{{ old('penalty_rate', 0) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Min Amount</label>
                            <input type="number" name="min_amount" class="form-control" step="0.01" min="0" value="{{ old('min_amount', 0) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Amount</label>
                            <input type="number" name="max_amount" class="form-control" step="0.01" min="0" value="{{ old('max_amount') }}">
                        </div>
                    </div>

                    <h6 class="fw-semibold mt-4 mb-3">Linked GL Accounts</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Loan Receivable Account</label>
                            <select name="receivable_account_id" class="form-select ts-select">
                                <option value="">— None —</option>
                                @foreach($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('receivable_account_id')==$a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Interest Income Account</label>
                            <select name="interest_income_account_id" class="form-select ts-select">
                                <option value="">— None —</option>
                                @foreach($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('interest_income_account_id')==$a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Penalty Income Account</label>
                            <select name="penalty_income_account_id" class="form-select ts-select">
                                <option value="">— None —</option>
                                @foreach($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('penalty_income_account_id')==$a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cash/Bank Account (Disbursement)</label>
                            <select name="disbursement_account_id" class="form-select ts-select">
                                <option value="">— None —</option>
                                @foreach($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('disbursement_account_id')==$a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active',1))>
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Save Product</button>
                        <a href="{{ route('loan-products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Live Schedule Preview --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Schedule Preview</span>
                <span class="text-muted small fw-normal">Sample amount:</span>
                <input type="number" id="pv_principal" class="form-control form-control-sm" style="max-width:150px" value="1000000" min="1" step="1">
            </div>
            <div class="card-body p-0" id="previewBody">
                <div class="text-center text-muted py-4 small">Fill in rate, method, frequency and term to preview</div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var timer = null;
    var previewUrl = '{{ route("loan-products.schedule-preview") }}';

    function updateHint() {
        var freq = document.getElementById('pv_freq').value;
        var hint = document.getElementById('termHint');
        hint.textContent = freq === 'quarterly' ? 'Must be a multiple of 3 (e.g. 12, 24, 36)' : '';
    }

    function fetchPreview() {
        var rate    = document.getElementById('pv_rate').value;
        var method  = document.getElementById('pv_method').value;
        var freq    = document.getElementById('pv_freq').value;
        var term    = document.getElementById('pv_term').value;
        var amount  = document.getElementById('pv_principal').value;

        if (!rate || !term || !amount) return;

        document.getElementById('previewBody').innerHTML = '<div class="text-center text-muted py-3 small">Loading…</div>';

        var params = new URLSearchParams({
            principal: amount, interest_rate: rate,
            interest_method: method, repayment_frequency: freq, term_months: term
        });

        fetch(previewUrl + '?' + params.toString(), {
            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                document.getElementById('previewBody').innerHTML =
                    '<div class="alert alert-warning m-3 small">' + data.error + '</div>';
                return;
            }
            renderPreview(data.rows, data.frequency);
        })
        .catch(function() {
            document.getElementById('previewBody').innerHTML =
                '<div class="text-danger text-center py-3 small">Preview unavailable</div>';
        });
    }

    function fmt(n) { return Number(n).toLocaleString('en-UG', {minimumFractionDigits:0, maximumFractionDigits:0}); }

    function renderPreview(rows, frequency) {
        if (!rows || rows.length === 0) {
            document.getElementById('previewBody').innerHTML = '<div class="text-muted text-center py-3 small">No data</div>';
            return;
        }

        var isQuarterly = frequency === 'quarterly';
        var html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0" style="font-size:.78rem">';

        // Header
        html += '<thead class="table-dark"><tr>';
        html += '<th class="ps-3">#</th><th>Due Date</th>';
        html += '<th class="text-end">Principal</th><th class="text-end">Interest</th><th class="text-end">Total Due</th><th class="text-end pe-2">Balance</th>';
        if (isQuarterly) {
            html += '<th class="text-end text-warning" title="Equivalent per month within the quarter">Mo. Equiv.</th>';
        }
        html += '</tr></thead><tbody>';

        var totP = 0, totI = 0, totT = 0;
        rows.forEach(function(r) {
            totP += r.principal_due; totI += r.interest_due; totT += r.total_due;
            html += '<tr>';
            html += '<td class="ps-3 text-muted">' + (isQuarterly ? 'Q' : '') + r.installment_no + '</td>';
            html += '<td>' + r.due_date + '</td>';
            html += '<td class="text-end">' + fmt(r.principal_due) + '</td>';
            html += '<td class="text-end text-warning">' + fmt(r.interest_due) + '</td>';
            html += '<td class="text-end fw-semibold">' + fmt(r.total_due) + '</td>';
            html += '<td class="text-end pe-2 text-muted">' + fmt(r.balance_after) + '</td>';
            if (isQuarterly) {
                html += '<td class="text-end text-info">' + fmt(r.monthly_total) + '</td>';
            }
            html += '</tr>';

            // Monthly breakdown rows for quarterly
            if (isQuarterly) {
                for (var m = 1; m <= 3; m++) {
                    html += '<tr style="background:#f9fafb;font-size:.72rem;color:#6b7280">';
                    html += '<td class="ps-4 text-muted">↳ Mo ' + m + '</td>';
                    html += '<td class="text-muted" style="font-size:.7rem">within Q' + r.installment_no + '</td>';
                    html += '<td class="text-end">' + fmt(r.monthly_principal) + '</td>';
                    html += '<td class="text-end">' + fmt(r.monthly_interest) + '</td>';
                    html += '<td class="text-end">' + fmt(r.monthly_total) + '</td>';
                    html += '<td class="text-end pe-2">—</td>';
                    html += '<td></td>';
                    html += '</tr>';
                }
            }
        });

        // Footer totals
        html += '</tbody><tfoot class="table-secondary fw-semibold" style="font-size:.78rem"><tr>';
        html += '<td colspan="2" class="ps-3">Totals (' + rows.length + ' ' + (isQuarterly ? 'quarters' : 'months') + ')</td>';
        html += '<td class="text-end">' + fmt(totP) + '</td>';
        html += '<td class="text-end text-warning">' + fmt(totI) + '</td>';
        html += '<td class="text-end">' + fmt(totT) + '</td>';
        html += '<td class="pe-2"></td>';
        if (isQuarterly) html += '<td></td>';
        html += '</tr></tfoot></table></div>';

        if (isQuarterly) {
            html += '<div class="px-3 py-2 text-muted" style="font-size:.72rem">'
                + '<i class="bi bi-info-circle me-1"></i>'
                + 'Quarterly schedule: actual payment every 3 months. '
                + '<span class="text-info">Mo. Equiv.</span> = quarterly amount ÷ 3 (informational only).'
                + '</div>';
        }

        document.getElementById('previewBody').innerHTML = html;
    }

    function scheduleRefresh() {
        clearTimeout(timer);
        timer = setTimeout(fetchPreview, 400);
    }

    ['pv_rate','pv_method','pv_freq','pv_term','pv_principal'].forEach(function(id) {
        document.getElementById(id).addEventListener('input', function() { updateHint(); scheduleRefresh(); });
        document.getElementById(id).addEventListener('change', function() { updateHint(); scheduleRefresh(); });
    });

    updateHint();
})();
</script>
@endsection
