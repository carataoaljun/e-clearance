<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Registrar extends Authenticatable
{
    public $timestamps = false;

    protected $table = 'registrar';

    protected $fillable = ['registrar_id', 'firstname', 'lastname', 'email', 'password', 'role'];

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
        // no remember_token column on registrar table
    }

    public function hasRememberToken()
    {
        return false;
    }
}
