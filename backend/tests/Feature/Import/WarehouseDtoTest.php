<?php

use App\Support\Import\WarehouseDto;

test('WarehouseDto.toStagingRow produces complete row for a district', function () {
    $dto = new WarehouseDto(
        regionCode: 1703, districtCode: 1703401, year: 2026,
        reserveWarehouses: 3, reserveCapacityT: 600,
        coldStorageCount: 10, coldStorageCapacityT: 10000,
        newSmallColdCount: null, newSmallColdCapacityT: null, newSmallColdMfys: null,
        newLargeColdCount: null, newLargeColdCapacityT: null,
        sourceLabel: '2.1-2.2-жадваллар (инфляция).xlsx · 1.2. Омборлар · row 8',
    );

    $row = $dto->toStagingRow(importRunId: 5);

    expect($row['import_run_id'])->toBe(5);
    expect($row['region_code'])->toBe(1703);
    expect($row['district_code'])->toBe(1703401);
    expect($row['year'])->toBe(2026);
    expect($row['reserve_warehouses'])->toBe(3);
    expect($row['reserve_capacity_t'])->toBe(600);
    expect($row['cold_storage_count'])->toBe(10);
    expect($row['cold_storage_capacity_t'])->toBe(10000);
    expect($row['new_small_cold_count'])->toBeNull();
    expect($row['new_large_cold_count'])->toBeNull();
    expect($row['source_label'])->toContain('1.2. Омборлар');
    expect($row['staging_status'])->toBe('pending');
});

test('WarehouseDto allows null district_code for region rollup row', function () {
    $dto = new WarehouseDto(
        regionCode: 1703, districtCode: null, year: 2026,
        reserveWarehouses: 89, reserveCapacityT: 36321,
        coldStorageCount: 320, coldStorageCapacityT: 109235,
        newSmallColdCount: 1, newSmallColdCapacityT: 80, newSmallColdMfys: 1,
        newLargeColdCount: 32, newLargeColdCapacityT: 8730,
        sourceLabel: '2.1-2.2-жадваллар (инфляция).xlsx · 1.2. Омборлар · row 5',
    );

    $row = $dto->toStagingRow(1);

    expect($row['district_code'])->toBeNull();
    expect($row['reserve_warehouses'])->toBe(89);
    expect($row['new_large_cold_count'])->toBe(32);
});
