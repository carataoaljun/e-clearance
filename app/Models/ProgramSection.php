<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramSection extends Model
{
    protected $table = 'program_sections';

    const UPDATED_AT = null;

    protected $fillable = ['program', 'year_level', 'section'];
}
