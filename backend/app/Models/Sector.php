<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $fillable = ['code', 'name_short', 'org_full', 'signer_text', 'sort_order'];

    public function tasks(): HasMany
    {
        return $this->hasMany(SectorTask::class);
    }
}
