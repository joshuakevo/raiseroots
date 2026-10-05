{{--
    ElTech Credit Check card, filled in after the page loads so a slow registry never
    delays it. Usage: @include('credit-check._panel', ['nationalId' => ..., 'panelId' => 'x'])
--}}
<div class="card mb-3" id="creditCheck-{{ $panelId }}">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-shield-check text-danger me-1"></i>Eltech Systems Credit Check
            @if($nationalId)<span class="badge bg-light text-secondary border font-monospace fw-normal ms-1">{{ $nationalId }}</span>@endif
        </span>
        @if($nationalId)
            <a href="{{ route('credit-check.index', ['national_id' => $nationalId]) }}" class="small">Open full check →</a>
        @endif
    </div>
    <div class="card-body js-credit-check-body">
        @if(!$nationalId)
            <div class="text-muted small">No national ID number on this client, so they can't be checked. Add it on the client's profile.</div>
        @else
            <div class="text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Checking other ElTech lenders…</div>
        @endif
    </div>
</div>
@if($nationalId)
@push('scripts')
<script>
(function () {
    const body = document.querySelector('#creditCheck-{{ $panelId }} .js-credit-check-body');
    fetch(@json(route('credit-check.panel', ['national_id' => $nationalId])), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => { if (!r.ok) throw new Error(r.status); return r.text(); })
        .then(html => { body.innerHTML = html; })
        .catch(() => { body.innerHTML = '<div class="alert alert-warning small mb-0">Could not load the credit check. Refresh to try again.</div>'; });
})();
</script>
@endpush
@endif
