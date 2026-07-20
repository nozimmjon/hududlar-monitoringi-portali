<?php

namespace App\Models;

use App\Enums\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorFact extends Model
{
    protected $fillable = [
        'region_code','district_code','year','indicator_code','period',
        'plan_value','expected_value','actual_hokimyat','actual_statkom',
        'growth_pct','plan_growth_pct','pct_of_plan','count_extra','count_extra_2',
        'is_sentinel','sentinel_label','unit','source_label',
        'hokimyat_reported_at','statkom_published_at',
    ];

    protected $casts = [
        'region_code'          => 'integer',
        'district_code'        => 'integer',
        'period'               => Period::class,
        'plan_value'           => 'decimal:6',
        'expected_value'       => 'decimal:6',
        'actual_hokimyat'      => 'decimal:6',
        'actual_statkom'       => 'decimal:6',
        'growth_pct'           => 'decimal:4',
        'plan_growth_pct'      => 'decimal:4',
        'pct_of_plan'          => 'decimal:4',
        'is_sentinel'          => 'boolean',
        'hokimyat_reported_at' => 'datetime',
        'statkom_published_at' => 'datetime',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_code', 'code');
    }
}
