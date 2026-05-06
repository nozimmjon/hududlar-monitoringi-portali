<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionWorkbookSheet extends Model
{
    protected $fillable = [
        'region_workbook_id',
        'sheet_name',
        'logical_kind',
        'header_row',
        'district_start_row',
        'source_label',
        'detection_hints',
    ];

    public function regionWorkbook(): BelongsTo
    {
        return $this->belongsTo(RegionWorkbook::class);
    }
}
