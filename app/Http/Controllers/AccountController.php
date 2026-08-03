<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function edit()
    {
        $user = $this->getAuthenticatedUser();
        if (! $user instanceof Model) {
            abort(403);
        }

        $guard = $this->getAuthenticatedGuard();
        $routePrefix = $this->getRoutePrefix($guard);

        return view('portal.account.edit', [
            'user' => $user,
            'guard' => $guard,
            'routePrefix' => $routePrefix,
        ]);
    }

    public function update(Request $request)
    {
        $user = $this->getAuthenticatedUser();
        if (! $user instanceof Model) {
            abort(403);
        }

        $userTable = $user->getTable();
        $userKeyName = $user->getKeyName();
        $userKey = $user->getKey();

        $data = $request->validate([
            'firstname' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\pL\s\'\-]+$/u'],
            'lastname' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\pL\s\'\-]+$/u'],
            'middlename' => ['nullable', 'string', 'max:20', 'regex:/^[\pL\s\'\-]+$/u'],
            'suffix' => ['nullable', 'string', 'max:25'],
            'email' => [
                'required',
                'lowercase',
                'email',
                'max:100',
                Rule::unique($userTable, 'email')->ignore($userKey, $userKeyName),
            ],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'current_password' => ['nullable', 'string', 'max:255'],
        ]);

        $emailChanged = ! hash_equals(strtolower((string) $user->email), strtolower($data['email']));
        if (($emailChanged || ! empty($data['password']))
            && ! Hash::check((string) ($data['current_password'] ?? ''), (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is required to change the email address or password.',
            ]);
        }

        $user->firstname = $data['firstname'];
        $user->lastname = $data['lastname'];
        $user->middlename = $data['middlename'] ?? null;
        $user->suffix = $data['suffix'] ?? null;
        $user->email = $data['email'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        $routePrefix = $this->getRoutePrefix($this->getAuthenticatedGuard());
        $redirect = $routePrefix ? route($routePrefix.'.account.edit') : url()->previous();

        return redirect($redirect)->with('status', 'Account updated successfully.');
    }

    private function getAuthenticatedGuard(): ?string
    {
        $routeName = (string) request()->route()?->getName();
        foreach (['office', 'treasurer', 'registrar', 'student', 'instructor'] as $routeGuard) {
            if (str_starts_with($routeName, $routeGuard.'.')) {
                return Auth::guard($routeGuard)->check() ? $routeGuard : null;
            }
        }

        foreach (['office', 'treasurer', 'registrar', 'student', 'instructor', 'admin'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return Auth::check() ? Auth::getDefaultDriver() : null;
    }

    private function getAuthenticatedUser()
    {
        $guard = $this->getAuthenticatedGuard();
        if (! $guard) {
            return null;
        }

        return Auth::guard($guard)->user();
    }

    private function getRoutePrefix(?string $guard): ?string
    {
        return match ($guard) {
            'office' => 'office',
            'treasurer' => 'treasurer',
            'registrar' => 'registrar',
            'student' => 'student',
            'instructor' => 'instructor',
            'admin' => 'mainAdmin',
            default => null,
        };
    }
}
