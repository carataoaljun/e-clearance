<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'recipient_role', 'message', 'is_read', 'notif_type', 'link_url'];
}
