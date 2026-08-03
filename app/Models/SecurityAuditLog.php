<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'event',
        'actor_guard',
        'actor_id',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Security audit records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Security audit records are append-only.'));
    }
}
