<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $student_id
 * @property int $subject_id
 * @property string $instructor_id
 * @property string $file_path
 * @property string $file_name
 * @property string|null $file_type
 * @property string|null $description
 * @property string $submitted_at
 */
class StudentSubmission extends Model
{
    protected $table = 'student_submissions';

    const CREATED_AT = 'submitted_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'student_id', 'subject_id', 'instructor_id',
        'file_path', 'file_name', 'file_type', 'description',
    ];

    public function subject()
    {
        return $this->belongsTo(SubjectCode::class, 'subject_id', 'subject_id');
    }

    public function student()
    {
        return $this->belongsTo(StudentAccount::class, 'student_id', 'student_id');
    }

    public function clearanceStatus()
    {
        return $this->belongsTo(ClearanceStatus::class, 'subject_id', 'subject_id')
            ->where('student_id', $this->student_id)
            ->where('instructor_id', $this->instructor_id);
    }
}
