<?php

namespace App\Models;

use App\Enums\IndicatorScope;
use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    protected $primaryKey = 'id';

    protected $fillable = [
        'code', 'label_full', 'label_short', 'sector', 'module_code', 'scope',
        'default_unit', 'lower_is_better', 'supported_periods',
        'has_growth_pct', 'has_pct_of_plan', 'has_sentinel',
        'count_extra_label', 'count_extra_2_label', 'icon', 'sort_order', 'notes',
    ];

    protected $casts = [
        'supported_periods' => 'array',
        'lower_is_better'   => 'boolean',
        'has_growth_pct'    => 'boolean',
        'has_pct_of_plan'   => 'boolean',
        'has_sentinel'      => 'boolean',
        'scope'             => IndicatorScope::class,
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
