<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $instructor_id
 * @property string $firstname
 * @property string|null $middlename
 * @property string $lastname
 * @property string|null $suffix
 * @property string $email
 * @property string $password
 * @property string $department
 * @property string $full_name
 */
class Instructor extends Authenticatable
{
    public $timestamps = false;

    protected $table = 'instructor_account';

    protected $fillable = [
        'instructor_id', 'firstname', 'middlename', 'lastname', 'suffix',
        'email', 'password', 'department',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }
}
