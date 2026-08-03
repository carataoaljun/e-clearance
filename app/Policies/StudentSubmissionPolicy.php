<?php

namespace App\Policies;

use App\Models\Instructor;
use App\Models\StudentAccount;
use App\Models\StudentSubmission;
use App\Support\ClearanceAccess;
use Illuminate\Contracts\Auth\Authenticatable;

class StudentSubmissionPolicy
{
    public function __construct(private readonly ClearanceAccess $access) {}

    public function view(Authenticatable $actor, StudentSubmission $submission): bool
    {
        if ($actor instanceof StudentAccount) {
            return hash_equals((string) $submission->student_id, (string) $actor->student_id);
        }

        return $actor instanceof Instructor
            && hash_equals((string) $submission->instructor_id, (string) $actor->instructor_id)
            && $submission->student instanceof StudentAccount
            && $this->access->instructorCanReview($actor, $submission->student, (int) $submission->subject_id);
    }

    public function delete(Authenticatable $actor, StudentSubmission $submission): bool
    {
        return $actor instanceof Instructor && $this->view($actor, $submission);
    }
}
