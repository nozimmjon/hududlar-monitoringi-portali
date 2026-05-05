<?php

namespace App\Models;

use App\Enums\Period;
use App\Enums\StagingStatus;
use Illuminate\Database\Eloquent\Model;

class ImportStagingIndicatorFact extends Model
{
    protected $table = 'import_staging_indicator_facts';

    protected $fillable = [
        'import_run_id','region_code','district_code','year','indicator_code','period',
        'plan_value','expected_value','actual_hokimyat','actual_statkom',
        'growth_pct','pct_of_plan','count_extra','count_extra_2',
        'is_sentinel','sentinel_label','unit','source_label',
        'hokimyat_reported_at','statkom_published_at',
        'staging_status','validation_errors',
    ];

    protected $casts = [
        'period'             => Period::class,
        'staging_status'     => StagingStatus::class,
        'validation_errors'  => 'array',
        'is_sentinel'        => 'boolean',
        'plan_value'         => 'decimal:6',
        'actual_hokimyat'    => 'decimal:6',
        'growth_pct'         => 'decimal:4',
    ];
}
