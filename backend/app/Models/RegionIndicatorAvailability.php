<?php

namespace App\Models;

use App\Enums\AvailabilityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionIndicatorAvailability extends Model
{
    protected $table = 'region_indicator_availability';

    protected $fillable = [
        'region_code', 'indicator_code', 'status', 'note',
        'blocked_until', 'updated_by_user_id',
    ];

    protected $casts = [
        'status'        => AvailabilityStatus::class,
        'blocked_until' => 'date',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_code', 'code');
    }
}
