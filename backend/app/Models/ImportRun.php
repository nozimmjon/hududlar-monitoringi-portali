<?php

namespace App\Models;

use App\Enums\ImportRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRun extends Model
{
    protected $fillable = [
        'region_code','year','triggered_by_user_id','trigger_kind','status',
        'started_at','parsed_at','promoted_at','rejected_at','failed_at',
        'files_processed','rows_staged','rows_promoted',
        'issues_open_count','issues_blocker_count','notes',
    ];

    protected $casts = [
        'region_code'  => 'integer',
        'status'       => ImportRunStatus::class,
        'started_at'   => 'datetime',
        'parsed_at'    => 'datetime',
        'promoted_at'  => 'datetime',
        'rejected_at'  => 'datetime',
        'failed_at'    => 'datetime',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(ImportFile::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(DataQualityIssue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'triggered_by_user_id');
    }
}
