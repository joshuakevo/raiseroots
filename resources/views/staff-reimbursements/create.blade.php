@extends('layouts.app')
@php $editing = isset($run); @endphp
@section('title', $editing ? 'Edit Reimbursement Run' : 'New Reimbursement Run')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('staff-reimbursements.index') }}">Staff Reimbursements</a></li>
    @if($editing)
    <li class="breadcrumb-item"><a href="{{ route('staff-reimbursements.show', $run) }}">{{ $run->run_number }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
    @else
    <li class="breadcrumb-item active">New Run</li>
    @endif
@endsection
@section('content')
<div class="card">
    <div class="card-header fw-semibold">{{ $editing ? 'Edit Reimbursement Run — ' . $run->run_number : 'New Staff Reimbursement Run' }}</div>
    <div class="card-body">
    @if($errors->any())
        <div class="alert alert-danger small"><ul class="mb-0 ps-3">@foreach($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $editing ? route('staff-reimbursements.update', $run) : route('staff-reimbursements.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                <select name="period_month" class="form-select" required>
                    @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" @selected(old('period_month', $editing ? $run->period_month : now()->month) == $m)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Year <span class="text-danger">*</span></label>
                <input type="number" name="period_year" class="form-control" value="{{ old('period_year', $editing ? $run->period_year : now()->year) }}" min="2000" max="2100" required>
            </div>
            <div class="col-md-7">
                <label class="form-label fw-semibold">Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description', $editing ? $run->description : '') }}" placeholder="e.g. Field transport, September">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Expense Account <span class="text-danger">*</span></label>
                <select name="expense_account_id" class="form-select ts-select" required>
                    <option value="">— Select —</option>
                    @foreach($expenseAccounts as $a)
                        <option value="{{ $a->id }}" @selected(old('expense_account_id', $editing ? $run->expense_account_id : null) == $a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Deductions Go To</label>
                <select name="deduction_account_id" class="form-select ts-select">
                    <option value="">— Same as expense account (part not allowed) —</option>
                    @foreach($allAccounts as $a)
                        <option value="{{ $a->id }}" @selected(old('deduction_account_id', $editing ? $run->deduction_account_id : null) == $a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                    @endforeach
                </select>
                <div class="form-text">Recovering advances? Choose the staff advances account.</div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">Staff</h6>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addAllEmployees()">
                <i class="bi bi-people me-1"></i>Add All Active
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="itemsTable">
                <thead>
                    <tr>
                        <th class="rb-emp">Staff</th>
                        <th class="rb-desc">What For</th>
                        <th class="text-end rb-g-amt">Amount</th>
                        <th class="text-end rb-g-ded">Deduction</th>
                        <th class="rb-g-ded rb-reason">Deduction Reason</th>
                        <th class="text-end rb-g-pay">Net to Pay</th>
                        <th style="width:44px"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">{{-- rows added by JS --}}</tbody>
                <tfoot>
                    <tr class="fw-bold rb-totals">
                        <td colspan="2" class="text-end">Totals:</td>
                        <td class="text-end" id="totAmount">0</td>
                        <td class="text-end text-danger" id="totDed">0</td>
                        <td></td>
                        <td class="text-end rb-g-pay" id="totNet">0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="form-text mb-2">Staff left at 0 are skipped when you save, so "Add All Active" and fill in just the ones being reimbursed.</div>
        <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addRow()">
            <i class="bi bi-plus me-1"></i>Add Staff Row
        </button>

        <div class="d-flex gap-2 mt-2">
            <button class="btn btn-primary">{{ $editing ? 'Update Run' : 'Save Run' }}</button>
            <a href="{{ $editing ? route('staff-reimbursements.show', $run) : route('staff-reimbursements.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
    </div>
</div>
@endsection
@push('styles')
<style>
    #itemsTable { min-width: 1000px; border: 1px solid var(--bs-border-color); }
    #itemsTable thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; font-weight: 600; color: #6b7280; white-space: nowrap; vertical-align: middle; padding-top: .6rem; padding-bottom: .5rem; }
    #itemsTable .rb-emp { min-width: 250px; }
    #itemsTable .rb-desc { min-width: 200px; }
    #itemsTable .rb-reason { min-width: 170px; }
    #itemsTable .rb-meta { font-size: .72rem; color: #6b7280; margin-top: 3px; min-height: 1em; }
    #itemsTable .rb-input { text-align: right; min-width: 115px; font-variant-numeric: tabular-nums; }
    #itemsTable .rb-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; min-width: 105px; }
    #itemsTable tbody td { padding: .55rem .5rem; border-bottom: 1px solid var(--bs-border-color); }
    #itemsTable .rb-g-amt { background: #f8fafc; }
    #itemsTable .rb-g-ded { background: #fef6f6; }
    #itemsTable .rb-g-pay { background: #ecfdf3; color: #065f46; }
    #itemsTable .rb-totals td { background: #f8fafc; border-top: 2px solid var(--bs-border-color); font-variant-numeric: tabular-nums; white-space: nowrap; }
    #itemsTable .rb-totals td.rb-g-pay { background: #ecfdf3; }
</style>
@endpush
@push('scripts')
<script>
@php $staffList = $employees->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'employee_number' => $e->employee_number, 'position' => $e->position])->values(); @endphp
const employees = {!! json_encode($staffList) !!};
let rowIndex = 0;
const fmt = n => n.toLocaleString('en-US', { maximumFractionDigits: 0 });
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function selectedEmployeeIds(excludeRowId) {
    const ids = new Set();
    document.querySelectorAll('[name$="[employee_id]"]').forEach(sel => {
        if (sel.closest('tr').id !== 'row_' + excludeRowId && sel.value) ids.add(sel.value);
    });
    return ids;
}

function empMeta(empId) {
    const e = employees.find(x => String(x.id) === String(empId));
    return e ? [e.position, e.employee_number].filter(Boolean).join(' · ') : '';
}

function addRow(empId = '', desc = '', amount = 0, ded = 0, reason = '') {
    const i = rowIndex++;
    const used = selectedEmployeeIds(-1);
    const opts = employees.map(e => {
        const isUsed = used.has(String(e.id)) && String(e.id) !== String(empId);
        return `<option value="${e.id}" ${String(e.id) === String(empId) ? 'selected' : ''} ${isUsed ? 'disabled' : ''}>${esc(e.name)} — ${esc(e.employee_number)}${isUsed ? ' (already added)' : ''}</option>`;
    }).join('');
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', `<tr id="row_${i}">
        <td class="rb-emp">
            <select name="items[${i}][employee_id]" class="form-select form-select-sm" onchange="onEmpChange(this,${i})" required>
                <option value="">— Select staff —</option>${opts}
            </select>
            <div class="rb-meta" id="meta_${i}">${esc(empMeta(empId))}</div>
        </td>
        <td><input type="text" name="items[${i}][description]" class="form-control form-control-sm" maxlength="255" value="${esc(desc)}" placeholder="e.g. Transport, airtime"></td>
        <td><input type="number" name="items[${i}][amount]" id="amount_${i}" class="form-control form-control-sm rb-input" value="${amount}" min="0" step="any" oninput="recalcRow(${i})"></td>
        <td><input type="number" name="items[${i}][deduction]" id="ded_${i}" class="form-control form-control-sm rb-input" value="${ded}" min="0" step="any" oninput="recalcRow(${i})"></td>
        <td><input type="text" name="items[${i}][deduction_reason]" class="form-control form-control-sm" maxlength="255" value="${esc(reason)}" placeholder="If deducting"></td>
        <td class="rb-num rb-g-pay fw-bold" id="net_${i}">0</td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeRow(${i})" title="Remove"><i class="bi bi-x-lg"></i></button></td>
    </tr>`);
    recalcRow(i);
}

function refreshDisabled() {
    document.querySelectorAll('[name$="[employee_id]"]').forEach(sel => {
        const used = selectedEmployeeIds(sel.closest('tr').id.replace('row_', ''));
        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return;
            const isUsed = used.has(opt.value);
            opt.disabled = isUsed;
            if (isUsed && !opt.textContent.includes('(already added)')) opt.textContent += ' (already added)';
            if (!isUsed) opt.textContent = opt.textContent.replace(' (already added)', '');
        });
    });
}

function onEmpChange(sel, i) {
    document.getElementById('meta_' + i).textContent = empMeta(sel.value);
    refreshDisabled();
}

function recalcRow(i) {
    const a = parseFloat(document.getElementById('amount_' + i).value) || 0;
    const d = parseFloat(document.getElementById('ded_' + i).value) || 0;
    const net = document.getElementById('net_' + i);
    net.textContent = fmt(a - d);
    net.classList.toggle('text-danger', d > a);
    recalcTotal();
}

function removeRow(i) {
    document.getElementById('row_' + i)?.remove();
    recalcTotal();
    refreshDisabled();
}

function recalcTotal() {
    const sum = prefix => [...document.querySelectorAll('[id^="' + prefix + '"]')].reduce((t, el) => t + (parseFloat(el.value) || 0), 0);
    const a = sum('amount_'), d = sum('ded_');
    document.getElementById('totAmount').textContent = fmt(a);
    document.getElementById('totDed').textContent    = fmt(d);
    document.getElementById('totNet').textContent    = fmt(a - d);
}

function addAllEmployees() {
    // Keep anything already typed; add every active staff member not yet listed.
    const used = selectedEmployeeIds(-1);
    employees.forEach(e => { if (!used.has(String(e.id))) addRow(e.id); });
    refreshDisabled();
}

// Pre-fill: the previous submission (after a validation error) or the draft being edited.
@php
    $initialItems = old('items');
    if ($initialItems === null) {
        $initialItems = [];
        if ($editing) {
            foreach ($run->items as $i) {
                $initialItems[] = [
                    'employee_id'      => $i->employee_id,
                    'description'      => $i->description,
                    'amount'           => (float) $i->amount,
                    'deduction'        => (float) $i->deduction,
                    'deduction_reason' => $i->deduction_reason,
                ];
            }
        }
    }
@endphp
const initialItems = {!! json_encode(array_values($initialItems)) !!};
initialItems.forEach(it => addRow(it.employee_id, it.description || '', it.amount || 0, it.deduction || 0, it.deduction_reason || ''));
@if(!$editing && old('items') === null)
addAllEmployees();
@endif
refreshDisabled();
</script>
@endpush
