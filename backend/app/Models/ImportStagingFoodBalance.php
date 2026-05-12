<?php

namespace App\Models;

use App\Enums\StagingStatus;
use Illuminate\Database\Eloquent\Model;

class ImportStagingFoodBalance extends Model
{
    protected $table = 'import_staging_food_balance';

    protected $fillable = [
        'import_run_id','region_code','year','product','product_sort_order',
        'resource_total','year_start_stock','production','import_volume',
        'use_total','use_household','use_processing','use_other',
        'per_capita_norm','per_capita_balance','local_supply_ratio','year_end_stock',
        'source_label','staging_status','validation_errors',
    ];

    protected $casts = [
        'region_code'        => 'integer',
        'staging_status'     => StagingStatus::class,
        'validation_errors'  => 'array',
    ];
}
