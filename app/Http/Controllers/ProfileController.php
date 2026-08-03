<?php

namespace App\Http\Controllers;

use App\Models\MainAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('mainAdmin.profile.edit', [
            'user' => MainAdmin::findOrFail($request->session()->get('admin_id')),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        $admin = MainAdmin::findOrFail($request->session()->get('admin_id'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('main_admin', 'email')->ignore($admin->id),
            ],
            'current_password' => ['nullable', 'string', 'max:255'],
        ]);

        if (! hash_equals(strtolower((string) $admin->email), strtolower($data['email']))
            && ! Hash::check((string) ($data['current_password'] ?? ''), (string) $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is required to change the administrator email address.',
            ]);
        }

        $admin->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);
        $request->session()->put([
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ]);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
}
