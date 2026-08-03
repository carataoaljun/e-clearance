<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property string $student_id
 * @property string $firstname
 * @property string|null $middlename
 * @property string $lastname
 * @property string|null $suffix
 * @property string $email
 * @property string $program
 * @property string $year_level
 * @property string $section
 * @property string|null $student_type
 * @property string|null $semester
 * @property-read string $full_name
 */
class StudentAccount extends Authenticatable
{
    protected $table = 'student_account';

    protected $primaryKey = 'student_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'student_id', 'firstname', 'middlename', 'lastname', 'suffix',
        'email', 'password', 'program', 'year_level', 'section', 'student_type',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    /**
     * Disable the default remember token support for this model.
     * The student_account table does not contain a remember_token column.
     */
    public function getRememberTokenName()
    {
        return null;
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value)
    {
        // no remember token column on student_account
    }

    public function hasRememberToken()
    {
        return false;
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }
}
