<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadmapLineProgress extends Model
{
    protected $table = 'roadmap_line_progress';

    protected $fillable = [
        'roadmap_measure_line_id', 'report_period', 'period_type', 'actual_value', 'pct_of_plan', 'note', 'reported_at',
    ];

    protected $casts = [
        'roadmap_measure_line_id' => 'integer',
        'reported_at' => 'date',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(RoadmapMeasureLine::class, 'roadmap_measure_line_id');
    }
}
