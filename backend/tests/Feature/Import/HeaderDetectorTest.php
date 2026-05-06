<?php

use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Models\RegionWorkbookSheet;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

function loadAndijanMacroSheet(string $sheetName): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
{
    $path = base_path('../data/2. Андижон/1.1-1.5-жадваллар (макро).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan macro workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    return $reader->load($path)->getSheetByName($sheetName);
}

function makeHeaderDetectorCtx(): ImportContext
{
    $region = Region::where('code', 'andijan')->first();
    return new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region,
        year: 2026, dataPath: base_path('../data'),
    );
}

function makeRwSheet(string $name, string $kind): RegionWorkbookSheet
{
    $region = Region::where('code', 'andijan')->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'macro')->value('id'),
        'file_name'         => 'fixture.xlsx', 'file_path' => 'fixture', 'last_seen_at' => now(),
    ]);
    return RegionWorkbookSheet::create([
        'region_workbook_id' => $rwb->id, 'sheet_name' => $name, 'logical_kind' => $kind,
    ]);
}

test('HeaderDetector finds row 6 as data start in 1.1 ЯҲМ', function () {
    $this->seed();
    $sheet = loadAndijanMacroSheet('1.1. ЯҲМ');
    $detector = new HeaderDetector(new IssueCollector());
    $rwSheet = makeRwSheet('1.1. ЯҲМ', 'rollup');

    $row = $detector->detect($sheet, makeHeaderDetectorCtx(), $rwSheet->id);

    expect($row)->toBe(6);
    expect($rwSheet->fresh()->header_row)->toBe(6);
});

test('HeaderDetector finds row 8 in 1.2 Саноат (region rollup row 7, districts start row 8)', function () {
    $this->seed();
    $sheet = loadAndijanMacroSheet('1.2. Саноат');
    $detector = new HeaderDetector(new IssueCollector());
    $rwSheet = makeRwSheet('1.2. Саноат', 'district_industry');

    $row = $detector->detect($sheet, makeHeaderDetectorCtx(), $rwSheet->id);

    expect($row)->toBe(8);
});
