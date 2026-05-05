<?php

namespace App\Models;

use App\Enums\StagingStatus;
use Illuminate\Database\Eloquent\Model;

class ImportStagingWarehouse extends Model
{
    protected $fillable = [
        'import_run_id','region_code','district_code','year',
        'reserve_warehouses','reserve_capacity_t',
        'cold_storage_count','cold_storage_capacity_t',
        'new_small_cold_count','new_small_cold_capacity_t','new_small_cold_mfys',
        'new_large_cold_count','new_large_cold_capacity_t',
        'source_label','staging_status','validation_errors',
    ];

    protected $casts = [
        'staging_status'     => StagingStatus::class,
        'validation_errors'  => 'array',
    ];
}
