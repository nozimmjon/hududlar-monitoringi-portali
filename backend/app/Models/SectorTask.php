<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectorTask extends Model
{
    protected $fillable = [
        'sector_id', 'task_no', 'title', 'status', 'lines_total', 'lines_done',
        'latest_period', 'headline_unit', 'headline_plan', 'headline_actual', 'headline_pct',
    ];

    protected $casts = [
        'headline_plan'   => 'decimal:6',
        'headline_actual' => 'decimal:6',
        'headline_pct'    => 'decimal:4',
    ];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(SectorTaskProgress::class);
    }
}
