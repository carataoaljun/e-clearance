<?php

namespace App\Http\Controllers;

use App\Models\Registrar;
use App\Support\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistrarController extends Controller
{
    public function index(Request $request)
    {
        $query = Registrar::query();
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('firstname', 'like', "%{$request->search}%")
                    ->orWhere('lastname', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('registrar_id', 'like', "%{$request->search}%");
            });
        }
        $registrars = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return view('mainAdmin.registrar.index', compact('registrars'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstname' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\pL\s\'\-]+$/u'],
            'lastname' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\pL\s\'\-]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:registrar,email'],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'email.unique' => 'This email address is already in use.',
        ]);
        $plainPassword = ! empty($data['password']) ? $data['password'] : StrongPassword::generate();
        $data['password'] = Hash::make($plainPassword);
        $data['registrar_id'] = $this->generateId();
        $data['role'] = 'registrar';
        Registrar::create($data);

        return redirect()->route('registrar.index')->with('flash', ['type' => 'success', 'message' => "Registrar account created. Initial password: {$plainPassword}"]);
    }

    public function update(Request $request, $id)
    {
        $r = Registrar::findOrFail($id);
        $data = $request->validate([
            'firstname' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\pL\s\'\-]+$/u'],
            'lastname' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\pL\s\'\-]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', Rule::unique('registrar', 'email')->ignore($id)],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'email.unique' => 'This email address is already in use.',
        ]);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $r->update($data);

        return redirect()->route('registrar.index')->with('flash', ['type' => 'success', 'message' => 'Registrar updated.']);
    }

    public function destroy($id)
    {
        Registrar::destroy($id);

        return redirect()->route('registrar.index')->with('flash', ['type' => 'success', 'message' => 'Registrar deleted.']);
    }

    private function generateId(): string
    {
        do {
            $id = 'REG-'.random_int(10000, 99999);
        } while (Registrar::where('registrar_id', $id)->exists());

        return $id;
    }
}
