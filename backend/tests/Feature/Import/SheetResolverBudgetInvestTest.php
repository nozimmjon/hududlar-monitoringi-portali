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

function loadAndijanBudgetInvest(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan budget_invest workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(false);
    return $reader->load($path);
}

function budgetInvestSheetCtx(): array
{
    $region = Region::where('code', 1703)->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'budget_invest')->value('id'),
        'file_name' => '4.1-жадвал (бюджет инвестка).xlsx',
        'file_path' => 'fixture',
        'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region, year: 2026, dataPath: base_path('../data'),
    );
    return ['ctx' => $ctx, 'rwb' => $rwb];
}

test('SheetResolver detects budget_invest sheet by content (2.Анд for Andijan)', function () {
    $this->seed();
    $book = loadAndijanBudgetInvest();
    ['ctx' => $ctx, 'rwb' => $rwb] = budgetInvestSheetCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'budget_invest', 'budget_invest');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('2.Анд');
});
