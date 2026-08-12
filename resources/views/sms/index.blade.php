@extends('layouts.app')
@section('title', 'Send SMS')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h5 class="mb-0 fw-semibold">Send SMS</h5>
        <span class="text-muted small">Send SMS reminders to clients — loan repayments due/overdue, dormant savings accounts, or anyone else.</span>
    </div>
    <a href="{{ route('sms.deliveries') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-clock-history me-1"></i>Delivery Log
    </a>
</div>

{{-- Subscription status --}}
@php $paidActive = $subscription?->isActive() ?? false; @endphp
@if($subscriptionRequired)
<div class="card mb-3 border-{{ $paidActive ? 'success' : ($pendingPayment ? 'warning' : ($trialRemaining > 0 ? 'info' : 'danger')) }}">
    <div class="card-body p-3">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                @if($paidActive)
                    <div class="fw-semibold text-success"><i class="bi bi-check-circle-fill me-1"></i>SMS subscription active</div>
                    <div class="small text-muted">Expires {{ $subscription->period_end->format('d M Y') }} ({{ today()->diffInDays($subscription->period_end) }} day(s) left)</div>
                @elseif($pendingPayment)
                    <div class="fw-semibold text-warning"><i class="bi bi-hourglass-split me-1"></i>Payment pending</div>
                    <div class="small text-muted">Waiting for approval on {{ $pendingPayment->phone_number }}. Approve the mobile money prompt, then check status.</div>
                @elseif($trialRemaining > 0)
                    <div class="fw-semibold text-info"><i class="bi bi-gift-fill me-1"></i>Free trial active</div>
                    <div class="small text-muted">{{ $trialRemaining }} of {{ \App\Services\SmsSubscriptionService::TRIAL_LIMIT }} free SMS remaining, no subscription needed yet.</div>
                @else
                    <div class="fw-semibold text-danger"><i class="bi bi-x-circle-fill me-1"></i>SMS subscription not active</div>
                    <div class="small text-muted">Free trial used up. Sending SMS is disabled until you subscribe.</div>
                @endif
            </div>
            <div class="col-md-7">
                @if($pendingPayment)
                    <form method="POST" action="{{ route('sms.subscribe.refresh') }}" class="d-flex justify-content-md-end">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-arrow-clockwise me-1"></i>Check Payment Status
                        </button>
                    </form>
                @elseif(!$paidActive)
                    <form method="POST" action="{{ route('sms.subscribe') }}" class="row g-2 justify-content-md-end align-items-center">
                        @csrf
                        <div class="col-6 col-md-5">
                            <input type="text" name="phone_number" class="form-control form-control-sm" placeholder="Mobile money phone e.g. 0770000000" required>
                        </div>
                        <div class="col-3 col-md-4 small text-muted text-end">
                            UGX {{ number_format($subscriptionAmount, 0) }}/month
                        </div>
                        <div class="col-3 col-md-3">
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="bi bi-credit-card me-1"></i>Subscribe
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@else
<div class="alert alert-secondary py-2 small mb-3">
    <i class="bi bi-info-circle me-1"></i>SMS subscription is turned off (Settings &rsaquo; Modules) — sending is unrestricted.
</div>
@endif

<form method="POST" action="{{ route('sms.send') }}" id="smsForm">
@csrf

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body p-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-1 small">Recipient Group</label>
                <select name="group" id="groupSelect" class="form-select form-select-sm">
                    @foreach($groups as $key => $label)
                        <option value="{{ $key }}" @selected(old('group', 'all') == $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2" id="daysFieldWrap" style="display:none">
                <label class="form-label mb-1 small" id="daysFieldLabel">Due Within (days)</label>
                <input type="number" name="due_within" id="daysField" class="form-control form-control-sm" min="1" max="365" value="7">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1 small">Search</label>
                <input type="text" name="search" id="searchField" class="form-control form-control-sm" placeholder="Name or client #...">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-primary w-100" id="filterBtn">
                    <i class="bi bi-funnel-fill me-1"></i>Filter
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header py-2 small fw-semibold">Message</div>
            <div class="card-body p-3">
                <textarea name="message" id="messageInput" class="form-control @error('message') is-invalid @enderror"
                          rows="7" maxlength="459" required placeholder="Dear {name}, ...">{{ old('message') }}</textarea>
                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">
                    <span id="charCount">0</span> characters (~<span id="segCount">1</span> SMS segment(s)).
                    Placeholders: <span id="placeholderHelp" class="text-primary"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="small fw-semibold"><span id="recipientCount">0</span> recipient(s) found</span>
                <div class="text-end">
                    <button type="button" class="btn btn-sm btn-success" id="sendBtn" disabled>
                        <i class="bi bi-send-fill me-1"></i>Send SMS to Selected (<span id="selectedCount">0</span>)
                    </button>
                    @unless($sendAllowed)
                    <div class="small text-danger mt-1">Subscribe above to enable sending</div>
                    @endunless
                </div>
            </div>
            <div class="table-responsive" style="max-height:520px;overflow-y:auto">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="sticky-top bg-white">
                        <tr>
                            <th style="width:32px"><input type="checkbox" id="selectAll"></th>
                            <th>Client</th>
                            <th>Phone</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody id="recipientsBody">
                        <tr><td colspan="4" class="text-center text-muted py-4 small">Click "Filter" to load recipients.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</form>

{{-- Send progress modal --}}
<div class="modal" id="sendProgressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="sendProgressTitle">Sending SMS...</h6>
            </div>
            <div class="modal-body">
                <div class="progress mb-2" style="height:20px">
                    <div class="progress-bar bg-success" id="sendProgressBar" style="width:0%">0%</div>
                </div>
                <div class="small text-muted mb-2" id="sendProgressText">Preparing...</div>
                <div id="sendResultsList" style="max-height:220px;overflow-y:auto;font-size:.8rem"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="sendCancelBtn">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary d-none" id="sendCloseBtn" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const EXTRA_PLACEHOLDERS = @json($extraPlaceholders);
const DEFAULT_TEMPLATES  = @json($defaultTemplates);
const SUBSCRIPTION_ACTIVE = @json($sendAllowed);
const COMMON_PLACEHOLDERS = ['name', 'client_number', 'org'];
let lastAutoFilled = null; // tracks the auto-filled text so group changes don't clobber a hand-edited message

const groupSelect     = document.getElementById('groupSelect');
const daysFieldWrap   = document.getElementById('daysFieldWrap');
const daysFieldLabel  = document.getElementById('daysFieldLabel');
const daysField       = document.getElementById('daysField');
const searchField     = document.getElementById('searchField');
const filterBtn       = document.getElementById('filterBtn');
const recipientsBody  = document.getElementById('recipientsBody');
const recipientCount  = document.getElementById('recipientCount');
const selectAll       = document.getElementById('selectAll');
const sendBtn         = document.getElementById('sendBtn');
const selectedCount   = document.getElementById('selectedCount');
const messageInput    = document.getElementById('messageInput');
const charCount       = document.getElementById('charCount');
const segCount        = document.getElementById('segCount');
const placeholderHelp = document.getElementById('placeholderHelp');

function escHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function updatePlaceholderHelp() {
    const extra = EXTRA_PLACEHOLDERS[groupSelect.value] || [];
    placeholderHelp.textContent = COMMON_PLACEHOLDERS.concat(extra).map(p => `{${p}}`).join(', ');
}

// Fill the composer with the group's default template — but only if the box is
// still empty or still holds the last auto-filled text, so a hand-edited
// message (or one restored via old('message') after a validation error) is never clobbered.
function applyDefaultTemplate() {
    const template = DEFAULT_TEMPLATES[groupSelect.value] || '';
    if (messageInput.value.trim() === '' || messageInput.value === lastAutoFilled) {
        messageInput.value = template;
        lastAutoFilled = template;
        updateCharCount();
    }
}

function updateDaysField() {
    if (groupSelect.value === 'loan_due_soon') {
        daysFieldWrap.style.display = '';
        daysFieldLabel.textContent = 'Due Within (days)';
        daysField.name = 'due_within';
        daysField.value = daysField.value || 7;
    } else if (groupSelect.value === 'dormant_savings') {
        daysFieldWrap.style.display = '';
        daysFieldLabel.textContent = 'Dormant For (days)';
        daysField.name = 'dormant_days';
        daysField.value = daysField.value || 60;
    } else {
        daysFieldWrap.style.display = 'none';
    }
}

function updateCharCount() {
    const len = messageInput.value.length;
    charCount.textContent = len;
    segCount.textContent = len <= 160 ? 1 : Math.ceil(len / 153);
}

function updateSendState() {
    const checked = recipientsBody.querySelectorAll('input.recipient-check:checked').length;
    selectedCount.textContent = checked;
    sendBtn.disabled = checked === 0 || !SUBSCRIPTION_ACTIVE;
}

function loadRecipients() {
    recipientsBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4 small">Loading...</td></tr>';

    const params = new URLSearchParams({
        group: groupSelect.value,
        search: searchField.value,
    });
    if (daysFieldWrap.style.display !== 'none') {
        params.set(daysField.name, daysField.value);
    }

    fetch(`{{ route('sms.recipients') }}?` + params.toString())
        .then(r => r.json())
        .then(data => {
            recipientCount.textContent = data.count;
            if (!data.recipients.length) {
                recipientsBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4 small">No recipients match these filters.</td></tr>';
                updateSendState();
                return;
            }
            recipientsBody.innerHTML = data.recipients.map(r => `
                <tr>
                    <td><input type="checkbox" class="recipient-check" name="client_ids[]" value="${r.client_id}" checked></td>
                    <td>${escHtml(r.name)}</td>
                    <td>${escHtml(r.phone)}</td>
                    <td class="small text-muted">${escHtml(r.detail)}</td>
                </tr>
            `).join('');
            selectAll.checked = true;
            recipientsBody.querySelectorAll('input.recipient-check').forEach(cb => cb.addEventListener('change', updateSendState));
            updateSendState();
        });
}

groupSelect.addEventListener('change', () => { updateDaysField(); updatePlaceholderHelp(); applyDefaultTemplate(); });
filterBtn.addEventListener('click', loadRecipients);
messageInput.addEventListener('input', updateCharCount);
selectAll.addEventListener('change', () => {
    recipientsBody.querySelectorAll('input.recipient-check').forEach(cb => cb.checked = selectAll.checked);
    updateSendState();
});

// ── Send with a live progress bar (replaces the native confirm() popup) ──
const sendProgressModalEl = document.getElementById('sendProgressModal');
const sendProgressModal   = new bootstrap.Modal(sendProgressModalEl);
const sendProgressTitle   = document.getElementById('sendProgressTitle');
const sendProgressBar     = document.getElementById('sendProgressBar');
const sendProgressText    = document.getElementById('sendProgressText');
const sendResultsList     = document.getElementById('sendResultsList');
const sendCancelBtn       = document.getElementById('sendCancelBtn');
const sendCloseBtn        = document.getElementById('sendCloseBtn');
const csrfToken           = document.querySelector('meta[name="csrf-token"]').content;

function setProgressBar(done, total) {
    const pct = total ? Math.round((done / total) * 100) : 0;
    sendProgressBar.style.width = pct + '%';
    sendProgressBar.textContent = pct + '%';
}

function appendSendResult(name, phone, ok) {
    const badge = ok ? '<span class="badge bg-success">Sent</span>' : '<span class="badge bg-danger">Failed</span>';
    sendResultsList.insertAdjacentHTML('beforeend',
        `<div class="d-flex justify-content-between border-bottom py-1"><span>${escHtml(name)} ${phone ? '(' + escHtml(phone) + ')' : ''}</span>${badge}</div>`);
    sendResultsList.scrollTop = sendResultsList.scrollHeight;
}

sendBtn.addEventListener('click', async () => {
    const clientIds = Array.from(recipientsBody.querySelectorAll('input.recipient-check:checked')).map(cb => cb.value);
    if (!clientIds.length) return;

    if (!confirm(`Send this SMS to ${clientIds.length} selected client(s) now?`)) return;

    const total = clientIds.length;
    let sent = 0, failed = 0, cancelled = false;

    sendProgressTitle.textContent = 'Sending SMS...';
    sendResultsList.innerHTML = '';
    setProgressBar(0, total);
    sendProgressText.textContent = `0 of ${total}`;
    sendCloseBtn.classList.add('d-none');
    sendCancelBtn.classList.remove('d-none');
    sendCancelBtn.disabled = false;
    sendProgressModal.show();

    sendCancelBtn.onclick = () => { cancelled = true; sendCancelBtn.disabled = true; };

    for (let i = 0; i < clientIds.length; i++) {
        if (cancelled) break;

        const params = new URLSearchParams({
            group: groupSelect.value,
            search: searchField.value,
            message: messageInput.value,
            client_id: clientIds[i],
        });
        if (daysFieldWrap.style.display !== 'none') {
            params.set(daysField.name, daysField.value);
        }

        try {
            const res = await fetch(`{{ route('sms.send-one') }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                },
                body: params.toString(),
            });
            const data = await res.json();
            if (data.sent) sent++; else failed++;
            appendSendResult(data.client || '(no longer matches filters)', data.phone, !!data.sent);
        } catch (e) {
            failed++;
            appendSendResult('(request failed)', '', false);
        }

        setProgressBar(i + 1, total);
        sendProgressText.textContent = `${i + 1} of ${total} — ${sent} sent, ${failed} failed`;
    }

    sendProgressTitle.textContent = cancelled ? 'Sending cancelled' : 'Sending complete';
    sendCancelBtn.classList.add('d-none');
    sendCloseBtn.classList.remove('d-none');
});

sendProgressModalEl.addEventListener('hidden.bs.modal', loadRecipients);

updateDaysField();
updatePlaceholderHelp();
applyDefaultTemplate();
updateCharCount();
loadRecipients();
</script>
@endpush
@endsection
