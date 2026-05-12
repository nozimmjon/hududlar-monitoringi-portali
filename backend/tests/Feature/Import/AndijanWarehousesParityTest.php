<?php

use App\Models\ImportRun;
use App\Models\ImportStagingWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanWhDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 1703)
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

test('Andijan warehouses import reproduces DATA.districts[*].data.warehouses', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/2.1-2.2-жадваллар (инфляция).xlsx'))) {
        $this->markTestSkipped('Andijan inflation data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'inflation',
    ]);

    $run = ImportRun::latest()->first();
    $rows = ImportStagingWarehouse::where('import_run_id', $run->id)->get();

    expect($rows)->toHaveCount(17);
    expect($rows->whereNull('district_code'))->toHaveCount(1);
    expect($rows->whereNotNull('district_code'))->toHaveCount(16);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));

    $matched = 0;
    $unmatched = [];

    foreach ($expected['districts'] as $expectedDistrict) {
        $w = $expectedDistrict['data']['warehouses'] ?? null;
        if ($w === null) continue;

        $districtCode = andijanWhDistrictCode($expectedDistrict['name']);
        if ($districtCode === null) {
            $unmatched[] = "lookup-failed:{$expectedDistrict['name']}";
            continue;
        }

        $actual = $rows->firstWhere('district_code', $districtCode);
        if ($actual === null) {
            $unmatched[] = "no-staging-row:{$districtCode}";
            continue;
        }
        $matched++;

        // Always-present integer fields
        expect($actual->reserve_warehouses)->toBe($w['reserve_warehouses']);
        expect($actual->reserve_capacity_t)->toBe($w['reserve_capacity_t']);
        expect($actual->cold_storage_count)->toBe($w['cold_storage_count']);
        expect($actual->cold_storage_capacity_t)->toBe($w['cold_storage_capacity_t']);

        // Optional fields — DATA blob has these as null for most districts.
        // If DATA has a value, importer should match. If DATA is null, don't assert.
        if ($w['new_small_cold_storage_count'] !== null) {
            expect($actual->new_small_cold_count)->toBe($w['new_small_cold_storage_count']);
        }
        if ($w['new_large_cold_storage_count'] !== null) {
            expect($actual->new_large_cold_count)->toBe($w['new_large_cold_storage_count']);
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(15);    // floor: at least 15 of 16 districts match

    if (! empty($unmatched)) {
        echo "\nUnmatched warehouses entries: " . implode(', ', $unmatched) . "\n";
    }
});
