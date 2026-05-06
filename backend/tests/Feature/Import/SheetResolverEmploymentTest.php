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

function loadAndijanEmployment(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan employment workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(false);
    return $reader->load($path);
}

function employmentSheetCtx(): array
{
    $region = Region::where('code', 'andijan')->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'employment')->value('id'),
        'file_name' => '6-жадвал (бандлик ва камбағаллик даражаси).xlsx',
        'file_path' => 'fixture',
        'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region, year: 2026, dataPath: base_path('../data'),
    );
    return ['ctx' => $ctx, 'rwb' => $rwb];
}

test('SheetResolver detects employment sheet by content (6. Камбағаллик for Andijan)', function () {
    $this->seed();
    $book = loadAndijanEmployment();
    ['ctx' => $ctx, 'rwb' => $rwb] = employmentSheetCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'employment', 'employment');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('6. Камбағаллик');
});
