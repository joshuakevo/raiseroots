@extends('layouts.app')
@section('title', 'Edit Journal Entry')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}">Journal Entries</a></li>
    <li class="breadcrumb-item"><a href="{{ route('transactions.show', $transaction) }}">{{ $transaction->reference }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection
@section('content')

<form method="POST" action="{{ route('transactions.update', $transaction) }}">
@csrf @method('PUT')
<div class="card">
    <div class="card-header py-2 fw-semibold">
        <i class="bi bi-pencil me-2 text-primary"></i>Edit Journal Entry — <span class="font-monospace">{{ $transaction->reference }}</span>
        <span class="badge bg-light text-secondary border ms-2 fw-normal">{{ ucfirst(str_replace('_', ' ', $transaction->module)) }}</span>
    </div>
    <div class="card-body p-3">

        @if(session('error'))
            <div class="alert alert-danger small mb-3">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger small mb-3">
                <ul class="mb-0 ps-3">@foreach($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
            </div>
        @endif

        @if($lockedHint && $dateEditable)
            <div class="alert alert-info small mb-3">
                <i class="bi bi-lock me-1"></i>This entry was posted by the system. You can edit its date, reference, description, line
                descriptions and client tags - a new date is also applied to the linked member statement / repayment record.
                <strong>Accounts and amounts are locked:</strong> {{ $lockedHint }}
            </div>
        @elseif($lockedHint)
            <div class="alert alert-info small mb-3">
                <i class="bi bi-lock me-1"></i>This entry was posted by the system. You can edit its reference, description, line
                descriptions and client tags. <strong>Accounts, amounts and date are locked:</strong> {{ $lockedHint }}
            </div>
        @else
            <div class="alert alert-info small mb-3">
                <i class="bi bi-info-circle me-1"></i>This entry was posted by the system. You can edit its date, amounts, descriptions
                and client tags. Accounts are locked, and debits must still equal credits.
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Date</label>
                @if($dateEditable)
                    <input type="date" name="date" class="form-control form-control-sm" required max="{{ today()->toDateString() }}"
                           value="{{ old('date', $transaction->date->toDateString()) }}">
                @else
                    <input type="text" class="form-control form-control-sm" value="{{ $transaction->date->format('d M Y') }}" disabled>
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Reference <span class="text-danger">*</span></label>
                <input type="text" name="reference" class="form-control form-control-sm font-monospace" required maxlength="100"
                       value="{{ old('reference', $transaction->reference) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Description <span class="text-danger">*</span></label>
                <input type="text" name="description" class="form-control form-control-sm" required maxlength="500"
                       value="{{ old('description', $transaction->description) }}">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr>
                    <th style="width:22%">Account</th>
                    <th style="width:24%">Client</th>
                    <th>Line Description</th>
                    <th class="text-end" style="width:13%">Debit</th>
                    <th class="text-end" style="width:13%">Credit</th>
                </tr></thead>
                <tbody>
                @foreach($transaction->lines as $line)
                    <tr>
                        <td class="small">{{ $line->account->account_code }} — {{ $line->account->account_name }}</td>
                        <td>
                            <select name="lines[{{ $line->id }}][client_id]" class="form-select form-select-sm ts-select">
                                <option value="">— None —</option>
                                @foreach($clients as $c)
                                    <option value="{{ $c->id }}" @selected((string) old("lines.{$line->id}.client_id", $line->client_id) === (string) $c->id)>
                                        {{ $c->name }} ({{ $c->client_number }})
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="text" name="lines[{{ $line->id }}][description]" class="form-control form-control-sm" maxlength="255"
                                   value="{{ old("lines.{$line->id}.description", $line->description) }}">
                        </td>
                        @if($amountsEditable)
                            <td><input type="number" name="lines[{{ $line->id }}][debit]" class="form-control form-control-sm text-end js-debit" step="any" min="0" required
                                       value="{{ old("lines.{$line->id}.debit", $line->debit + 0) }}"></td>
                            <td><input type="number" name="lines[{{ $line->id }}][credit]" class="form-control form-control-sm text-end js-credit" step="any" min="0" required
                                       value="{{ old("lines.{$line->id}.credit", $line->credit + 0) }}"></td>
                        @else
                            <td class="text-end small">{{ $line->debit > 0 ? number_format($line->debit, $dp) : '' }}</td>
                            <td class="text-end small">{{ $line->credit > 0 ? number_format($line->credit, $dp) : '' }}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
                @if($amountsEditable)
                <tfoot class="table-light fw-semibold small">
                    <tr>
                        <td colspan="3" class="text-end">Totals <span id="balanceFlag" class="ms-2"></span></td>
                        <td class="text-end" id="totalDebit"></td>
                        <td class="text-end" id="totalCredit"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('transactions.show', $transaction) }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
        <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
    </div>
</div>
</form>

@if($amountsEditable)
@push('scripts')
<script>
(function () {
    const sum = sel => [...document.querySelectorAll(sel)].reduce((t, el) => t + (parseFloat(el.value) || 0), 0);
    function refresh() {
        const d = sum('.js-debit'), c = sum('.js-credit');
        document.getElementById('totalDebit').textContent  = d.toLocaleString();
        document.getElementById('totalCredit').textContent = c.toLocaleString();
        const ok = Math.abs(d - c) < 0.01 && d > 0;
        document.getElementById('balanceFlag').innerHTML = ok
            ? '<span class="text-success"><i class="bi bi-check-circle"></i> Balanced</span>'
            : '<span class="text-danger"><i class="bi bi-x-circle"></i> Not balanced</span>';
    }
    document.querySelectorAll('.js-debit, .js-credit').forEach(el => el.addEventListener('input', refresh));
    refresh();
})();
</script>
@endpush
@endif
@endsection
