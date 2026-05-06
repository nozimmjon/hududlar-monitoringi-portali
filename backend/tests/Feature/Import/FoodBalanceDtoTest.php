<?php

use App\Support\Import\FoodBalanceDto;

test('FoodBalanceDto.toStagingRow produces a complete row array', function () {
    $dto = new FoodBalanceDto(
        regionCode: 'andijan', year: 2026, product: 'Ун', productSortOrder: 1,
        resourceTotal: 430.27, yearStartStock: 21.84,
        production: 368.34, importVolume: 40.09,
        useTotal: 260.82, useHousehold: 86.93,
        useProcessing: 173.89, useOther: null,
        perCapitaNorm: null, perCapitaBalance: null,
        localSupplyRatio: 1.41, yearEndStock: null,
        sourceLabel: '2.1-2.2-жадваллар (инфляция).xlsx · 1.1. Баланс · row 6',
    );

    $row = $dto->toStagingRow(importRunId: 99);

    expect($row['import_run_id'])->toBe(99);
    expect($row['region_code'])->toBe('andijan');
    expect($row['year'])->toBe(2026);
    expect($row['product'])->toBe('Ун');
    expect($row['product_sort_order'])->toBe(1);
    expect((float) $row['resource_total'])->toBe(430.27);
    expect((float) $row['year_start_stock'])->toBe(21.84);
    expect((float) $row['production'])->toBe(368.34);
    expect((float) $row['import_volume'])->toBe(40.09);
    expect((float) $row['use_total'])->toBe(260.82);
    expect((float) $row['local_supply_ratio'])->toBe(1.41);
    expect($row['year_end_stock'])->toBeNull();
    expect($row['use_other'])->toBeNull();
    expect($row['per_capita_norm'])->toBeNull();
    expect($row['source_label'])->toContain('1.1. Баланс');
    expect($row['staging_status'])->toBe('pending');
    expect($row['created_at'])->not->toBeNull();
    expect($row['updated_at'])->not->toBeNull();
});

test('FoodBalanceDto handles all-nullable optional fields', function () {
    $dto = new FoodBalanceDto(
        regionCode: 'andijan', year: 2026, product: 'Шакар', productSortOrder: 3,
        resourceTotal: null, yearStartStock: null,
        production: null, importVolume: null,
        useTotal: null, useHousehold: null,
        useProcessing: null, useOther: null,
        perCapitaNorm: null, perCapitaBalance: null,
        localSupplyRatio: null, yearEndStock: null,
        sourceLabel: 'fixture',
    );

    $row = $dto->toStagingRow(1);

    expect($row['resource_total'])->toBeNull();
    expect($row['use_total'])->toBeNull();
    expect($row['local_supply_ratio'])->toBeNull();
});
