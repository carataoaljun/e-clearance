<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorRemark extends Model
{
    protected $table = 'instructor_remarks';

    const UPDATED_AT = null;

    protected $fillable = ['student_id', 'subject_id', 'instructor_id', 'remark'];
}
