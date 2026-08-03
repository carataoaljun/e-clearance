<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorAssignment extends Model
{
    protected $table = 'instructor_assignment';

    protected $primaryKey = 'assignment_id';

    const CREATED_AT = 'assigned_at';

    const UPDATED_AT = null;

    protected $fillable = ['instructor_id', 'subject_id', 'program', 'year_level', 'section'];

    public function instructor()
    {
        return $this->belongsTo(Instructor::class, 'instructor_id', 'instructor_id');
    }

    public function subject()
    {
        return $this->belongsTo(SubjectCode::class, 'subject_id', 'subject_id');
    }
}
