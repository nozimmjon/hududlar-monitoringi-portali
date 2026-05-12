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

function loadAndijanForeignInvest(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan foreign_invest workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(false);
    return $reader->load($path);
}

function foreignInvestSheetCtx(): array
{
    $region = Region::where('code', 1703)->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'foreign_invest')->value('id'),
        'file_name' => '4.2-жадвал (инвестициялар).xlsx',
        'file_path' => 'fixture',
        'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region, year: 2026, dataPath: base_path('../data'),
    );
    return ['ctx' => $ctx, 'rwb' => $rwb];
}

test('SheetResolver detects foreign_invest sheet by content (4,2-хорижий инв for Andijan)', function () {
    $this->seed();
    $book = loadAndijanForeignInvest();
    ['ctx' => $ctx, 'rwb' => $rwb] = foreignInvestSheetCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'foreign_invest', 'foreign_invest');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('4,2-хорижий инв');
});
