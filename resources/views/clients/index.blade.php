@extends('layouts.app')
@section('title', 'Clients')
@section('breadcrumb')
    <li class="breadcrumb-item active">Clients</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Clients</h4>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}"><i class="bi bi-filetype-csv me-2 text-success"></i>Export CSV</a></li>
                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            </ul>
        </div>
        @can('create clients')
        <a href="{{ route('clients.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Client</a>
        @endcan
    </div>
</div>

<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search name, number, phone..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(request('status')=='active')>Active</option>
                    <option value="inactive" @selected(request('status')=='inactive')>Inactive</option>
                    <option value="blacklisted" @selected(request('status')=='blacklisted')>Blacklisted</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
            <div class="col-auto"><a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">Clear</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">#</th>
                <th>Client Number</th>
                <th>Name</th>
                <th>Loan Officer</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Status</th>
                @if(\App\Models\SystemSetting::get('membership_fee_module_enabled', '1'))
                <th>Membership</th>
                @endif
                <th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($clients as $client)
                <tr>
                    <td class="ps-3 text-muted">{{ $loop->iteration }}</td>
                    <td><span class="font-monospace">{{ $client->client_number }}</span></td>
                    <td class="fw-semibold">{{ $client->name }}</td>
                    <td class="small">
                        @can('assign relationship manager')
                        <select class="form-select form-select-sm loan-officer-select" style="min-width:170px"
                            data-action="{{ route('clients.relationship-manager', $client) }}"
                            data-original="{{ $client->relationship_manager_id }}"
                            aria-label="Loan Officer for {{ $client->name }}">
                            <option value="">{{ $client->createdBy?->name ? $client->createdBy->name . ' (default)' : 'Unassigned' }}</option>
                            @foreach($relationshipManagers as $officer)
                                <option value="{{ $officer->id }}" @selected($client->relationship_manager_id == $officer->id)>{{ $officer->name }}</option>
                            @endforeach
                        </select>
                        @else
                        {{ $client->relationship_manager_name ?? '—' }}
                        @endcan
                    </td>
                    <td>{{ $client->phone ?? '—' }}</td>
                    <td>{{ $client->email ?? '—' }}</td>
                    <td>
                        <span class="badge badge-status-{{ $client->status }}">{{ ucfirst($client->status) }}</span>
                    </td>
                    @if(\App\Models\SystemSetting::get('membership_fee_module_enabled', '1'))
                    <td>
                        <span class="badge badge-membership-{{ $client->membership_fee_status }}">
                            {{ ucfirst($client->membership_fee_status) }}
                        </span>
                    </td>
                    @endif
                    <td class="pe-3">
                        <a href="{{ route('clients.show', $client) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('edit clients')
                        <a href="{{ route('clients.edit', $client) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        @if(($client->client_type ?? 'individual') === 'individual')
                        @if($client->email)
                        <button type="button" class="btn btn-sm btn-outline-success invite-btn"
                            title="Send portal invitation"
                            data-action="{{ route('clients.invite', $client) }}"
                            data-name="{{ $client->name }}"
                            data-email="{{ $client->email }}"
                            data-type="individual">
                            <i class="bi bi-envelope-arrow-up"></i>
                        </button>
                        @else
                        <button class="btn btn-sm btn-outline-success" disabled title="No email address on file"><i class="bi bi-envelope-arrow-up"></i></button>
                        @endif
                        @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No clients found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $clients->withQueryString()->links() }}</div>
</div>

{{-- Invite modal --}}
<div class="modal fade" id="inviteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope-arrow-up me-2 text-success"></i>Send Portal Invitation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Confirm state --}}
                <div id="inviteConfirmBody">
                    <p class="mb-1" id="inviteIntroText">Send a password reset link to:</p>
                    <div class="d-flex align-items-center gap-2 p-2 bg-light rounded mb-3">
                        <i class="bi bi-person-circle fs-4 text-muted" id="inviteIcon"></i>
                        <div>
                            <div class="fw-semibold" id="inviteClientName"></div>
                            <div class="text-muted small" id="inviteClientEmail"></div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0" id="inviteHelpText">The client will receive a link to set their password and access their portal.</p>
                </div>
                {{-- Sending state --}}
                <div id="inviteSendingBody" class="d-none text-center py-3">
                    <div class="spinner-border text-success mb-3" role="status" style="width:2.5rem;height:2.5rem"></div>
                    <div class="fw-semibold">Sending invitation…</div>
                    <div class="text-muted small">Please wait</div>
                </div>
                {{-- Result state --}}
                <div id="inviteResultBody" class="d-none text-center py-2">
                    <div id="inviteResultIcon" class="fs-1 mb-2"></div>
                    <div id="inviteResultMsg" class="fw-semibold"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0" id="inviteFooter">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4" id="inviteSendBtn">
                    <i class="bi bi-send me-1"></i>Send Invitation
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    let inviteAction = '';
    const modal       = new bootstrap.Modal(document.getElementById('inviteModal'));
    const confirmBody = document.getElementById('inviteConfirmBody');
    const sendingBody = document.getElementById('inviteSendingBody');
    const resultBody  = document.getElementById('inviteResultBody');
    const footer      = document.getElementById('inviteFooter');
    const sendBtn     = document.getElementById('inviteSendBtn');

    function resetModal() {
        confirmBody.classList.remove('d-none');
        sendingBody.classList.add('d-none');
        resultBody.classList.add('d-none');
        footer.innerHTML = `
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success px-4" id="inviteSendBtn">
                <i class="bi bi-send me-1"></i>Send Invitation
            </button>`;
        document.getElementById('inviteSendBtn').addEventListener('click', doSend);
    }

    document.querySelectorAll('.invite-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            inviteAction = btn.dataset.action;
            const isGroup = btn.dataset.type === 'group';
            document.getElementById('inviteClientName').textContent = btn.dataset.name;
            if (isGroup) {
                document.getElementById('inviteIntroText').textContent  = 'Send portal invitations to members of:';
                document.getElementById('inviteClientEmail').textContent = 'All active members with portal accounts will be notified.';
                document.getElementById('inviteIcon').className = 'bi bi-people-fill fs-4 text-primary';
                document.getElementById('inviteHelpText').textContent = 'Each member will receive a link to set their password and access the group portal.';
            } else {
                document.getElementById('inviteIntroText').textContent  = 'Send a password reset link to:';
                document.getElementById('inviteClientEmail').textContent = btn.dataset.email;
                document.getElementById('inviteIcon').className = 'bi bi-person-circle fs-4 text-muted';
                document.getElementById('inviteHelpText').textContent = 'The client will receive a link to set their password and access their portal.';
            }
            resetModal();
            modal.show();
        });
    });

    async function doSend() {
        confirmBody.classList.add('d-none');
        footer.classList.add('d-none');
        sendingBody.classList.remove('d-none');

        try {
            const res  = await fetch(inviteAction, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });
            const data = await res.json();
            sendingBody.classList.add('d-none');
            resultBody.classList.remove('d-none');
            const icon = document.getElementById('inviteResultIcon');
            const msg  = document.getElementById('inviteResultMsg');
            if (data.success) {
                icon.innerHTML  = '<i class="bi bi-check-circle-fill text-success"></i>';
                msg.textContent = data.message;
                msg.className   = 'fw-semibold text-success mt-1';
            } else {
                icon.innerHTML  = '<i class="bi bi-x-circle-fill text-danger"></i>';
                msg.textContent = data.message;
                msg.className   = 'fw-semibold text-danger mt-1';
            }
        } catch (e) {
            sendingBody.classList.add('d-none');
            resultBody.classList.remove('d-none');
            document.getElementById('inviteResultIcon').innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
            document.getElementById('inviteResultMsg').textContent = 'Network error. Please try again.';
        }

        footer.innerHTML = '<button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>';
        footer.classList.remove('d-none');
    }

    sendBtn.addEventListener('click', doSend);
})();

// Inline Loan Officer change: saves on selection, reverts the dropdown if the save fails.
(function () {
    const token = document.querySelector('meta[name="csrf-token"]').content;

    document.querySelectorAll('.loan-officer-select').forEach(select => {
        select.addEventListener('change', async () => {
            const previous = select.dataset.original;
            select.disabled = true;
            select.classList.remove('is-valid', 'is-invalid');

            try {
                const res = await fetch(select.dataset.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ relationship_manager_id: select.value || null }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Could not update the Loan Officer.');
                }
                select.dataset.original = select.value;
                select.classList.add('is-valid');
                setTimeout(() => select.classList.remove('is-valid'), 1500);
            } catch (e) {
                select.value = previous;
                select.classList.add('is-invalid');
                setTimeout(() => select.classList.remove('is-invalid'), 2500);
                alert(e.message || 'Network error. Please try again.');
            } finally {
                select.disabled = false;
            }
        });
    });
})();
</script>
@endpush
@endsection
