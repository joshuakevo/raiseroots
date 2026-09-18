@extends('layouts.app')
@section('title', 'Member Summary Statement')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Member Summary</li>
@endsection
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Member Summary Statement</h4>
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
</div>

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">As of Date</label>
                <input type="date" name="as_of" class="form-control" value="{{ $asOf }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Member Status</label>
                <select name="status" class="form-select">
                    <option value="">All Members</option>
                    <option value="active"      {{ request('status') === 'active'      ? 'selected' : '' }}>Active</option>
                    <option value="inactive"    {{ request('status') === 'inactive'    ? 'selected' : '' }}>Inactive</option>
                    <option value="blacklisted" {{ request('status') === 'blacklisted' ? 'selected' : '' }}>Blacklisted</option>
                </select>
            </div>
            <div class="col-auto align-self-end">
                <button class="btn btn-primary">Run Report</button>
                <a href="{{ route('reports.member-summary') }}" class="btn btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- Summary totals --}}
<div class="row g-3 mb-4">
    <div class="col">
        <div class="card text-center border-0 bg-light">
            <div class="card-body py-2">
                <div class="text-muted small">Members</div>
                <div class="fw-bold fs-5">{{ $members->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-0 bg-primary bg-opacity-10">
            <div class="card-body py-2">
                <div class="text-muted small">Loans Taken</div>
                <div class="fw-bold fs-6 text-primary">{{ number_format($totals['loans_taken']) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-0 bg-danger bg-opacity-10">
            <div class="card-body py-2">
                <div class="text-muted small">Loan Principal</div>
                <div class="fw-bold fs-6 text-danger">{{ number_format($totals['loan_principal'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-0 bg-warning bg-opacity-10">
            <div class="card-body py-2">
                <div class="text-muted small">Loan Interest</div>
                <div class="fw-bold fs-6 text-warning">{{ number_format($totals['loan_interest'], 0) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Main table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0 small">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">#</th>
                    <th>Member</th>
                    <th>Client #</th>
                    <th class="text-end">Loans Taken</th>
                    <th class="text-end">Loan Principal</th>
                    <th class="text-end">Loan Interest</th>
                    <th class="pe-3">Relationship Manager</th>
                </tr>
            </thead>
            <tbody>
            @forelse($members as $i => $row)
            <tr>
                <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                <td>
                    <a href="{{ route('clients.show', $row->client) }}" class="fw-semibold text-decoration-none text-dark">
                        {{ $row->client->name }}
                    </a>
                    <div class="text-muted" style="font-size:.72rem">
                        <span class="badge badge-status-{{ $row->client->status }} py-0">{{ ucfirst($row->client->status) }}</span>
                        @if($row->client->joining_date)
                            &nbsp; Joined {{ $row->client->joining_date->format('d M Y') }}
                        @endif
                    </div>
                </td>
                <td class="font-monospace text-muted">{{ $row->client->client_number }}</td>
                <td class="text-end {{ $row->loans_taken > 0 ? 'fw-semibold' : 'text-muted' }}">
                    {{ $row->loans_taken > 0 ? $row->loans_taken : '—' }}
                </td>
                <td class="text-end {{ $row->loan_principal > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                    {{ $row->loan_principal > 0 ? number_format($row->loan_principal, 0) : '—' }}
                </td>
                <td class="text-end {{ $row->loan_interest > 0 ? 'text-warning' : 'text-muted' }}">
                    {{ $row->loan_interest > 0 ? number_format($row->loan_interest, 0) : '—' }}
                </td>
                <td class="pe-3">
                    @can('assign relationship manager')
                    <form method="POST" action="{{ route('clients.relationship-manager', $row->client) }}">
                        @csrf
                        <select name="relationship_manager_id" class="form-select form-select-sm"
                                onchange="this.form.submit()" style="min-width:160px">
                            <option value="">— Unassigned —</option>
                            @foreach($relationshipManagers as $user)
                            <option value="{{ $user->id }}" @selected($row->client->relationship_manager_id == $user->id)>{{ $user->name }} — {{ ucfirst(str_replace('_',' ',$user->role_name)) }}</option>
                            @endforeach
                        </select>
                    </form>
                    @else
                        {{ $row->client->relationshipManager?->name ?? '—' }}
                    @endcan
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No members found.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-secondary fw-bold small">
                <tr>
                    <td colspan="3" class="ps-3">Totals ({{ $members->count() }} members)</td>
                    <td class="text-end">{{ number_format($totals['loans_taken']) }}</td>
                    <td class="text-end text-danger">{{ number_format($totals['loan_principal'], 0) }}</td>
                    <td class="text-end text-warning">{{ number_format($totals['loan_interest'], 0) }}</td>
                    <td class="pe-3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="card-footer text-muted small">
        Loans shows active outstanding balances only
    </div>
</div>
@endsection
