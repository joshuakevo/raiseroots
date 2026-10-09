@extends('layouts.app')
@section('title', 'Staff Reimbursements')
@section('breadcrumb')
    <li class="breadcrumb-item active">Staff Reimbursements</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0">Staff Reimbursement Runs</h6>
    @can('create payroll')
    <a href="{{ route('staff-reimbursements.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Reimbursement Run
    </a>
    @endcan
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Run #</th>
                <th>Period</th>
                <th>Description</th>
                <th class="text-end">Staff</th>
                <th class="text-end">Amount</th>
                <th class="text-end">Deductions</th>
                <th class="text-end">Net Paid</th>
                <th>Status</th>
                <th>Paid On</th>
                <th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($runs as $run)
            <tr>
                <td class="ps-3 font-monospace small">{{ $run->run_number }}</td>
                <td class="fw-semibold">{{ $run->period_label }}</td>
                <td class="text-muted small">{{ $run->description ?? '—' }}</td>
                <td class="text-end">{{ $run->items_count }}</td>
                <td class="text-end">{{ number_format($run->total_amount, 0) }}</td>
                <td class="text-end text-danger">{{ $run->total_deduction > 0 ? number_format($run->total_deduction, 0) : '—' }}</td>
                <td class="text-end fw-semibold">{{ number_format($run->total_net, 0) }}</td>
                <td>
                    @if($run->status === 'processed')
                        <span class="badge bg-success">Processed</span>
                    @else
                        <span class="badge bg-warning text-dark">Draft</span>
                    @endif
                </td>
                <td class="small text-muted">{{ $run->payment_date?->format('d M Y') ?? '—' }}</td>
                <td class="pe-3 text-nowrap">
                    <a href="{{ route('staff-reimbursements.show', $run) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                    @if($run->status === 'draft')
                    @can('create payroll')
                    <a href="{{ route('staff-reimbursements.edit', $run) }}" class="btn btn-sm btn-outline-secondary" title="Edit draft"><i class="bi bi-pencil"></i></a>
                    @endcan
                    @can('delete payroll')
                    <form method="POST" action="{{ route('staff-reimbursements.destroy', $run) }}" class="d-inline"
                          onsubmit="return confirm('Delete draft run {{ $run->run_number }}? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                    @endcan
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="10" class="text-center text-muted py-4">No reimbursement runs yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $runs->links() }}</div>
</div>
@endsection
