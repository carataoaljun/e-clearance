<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Support\PersonName;
use App\Support\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class InstructorController extends Controller
{
    private array $departments = ['BSIT', 'BSED', 'BEED', 'BSBA', 'BSHM'];

    public function index(Request $request)
    {
        $query = Instructor::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('instructor_id', 'like', "%{$request->search}%")
                    ->orWhere('firstname', 'like', "%{$request->search}%")
                    ->orWhere('lastname', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('department', 'like', "%{$request->search}%");
            });
        }
        if ($request->department) {
            $query->where('department', $request->department);
        }

        $order = in_array($request->order, ['ASC', 'DESC']) ? $request->order : 'DESC';
        $limit = in_array($request->limit, [10, 25, 50, 100]) ? (int) $request->limit : 25;

        $instructors = $query->orderBy('id', $order)->paginate($limit)->withQueryString();
        $departments = $this->departments;

        return view('mainAdmin.instructors.index', compact('instructors', 'departments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'instructor_id' => ['required', 'regex:/^\d{4}$/', 'unique:instructor_account,instructor_id'],
            'firstname' => PersonName::requiredRules(),
            'middlename' => PersonName::optionalRules(),
            'lastname' => PersonName::requiredRules(),
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN.\s\'\-]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:instructor_account,email'],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'department' => 'required|in:BSIT,BSED,BEED,BSBA,BSHM',
        ], [
            'instructor_id.unique' => 'This employee ID is already in use.',
            'email.unique' => 'This email address is already in use.',
            ...PersonName::messages('firstname', 'middlename', 'lastname'),
        ]);
        $plainPassword = ! empty($data['password']) ? $data['password'] : StrongPassword::generate();
        $data['password'] = Hash::make($plainPassword);
        Instructor::create($data);

        return redirect()->route('instructors.index')->with('flash', ['type' => 'success', 'message' => "New instructor added. Initial password: {$plainPassword}"]);
    }

    public function update(Request $request, $instructor_id)
    {
        $inst = Instructor::where('instructor_id', $instructor_id)->firstOrFail();
        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'middlename' => PersonName::optionalRules(),
            'lastname' => PersonName::requiredRules(),
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN.\s\'\-]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', Rule::unique('instructor_account', 'email')->ignore($inst->id)],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'department' => 'required|in:BSIT,BSED,BEED,BSBA,BSHM',
        ], [
            'email.unique' => 'This email address is already in use.',
            ...PersonName::messages('firstname', 'middlename', 'lastname'),
        ]);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $inst->update($data);

        return redirect()->route('instructors.index')->with('flash', ['type' => 'success', 'message' => 'Instructor updated.']);
    }

    public function destroy($instructor_id)
    {
        Instructor::where('instructor_id', $instructor_id)->delete();

        return redirect()->route('instructors.index')->with('flash', ['type' => 'success', 'message' => 'Instructor deleted.']);
    }

    public function reset($instructor_id)
    {
        $instructor = Instructor::where('instructor_id', $instructor_id)->firstOrFail();
        $temporaryPassword = StrongPassword::generate(16);

        $instructor->update(['password' => Hash::make($temporaryPassword)]);

        return redirect()->route('instructors.index')->with('flash', [
            'type' => 'success',
            'message' => "Password reset. One-time temporary password: {$temporaryPassword}",
        ]);
    }
}
