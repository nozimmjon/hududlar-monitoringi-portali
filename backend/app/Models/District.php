<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = [
        'region_id', 'region_code', 'code', 'name_short', 'name_full',
        'name_latin', 'alt_labels', 'kind', 'sort_order',
    ];
}
