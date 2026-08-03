<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Esignature extends Model
{
    protected $table = 'esignatures';

    protected $fillable = ['signer_id', 'signer_role', 'signer_name', 'signature_data'];
}
