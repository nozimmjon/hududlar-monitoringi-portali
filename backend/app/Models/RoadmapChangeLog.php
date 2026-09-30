<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per change made through the admin panel (or an import): who, when, what, old → new. */
class RoadmapChangeLog extends Model
{
    public const UPDATED_AT = null;

    /** created_at is timestamp(3); without this Eloquent writes whole seconds and the precision is wasted. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    public const ACTION_LABELS = [
        'actual'       => 'Амалда',
        'note'         => 'Изоҳ',
        'label'        => 'Индикатор номи',
        'unit'         => 'Ўлчов',
        'plan'         => 'Режа',
        'line_added'   => 'Индикатор қўшилди',
        'line_removed' => 'Индикатор ўчирилди',
        'confirm'      => 'Ўзгаришсиз тасдиқланди',
        'import'       => 'Excel’дан юкланди',
    ];

    protected $table = 'roadmap_change_log';

    protected $fillable = [
        'user_id', 'roadmap_id', 'roadmap_measure_id', 'roadmap_measure_line_id', 'line_label',
        'report_period', 'action', 'old_value', 'new_value',
    ];

    protected $casts = [
        'user_id'                 => 'integer',
        'roadmap_id'              => 'integer',
        'roadmap_measure_id'      => 'integer',
        'roadmap_measure_line_id' => 'integer',
        'created_at'              => 'datetime',
    ];

    /** @param array<string, mixed> $attributes */
    public static function record(array $attributes): self
    {
        if (! isset(self::ACTION_LABELS[$attributes['action'] ?? ''])) {
            throw new \InvalidArgumentException('Unknown change-log action «' . ($attributes['action'] ?? '') . '».');
        }

        return self::create($attributes);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roadmap(): BelongsTo
    {
        return $this->belongsTo(Roadmap::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(RoadmapMeasure::class, 'roadmap_measure_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(RoadmapMeasureLine::class, 'roadmap_measure_line_id');
    }
}
