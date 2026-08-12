@extends('layouts.app')
@section('title', 'Edit Employee')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Employees</a></li>
    <li class="breadcrumb-item active">Edit — {{ $employee->name }}</li>
@endsection
@section('content')
<div class="card shadow-sm">
    <div class="card-header py-2 px-3 fw-semibold">Edit Employee — {{ $employee->employee_number }}</div>
    <div class="card-body p-3">
    <form method="POST" action="{{ route('employees.update', $employee) }}">
        @csrf @method('PUT')
        @include('employees._form', ['employee' => $employee])
        <div class="d-flex flex-wrap gap-2 pt-3 mt-2 border-top">
            <button type="submit" class="btn btn-primary btn-sm">Update employee</button>
            <a href="{{ route('employees.show', $employee) }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
    </form>
    </div>
</div>
@endsection
