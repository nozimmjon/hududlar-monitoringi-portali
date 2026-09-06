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

    /** SOATO code of the first region (by regions.sort_order) that has a loaded road map, or null. */
    public static function firstLoadedRegionCode(string $domain = 'water', int $year = 2026): ?int
    {
        $code = static::query()
            ->where('roadmaps.domain', $domain)
            ->where('roadmaps.year', $year)
            ->join('regions', 'regions.code', '=', 'roadmaps.region_code')
            ->orderBy('regions.sort_order')
            ->value('roadmaps.region_code');

        return $code === null ? null : (int) $code;
    }
}
