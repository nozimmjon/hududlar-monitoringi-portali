<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegionWorkbook extends Model
{
    protected $fillable = [
        'region_id',
        'reporting_year_id',
        'module_id',
        'file_name',
        'file_path',
        'sha256',
        'last_seen_at',
        'notes',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function sheets(): HasMany
    {
        return $this->hasMany(RegionWorkbookSheet::class);
    }
}
