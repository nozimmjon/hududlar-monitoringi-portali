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
