<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;
    protected $fillable = [
        'region_code', 'guarantee_letter_id', 'task_number', 'title',
        'deadline_text', 'period_code', 'executor_text', 'kind',
        'module_code', 'indicator_code', 'section_path', 'section_label',
        'source_paragraph_index', 'status',
        // XLSX progress fields
        'cadence', 'data_source', 'report_schedule_text', 'integration_status',
        'mechanism_text', 'latest_period', 'headline_unit', 'headline_plan',
        'headline_actual', 'headline_pct',
    ];

    protected $casts = [
        'region_code' => 'integer',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function guaranteeLetter(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLetter::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_code', 'code');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_code', 'code');
    }

    public function districts(): BelongsToMany
    {
        return $this->belongsToMany(District::class, 'task_districts');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(TaskProgress::class);
    }

    /** Headline (line_no 0) progress row for a given report period. */
    public function headlineProgress(?string $period = null): ?TaskProgress
    {
        $period ??= $this->latest_period;
        if ($period === null) return null;

        return $this->progress()
            ->where('report_period', $period)
            ->where('line_no', 0)
            ->first();
    }

    public function scopeForRegion(Builder $q, int $code): Builder
    {
        return $q->where('region_code', $code);
    }

    public function scopeForModule(Builder $q, string $code): Builder
    {
        return $q->where('module_code', $code);
    }

    public function scopeForIndicator(Builder $q, string $code): Builder
    {
        return $q->where('indicator_code', $code);
    }

    public function scopeForDistrict(Builder $q, int $districtId): Builder
    {
        return $q->whereHas('districts', fn ($d) => $d->where('districts.id', $districtId));
    }

    public function scopeForPeriod(Builder $q, string $code): Builder
    {
        return $q->where('period_code', $code);
    }

    public function scopeOfKind(Builder $q, string $kind): Builder
    {
        return $q->where('kind', $kind);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $like = '%' . $term . '%';
        return $q->where(function ($w) use ($like) {
            $w->where('title', 'ILIKE', $like)
              ->orWhere('executor_text', 'ILIKE', $like)
              ->orWhere('section_label', 'ILIKE', $like);
        });
    }
}
