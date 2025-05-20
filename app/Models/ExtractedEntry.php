<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtractedEntry extends Model
{
    protected $fillable = [
        'case_file_id',
        'entry_type',
        'code',
        'category',
        'content',
        'timestamp',
        'metadata',
        'severity',
        'pattern_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'timestamp' => 'datetime',
    ];

    public function file()
    {
        return $this->belongsTo(CaseFile::class, 'case_file_id');
    }

    public function pattern()
    {
        return $this->belongsTo(ErrorPattern::class, 'pattern_id');
    }
}
