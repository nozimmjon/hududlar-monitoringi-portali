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
