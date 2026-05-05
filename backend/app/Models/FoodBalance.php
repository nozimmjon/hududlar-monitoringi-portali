<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodBalance extends Model
{
    protected $table = 'food_balance';

    protected $fillable = [
        'region_code','year','product','product_sort_order',
        'resource_total','year_start_stock','production','import_volume',
        'use_total','use_household','use_processing','use_other',
        'per_capita_norm','per_capita_balance','local_supply_ratio','year_end_stock',
        'source_label',
    ];

    protected $casts = [
        'resource_total'    => 'decimal:6',
        'year_start_stock'  => 'decimal:6',
        'production'        => 'decimal:6',
        'import_volume'     => 'decimal:6',
        'use_total'         => 'decimal:6',
        'use_household'     => 'decimal:6',
        'use_processing'    => 'decimal:6',
        'use_other'         => 'decimal:6',
        'per_capita_norm'   => 'decimal:6',
        'per_capita_balance'=> 'decimal:6',
        'local_supply_ratio'=> 'decimal:6',
        'year_end_stock'    => 'decimal:6',
    ];
}
