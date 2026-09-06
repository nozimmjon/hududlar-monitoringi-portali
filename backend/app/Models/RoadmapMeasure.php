<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadmapMeasure extends Model
{
    protected $fillable = [
        'roadmap_id', 'section_no', 'section_title', 'district_id', 'district_head_text', 'seq_no',
        'title', 'details', 'body_raw', 'funding_text', 'deadline_text', 'responsible_text', 'source_row',
    ];

    protected $casts = [
        'roadmap_id'  => 'integer',
        'section_no'  => 'integer',
        'district_id' => 'integer',
        'seq_no'      => 'integer',
        'source_row'  => 'integer',
    ];

    public function roadmap(): BelongsTo
    {
        return $this->belongsTo(Roadmap::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** Sections I–IV: measures without a district. */
    public function scopeRegionLevel(Builder $q): Builder
    {
        return $q->whereNull('district_id');
    }

    /** The "Туманларда амалга ошириладиган лойиҳалар" section. */
    public function scopeDistrictLevel(Builder $q): Builder
    {
        return $q->whereNotNull('district_id');
    }

    /** @return list<string> the collapsed «Батафсил» lines */
    public function detailLines(): array
    {
        if ($this->details === null || $this->details === '') {
            return [];
        }

        return preg_split('/\R/u', $this->details, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
