@extends('layouts.app')
@section('title', 'SMS Delivery Log')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <a href="{{ route('sms.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Send SMS
        </a>
        <h5 class="mb-0 fw-semibold mt-1">Delivery Log</h5>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('sms.deliveries') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-1 small">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Client name or phone...">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1 small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="sent"   @selected(request('status')=='sent')>Sent</option>
                    <option value="failed" @selected(request('status')=='failed')>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-funnel-fill me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Phone</th>
                    <th>Group</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Sent By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="small text-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $log->client_name }}</td>
                    <td>{{ $log->phone }}</td>
                    <td class="small">{{ $groups[$log->recipient_group] ?? $log->recipient_group }}</td>
                    <td class="small text-truncate" style="max-width:280px" title="{{ $log->message }}">{{ $log->message }}</td>
                    <td>
                        @if($log->status === 'sent')
                            <span class="badge bg-success">Sent</span>
                        @else
                            <span class="badge bg-danger">Failed</span>
                        @endif
                    </td>
                    <td class="small">{{ $log->sentBy?->name ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4 small">No SMS have been sent yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="card-footer py-2">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
