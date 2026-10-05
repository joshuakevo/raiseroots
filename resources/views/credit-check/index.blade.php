@extends('layouts.app')
@section('title', 'Eltech Systems Credit Check')
@section('breadcrumb')
    <li class="breadcrumb-item active">Eltech Systems Credit Check</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-shield-check text-danger me-2"></i>Eltech Systems Credit Check</h4>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">National ID Number</label>
                <input type="text" name="national_id" class="form-control font-monospace" value="{{ $nationalId }}" placeholder="e.g. CM90012345ABCD" required autofocus>
            </div>
            <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Check</button></div>
        </form>
        <div class="form-text">Shows loans this ID holds at other ElTech lenders - works for anyone, including people who aren't your clients yet.</div>
    </div>
</div>

@if($result)
    @if($localClients->isNotEmpty())
        <div class="alert alert-light border small">
            <i class="bi bi-person-check me-1"></i>Your client{{ $localClients->count() > 1 ? 's' : '' }} with this ID:
            @foreach($localClients as $c)
                <a href="{{ route('clients.show', $c) }}" class="fw-semibold">{{ $c->name }}</a>
                <span class="badge bg-light text-secondary border font-monospace fw-normal">{{ $c->client_number }}</span>@if(!$loop->last), @endif
            @endforeach
        </div>
    @endif
    <div class="card">
        <div class="card-header">Loans at other ElTech lenders — <span class="font-monospace">{{ $nationalId }}</span></div>
        <div class="card-body">
            @include('credit-check._results', ['result' => $result])
        </div>
    </div>
@endif
@endsection
