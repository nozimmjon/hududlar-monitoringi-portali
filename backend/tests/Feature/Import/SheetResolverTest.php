<?php

use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Models\RegionWorkbookSheet;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\SheetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

function loadAndijanMacro(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/1.1-1.5-жадваллар (макро).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan macro workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    return $reader->load($path);
}

function makeRegionWorkbook(): RegionWorkbook
{
    $region = Region::where('code', 1703)->firstOrFail();
    $yearId = \Illuminate\Support\Facades\DB::table('reporting_years')->where('year', 2026)->value('id');
    $moduleId = \Illuminate\Support\Facades\DB::table('modules')->where('code', 'macro')->value('id');

    return RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => $yearId,
        'module_id'         => $moduleId,
        'file_name'         => '1.1-1.5-жадваллар (макро).xlsx',
        'file_path'         => 'fixture',
        'last_seen_at'      => now(),
    ]);
}

function makeAndijanCtx(): ImportContext
{
    return new ImportContext(
        run: ImportRun::create(['region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: Region::where('code', 1703)->first(),
        year: 2026,
        dataPath: base_path('../data'),
    );
}

test('SheetResolver detects rollup sheet on cache miss and writes to cache', function () {
    $this->seed();
    $book = loadAndijanMacro();
    $rwb = makeRegionWorkbook();
    $issues = new IssueCollector();
    $resolver = new SheetResolver($issues);

    $sheet = $resolver->resolve(makeAndijanCtx(), $book, $rwb->id, 'macro', 'rollup');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('1.1. ЯҲМ');

    $cached = RegionWorkbookSheet::where('region_workbook_id', $rwb->id)
        ->where('logical_kind', 'rollup')->first();
    expect($cached)->not->toBeNull();
    expect($cached->sheet_name)->toBe('1.1. ЯҲМ');
});

test('SheetResolver uses cached sheet on subsequent calls', function () {
    $this->seed();
    $book = loadAndijanMacro();
    $rwb = makeRegionWorkbook();

    // Pre-populate cache with a different sheet name to prove cache is used
    RegionWorkbookSheet::create([
        'region_workbook_id' => $rwb->id,
        'sheet_name'         => '1.2. Саноат',
        'logical_kind'       => 'rollup',
    ]);

    $resolver = new SheetResolver(new IssueCollector());
    $sheet = $resolver->resolve(makeAndijanCtx(), $book, $rwb->id, 'macro', 'rollup');

    expect($sheet->getTitle())->toBe('1.2. Саноат');
});

test('SheetResolver detects district_industry sheet by content', function () {
    $this->seed();
    $book = loadAndijanMacro();
    $rwb = makeRegionWorkbook();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve(makeAndijanCtx(), $book, $rwb->id, 'macro', 'district_industry');

    expect($sheet->getTitle())->toBe('1.2. Саноат');
});

test('SheetResolver raises SheetMissing blocker when no signature matches', function () {
    $this->seed();
    $book = loadAndijanMacro();
    $rwb = makeRegionWorkbook();
    $issues = new IssueCollector();
    $resolver = new SheetResolver($issues);

    $sheet = $resolver->resolve(makeAndijanCtx(), $book, $rwb->id, 'macro', 'no_such_kind');

    expect($sheet)->toBeNull();
    expect($issues->blockerCount())->toBe(1);
});
