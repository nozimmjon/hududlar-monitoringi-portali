<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['code','name_short','name_full','name_latin','folder_name','sort_order','has_districts'];

    protected $casts = [
        'has_districts' => 'boolean',
        'code'          => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
