<?php

use App\Console\Commands\PatchWorkbookCityRows;
use App\Models\District;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

uses(RefreshDatabase::class);

test('command is registered as data:patch-city-rows', function () {
    $exitCode = Artisan::call('data:patch-city-rows', ['--dry-run' => true, '--region' => ['nonexistent_slug']]);
    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Patched 0 row(s)');
});

test('isDistrictSheet recognizes Туман col B in first 6 rows', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('1.5');
    $sheet->setCellValue('A4', '№');
    $sheet->setCellValue('B4', 'Туман/шаҳар номи');

    $rows = $sheet->toArray(null, true, true, false);
    $cmd = new PatchWorkbookCityRows();
    expect(invade($cmd)->isDistrictSheet($rows))->toBeTrue();
});

test('isDistrictSheet returns false for header-only sheets', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('other');
    $sheet->setCellValue('A1', 'unrelated');

    $rows = $sheet->toArray(null, true, true, false);
    $cmd = new PatchWorkbookCityRows();
    expect(invade($cmd)->isDistrictSheet($rows))->toBeFalse();
});

test('cityFormsForRegion returns bare + full + normalized variants', function () {
    DB::table('regions')->insert([
        'code' => 1710, 'name_short' => 'Қашқадарё', 'name_full' => 'Қашқадарё вилояти',
        'name_latin' => 'kashkadarya', 'sort_order' => 5,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = DB::table('regions')->where('code', 1710)->value('id');
    DB::table('districts')->insert([
        ['code' => 1710401, 'region_id' => $regionId, 'region_code' => 1710, 'name_short' => 'Қарши ш.', 'name_full' => 'Қарши шаҳри', 'kind' => 'city', 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 1710405, 'region_id' => $regionId, 'region_code' => 1710, 'name_short' => 'Шаҳрисабз ш.', 'name_full' => 'Шаҳрисабз шаҳри', 'kind' => 'city', 'sort_order' => 16, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 1710224, 'region_id' => $regionId, 'region_code' => 1710, 'name_short' => 'Қарши т.', 'name_full' => 'Қарши тумани', 'kind' => 'district', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $cmd = new PatchWorkbookCityRows();
    $forms = invade($cmd)->cityFormsForRegion(1710);

    expect($forms)->toHaveCount(2);
    expect($forms[0]['full'])->toBe('Қарши ш.');
    expect($forms[0]['bare'])->toBe('Қарши');
    expect($forms[0]['bareNorm'])->toBe('қарши');
    expect($forms[0]['fullNorm'])->toBe('қарши ш.');
});
