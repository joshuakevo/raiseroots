@extends('layouts.app')
@section('title', $transaction->reference)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}">Journal Entries</a></li>
    <li class="breadcrumb-item active">{{ $transaction->reference }}</li>
@endsection
@section('content')
<div class="row justify-content-center">
    <div class="col-md-9">

        {{-- Reversal notices --}}
        @if($transaction->isReversed())
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-arrow-counterclockwise fs-5"></i>
            <span>This transaction has been <strong>reversed</strong> by
                <a href="{{ route('transactions.show', $transaction->reversed_by) }}" class="fw-semibold">{{ $transaction->reversalTransaction->reference }}</a>.
            </span>
        </div>
        @endif
        @if($transaction->isReversal())
        <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-arrow-counterclockwise fs-5"></i>
            <span>This is a <strong>reversal</strong> of
                <a href="{{ route('transactions.show', $transaction->reversal_of) }}" class="fw-semibold">{{ $transaction->originalTransaction->reference }}</a>.
                Reason: {{ $transaction->reversal_reason }}
            </span>
        </div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Journal Entry — <span class="font-monospace">{{ $transaction->reference }}</span></span>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">{{ $transaction->date->format('d M Y') }}</span>
                    <a href="{{ route('transactions.receipt', $transaction) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-printer me-1"></i>Print Receipt
                    </a>
                    <a href="{{ route('transactions.voucher', $transaction) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i>Print Voucher
                    </a>
                    @if(!$transaction->isReversed() && !$transaction->isReversal())
                    @can('create transactions')
                    <a href="{{ route('transactions.edit', $transaction) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    @endcan
                    @endif
                    @if(!$transaction->isReversed() && !$transaction->isReversal())
                    @can('reverse transactions')
                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reverseModal">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reverse
                    </button>
                    @endcan
                    @endif
                    @role('super_admin')
                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                    @endrole
                </div>
            </div>
            <div class="card-body">
                <dl class="row mb-3">
                    <dt class="col-3 fw-normal text-muted">Reference</dt><dd class="col-9 font-monospace">{{ $transaction->reference }}</dd>
                    <dt class="col-3 fw-normal text-muted">Description</dt><dd class="col-9">{{ $transaction->description }}</dd>
                    <dt class="col-3 fw-normal text-muted">Module</dt><dd class="col-9"><span class="badge bg-secondary">{{ $transaction->module ?? 'manual' }}</span></dd>
                    <dt class="col-3 fw-normal text-muted">Created By</dt><dd class="col-9">{{ $transaction->createdBy->name ?? '—' }}</dd>
                </dl>

                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr><th>Account</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr>
                    </thead>
                    <tbody>
                    @foreach($transaction->lines as $line)
                        <tr>
                            <td>
                                <a href="{{ route('accounts.show', $line->account) }}" class="text-decoration-none">
                                    <span class="font-monospace small">{{ $line->account->account_code }}</span>
                                    {{ $line->account->account_name }}
                                </a>
                            </td>
                            <td class="text-muted small">{{ $line->description }}</td>
                            <td class="text-end">{{ $line->debit > 0 ? number_format($line->debit, $dp) : '' }}</td>
                            <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, $dp) : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td colspan="2" class="text-end">Totals</td>
                            <td class="text-end">{{ number_format($transaction->lines->sum('debit'), $dp) }}</td>
                            <td class="text-end">{{ number_format($transaction->lines->sum('credit'), $dp) }}</td>
                        </tr>
                    </tfoot>
                </table>
                @if($transaction->isBalanced())
                    <div class="alert alert-success py-2 mb-0"><i class="bi bi-check-circle me-2"></i>Transaction is balanced.</div>
                @else
                    <div class="alert alert-danger py-2 mb-0"><i class="bi bi-x-circle me-2"></i>Transaction is NOT balanced.</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Reverse Modal --}}
@if(!$transaction->isReversed() && !$transaction->isReversal())
@can('reverse transactions')
<div class="modal fade" id="reverseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('transactions.reverse', $transaction) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-counterclockwise me-2"></i>Reverse Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">This will post an equal and opposite journal entry to cancel <strong>{{ $transaction->reference }}</strong>. The original entry is not deleted.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reversal Date <span class="text-danger">*</span></label>
                        <input type="date" name="reversal_date" class="form-control" value="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <input type="text" name="reversal_reason" class="form-control" placeholder="e.g. Posted to wrong account" required maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Confirm Reversal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endif

{{-- Delete Modal (super_admin only) --}}
@role('super_admin')
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('transactions.destroy', $transaction) }}">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete Transaction</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Warning:</strong> This permanently deletes the transaction and all its journal lines. This action cannot be undone.
                    </div>
                    <p class="mb-1">You are about to delete:</p>
                    <p class="font-monospace fw-semibold">{{ $transaction->reference }}</p>
                    <p class="text-muted small mb-0">{{ $transaction->description }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i>Permanently Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endrole
@endsection
