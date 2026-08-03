@extends('mainAdmin.layouts.admin')
@section('title', 'Account Settings — ClearanceMS')

@section('content')
<x-main-admin.page-header
    title="Account Settings"
    description="Keep your administrator identity and sign-in credentials up to date."
    icon="bi bi-person-gear"
    eyebrow="Administrator account"
/>

<div class="admin-profile-grid">
    <section class="admin-profile-card">
        <div class="admin-card-heading">
            <span class="admin-card-icon" aria-hidden="true"><i class="bi bi-person-vcard"></i></span>
            <div>
                <h2>Profile Information</h2>
                <p>Change the name and email used by your administrator account.</p>
            </div>
        </div>

        @if(session('status') === 'profile-updated')
            <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Profile information updated.</div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PATCH')
            <div class="mb-3">
                <label for="admin_name" class="form-label">Name</label>
                <input id="admin_name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required maxlength="255">
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label for="admin_email" class="form-label">Email Address</label>
                <input id="admin_email" type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required maxlength="255">
                @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label for="profile_current_password" class="form-label">Current Password</label>
                <input id="profile_current_password" type="password" name="current_password" class="form-control" autocomplete="current-password">
                <div class="form-text">Required only when changing the administrator email address.</div>
                @error('current_password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn-save"><i class="bi bi-check-circle-fill"></i> Save Profile</button>
        </form>
    </section>

    <section class="admin-profile-card">
        <div class="admin-card-heading">
            <span class="admin-card-icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
            <div>
                <h2>Change Password</h2>
                <p>Confirm your current password before choosing a new one.</p>
            </div>
        </div>

        @if(session('status') === 'password-updated')
            <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Password updated successfully.</div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label for="current_password" class="form-label">Current Password</label>
                <input id="current_password" type="password" name="current_password" class="form-control" required autocomplete="current-password">
                @error('current_password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input id="new_password" type="password" name="password" class="form-control" required autocomplete="new-password">
                <div class="form-text">At least 8 characters with uppercase, lowercase, number, and symbol.</div>
                @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn-save"><i class="bi bi-shield-lock-fill"></i> Update Password</button>
        </form>
    </section>
</div>
@endsection
