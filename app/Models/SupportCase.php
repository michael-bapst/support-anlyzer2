<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCase extends Model
{
    protected $table = 'cases';

    protected $fillable = [
        'name',
        'source_path',
        'description',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function files()
    {
        return $this->hasMany(CaseFile::class, 'case_id');
    }
}
