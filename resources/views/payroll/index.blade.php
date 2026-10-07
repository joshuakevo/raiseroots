@extends('layouts.app')
@section('title', 'Payroll Runs')
@section('breadcrumb')
    <li class="breadcrumb-item active">Payroll</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0">Payroll Runs</h6>
    <a href="{{ route('payroll.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Payroll Run
    </a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Run #</th>
                <th>Period</th>
                <th>Description</th>
                <th class="text-end">Employees</th>
                <th class="text-end">Total Net</th>
                <th>Status</th>
                <th>Processed</th>
                <th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($runs as $run)
            <tr>
                <td class="ps-3 font-monospace small">{{ $run->run_number }}</td>
                <td class="fw-semibold">{{ $run->period_label }}</td>
                <td class="text-muted small">{{ $run->description ?? '—' }}</td>
                <td class="text-end">{{ $run->items_count }}</td>
                <td class="text-end fw-semibold">{{ number_format($run->total_gross, 0) }}</td>
                <td>
                    @if($run->status === 'processed')
                        <span class="badge bg-success">Processed</span>
                    @else
                        <span class="badge bg-warning text-dark">Draft</span>
                    @endif
                </td>
                <td class="small text-muted">{{ $run->processed_at?->format('d M Y') ?? '—' }}</td>
                <td class="pe-3">
                    <a href="{{ route('payroll.show', $run) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                    @if($run->status === 'draft')
                    @can('create payroll')
                    <a href="{{ route('payroll.edit', $run) }}" class="btn btn-sm btn-outline-secondary" title="Edit draft"><i class="bi bi-pencil"></i></a>
                    @endcan
                    <form method="POST" action="{{ route('payroll.destroy', $run) }}" class="d-inline"
                          onsubmit="return confirm('Delete draft run {{ $run->run_number }}? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No payroll runs yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $runs->links() }}</div>
</div>
@endsection
