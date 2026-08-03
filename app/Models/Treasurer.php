<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Treasurer extends Authenticatable
{
    protected $fillable = [
        'treasurer_id', 'firstname', 'middlename', 'lastname', 'suffix',
        'email', 'password', 'treasurer_type', 'department',
        'program', 'year_level', 'section',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    // The treasurers table does not include a remember_token column.
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
        // no remember token column
    }

    public function hasRememberToken()
    {
        return false;
    }

    public function isMainTreasurer(): bool
    {
        return strtolower((string) $this->treasurer_type) === 'main';
    }
}
