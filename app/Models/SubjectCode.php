<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectCode extends Model
{
    protected $table = 'subject_codes';

    protected $primaryKey = 'subject_id';

    public $timestamps = false;

    protected $fillable = ['subject_code', 'subject_description', 'year_level', 'program', 'semester'];
}
