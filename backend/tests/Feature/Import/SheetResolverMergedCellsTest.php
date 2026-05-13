<?php

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\SheetResolver;
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

test('scoreSheet finds rollup signature in merged title row', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('1.1. ЯҲМ');
    $sheet->setCellValue('J1', '1.1-жадвал');
    $sheet->setCellValue('A2', '2026 йил Андижон вилояти бўйича асосий иқтисодий кўрсаткичларнинг прогнози');
    $sheet->mergeCells('A2:J2');
    $sheet->setCellValue('A4', '№');
    $sheet->setCellValue('B4', 'Кўрсаткичлар');
    $sheet->setCellValue('A6', 1);
    $sheet->setCellValue('B6', 'ЯҲМ');

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

    $issues = new IssueCollector();
    $resolver = new SheetResolver($issues);
    $resolved = $resolver->resolve($ctx, $book, $rwb->id, 'macro', 'rollup');

    expect($resolved)->not->toBeNull();
    expect($resolved->getTitle())->toBe('1.1. ЯҲМ');
    expect($issues->bufferedCount())->toBe(0);
});
