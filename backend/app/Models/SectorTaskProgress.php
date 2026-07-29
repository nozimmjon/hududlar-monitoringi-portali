<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorTaskProgress extends Model
{
    protected $table = 'sector_task_progress';

    protected $fillable = [
        'sector_task_id', 'line_no', 'metric_label', 'unit', 'deadline_text', 'deadline_code',
        'report_period', 'period_type', 'plan_value', 'actual_value', 'pct_of_plan', 'reported_at',
    ];

    protected $casts = [
        'plan_value'   => 'decimal:6',
        'actual_value' => 'decimal:6',
        'pct_of_plan'  => 'decimal:4',
        'reported_at'  => 'date',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(SectorTask::class, 'sector_task_id');
    }
}
