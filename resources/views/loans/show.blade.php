@extends('layouts.app')
@section('title', $loan->loan_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item active">{{ $loan->loan_number }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ $loan->loan_number }}</h4>
        <span class="text-muted">{{ $loan->client->name }} &bull; {{ $loan->product->name }}</span>
    </div>
    <div class="d-flex gap-2">
        @can('approve loans')
        @if($loan->status === 'pending')
            <form method="POST" action="{{ route('loans.approve', $loan) }}" onsubmit="return confirm('Approve this loan for disbursement?')">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check2-circle me-1"></i>Approve Loan
                </button>
            </form>
        @endif
        @endcan
        @can('disburse loans')
        @if($loan->status === 'approved')
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#disburseModal">
                <i class="bi bi-send me-1"></i>Disburse Loan
            </button>
        @endif
        @if($loan->outstanding_admin_fee > 0.01)
            <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#adminFeeModal">
                <i class="bi bi-cash-coin me-1"></i>Record Admin Fee
            </button>
        @endif
        @if($loan->admin_cost <= 0.01 && in_array($loan->status, ['active', 'defaulted']))
            <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#setAdminCostModal">
                <i class="bi bi-pencil-square me-1"></i>Set Admin Cost
            </button>
        @endif
        @endcan
        @can('create loans')
        @if(in_array($loan->status, ['pending', 'approved']))
            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteLoanModal">
                <i class="bi bi-trash me-1"></i>Delete
            </button>
        @endif
        @endcan
        @can('repay loans')
        @if(in_array($loan->status, ['active', 'defaulted']))
            <a href="{{ route('loans.repay-form', $loan) }}" class="btn btn-success btn-sm"><i class="bi bi-cash"></i> Record Repayment</a>
        @endif
        @endcan
        @if(in_array($loan->status, ['active', 'defaulted']))
            <a href="{{ route('loans.schedule', $loan) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar3"></i> Schedule</a>
        @endif
        <div class="dropdown">
            <button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-download me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('loans.statement', $loan) }}"><i class="bi bi-file-earmark-text me-2 text-primary"></i>View Loan Statement</a></li>
                <li><a class="dropdown-item" href="{{ route('loans.statement-pdf', $loan) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Loan Statement (PDF)</a></li>
                <li><a class="dropdown-item" href="{{ route('loans.schedule-pdf', $loan) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Repayment Schedule (PDF)</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3">Loan Details</h6>
                <dl class="row mb-0 small">
                    <dt class="col-6 fw-normal text-muted">Principal</dt><dd class="col-6 fw-semibold">{{ number_format($loan->principal, $dp) }}</dd>
                    <dt class="col-6 fw-normal text-muted">Rate</dt><dd class="col-6">{{ $loan->interest_rate }}% ({{ ucfirst($loan->interest_method) }})</dd>
                    <dt class="col-6 fw-normal text-muted">Term</dt><dd class="col-6">{{ $loan->term_months }} months</dd>
                    <dt class="col-6 fw-normal text-muted">Disbursed</dt><dd class="col-6">{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-6 fw-normal text-muted">Maturity</dt><dd class="col-6">{{ $loan->maturity_date?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-6 fw-normal text-muted">Status</dt>
                    <dd class="col-6"><span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></dd>
                    @if($loan->approved_by)
                    <dt class="col-6 fw-normal text-muted">Approved By</dt>
                    <dd class="col-6">{{ $loan->approvedBy->name ?? '—' }} <span class="text-muted small">({{ $loan->approved_at?->format('d M Y') }})</span></dd>
                    @endif
                    @if($loan->total_fees > 0)
                    @if($loan->application_fee > 0)
                    <dt class="col-6 fw-normal text-muted">Application Fee</dt>
                    <dd class="col-6">{{ number_format($loan->application_fee, $dp) }}
                        <span class="text-muted small">(flat · from {{ $loan->application_fee_method }})</span>
                    </dd>
                    @endif
                    @if($loan->management_fee > 0)
                    <dt class="col-6 fw-normal text-muted">Management Fee</dt>
                    <dd class="col-6">{{ number_format($loan->management_fee, $dp) }}
                        <span class="text-muted small">({{ $loan->management_fee_rate }}% · from {{ $loan->management_fee_method }})</span>
                    </dd>
                    @endif
                    @if($loan->insurance_fee > 0)
                    <dt class="col-6 fw-normal text-muted">Insurance Fee</dt>
                    <dd class="col-6">{{ number_format($loan->insurance_fee, $dp) }}
                        <span class="text-muted small">({{ $loan->insurance_fee_rate }}% · from {{ $loan->insurance_fee_method }})</span>
                    </dd>
                    @endif
                    @endif
                    @if($loan->admin_cost > 0)
                    <dt class="col-6 fw-normal text-muted">Admin Cost</dt>
                    <dd class="col-6">{{ number_format($loan->admin_cost, $dp) }}
                        @if($adminFeeCollected)
                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:.65rem"
                                  title="{{ $adminFeePaidDate ? 'Collected '.\Carbon\Carbon::parse($adminFeePaidDate)->format('d M Y') : 'Collected' }}">collected</span>
                        @elseif($adminFeePartial)
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle ms-1" style="font-size:.65rem"
                                  title="{{ number_format($loan->outstanding_admin_fee, $dp) }} still outstanding">partial</span>
                        @else
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" style="font-size:.65rem">pending</span>
                        @endif
                    </dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="row g-2">
            <div class="col-4">
                <div class="stat-card text-center">
                    <div class="text-muted small">Outstanding Principal</div>
                    <div class="fw-bold fs-5 text-warning">{{ number_format($loan->outstanding_principal, $dp) }}</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card text-center">
                    <div class="text-muted small">Outstanding Interest</div>
                    <div class="fw-bold fs-5 text-info">{{ number_format($loan->outstanding_interest, $dp) }}</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card text-center">
                    <div class="text-muted small">Accrued Penalty</div>
                    <div class="fw-bold fs-5 text-danger">{{ number_format($currentPenalty, $dp) }}</div>
                    @if($currentPenalty > 0)
                    <div style="font-size:.68rem" class="text-muted">{{ $loan->product->penalty_rate }}%/day on overdue</div>
                    @endif
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card text-center">
                    <div class="text-muted small">Total Paid</div>
                    <div class="fw-bold fs-5 text-success">{{ number_format($loan->repayments->sum('amount'), $dp) }}</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card text-center">
                    <div class="text-muted small">Total Outstanding</div>
                    @php $liveTotal = $loan->outstanding_principal + $loan->outstanding_interest + $currentPenalty + $loan->outstanding_admin_fee; @endphp
                    <div class="fw-bold fs-5 text-danger">{{ number_format($liveTotal, $dp) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Repayment History -->
<div class="card mb-3">
    <div class="card-header">Repayment History</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Date</th><th class="text-end">Amount</th>
                <th class="text-end">Principal</th><th class="text-end">Interest</th>
                <th class="text-end">Penalty</th><th>Method</th><th>Receipt / Ref</th><th class="pe-3">Received By</th>
            </tr></thead>
            <tbody>
            @forelse($loan->repayments as $rp)
                <tr>
                    <td class="ps-3">{{ $rp->payment_date->format('d M Y') }}</td>
                    <td class="text-end fw-semibold">{{ number_format($rp->amount, $dp) }}</td>
                    <td class="text-end">{{ number_format($rp->principal_paid, $dp) }}</td>
                    <td class="text-end">{{ number_format($rp->interest_paid, $dp) }}</td>
                    <td class="text-end">{{ number_format($rp->penalty_paid, $dp) }}</td>
                    <td class="small">{{ ucfirst(str_replace('_',' ',$rp->payment_method)) }}</td>
                    <td class="font-monospace small">{{ $rp->reference ?? '—' }}</td>
                    <td class="pe-3 small text-muted">{{ $rp->receivedBy->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-3">No repayments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Guarantors -->
<div class="card mb-3">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-check me-1"></i>Guarantors</span>
        @can('create loans')
        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addGuarantorModal">
            <i class="bi bi-plus-circle me-1"></i>Add Guarantor
        </button>
        @endcan
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 small">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Photo</th><th>Name</th><th>Phone</th><th>ID No.</th>
                    <th>Relationship</th><th>Employer</th><th class="text-end">Monthly Income</th>
                    @can('create loans')<th></th>@endcan
                </tr>
            </thead>
            <tbody>
            @forelse($loan->guarantors as $g)
                <tr>
                    <td class="ps-3">
                        @if($g->photo)
                        <img src="{{ asset($g->photo) }}" alt="Photo" class="rounded-circle" style="height:36px;width:36px;object-fit:cover;border:1px solid #dee2e6">
                        @else
                        <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center" style="height:36px;width:36px;color:#6c757d">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        @endif
                    </td>
                    <td class="fw-semibold">{{ $g->name }}</td>
                    <td>{{ $g->phone ?? '—' }}</td>
                    <td>{{ $g->id_number ?? '—' }}</td>
                    <td>{{ $g->relationship ?? '—' }}</td>
                    <td>{{ $g->employer ?? '—' }}</td>
                    <td class="text-end">{{ $g->monthly_income ? number_format($g->monthly_income, $dp) : '—' }}</td>
                    @can('create loans')
                    <td class="text-end pe-3">
                        <form method="POST" action="{{ route('loans.guarantors.destroy', [$loan, $g]) }}" onsubmit="return confirm('Remove this guarantor?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-3">No guarantors recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Add Guarantor Modal -->
@can('create loans')
<div class="modal fade" id="addGuarantorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('loans.guarantors.store', $loan) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Guarantor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">ID Number</label>
                        <input type="text" name="id_number" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Relationship</label>
                        <input type="text" name="relationship" class="form-control" placeholder="e.g. Spouse, Friend">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Employer</label>
                        <input type="text" name="employer" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Monthly Income</label>
                        <input type="number" name="monthly_income" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Save Guarantor</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<!-- Collateral -->
<div class="card mb-3">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-shield-check me-1"></i>Collateral</span>
        @can('create loans')
        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addCollateralModal">
            <i class="bi bi-plus-circle me-1"></i>Add Collateral
        </button>
        @endcan
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 small">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Category</th><th>Description</th><th>Attachment</th>
                    @can('create loans')<th></th>@endcan
                </tr>
            </thead>
            <tbody>
            @forelse($loan->collaterals as $c)
                <tr>
                    <td class="ps-3 fw-semibold">{{ $c->category_label }}</td>
                    <td>{{ $c->description ?? '—' }}</td>
                    <td>
                        @if($c->file_path && $c->file_type === 'image')
                            <img src="{{ asset($c->file_path) }}" alt="Attachment" class="rounded"
                                 style="height:36px;width:36px;object-fit:cover;border:1px solid #dee2e6;cursor:pointer"
                                 role="button" data-bs-toggle="modal" data-bs-target="#collateralPhoto{{ $c->id }}">
                            <div class="modal fade" id="collateralPhoto{{ $c->id }}" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered modal-xl">
                                    <div class="modal-content bg-transparent border-0">
                                        <div class="d-flex justify-content-end gap-2 mb-2">
                                            <a href="{{ asset($c->file_path) }}" download class="btn btn-sm btn-light">
                                                <i class="bi bi-download me-1"></i>Download
                                            </a>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <img src="{{ asset($c->file_path) }}" class="img-fluid rounded mx-auto d-block" style="max-height:85vh" alt="Attachment">
                                    </div>
                                </div>
                            </div>
                        @elseif($c->file_path)
                            <a href="{{ asset($c->file_path) }}" target="_blank" class="text-decoration-none me-2">
                                <i class="bi bi-file-earmark-text me-1"></i>View
                            </a>
                            <a href="{{ asset($c->file_path) }}" download class="text-decoration-none">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    @can('create loans')
                    <td class="text-end pe-3">
                        <form method="POST" action="{{ route('loans.collaterals.destroy', [$loan, $c]) }}" onsubmit="return confirm('Remove this collateral?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-3">No collateral recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Add Collateral Modal -->
@can('create loans')
<div class="modal fade" id="addCollateralModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('loans.collaterals.store', $loan) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Collateral</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select ts-select" required>
                            <option value="">— Select category —</option>
                            @foreach(\App\Models\LoanCollateralCategory::activeOptions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Reg. no, plot no, cheque no...">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Attach Image or Document</label>
                        <input type="file" name="attachment" class="form-control" accept="image/*,.pdf,.doc,.docx">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Save Collateral</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<!-- Schedule preview (first 5 rows) -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            Repayment Schedule
            @if(in_array($loan->status, ['pending', 'approved']))
                <span class="badge bg-warning-subtle text-warning ms-1 small">Projected — assumes disbursement today</span>
            @endif
        </span>
        <a href="{{ route('loans.schedule', $loan) }}" class="btn btn-sm btn-outline-primary">View Full Schedule</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">#</th><th>Due Date</th><th class="text-end">Principal</th>
                <th class="text-end">Interest</th>
                @if(!in_array($loan->status, ['pending', 'approved']))<th class="text-end">Penalty</th>@endif
                <th class="text-end">Total</th>
                <th class="text-end pe-3">Balance</th>
                @if(!in_array($loan->status, ['pending', 'approved']))<th>Status</th>@endif
            </tr></thead>
            <tbody>
            @if(in_array($loan->status, ['pending', 'approved']))
                @forelse(array_slice($schedulePreview, 0, 5) as $s)
                <tr>
                    <td class="ps-3">{{ $s['installment_no'] }}</td>
                    <td>{{ $s['due_date'] }}</td>
                    <td class="text-end">{{ number_format($s['principal_due'], $dp) }}</td>
                    <td class="text-end">{{ number_format($s['interest_due'], $dp) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($s['total_due'], $dp) }}</td>
                    <td class="text-end pe-3">{{ number_format($s['balance_after'], $dp) }}</td>
                </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">Unable to generate preview.</td></tr>
                @endforelse
            @else
                @forelse($loan->schedules->take(5) as $s)
                @php $rowPenalty = $penaltyBreakdown[$s->id] ?? 0; @endphp
                <tr class="{{ $s->isOverdue() ? 'table-danger' : ($s->status === 'paid' ? 'table-success bg-opacity-10' : '') }}">
                    <td class="ps-3">{{ $s->installment_no }}</td>
                    <td>{{ $s->due_date->format('d M Y') }}</td>
                    <td class="text-end">{{ number_format($s->principal_due, $dp) }}</td>
                    <td class="text-end">{{ number_format($s->interest_due, $dp) }}</td>
                    <td class="text-end {{ $rowPenalty > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                        {{ $rowPenalty > 0 ? number_format($rowPenalty, $dp) : '—' }}
                    </td>
                    <td class="text-end fw-semibold">{{ number_format($s->total_due + $rowPenalty, $dp) }}</td>
                    <td class="text-end pe-3">{{ number_format($s->balance_after, $dp) }}</td>
                    <td><span class="badge badge-status-{{ $s->status }}">{{ ucfirst($s->status) }}</span></td>
                </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-3">No schedule generated yet.</td></tr>
                @endforelse
                @if($loan->admin_cost > 0)
                @php
                    $adminFeePaid   = $loan->admin_cost - $loan->outstanding_admin_fee;
                    $adminFeeStatus = $loan->outstanding_admin_fee <= 0.01 ? 'collected' : ($adminFeePaid > 0.01 ? 'partial' : 'pending');
                @endphp
                <tr class="table-warning bg-opacity-10">
                    <td class="ps-3">—</td>
                    <td><span class="fw-semibold">Admin Fee</span><span class="text-muted d-block" style="font-size:.68rem">Collected from any repayment, after interest</span></td>
                    <td class="text-end text-muted">—</td>
                    <td class="text-end text-muted">—</td>
                    <td class="text-end text-muted">—</td>
                    <td class="text-end fw-semibold">{{ number_format($loan->admin_cost, $dp) }}</td>
                    <td class="text-end pe-3">{{ number_format($loan->outstanding_admin_fee, $dp) }}</td>
                    <td>
                        @if($adminFeeStatus === 'collected')
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Collected</span>
                        @elseif($adminFeeStatus === 'partial')
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Partial</span>
                        @else
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Pending</span>
                        @endif
                    </td>
                </tr>
                @endif
            @endif
            </tbody>
        </table>
    </div>
</div>

@if($loan->status === 'approved')
@can('disburse loans')
{{-- Disburse Modal --}}
<div class="modal fade" id="disburseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-send me-1"></i>Disburse Loan — {{ $loan->loan_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('loans.disburse', $loan) }}">
                @csrf
                <div class="modal-body">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Admin Cost</label>
                            <input type="number" name="admin_cost" id="adminCostInput" class="form-control" step="any" min="0"
                                   value="{{ round($loan->principal * 0.122, 2) }}">
                            <div class="form-text">
                                Defaults to 12.2% of principal — adjust if needed. Added to the loan's balance and
                                collected through the client's ordinary repayments (priority: interest → admin fee
                                → principal) — nothing is posted to the books at disbursement, only as it's
                                actually collected.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Processing Fees</label>
                            <input type="number" name="processing_fee_amount" class="form-control" step="any" min="0" value="0">
                            <div class="form-text">Collected in cash now — posted as received, alongside the loan.</div>
                        </div>
                    </div>

                    {{-- Summary --}}
                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body py-2">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="text-muted">Principal</div>
                                    <div class="fw-bold">{{ number_format($loan->principal, $dp) }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted">Cash to Client</div>
                                    <div class="fw-bold text-success">{{ number_format($loan->principal, $dp) }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted">Admin Cost</div>
                                    <div class="fw-bold text-warning">Added to balance, not deducted here</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Disbursement Date <span class="text-danger">*</span></label>
                        <input type="date" name="disbursement_date" class="form-control" value="{{ today()->toDateString() }}" required>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-success"><i class="bi bi-send me-1"></i>Confirm Disbursement</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endif

{{-- Record Admin Fee Modal — for collecting it on its own, outside an ordinary repayment --}}
@can('disburse loans')
@if($loan->outstanding_admin_fee > 0.01)
<div class="modal fade" id="adminFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-cash-coin me-1"></i>Record Admin Fee — {{ $loan->loan_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('loans.admin-fee', $loan) }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">
                        Admin Cost is normally collected as part of an ordinary repayment (interest → admin fee →
                        principal). Use this only if the client is paying it on its own, separate from a repayment.
                        Posts a journal entry (debit Cash, credit GL 4009) and reduces the outstanding balance below.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="any" min="0.01" required
                               value="{{ $loan->outstanding_admin_fee }}">
                        <div class="form-text">{{ number_format($loan->outstanding_admin_fee, $dp) }} still outstanding.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date Paid <span class="text-danger">*</span></label>
                        <input type="date" name="paid_date" class="form-control" value="{{ today()->toDateString() }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Record</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endcan

{{-- Set Admin Cost Modal — for a loan that was disbursed without one recorded --}}
@can('disburse loans')
@if($loan->admin_cost <= 0.01 && in_array($loan->status, ['active', 'defaulted']))
<div class="modal fade" id="setAdminCostModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-pencil-square me-1"></i>Set Admin Cost — {{ $loan->loan_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('loans.set-admin-cost', $loan) }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">
                        This loan was disbursed without an Admin Cost recorded. Setting it here adds it to the
                        loan's balance, to be collected through ordinary repayments (priority: interest → admin
                        fee → principal) — nothing is posted to the books here, only as it's actually collected.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Admin Cost <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="any" min="0.01" required
                               value="{{ round($loan->principal * 0.122, 2) }}">
                        <div class="form-text">Defaults to 12.2% of principal ({{ number_format($loan->principal, $dp) }}) — adjust if different.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-warning"><i class="bi bi-check-lg me-1"></i>Set Admin Cost</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endcan

{{-- Delete Loan Modal --}}
@can('create loans')
@if(in_array($loan->status, ['pending', 'approved']))
<div class="modal fade" id="deleteLoanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('loans.destroy', $loan) }}" class="modal-content">
            @csrf @method('DELETE')
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-trash-fill text-danger me-2"></i>Delete Loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>You are about to permanently delete <strong>{{ $loan->loan_number }}</strong> for <strong>{{ $loan->client->name }}</strong>.</p>
                <div class="alert alert-danger py-2 small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    This action cannot be undone. All loan data will be removed.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-4"><i class="bi bi-trash me-1"></i>Delete Loan</button>
            </div>
        </form>
    </div>
</div>
@endif
@endcan


@endsection
