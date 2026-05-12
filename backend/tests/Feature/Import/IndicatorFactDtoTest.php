<?php

use App\Support\Import\IndicatorFactDto;

test('toStagingRow produces a complete row array', function () {
    $dto = new IndicatorFactDto(
        regionCode:     1703,
        districtCode:   null,
        year:           2026,
        indicatorCode:  'grp',
        period:         'h1',
        planValue:      52100.81,
        actualHokimyat: null,
        growthPct:      107.16,
        unit:           'млрд сўм',
        sourceLabel:    '1.1-1.5-жадваллар (макро).xlsx · 1.1. ЯҲМ · row 6',
    );

    $row = $dto->toStagingRow(importRunId: 42);

    expect($row['region_code'])->toBe(1703);
    expect($row['district_code'])->toBeNull();
    expect($row['year'])->toBe(2026);
    expect($row['indicator_code'])->toBe('grp');
    expect($row['period'])->toBe('h1');
    expect((float) $row['plan_value'])->toBe(52100.81);
    expect($row['actual_hokimyat'])->toBeNull();
    expect((float) $row['growth_pct'])->toBe(107.16);
    expect($row['unit'])->toBe('млрд сўм');
    expect($row['source_label'])->toContain('1.1. ЯҲМ');
    expect($row['import_run_id'])->toBe(42);
    expect($row['staging_status'])->toBe('pending');
    expect($row['is_sentinel'])->toBeFalse();
    expect($row['sentinel_label'])->toBeNull();
    expect($row['created_at'])->not->toBeNull();
    expect($row['updated_at'])->not->toBeNull();
});

test('sentinel DTO sets is_sentinel and sentinel_label', function () {
    $dto = new IndicatorFactDto(
        regionCode: 1703, districtCode: 1703401, year: 2026,
        indicatorCode: 'poverty', period: 'year',
        unit: '%', sourceLabel: 'fixture',
        isSentinel: true, sentinelLabel: 'холи ҳудуд',
    );

    $row = $dto->toStagingRow(1);

    expect($row['is_sentinel'])->toBeTrue();
    expect($row['sentinel_label'])->toBe('холи ҳудуд');
    expect($row['plan_value'])->toBeNull();
});
