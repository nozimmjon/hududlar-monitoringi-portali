<?php

use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\SheetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

function loadAndijanInflation(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/2.1-2.2-жадваллар (инфляция).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan inflation workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    return $reader->load($path);
}

function inflationSheetCtx(): array
{
    $region = Region::where('code', 1703)->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'inflation')->value('id'),
        'file_name' => '2.1-2.2-жадваллар (инфляция).xlsx',
        'file_path' => 'fixture',
        'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region, year: 2026, dataPath: base_path('../data'),
    );
    return ['ctx' => $ctx, 'rwb' => $rwb];
}

test('SheetResolver detects food_balance sheet by content', function () {
    $this->seed();
    $book = loadAndijanInflation();
    ['ctx' => $ctx, 'rwb' => $rwb] = inflationSheetCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'inflation', 'food_balance');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('1.1. Баланс');
});

test('SheetResolver detects warehouses_district_table sheet by content', function () {
    $this->seed();
    $book = loadAndijanInflation();
    ['ctx' => $ctx, 'rwb' => $rwb] = inflationSheetCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'inflation', 'warehouses_district_table');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('1.2. Омборлар');
});
