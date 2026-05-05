<?php

namespace App\Models;

use App\Enums\PromiseKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromiseTarget extends Model
{
    protected $fillable = [
        'guarantee_letter_id','region_code','year','kind','title','body',
        'sector','indicator_code','period','target_value','target_text','direction',
        'target_districts','source_paragraph_index',
    ];

    protected $casts = [
        'kind'             => PromiseKind::class,
        'target_value'     => 'decimal:6',
        'target_districts' => 'array',
    ];

    public function guaranteeLetter(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLetter::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_code', 'code');
    }
}
