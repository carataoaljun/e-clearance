<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class MainAdmin extends Authenticatable
{
    public $timestamps = false;

    protected $table = 'main_admin';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

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
        // The legacy main_admin table has no remember_token column.
    }

    public function hasRememberToken()
    {
        return false;
    }
}
