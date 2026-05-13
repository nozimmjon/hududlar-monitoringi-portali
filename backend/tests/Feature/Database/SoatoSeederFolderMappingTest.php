<?php

use App\Models\Region;
use Database\Seeders\SoatoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeded regions have correct folder_name for all 14 regions', function () {
    $this->seed(SoatoSeeder::class);

    expect(SoatoSeeder::REGION_FOLDER)->toHaveCount(14);

    foreach (SoatoSeeder::REGION_FOLDER as $code => $expected) {
        expect(Region::where('code', $code)->value('folder_name'))
            ->toBe($expected, "Region {$code} folder_name");
    }
});

test('REGION_LATIN, REGION_SORT, REGION_FOLDER all cover the same region codes', function () {
    $latin  = array_keys(SoatoSeeder::REGION_LATIN);
    $sort   = array_keys(SoatoSeeder::REGION_SORT);
    $folder = array_keys(SoatoSeeder::REGION_FOLDER);

    sort($latin); sort($sort); sort($folder);

    expect($latin)->toBe($sort);
    expect($latin)->toBe($folder);
});

test('every region folder_name exists on disk when data dir is present', function () {
    $dataPath = base_path('../data');
    if (! is_dir($dataPath)) {
        $this->markTestSkipped('data/ folder not present in this environment.');
    }

    foreach (SoatoSeeder::REGION_FOLDER as $code => $folder) {
        $full = $dataPath . DIRECTORY_SEPARATOR . $folder;
        expect(is_dir($full))->toBeTrue("Missing data folder for region {$code}: {$full}");
    }
});
