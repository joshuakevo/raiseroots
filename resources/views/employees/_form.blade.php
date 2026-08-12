@php
    $paymentMethod = old('payment_method', $employee->payment_method ?? 'cash');
@endphp
<div class="row g-2 g-lg-3">
    <div class="col-md-4">
        <label class="form-label small mb-0 fw-semibold">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
               value="{{ old('name', $employee->name ?? '') }}" required>
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-0 fw-semibold">Phone</label>
        <input type="text" name="phone" class="form-control form-control-sm" value="{{ old('phone', $employee->phone ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-0 fw-semibold">Email</label>
        <input type="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror"
               value="{{ old('email', $employee->email ?? '') }}">
        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label small mb-0 fw-semibold">ID / Passport No.</label>
        <input type="text" name="id_number" class="form-control form-control-sm" value="{{ old('id_number', $employee->id_number ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-0 fw-semibold">Position</label>
        <input type="text" name="position" class="form-control form-control-sm" value="{{ old('position', $employee->position ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-0 fw-semibold">Department</label>
        <input type="text" name="department" class="form-control form-control-sm" value="{{ old('department', $employee->department ?? '') }}">
    </div>

    <div class="col-md-4">
        <label class="form-label small mb-0 fw-semibold">Basic salary (UGX) <span class="text-danger">*</span></label>
        <input type="number" name="basic_salary" class="form-control form-control-sm @error('basic_salary') is-invalid @enderror"
               value="{{ old('basic_salary', $employee->basic_salary ?? 0) }}" min="0" step="1000" required>
        @error('basic_salary')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-0 fw-semibold">Status <span class="text-danger">*</span></label>
        <select name="status" class="form-select form-select-sm" required>
            <option value="active"   {{ old('status', $employee->status ?? 'active') === 'active'   ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $employee->status ?? 'active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label small mb-0 fw-semibold">Salary Payout <span class="text-danger">*</span></label>
        <select name="payment_method" id="paymentMethod" class="form-select form-select-sm @error('payment_method') is-invalid @enderror" required>
            <option value="cash"    {{ $paymentMethod === 'cash'    ? 'selected' : '' }}>Cash / Bank</option>
            <option value="savings" {{ $paymentMethod === 'savings' ? 'selected' : '' }}>Savings Account (member only)</option>
        </select>
        @error('payment_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6" id="cashBlock">
        <label class="form-label small mb-0 fw-semibold">Payout Account <span class="text-danger">*</span></label>
        <select name="payment_source_account_id" class="form-select form-select-sm @error('payment_source_account_id') is-invalid @enderror">
            <option value="">— Select payout account —</option>
            @foreach($paymentSourceAccounts as $acc)
            <option value="{{ $acc->id }}" {{ old('payment_source_account_id', $employee->payment_source_account_id ?? '') == $acc->id ? 'selected' : '' }}>
                {{ $acc->account_code }} — {{ $acc->account_name }}
            </option>
            @endforeach
        </select>
        @error('payment_source_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="form-text lh-sm" style="font-size:.7rem">Salary is paid directly from this GL account — no member/savings link needed.</div>
    </div>

    <div class="col-lg-5" id="savingsClientBlock">
        <label class="form-label small mb-0 fw-semibold">Client <span class="text-danger">*</span></label>
        <select name="client_id" id="clientSelect" class="form-select form-select-sm @error('client_id') is-invalid @enderror">
            <option value="">— Select client —</option>
            @foreach($clients as $client)
            <option value="{{ $client->id }}" {{ old('client_id', $employee->client_id ?? '') == $client->id ? 'selected' : '' }}>
                {{ $client->name }}
            </option>
            @endforeach
        </select>
        @error('client_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="form-text lh-sm" style="font-size:.7rem">Only for staff who are also SACCO members. Only clients with an active savings account are listed.</div>
    </div>
    <div class="col-lg-7" id="savingsAccountBlock">
        <label class="form-label small mb-0 fw-semibold">Salary savings account <span class="text-danger">*</span></label>
        <select name="savings_account_id" id="savingsSelect" class="form-select form-select-sm @error('savings_account_id') is-invalid @enderror">
            <option value="">— Select client first —</option>
        </select>
        @error('savings_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-12">
        <details class="border rounded px-2 py-1 bg-light" {{ old('notes', $employee->notes ?? '') ? 'open' : '' }}>
            <summary class="small fw-semibold user-select-none" style="cursor:pointer">Notes <span class="text-muted fw-normal">(optional)</span></summary>
            <textarea name="notes" class="form-control form-control-sm mt-1" rows="1" placeholder="Optional">{{ old('notes', $employee->notes ?? '') }}</textarea>
        </details>
    </div>
</div>

@push('scripts')
<script>
const allSavingsAccounts = @json($savingsAccounts);
const oldClientId  = '{{ old('client_id', $employee->client_id ?? '') }}';
const oldAccountId = '{{ old('savings_account_id', $employee->savings_account_id ?? '') }}';

const paymentMethodSel = document.getElementById('paymentMethod');
const cashBlock         = document.getElementById('cashBlock');
const savingsClientBlk  = document.getElementById('savingsClientBlock');
const savingsAcctBlk    = document.getElementById('savingsAccountBlock');
const clientSel  = document.getElementById('clientSelect');
const savingsSel = document.getElementById('savingsSelect');

function populateAccounts(clientId, selectedId) {
    savingsSel.innerHTML = '<option value="">— Select account —</option>';
    const accounts = allSavingsAccounts[clientId] || [];
    accounts.forEach(sa => {
        const opt = document.createElement('option');
        opt.value = sa.id;
        opt.textContent = sa.account_number + ' — ' + (sa.product ? sa.product.name : '');
        if (String(sa.id) === String(selectedId)) opt.selected = true;
        savingsSel.appendChild(opt);
    });
    if (accounts.length === 1) savingsSel.selectedIndex = 1;
}

function toggleMethodBlocks() {
    const isSavings = paymentMethodSel.value === 'savings';
    cashBlock.style.display        = isSavings ? 'none' : '';
    savingsClientBlk.style.display = isSavings ? '' : 'none';
    savingsAcctBlk.style.display   = isSavings ? '' : 'none';
    clientSel.required  = isSavings;
    savingsSel.required = isSavings;
    document.querySelector('[name=payment_source_account_id]').required = !isSavings;
}

clientSel.addEventListener('change', () => populateAccounts(clientSel.value, ''));
paymentMethodSel.addEventListener('change', toggleMethodBlocks);

if (oldClientId) populateAccounts(oldClientId, oldAccountId);
toggleMethodBlocks();
</script>
@endpush
