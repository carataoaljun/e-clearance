@extends('layouts.portal')

@section('title', 'Clearance Verification')
@section('portal-name', 'Registrar Portal')
@section('portal-subtitle', 'Clearance Verification')
@section('page-title', 'Clearance Verification')

@section('nav')
    <a class="nav-link" href="{{ route('registrar.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link active" href="{{ route('registrar.student-clearance') }}"><i class="bi bi-shield-check me-2"></i> Student Clearance</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('registrar.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@section('content')
    <div class="card card-stat mx-auto" style="max-width:680px;">
        <div class="card-body p-4 text-center">
            <i class="bi bi-patch-check-fill fs-1 {{ $overallStatus === 'Cleared' ? 'text-success' : 'text-warning' }}"></i>
            <h4 class="mt-2 mb-1">Clearance Record Verified</h4>
            <p class="text-secondary mb-4">This QR code belongs to the student record below.</p>
            <dl class="row text-start mb-3">
                <dt class="col-sm-4">Student</dt><dd class="col-sm-8">{{ $student->full_name }}</dd>
                <dt class="col-sm-4">Student ID</dt><dd class="col-sm-8">{{ $student->student_id }}</dd>
                <dt class="col-sm-4">Program / Year</dt><dd class="col-sm-8">{{ $student->program }} — {{ $student->year_level }} ({{ $student->section }})</dd>
                <dt class="col-sm-4">Clearance status</dt><dd class="col-sm-8"><span class="badge text-bg-{{ $overallStatus === 'Cleared' ? 'success' : 'warning' }}">{{ $overallStatus }}</span></dd>
            </dl>
            <a class="btn btn-primary" href="{{ route('registrar.clearance.form', $student->student_id) }}">Open Clearance Form</a>
        </div>
    </div>
@endsection
