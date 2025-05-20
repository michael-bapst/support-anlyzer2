<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorPattern extends Model
{
    protected $fillable = [
        'code',
        'keyword',
        'category',
        'severity',
        'description',
        'recommendation',
    ];
}

