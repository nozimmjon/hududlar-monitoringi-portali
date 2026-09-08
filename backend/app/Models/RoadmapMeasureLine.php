<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoadmapMeasureLine extends Model
{
    protected $fillable = ['roadmap_measure_id', 'line_no', 'label', 'unit', 'plan_value'];

    protected $casts = [
        'roadmap_measure_id' => 'integer',
        'line_no' => 'integer',
    ];

    public function measure(): BelongsTo
    {
        return $this->belongsTo(RoadmapMeasure::class, 'roadmap_measure_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(RoadmapLineProgress::class, 'roadmap_measure_line_id');
    }
}
