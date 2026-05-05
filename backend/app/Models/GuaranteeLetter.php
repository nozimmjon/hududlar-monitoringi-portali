<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuaranteeLetter extends Model
{
    protected $fillable = [
        'region_code','year','source_path','sha256','paragraph_count',
        'raw_text','signed_at','status','imported_at',
    ];

    protected $casts = [
        'signed_at'    => 'date',
        'imported_at'  => 'datetime',
    ];

    public function promiseTargets(): HasMany
    {
        return $this->hasMany(PromiseTarget::class);
    }
}
