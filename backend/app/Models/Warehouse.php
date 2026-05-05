<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = [
        'region_code','district_code','year',
        'reserve_warehouses','reserve_capacity_t',
        'cold_storage_count','cold_storage_capacity_t',
        'new_small_cold_count','new_small_cold_capacity_t','new_small_cold_mfys',
        'new_large_cold_count','new_large_cold_capacity_t','source_label',
    ];
}
