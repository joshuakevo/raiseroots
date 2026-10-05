@extends('layouts.app')
@section('title', 'New Staff Reimbursement')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('staff-reimbursements.index') }}">Staff Reimbursements</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection
@section('content')
<form method="POST" action="{{ route('staff-reimbursements.store') }}">
@csrf
<div class="card" style="max-width:860px">
    <div class="card-header fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>New Staff Reimbursement</div>
    <div class="card-body">
        @if(session('error'))<div class="alert alert-danger small">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger small"><ul class="mb-0 ps-3">@foreach($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul></div>
        @endif

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label fw-semibold">Staff <span class="text-danger">*</span></label>
                <select name="employee_id" id="employeeSelect" class="form-select ts-select" required>
                    <option value="">— Select staff —</option>
                    @foreach($employees as $e)
                        <option value="{{ $e->id }}" data-savings="{{ $e->savingsAccount?->status === 'active' ? $e->savingsAccount->account_number : '' }}"
                                @selected(old('employee_id') == $e->id)>{{ $e->name }} ({{ $e->employee_number }}){{ $e->position ? ' — ' . $e->position : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                <input type="date" name="date" class="form-control" required max="{{ today()->toDateString() }}" value="{{ old('date', today()->toDateString()) }}">
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold">What is it for? <span class="text-danger">*</span></label>
                <input type="text" name="description" class="form-control" required maxlength="255" value="{{ old('description') }}" placeholder="e.g. Transport to field visits, airtime, stationery">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Amount Claimed <span class="text-danger">*</span></label>
                <input type="number" name="amount" id="amount" class="form-control text-end" required min="1" step="any" value="{{ old('amount') }}">
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold">Expense Account <span class="text-danger">*</span></label>
                <select name="expense_account_id" id="expenseAccount" class="form-select ts-select" required>
                    <option value="">— Select —</option>
                    @foreach($expenseAccounts as $a)
                        <option value="{{ $a->id }}" @selected(old('expense_account_id') == $a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <hr class="my-4">
        <h6 class="fw-semibold mb-1">Deduction <span class="text-muted fw-normal small">(optional)</span></h6>
        <p class="text-muted small mb-3">Anything taken off before paying, e.g. an advance already given, or part of the claim that isn't allowed.</p>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Deduction Amount</label>
                <input type="number" name="deduction" id="deduction" class="form-control text-end" min="0" step="any" value="{{ old('deduction', 0) }}">
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold">Reason</label>
                <input type="text" name="deduction_reason" class="form-control js-ded" maxlength="255" value="{{ old('deduction_reason') }}" placeholder="e.g. Advance of 20 Sep recovered">
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold">Deduction Goes To</label>
                <select name="deduction_account_id" id="deductionAccount" class="form-select ts-select js-ded">
                    <option value="">— Same as expense account (part not allowed) —</option>
                    @foreach($allAccounts as $a)
                        <option value="{{ $a->id }}" @selected(old('deduction_account_id') == $a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                    @endforeach
                </select>
                <div class="form-text">Recovering an advance? Choose the staff advances account. Otherwise leave it on the expense account.</div>
            </div>
        </div>

        <hr class="my-4">
        <h6 class="fw-semibold mb-3">Pay To</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_method" id="payCash" value="cash" @checked(old('payment_method', 'cash') === 'cash')>
                    <label class="form-check-label" for="payCash">Cash / Bank</label>
                </div>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="radio" name="payment_method" id="paySavings" value="savings" @checked(old('payment_method') === 'savings')>
                    <label class="form-check-label" for="paySavings">Staff's savings account</label>
                </div>
            </div>
            <div class="col-md-8" id="cashBox">
                <label class="form-label fw-semibold">Paid From</label>
                <select name="payment_account_id" class="form-select">
                    @foreach($paymentAccounts as $a)
                        <option value="{{ $a->id }}" @selected(old('payment_account_id') == $a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8 d-none" id="savingsBox">
                <label class="form-label fw-semibold">Savings Account</label>
                <div class="form-control-plaintext font-monospace" id="savingsInfo">—</div>
            </div>
        </div>

        <div class="alert alert-success d-flex justify-content-between align-items-center mt-4 mb-0">
            <span>Amount <strong id="sumAmount">0</strong> − Deduction <strong id="sumDed">0</strong></span>
            <span class="fs-5">Net to pay: <strong id="sumNet">0</strong></span>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('staff-reimbursements.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary" onclick="return confirm('Pay this reimbursement now? It will be posted to the books.')"><i class="bi bi-check-lg me-1"></i>Pay Reimbursement</button>
    </div>
</div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    const fmt = n => (Math.round(n * 100) / 100).toLocaleString();
    const amount = document.getElementById('amount'), ded = document.getElementById('deduction');
    function refresh() {
        const a = parseFloat(amount.value) || 0, d = parseFloat(ded.value) || 0;
        document.getElementById('sumAmount').textContent = fmt(a);
        document.getElementById('sumDed').textContent = fmt(d);
        const net = document.getElementById('sumNet');
        net.textContent = fmt(a - d);
        net.classList.toggle('text-danger', d > a);
    }
    amount.addEventListener('input', refresh);
    ded.addEventListener('input', refresh);
    refresh();

    // "Same as expense account" = send the expense account as the deduction account.
    document.querySelector('form').addEventListener('submit', () => {
        const dedAcc = document.getElementById('deductionAccount');
        if ((parseFloat(ded.value) || 0) > 0 && !dedAcc.value) {
            const exp = document.getElementById('expenseAccount').value;
            if (dedAcc.tomselect) dedAcc.tomselect.setValue(exp, true); else dedAcc.value = exp;
        }
    });

    const emp = document.getElementById('employeeSelect');
    function payMethod() {
        const savings = document.getElementById('paySavings').checked;
        document.getElementById('cashBox').classList.toggle('d-none', savings);
        document.getElementById('savingsBox').classList.toggle('d-none', !savings);
        const opt = emp.options[emp.selectedIndex];
        const acc = opt ? opt.dataset.savings : '';
        document.getElementById('savingsInfo').innerHTML = acc
            ? acc
            : '<span class="text-danger font-sans small">This staff member has no active savings account linked.</span>';
    }
    document.querySelectorAll('[name="payment_method"]').forEach(r => r.addEventListener('change', payMethod));
    emp.addEventListener('change', payMethod);
    payMethod();
})();
</script>
@endpush
