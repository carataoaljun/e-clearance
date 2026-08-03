<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\ProgramSection;
use App\Models\SubjectCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstructorAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = InstructorAssignment::with(['instructor', 'subject']);

        if ($request->program) {
            $query->where('program', $request->program);
        }
        if ($request->year_level) {
            $query->where('year_level', $request->year_level);
        }
        if ($request->section) {
            $query->where('section', 'like', "%{$request->section}%");
        }

        $assignments = $query->orderByDesc('assignment_id')->paginate(25)->withQueryString();
        $instructors = Instructor::orderBy('lastname')->get();
        $subjects = SubjectCode::orderBy('subject_code')->get();
        $sections = ProgramSection::orderBy('program')->orderBy('year_level')->orderBy('section')->get();

        return view('mainAdmin.assignments.index', compact('assignments', 'instructors', 'subjects', 'sections'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'instructor_id' => 'required|string|max:50|exists:instructor_account,instructor_id',
            'subject_id' => 'required|integer|min:1|exists:subject_codes,subject_id',
            'program' => 'required|in:BSIT,BSBA,BSHM,BSED,BEED',
            'year_level' => 'required|integer|in:1,2,3,4',
            'sections' => 'required|array|min:1|max:20',
            'sections.*' => 'required|string|max:50|distinct',
        ]);
        $this->ensureSubjectScope($data);

        $sections = collect($data['sections'])->map(fn ($section) => strtoupper(trim($section)))->unique()->values();
        $this->ensureManagedSections($data, $sections);
        $created = 0;

        DB::transaction(function () use ($data, $sections, &$created): void {
            foreach ($sections as $section) {
                $exists = InstructorAssignment::where([
                    'subject_id' => $data['subject_id'], 'program' => $data['program'],
                    'year_level' => $data['year_level'], 'section' => $section,
                ])->exists();
                if (! $exists) {
                    InstructorAssignment::create([
                        'instructor_id' => $data['instructor_id'],
                        'subject_id' => $data['subject_id'],
                        'program' => $data['program'],
                        'year_level' => $data['year_level'],
                        'section' => $section,
                    ]);
                    $created++;
                }
            }
        });

        $message = $created ? "Assignment created for {$created} section(s)." : 'No new assignments were created; selected sections may already be assigned.';

        return redirect()->route('assignments.index')->with('flash', ['type' => $created ? 'success' : 'warning', 'message' => $message]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'instructor_id' => 'required|string|max:50|exists:instructor_account,instructor_id',
            'subject_id' => 'required|integer|min:1|exists:subject_codes,subject_id',
            'program' => 'required|in:BSIT,BSBA,BSHM,BSED,BEED',
            'year_level' => 'required|integer|in:1,2,3,4',
            'sections' => 'required|array|min:1|max:20',
            'sections.*' => 'required|string|max:50|distinct',
        ]);
        $assignment = InstructorAssignment::findOrFail($id);
        $this->ensureSubjectScope($data);
        $sections = collect($data['sections'])->map(fn ($section) => strtoupper(trim($section)))->unique()->values();
        $this->ensureManagedSections($data, $sections);

        $conflict = InstructorAssignment::where('subject_id', $data['subject_id'])
            ->where('program', $data['program'])
            ->where('year_level', $data['year_level'])
            ->whereIn('section', $sections)
            ->whereKeyNot($assignment->getKey())
            ->exists();
        if ($conflict) {
            throw ValidationException::withMessages([
                'sections' => 'One or more selected sections already have an instructor for this subject.',
            ]);
        }

        $created = 0;

        DB::transaction(function () use ($assignment, $data, $sections, &$created): void {
            $assignment->delete();
            foreach ($sections as $section) {
                InstructorAssignment::create([
                    'instructor_id' => $data['instructor_id'], 'subject_id' => $data['subject_id'],
                    'program' => $data['program'], 'year_level' => $data['year_level'], 'section' => $section,
                ]);
                $created++;
            }
        });

        return redirect()->route('assignments.index')->with('flash', [
            'type' => $created ? 'success' : 'warning',
            'message' => $created ? "Assignment updated for {$created} section(s)." : 'No assignments were created because the selected sections are already assigned.',
        ]);
    }

    public function destroy($id)
    {
        InstructorAssignment::destroy($id);

        return redirect()->route('assignments.index')->with('flash', ['type' => 'success', 'message' => 'Assignment deleted.']);
    }

    private function ensureSubjectScope(array $data): void
    {
        $matches = SubjectCode::whereKey($data['subject_id'])
            ->where('year_level', $data['year_level'])
            ->where(function ($query) use ($data) {
                $program = $data['program'];
                $query->where('program', $program)
                    ->orWhere('program', 'like', $program.',%')
                    ->orWhere('program', 'like', '%,'.$program)
                    ->orWhere('program', 'like', '%,'.$program.',%');
            })
            ->exists();

        if (! $matches) {
            throw ValidationException::withMessages([
                'subject_id' => 'Select a subject configured for the chosen program and year level.',
            ]);
        }
    }

    private function ensureManagedSections(array $data, $sections): void
    {
        $managedCount = ProgramSection::where('program', $data['program'])
            ->where('year_level', $data['year_level'])
            ->whereIn('section', $sections)
            ->count();

        if ($managedCount !== $sections->count()) {
            throw ValidationException::withMessages([
                'sections' => 'Select only sections configured in Section Management.',
            ]);
        }
    }
}
