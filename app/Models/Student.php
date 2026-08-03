<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'student_account';

    protected $primaryKey = 'id';

    const UPDATED_AT = null;

    protected $fillable = [
        'student_id', 'firstname', 'middlename', 'lastname', 'suffix',
        'email', 'password', 'program', 'year_level', 'section', 'student_type',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
