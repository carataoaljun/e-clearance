@extends('layouts.portal')

@section('title', 'Edit Account')
@section('portal-name', 'Account Settings')
@section('portal-subtitle', 'Update your profile information')
@section('page-title', 'Edit Account')
@section('user-label', $user->firstname . ' ' . $user->lastname)
@section('user-role', ucfirst($guard))

@section('nav')
    <a class="nav-link" href="{{ route($routePrefix . '.dashboard') }}">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route($routePrefix . '.logout') }}">
        @csrf
        <button type="submit" class="btn btn-outline-light w-100 btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i> Log Out
        </button>
    </form>
@endsection

@section('content')
    <div class="card card-stat p-4">
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route($routePrefix . '.account.update') }}">
            @csrf
            @method('PUT')

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input name="firstname" value="{{ old('firstname', $user->firstname) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input name="lastname" value="{{ old('lastname', $user->lastname) }}" class="form-control" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Middle Name</label>
                    <input name="middlename" value="{{ old('middlename', $user->middlename) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Suffix</label>
                    <input name="suffix" value="{{ old('suffix', $user->suffix) }}" class="form-control">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" autocomplete="current-password">
                    <div class="form-text">Required when changing your email address or password.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" autocomplete="new-password">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
@endsection
