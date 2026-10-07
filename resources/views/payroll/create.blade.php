@extends('layouts.app')
@php $editing = isset($payroll); @endphp
@section('title', $editing ? 'Edit Payroll Run' : 'New Payroll Run')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('payroll.index') }}">Payroll</a></li>
    @if($editing)
    <li class="breadcrumb-item"><a href="{{ route('payroll.show', $payroll) }}">{{ $payroll->run_number }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
    @else
    <li class="breadcrumb-item active">New Run</li>
    @endif
@endsection
@section('content')
<div class="card">
    <div class="card-header fw-semibold">{{ $editing ? 'Edit Payroll Run — ' . $payroll->run_number : 'New Payroll Run' }}</div>
    <div class="card-body">
    <form method="POST" action="{{ $editing ? route('payroll.update', $payroll) : route('payroll.store') }}" id="payrollForm">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                <select name="period_month" class="form-select" required>
                    @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ old('period_month', $editing ? $payroll->period_month : now()->month) == $m ? 'selected' : '' }}>
                        {{ date('F', mktime(0,0,0,$m,1)) }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Year <span class="text-danger">*</span></label>
                <input type="number" name="period_year" class="form-control" value="{{ old('period_year', $editing ? $payroll->period_year : now()->year) }}" min="2000" max="2100" required>
            </div>
            <div class="col-md-7">
                <label class="form-label fw-semibold">Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description', $editing ? $payroll->description : '') }}" placeholder="Optional note">
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">Employees</h6>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addAllEmployees()">
                <i class="bi bi-people me-1"></i>Add All Active
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="itemsTable">
                <thead>
                    <tr class="pr-group">
                        <th rowspan="2" class="pr-emp">Employee</th>
                        <th colspan="3" class="text-center pr-g-earn">Earnings</th>
                        <th colspan="3" class="text-center pr-g-ded">Deductions</th>
                        <th rowspan="2" class="text-end pr-g-pay">Take-home</th>
                        <th rowspan="2" class="text-end pr-g-co" title="Paid by the company on top of gross">Employer<br>NSSF 10%</th>
                        <th rowspan="2" style="width:44px"></th>
                    </tr>
                    <tr class="pr-sub">
                        <th class="text-end pr-g-earn">Basic Salary</th>
                        <th class="text-end pr-g-earn">Allowances</th>
                        <th class="text-end pr-g-earn">Gross</th>
                        <th class="text-end pr-g-ded">PAYE</th>
                        <th class="text-end pr-g-ded">NSSF 5%</th>
                        <th class="text-end pr-g-ded" title="Loan recoveries, advances, etc.">Other</th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    {{-- rows added by JS --}}
                </tbody>
                <tfoot>
                    <tr class="fw-bold pr-totals">
                        <td colspan="3" class="text-end">Totals:</td>
                        <td class="text-end" id="totGross">0</td>
                        <td class="text-end text-danger" id="totPaye">0</td>
                        <td class="text-end text-danger" id="totNssfEe">0</td>
                        <td class="text-end text-danger" id="totOther">0</td>
                        <td class="text-end table-success" id="grandTotal">0</td>
                        <td class="text-end text-muted" id="totNssfEr">0</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="10" class="text-muted small py-2">
                            Cost to company (gross + employer NSSF): <strong class="text-dark" id="totCost">0</strong>
                            &nbsp;&middot;&nbsp; Owed to URA (PAYE): <strong class="text-dark" id="owedUra">0</strong>
                            &nbsp;&middot;&nbsp; Owed to NSSF (5% + 10%): <strong class="text-dark" id="owedNssf">0</strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="addRow()">
            <i class="bi bi-plus me-1"></i>Add Employee Row
        </button>

        <div class="d-flex gap-2 mt-2">
            <button class="btn btn-primary">{{ $editing ? 'Update Payroll Run' : 'Save Payroll Run' }}</button>
            <a href="{{ $editing ? route('payroll.show', $payroll) : route('payroll.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
    </div>
</div>
@endsection
@push('styles')
<style>
    #itemsTable { min-width: 1150px; border: 1px solid var(--bs-border-color); }
    #itemsTable thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; font-weight: 600; color: #6b7280; white-space: nowrap; vertical-align: middle; border-bottom-width: 1px; }
    #itemsTable .pr-group th { font-size: .74rem; padding-top: .55rem; padding-bottom: .4rem; }
    #itemsTable .pr-emp { min-width: 260px; }
    #itemsTable .pr-meta { font-size: .72rem; color: #6b7280; margin-top: 3px; min-height: 1em; }
    #itemsTable .pr-input { text-align: right; min-width: 120px; font-variant-numeric: tabular-nums; }
    #itemsTable .pr-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; min-width: 100px; }
    #itemsTable tbody td { padding: .55rem .5rem; border-bottom: 1px solid var(--bs-border-color); }
    #itemsTable .pr-g-earn { background: #f8fafc; }
    #itemsTable .pr-g-ded  { background: #fef6f6; }
    #itemsTable .pr-g-pay  { background: #ecfdf3; color: #065f46; }
    #itemsTable .pr-g-co   { background: #f8fafc; }
    #itemsTable thead .pr-g-pay { color: #065f46; }
    #itemsTable .pr-totals td { background: #f8fafc; border-top: 2px solid var(--bs-border-color); font-variant-numeric: tabular-nums; white-space: nowrap; }
    #itemsTable .pr-totals td.table-success { background: #ecfdf3; color: #065f46; }
</style>
@endpush
@push('scripts')
<script>
const employees = @json($employees);
let rowIndex = 0;

function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:0}); }

function selectedEmployeeIds(excludeRowId) {
    const ids = new Set();
    document.querySelectorAll('[name$="[employee_id]"]').forEach(sel => {
        if (sel.closest('tr').id !== 'row_' + excludeRowId && sel.value) ids.add(sel.value);
    });
    return ids;
}

function addRow(empId = '', basic = 0, allow = 0, deduct = 0) {
    const i = rowIndex++;
    const used = selectedEmployeeIds(-1);
    const opts = employees.map(e => {
        const isUsed = used.has(String(e.id)) && String(e.id) !== String(empId);
        return `<option value="${e.id}" data-salary="${e.basic_salary}" ${String(e.id) === String(empId) ? 'selected' : ''} ${isUsed ? 'disabled' : ''}>${e.name} — ${e.employee_number}${isUsed ? ' (already added)' : ''}</option>`;
    }).join('');
    const row = `<tr id="row_${i}">
        <td class="pr-emp">
            <select name="items[${i}][employee_id]" class="form-select form-select-sm" onchange="onEmpChange(this,${i})" required>
                <option value="">— Select employee —</option>${opts}
            </select>
            <div class="pr-meta" id="meta_${i}">${empMeta(empId)}</div>
        </td>
        <td><input type="number" name="items[${i}][basic_salary]" id="basic_${i}" class="form-control form-control-sm pr-input" value="${basic}" min="0" step="1000" oninput="recalcRow(${i})" required></td>
        <td><input type="number" name="items[${i}][allowances]" id="allow_${i}" class="form-control form-control-sm pr-input" value="${allow}" min="0" step="1000" oninput="recalcRow(${i})"></td>
        <td class="pr-num" id="gross_${i}">0</td>
        <td class="pr-num text-danger" id="paye_${i}">0</td>
        <td class="pr-num text-danger" id="nssfee_${i}">0</td>
        <td><input type="number" name="items[${i}][deductions]" id="deduct_${i}" class="form-control form-control-sm pr-input" value="${deduct}" min="0" step="1000" oninput="recalcRow(${i})"></td>
        <td class="pr-num pr-g-pay fw-bold" id="net_${i}">0</td>
        <td class="pr-num text-muted" id="nssfer_${i}">0</td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeRow(${i})" title="Remove"><i class="bi bi-x-lg"></i></button></td>
    </tr>`;
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', row);
    recalcRow(i);
}

function refreshDisabled() {
    document.querySelectorAll('[name$="[employee_id]"]').forEach(sel => {
        const rowId = sel.closest('tr').id.replace('row_', '');
        const used = selectedEmployeeIds(rowId);
        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return;
            const isUsed = used.has(opt.value);
            opt.disabled = isUsed;
            if (!opt.textContent.includes('(already added)') && isUsed) opt.textContent += ' (already added)';
            if (opt.textContent.includes('(already added)') && !isUsed) opt.textContent = opt.textContent.replace(' (already added)', '');
        });
    });
}

function empMeta(empId) {
    const e = employees.find(x => String(x.id) === String(empId));
    if (!e) return '';
    return [e.position, e.employee_number].filter(Boolean).join(' · ');
}

function onEmpChange(sel, i) {
    const opt = sel.options[sel.selectedIndex];
    const salary = opt.dataset.salary || 0;
    document.getElementById('basic_' + i).value = salary;
    document.getElementById('meta_' + i).textContent = empMeta(sel.value);
    recalcRow(i);
    refreshDisabled();
}

// Mirrors App\Services\PayrollTaxService (the server recomputes on save).
const PAYE_BANDS = [[335000, 410000, 0.20], [410000, 485000, 0.25], [485000, 10000000, 0.30], [10000000, Infinity, 0.40]];
const NSSF_EE = {{ \App\Services\PayrollTaxService::NSSF_EMPLOYEE_RATE }}, NSSF_ER = {{ \App\Services\PayrollTaxService::NSSF_EMPLOYER_RATE }};
const round2 = n => Math.round(n * 100) / 100;
function paye(gross) {
    return round2(PAYE_BANDS.reduce((t, [from, to, rate]) => gross > from ? t + (Math.min(gross, to) - from) * rate : t, 0));
}
function calc(basic, allow, other) {
    const gross = basic + allow, p = paye(gross), ee = round2(gross * NSSF_EE), er = round2(gross * NSSF_ER);
    return { gross, paye: p, ee, er, other, net: round2(gross - p - ee - other) };
}

function recalcRow(i) {
    const b = parseFloat(document.getElementById('basic_' + i).value) || 0;
    const a = parseFloat(document.getElementById('allow_' + i).value) || 0;
    const d = parseFloat(document.getElementById('deduct_' + i).value) || 0;
    const c = calc(b, a, d);
    document.getElementById('gross_' + i).textContent  = fmt(c.gross);
    document.getElementById('paye_' + i).textContent   = fmt(c.paye);
    document.getElementById('nssfee_' + i).textContent = fmt(c.ee);
    document.getElementById('nssfer_' + i).textContent = fmt(c.er);
    const net = document.getElementById('net_' + i);
    net.textContent = fmt(c.net);
    net.classList.toggle('text-danger', c.net < 0);
    recalcTotal();
}

function removeRow(i) {
    const row = document.getElementById('row_' + i);
    if (row) row.remove();
    recalcTotal();
}

function recalcTotal() {
    const sum = prefix => [...document.querySelectorAll('[id^="' + prefix + '"]')]
        .reduce((t, el) => t + (parseFloat(String(el.value ?? el.textContent).replace(/,/g, '')) || 0), 0);
    const gross = sum('gross_'), p = sum('paye_'), ee = sum('nssfee_'), er = sum('nssfer_');
    document.getElementById('totGross').textContent   = fmt(gross);
    document.getElementById('totPaye').textContent    = fmt(p);
    document.getElementById('totNssfEe').textContent  = fmt(ee);
    document.getElementById('totOther').textContent   = fmt(sum('deduct_'));
    document.getElementById('grandTotal').textContent = fmt(sum('net_'));
    document.getElementById('totNssfEr').textContent  = fmt(er);
    document.getElementById('totCost').textContent    = fmt(gross + er);
    document.getElementById('owedUra').textContent    = fmt(p);
    document.getElementById('owedNssf').textContent   = fmt(ee + er);
}

function addAllEmployees() {
    document.getElementById('itemsBody').innerHTML = '';
    rowIndex = 0;
    employees.forEach(e => addRow(e.id, e.basic_salary));
}

// Pre-fill rows: the previous submission (after a validation error) or the draft being edited.
const initialItems = @json(array_values(old('items', $editing ? $payroll->items->map(fn ($i) => [
    'employee_id' => $i->employee_id, 'basic_salary' => (float) $i->basic_salary,
    'allowances' => (float) $i->allowances, 'deductions' => (float) $i->deductions,
])->all() : [])));
initialItems.forEach(it => addRow(it.employee_id, it.basic_salary || 0, it.allowances || 0, it.deductions || 0));
refreshDisabled();
</script>
@endpush
