@extends('layouts.app')
@section('title', 'New Payroll Run')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('payroll.index') }}">Payroll</a></li>
    <li class="breadcrumb-item active">New Run</li>
@endsection
@section('content')
<div class="card">
    <div class="card-header fw-semibold">New Payroll Run</div>
    <div class="card-body">
    <form method="POST" action="{{ route('payroll.store') }}" id="payrollForm">
        @csrf
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                <select name="period_month" class="form-select" required>
                    @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ old('period_month', now()->month) == $m ? 'selected' : '' }}>
                        {{ date('F', mktime(0,0,0,$m,1)) }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Year <span class="text-danger">*</span></label>
                <input type="number" name="period_year" class="form-control" value="{{ old('period_year', now()->year) }}" min="2000" max="2100" required>
            </div>
            <div class="col-md-7">
                <label class="form-label fw-semibold">Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="Optional note">
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">Employees</h6>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addAllEmployees()">
                <i class="bi bi-people me-1"></i>Add All Active
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="itemsTable">
                <thead class="table-light">
                    <tr class="small">
                        <th>Employee</th>
                        <th style="width:130px">Basic Salary</th>
                        <th style="width:115px">Allowances</th>
                        <th style="width:105px" class="text-end">Gross</th>
                        <th style="width:100px" class="text-end">PAYE</th>
                        <th style="width:95px" class="text-end">NSSF 5%</th>
                        <th style="width:115px" title="Loan recoveries, advances, etc.">Other Deductions</th>
                        <th style="width:110px" class="text-end table-success">Take-home</th>
                        <th style="width:100px" class="text-end text-muted" title="Paid by the company on top of gross">Employer NSSF 10%</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    {{-- rows added by JS --}}
                </tbody>
                <tfoot class="small">
                    <tr class="fw-bold">
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
                        <td colspan="10" class="text-muted">
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
            <button class="btn btn-primary">Save Payroll Run</button>
            <a href="{{ route('payroll.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
    </div>
</div>
@endsection
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
        <td>
            <select name="items[${i}][employee_id]" class="form-select form-select-sm" onchange="onEmpChange(this,${i})" required>
                <option value="">— Select —</option>${opts}
            </select>
        </td>
        <td><input type="number" name="items[${i}][basic_salary]" id="basic_${i}" class="form-control form-control-sm" value="${basic}" min="0" step="1000" oninput="recalcRow(${i})" required></td>
        <td><input type="number" name="items[${i}][allowances]" id="allow_${i}" class="form-control form-control-sm" value="${allow}" min="0" step="1000" oninput="recalcRow(${i})"></td>
        <td class="text-end align-middle" id="gross_${i}">0</td>
        <td class="text-end align-middle text-danger" id="paye_${i}">0</td>
        <td class="text-end align-middle text-danger" id="nssfee_${i}">0</td>
        <td><input type="number" name="items[${i}][deductions]" id="deduct_${i}" class="form-control form-control-sm" value="${deduct}" min="0" step="1000" oninput="recalcRow(${i})"></td>
        <td class="text-end align-middle fw-bold table-success" id="net_${i}">0</td>
        <td class="text-end align-middle text-muted" id="nssfer_${i}">0</td>
        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="removeRow(${i})"><i class="bi bi-x"></i></button></td>
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

function onEmpChange(sel, i) {
    const opt = sel.options[sel.selectedIndex];
    const salary = opt.dataset.salary || 0;
    document.getElementById('basic_' + i).value = salary;
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
</script>
@endpush
