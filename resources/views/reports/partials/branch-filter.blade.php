@if(auth()->user()->isBranchScoped())
<div class="col-md-3">
    <label class="form-label small fw-semibold">Branch</label>
    <input type="text" class="form-control" value="{{ auth()->user()->branch?->name ?? 'No branch assigned' }}" disabled>
</div>
@else
<div class="col-md-3">
    <label class="form-label small fw-semibold">Branch</label>
    <select name="branch_id" class="form-select">
        <option value="">All Branches</option>
        @foreach($branches as $b)
        <option value="{{ $b->id }}" {{ (string) $branchId === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
        @endforeach
    </select>
</div>
@endif
