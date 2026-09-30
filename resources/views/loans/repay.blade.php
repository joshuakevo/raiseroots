@extends('layouts.app')
@section('title', 'Record Repayment')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item"><a href="{{ route('loans.show', $loan) }}">{{ $loan->loan_number }}</a></li>
    <li class="breadcrumb-item active">Repayment</li>
@endsection
@section('content')
<div class="row g-3">

    {{-- Left: Loan summary --}}
    <div class="col-md-4">

        <div class="card mb-3">
            <div class="card-header fw-semibold">Loan Summary</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Loan</span><span class="fw-semibold">{{ $loan->loan_number }}</span></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Client</span><span>{{ $loan->client->name }}</span></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Product</span><span>{{ $loan->product->name }}</span></div>
                <hr class="my-2">
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Principal O/S</span><span class="fw-semibold text-warning">{{ number_format($loan->outstanding_principal, $dp) }}</span></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Interest O/S</span><span class="fw-semibold text-info">{{ number_format($loan->outstanding_interest, $dp) }}</span></div>
                @if($penaltyDue > 0)
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Penalty</span><span class="fw-semibold text-danger">{{ number_format($penaltyDue, $dp) }}</span></div>
                @endif
                @if($adminCostPending > 0)
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Admin Cost <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size:.6rem">pending</span></span><span class="fw-semibold text-warning-emphasis">{{ number_format($adminCostPending, $dp) }}</span></div>
                @endif
                <hr class="my-2">
                <div class="d-flex justify-content-between"><span class="fw-semibold">Total Outstanding</span><span class="fw-bold text-danger">{{ number_format($loan->total_outstanding + $penaltyDue + $adminCostPending, $dp) }}</span></div>
                @if($adminCostPending > 0)
                <div class="text-muted mt-1" style="font-size:.68rem">Includes {{ number_format($adminCostPending, $dp) }} pending Admin Cost — collected separately, not part of the repayment below.</div>
                @endif
            </div>
        </div>

        {{-- Next installment --}}
        @if($nextInstallment)
        <div class="card mb-3 border-primary">
            <div class="card-header fw-semibold text-primary">
                <i class="bi bi-calendar3 me-1"></i>Next Installment #{{ $nextInstallment->installment_no }}
            </div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Due Date</span>
                    <span class="{{ $nextInstallment->isOverdue() ? 'text-danger fw-semibold' : '' }}">
                        {{ $nextInstallment->due_date->format('d M Y') }}
                        @if($nextInstallment->isOverdue()) <span class="badge bg-danger ms-1">Overdue</span> @endif
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Principal</span><span>{{ number_format($nextInstallment->principal_due - $nextInstallment->principal_paid, $dp) }}</span></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Interest</span><span>{{ number_format($nextInstallment->interest_due - $nextInstallment->interest_paid, $dp) }}</span></div>
                <hr class="my-2">
                <div class="d-flex justify-content-between"><span class="fw-semibold">Installment Due</span><span class="fw-bold">{{ number_format($suggestedAmount, $dp) }}</span></div>
            </div>
        </div>
        @endif

        @if($overdueInstallments->count() > 1)
        <div class="alert alert-danger small py-2">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>{{ $overdueInstallments->count() }} overdue installments</strong> totalling {{ number_format($overdueAmount, $dp) }}
        </div>
        @endif

        {{-- Savings accounts --}}
        @if($savingsAccounts->isNotEmpty())
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-piggy-bank me-1"></i>Savings Accounts</div>
            <div class="card-body small p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                    @foreach($savingsAccounts as $sa)
                    <tr>
                        <td class="ps-3">{{ $sa->account_number }}<br><span class="text-muted" style="font-size:.7rem">{{ $sa->product->name }}</span></td>
                        <td class="text-end pe-3 fw-semibold {{ $sa->balance >= $suggestedAmount ? 'text-success' : 'text-warning' }}">
                            {{ number_format($sa->balance, $dp) }}
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- Right: Payment form --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header fw-semibold">Record Repayment</div>
            <div class="card-body">
                <form method="POST" action="{{ route('loans.repay', $loan) }}" id="repayForm">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Source <span class="text-danger">*</span></label>
                            <select name="payment_source_account_id" class="form-select" id="paymentMethod" onchange="onMethodChange()" required>
                                @if($savingsAccounts->isNotEmpty())
                                <option value="savings" {{ old('payment_source_account_id') === 'savings' ? 'selected' : '' }}>Deduct from Savings Account</option>
                                @endif
                                @foreach($paymentSourceAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ old('payment_source_account_id') == $acc->id ? 'selected' : '' }}>
                                    {{ $acc->account_code }} — {{ $acc->account_name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Savings account selector (shown only when method = savings) --}}
                    @if($savingsAccounts->isNotEmpty())
                    <div class="mb-3" id="savingsAccountRow" style="display:none">
                        <label class="form-label fw-semibold">Savings Account</label>
                        <select name="savings_account_id" class="form-select" id="savingsAccountSelect" onchange="onSavingsAccountChange()">
                            @foreach($savingsAccounts as $sa)
                            <option value="{{ $sa->id }}"
                                data-balance="{{ $sa->balance }}"
                                {{ old('savings_account_id') == $sa->id ? 'selected' : '' }}>
                                {{ $sa->account_number }} — {{ $sa->product->name }} (Bal: {{ number_format($sa->balance, $dp) }})
                            </option>
                            @endforeach
                        </select>
                        <div class="form-text" id="savingsBalanceHint"></div>
                    </div>
                    @endif

                    {{-- Amount options --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount to Pay</label>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @if($nextInstallment)
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="setAmount({{ $suggestedAmount }})">
                                Installment #{{ $nextInstallment->installment_no }} — {{ number_format($suggestedAmount, $dp) }}
                            </button>
                            @endif
                            @if($overdueAmount > 0 && $overdueInstallments->count() > 1)
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="setAmount({{ round($overdueAmount, 2) }})">
                                All Overdue — {{ number_format($overdueAmount, $dp) }}
                            </button>
                            @endif
                            @php $totalOwed = round($loan->total_outstanding + $penaltyDue, 2); @endphp
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAmount({{ $totalOwed }})">
                                Full Outstanding — {{ number_format($totalOwed, $dp) }}
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="setAmount({{ $earlySettlement['total'] }})">
                                <i class="bi bi-lightning-fill me-1"></i>Early Settlement — {{ number_format($earlySettlement['total'], $dp) }}
                            </button>
                        </div>
                        @if($earlySettlement['total'] < $totalOwed)
                        <div class="alert alert-warning py-2 small mb-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Early Settlement:</strong>
                            Principal {{ number_format($earlySettlement['principal'], $dp) }}
                            + Interest to date {{ number_format($earlySettlement['interest'], $dp) }}
                            @if($earlySettlement['penalty'] > 0) + Penalty {{ number_format($earlySettlement['penalty'], $dp) }} @endif
                            = <strong>{{ number_format($earlySettlement['total'], $dp) }}</strong>
                            <span class="text-success ms-1">(saves {{ number_format($totalOwed - $earlySettlement['total'], $dp) }} in future interest)</span>
                        </div>
                        @endif
                        <div class="input-group">
                            <span class="input-group-text">{{ \App\Models\SystemSetting::get('currency', 'KES') }}</span>
                            <input type="number" name="amount" id="amountInput"
                                class="form-control @error('amount') is-invalid @enderror"
                                step="0.01" min="0.01"
                                value="{{ old('amount', $suggestedAmount) }}"
                                required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-text" id="amountBreakdown"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                        <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" placeholder="Cheque no., bank ref, etc." required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>

                    <div class="alert alert-secondary small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Allocation: <strong>Penalty → then per installment (Interest → Principal), earliest first</strong>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Process Repayment</button>
                        <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
var penaltyDue = {{ $penaltyDue }};
var schedules  = @json($schedulesJson);
var dp         = {{ $dp }};

function fmt(val) {
    return parseFloat(val).toFixed(dp);
}

function setAmount(val) {
    document.getElementById('amountInput').value = fmt(val);
    updateBreakdown();
}

function updateBreakdown() {
    var amount    = parseFloat(document.getElementById('amountInput').value) || 0;
    var remaining = amount;
    var totalInterest = 0, totalPrincipal = 0, totalPenalty = 0;
    var installmentsCovered = [];

    // Penalty first
    var pPenalty = Math.min(remaining, penaltyDue);
    remaining -= pPenalty;
    totalPenalty = pPenalty;

    // Per-installment: interest then principal
    var schCopy = schedules.map(function(s) { return {installment_no: s.installment_no, iRem: s.interest_rem, pRem: s.principal_rem}; });
    for (var i = 0; i < schCopy.length && remaining > 0; i++) {
        var s = schCopy[i];
        var iApply = Math.min(remaining, s.iRem); remaining -= iApply; totalInterest += iApply; s.iRem -= iApply;
        var pApply = Math.min(remaining, s.pRem); remaining -= pApply; totalPrincipal += pApply; s.pRem -= pApply;
        if (iApply > 0 || pApply > 0) installmentsCovered.push(s.installment_no);
    }

    var parts = [];
    if (totalPenalty  > 0) parts.push('Penalty: ' + fmt(totalPenalty));
    if (totalInterest > 0) parts.push('Interest: ' + fmt(totalInterest));
    if (totalPrincipal> 0) parts.push('Principal: ' + fmt(totalPrincipal));
    if (installmentsCovered.length > 0) parts.push('Installments: #' + installmentsCovered.join(', #'));

    document.getElementById('amountBreakdown').textContent = parts.length ? 'Allocation → ' + parts.join(' | ') : '';
}

function onMethodChange() {
    var val = document.getElementById('paymentMethod').value;
    var row = document.getElementById('savingsAccountRow');
    if (row) row.style.display = val === 'savings' ? '' : 'none';
    if (val === 'savings') onSavingsAccountChange();
}

function onSavingsAccountChange() {
    var sel = document.getElementById('savingsAccountSelect');
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var bal = parseFloat(opt.getAttribute('data-balance')) || 0;
    var hint = document.getElementById('savingsBalanceHint');
    hint.textContent = 'Available balance: ' + fmt(bal);
    hint.className = 'form-text ' + (bal >= parseFloat(document.getElementById('amountInput').value || 0) ? 'text-success' : 'text-danger');
}

document.getElementById('amountInput').addEventListener('input', function() {
    updateBreakdown();
    if (document.getElementById('paymentMethod').value === 'savings') onSavingsAccountChange();
});

updateBreakdown();
onMethodChange();
</script>
@endpush
