<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataQualityIssue extends Model
{
    protected $fillable = [
        'import_run_id','region_code','district_code','indicator_code','year','period',
        'issue_kind','severity','detail','detected_value','expected_value',
        'source_label','detected_at','resolved_at','resolved_by_user_id',
        'resolution_kind','resolution_note',
    ];

    protected $casts = [
        'severity'    => IssueSeverity::class,
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }
}
