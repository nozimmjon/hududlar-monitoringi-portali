<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class District extends Model
{
    protected $fillable = [
        'region_id', 'region_code', 'code', 'name_short', 'name_full',
        'name_latin', 'alt_labels', 'kind', 'sort_order',
    ];

    protected $casts = [
        'alt_labels' => 'array',
    ];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_districts');
    }
}
