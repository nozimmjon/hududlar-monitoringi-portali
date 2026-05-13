<?php

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Models\RegionWorkbookSheet;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'sort_order' => 2,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('reporting_years')->insert([
        'year' => 2026, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        'code' => 'macro', 'label' => 'Макро', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

test('detect returns the row where col A is digit after merged unit row', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('1.2. Саноат');
    $sheet->setCellValue('J1', '1.2-жадвал');
    $sheet->setCellValue('A2', '2026 йил Андижон вилояти бўйича Саноат прогнози');
    $sheet->mergeCells('A2:J2');
    $sheet->setCellValue('A4', '№');
    $sheet->setCellValue('B4', 'Туман/шаҳар номи');
    $sheet->setCellValue('C4', 'Саноат маҳсулотларини ишлаб чиқариш');
    $sheet->setCellValue('C5', 'ҳажми (млрд.сўм)');
    $sheet->mergeCells('C5:J5');
    $sheet->setCellValue('B7', 'Андижон вилояти');
    $sheet->setCellValue('A8', '1');
    $sheet->setCellValue('B8', 'Андижон шаҳри');

    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => ImportRunStatus::Parsing,
        'started_at'   => now(),
    ]);
    $ctx = new ImportContext(run: $run, region: $region, year: 2026, dataPath: null);

    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'macro')->value('id'),
        'file_name'         => 'test.xlsx',
        'file_path'         => '/tmp/test.xlsx',
        'last_seen_at'      => now(),
    ]);

    $rws = RegionWorkbookSheet::create([
        'region_workbook_id' => $rwb->id,
        'sheet_name'         => '1.2. Саноат',
        'logical_kind'       => 'district_industry',
    ]);

    $issues = new IssueCollector();
    $detector = new HeaderDetector($issues);
    $headerRow = $detector->detect($sheet, $ctx, $rws->id);

    expect($headerRow)->toBe(8);
    expect($issues->bufferedCount())->toBe(0);
});

test('detect emits issue when no digit row follows unit marker', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('empty');
    $sheet->setCellValue('A1', 'a sheet without unit markers');

    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => ImportRunStatus::Parsing,
        'started_at'   => now(),
    ]);
    $ctx = new ImportContext(run: $run, region: $region, year: 2026, dataPath: null);

    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'macro')->value('id'),
        'file_name'         => 'test.xlsx',
        'file_path'         => '/tmp/test.xlsx',
        'last_seen_at'      => now(),
    ]);

    $rws = RegionWorkbookSheet::create([
        'region_workbook_id' => $rwb->id,
        'sheet_name'         => 'empty',
        'logical_kind'       => 'district_industry',
    ]);

    $issues = new IssueCollector();
    $detector = new HeaderDetector($issues);
    $headerRow = $detector->detect($sheet, $ctx, $rws->id);

    expect($headerRow)->toBeNull();
    expect($issues->bufferedCount())->toBeGreaterThan(0);
});
