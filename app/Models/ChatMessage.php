<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    const UPDATED_AT = null;

    protected $fillable = [
        'sender_id', 'sender_role', 'receiver_id', 'receiver_role', 'message', 'is_read',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_read' => 'boolean',
    ];
}
