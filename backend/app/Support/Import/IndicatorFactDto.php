<?php

namespace App\Support\Import;

final readonly class IndicatorFactDto
{
    public function __construct(
        public string $regionCode,
        public ?string $districtCode,
        public int $year,
        public string $indicatorCode,
        public string $period,
        public ?float $planValue = null,
        public ?float $expectedValue = null,
        public ?float $actualHokimyat = null,
        public ?float $actualStatkom = null,
        public ?float $growthPct = null,
        public ?float $pctOfPlan = null,
        public ?int $countExtra = null,
        public ?int $countExtra2 = null,
        public bool $isSentinel = false,
        public ?string $sentinelLabel = null,
        public string $unit = '',
        public string $sourceLabel = '',
    ) {}

    public function toStagingRow(int $importRunId): array
    {
        $now = now();
        return [
            'import_run_id'   => $importRunId,
            'region_code'     => $this->regionCode,
            'district_code'   => $this->districtCode,
            'year'            => $this->year,
            'indicator_code'  => $this->indicatorCode,
            'period'          => $this->period,
            'plan_value'      => $this->planValue,
            'expected_value'  => $this->expectedValue,
            'actual_hokimyat' => $this->actualHokimyat,
            'actual_statkom'  => $this->actualStatkom,
            'growth_pct'      => $this->growthPct,
            'pct_of_plan'     => $this->pctOfPlan,
            'count_extra'     => $this->countExtra,
            'count_extra_2'   => $this->countExtra2,
            'is_sentinel'     => $this->isSentinel,
            'sentinel_label'  => $this->sentinelLabel,
            'unit'            => $this->unit,
            'source_label'    => $this->sourceLabel,
            'staging_status'  => 'pending',
            'created_at'      => $now,
            'updated_at'      => $now,
        ];
    }
}
