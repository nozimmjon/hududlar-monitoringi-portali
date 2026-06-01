<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskProgress extends Model
{
    protected $table = 'task_progress';

    protected $fillable = [
        'task_id', 'line_no', 'metric_label', 'unit', 'report_period', 'period_type',
        'plan_value', 'actual_value', 'pct_of_plan', 'reported_at', 'import_run_id',
    ];

    protected $casts = [
        'line_no'     => 'integer',
        'reported_at' => 'date',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }
}
