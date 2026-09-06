<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Roadmap extends Model
{
    protected $fillable = [
        'domain', 'region_code', 'year', 'title_text', 'approvers_text', 'source_file', 'imported_at',
    ];

    protected $casts = [
        'region_code' => 'integer',
        'year'        => 'integer',
        'imported_at' => 'datetime',
    ];

    protected $attributes = [
        'domain' => 'water',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function measures(): HasMany
    {
        return $this->hasMany(RoadmapMeasure::class);
    }
}
