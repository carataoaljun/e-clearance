<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClearanceVerificationToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'token_hash',
        'token_encrypted',
        'issued_at',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'last_verified_at' => 'immutable_datetime',
        ];
    }
}
