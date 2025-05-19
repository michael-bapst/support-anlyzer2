<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolutionMatch extends Model
{
    protected $fillable = [
        'code',
        'keyword',
        'solution_text',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}
