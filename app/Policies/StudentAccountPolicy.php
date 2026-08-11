<?php

namespace App\Policies;

use App\Models\AdminPersonnel;
use App\Models\Instructor;
use App\Models\Registrar;
use App\Models\StudentAccount;
use App\Models\Treasurer;
use App\Support\ClearanceAccess;
use Illuminate\Contracts\Auth\Authenticatable;

class StudentAccountPolicy
{
    public function __construct(private readonly ClearanceAccess $access) {}

    public function reviewSubject(Authenticatable $actor, StudentAccount $student, int $subjectId): bool
    {
        return $actor instanceof Instructor
            && $this->access->instructorCanReview($actor, $student, $subjectId);
    }

    public function reviewOffice(Authenticatable $actor, StudentAccount $student): bool
    {
        return $actor instanceof AdminPersonnel
            && $this->access->officeCanReview($actor, $student);
    }

    public function reviewTreasury(Authenticatable $actor, StudentAccount $student): bool
    {
        return $actor instanceof Treasurer
            && $this->access->treasurerCanReview($actor, $student);
    }

    public function reviewRegistrar(Authenticatable $actor, StudentAccount $student): bool
    {
        return $actor instanceof Registrar
            && $this->access->registrarCanReview($student);
    }
}
