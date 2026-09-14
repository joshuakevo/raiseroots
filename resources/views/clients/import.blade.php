@extends('layouts.app')
@section('title', 'Import Clients')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">Clients</a></li>
    <li class="breadcrumb-item active">Import</li>
@endsection
@section('content')
<div class="row justify-content-center">
<div class="col-xl-8 col-lg-10">

<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="fw-bold mb-0">Import Clients</h4>
    <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Clients</a>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(isset($result))
<div class="card mb-3 border-success">
    <div class="card-header d-flex align-items-center gap-2 fw-semibold">
        <i class="bi bi-check-circle-fill text-success"></i> Import complete
    </div>
    <div class="card-body">
        <p class="mb-2"><strong>{{ $result['created'] }}</strong> client(s) created.</p>

        @if(count($result['skipped_duplicate_in_file']))
        <div class="mt-3">
            <div class="fw-semibold text-danger small mb-1">Skipped — duplicate reference number within the file ({{ count($result['skipped_duplicate_in_file']) }})</div>
            <ul class="small text-muted mb-0">
                @foreach($result['skipped_duplicate_in_file'] as $line)<li>{{ $line }}</li>@endforeach
            </ul>
        </div>
        @endif

        @if(count($result['skipped_existing']))
        <div class="mt-3">
            <div class="fw-semibold text-warning small mb-1">Skipped — client number already exists ({{ count($result['skipped_existing']) }})</div>
            <ul class="small text-muted mb-0">
                @foreach($result['skipped_existing'] as $line)<li>{{ $line }}</li>@endforeach
            </ul>
        </div>
        @endif

        @if(count($result['flagged_phones']))
        <div class="mt-3">
            <div class="fw-semibold text-info small mb-1">Created but phone number looks off — check manually ({{ count($result['flagged_phones']) }})</div>
            <ul class="small text-muted mb-0">
                @foreach($result['flagged_phones'] as $line)<li>{{ $line }}</li>@endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endif

<div class="card">
    <div class="card-header fw-semibold">Upload File</div>
    <div class="card-body">
        <p class="text-muted small">
            Upload a CSV file with a header row. Only a <strong>Name</strong> column is required — the importer
            also recognizes <strong>Reference Number</strong> / <strong>Client Number</strong>, <strong>Phone</strong> /
            <strong>Telephone Number</strong>, and <strong>NIN</strong> / <strong>ID Number</strong> columns (matched
            case-insensitively, so exports from other systems don't need to be reformatted first). New clients are
            created as <code>individual</code> / <code>active</code>; everything else (gender, next of kin, etc.)
            is left blank for staff to fill in later. If a row's Reference Number matches an existing client, or
            repeats within the file, that row is skipped and listed above rather than overwriting anything.
        </p>
        <form method="POST" action="{{ route('clients.import.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label small fw-semibold">CSV File <span class="text-danger">*</span></label>
                    <input type="file" name="file" accept=".csv,.txt" class="form-control @error('file') is-invalid @enderror" required>
                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Branch <span class="text-muted fw-normal">(optional, applied to every imported client)</span></label>
                    <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                        <option value="">— No branch —</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">
                <i class="bi bi-upload me-1"></i>Import
            </button>
        </form>
    </div>
</div>

</div>
</div>
@endsection
