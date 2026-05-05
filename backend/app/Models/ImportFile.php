<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportFile extends Model
{
    protected $fillable = [
        'import_run_id','module_code','file_name','file_path','sha256',
        'size_bytes','sheet_count','parsed_ok','error_text','parsed_at',
    ];

    protected $casts = [
        'parsed_ok' => 'boolean',
        'parsed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }
}
