<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentreSetting extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'logo_path',
    ];
}
