<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseFile extends Model
{
    protected $fillable = [
        'case_id',
        'filename',
        'path',
        'extension',
        'size_kb',
        'hash',
        'parsed',
    ];

    public function case()
    {
        return $this->belongsTo(SupportCase::class, 'case_id');
    }

    public function entries()
    {
        return $this->hasMany(ExtractedEntry::class, 'case_file_id');
    }
}
