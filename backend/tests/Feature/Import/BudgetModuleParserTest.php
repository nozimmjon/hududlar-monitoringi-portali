<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\BudgetModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function budgetParserCtx(): array
{
    $path = base_path('../data/2. Андижон/3-жадвал (бюджет).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan budget workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'budget')->value('id'),
        'file_name' => '3-жадвал (бюджет).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('BudgetModuleParser produces 51 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = budgetParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new BudgetModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        $districts,
        $writer,
        $issues,
    );

    $parser->parse($ctx, $path, $rwb->id);

    expect($writer->bufferedCount('import_staging_indicator_facts'))->toBe(51);

    DB::transaction(fn() => $writer->flush());
    $issues->flush();

    expect(ImportStagingIndicatorFact::where('indicator_code', 'budget')->count())->toBe(51);

    $rollup = ImportStagingIndicatorFact::where('indicator_code', 'budget')->whereNull('district_code');
    expect($rollup->count())->toBe(3);
    $districtRows = ImportStagingIndicatorFact::where('indicator_code', 'budget')->whereNotNull('district_code');
    expect($districtRows->count())->toBe(48);

    $regionYear = ImportStagingIndicatorFact::where('indicator_code', 'budget')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($regionYear)->not->toBeNull();
    expect($regionYear->plan_value)->toBeNumericallyClose(5298.6, 0.05);
    expect($regionYear->expected_value)->toBeNumericallyClose(5888.6, 0.05);

    $regionH1 = ImportStagingIndicatorFact::where('indicator_code', 'budget')
        ->whereNull('district_code')->where('period', 'h1')->first();
    expect($regionH1->plan_value)->toBeNumericallyClose(2407.9, 0.05);
    expect($regionH1->expected_value)->toBeNumericallyClose(2598.6, 0.05);
    expect($regionH1->pct_of_plan)->toBeNumericallyClose(107.9, 0.05);

    $regionQ2 = ImportStagingIndicatorFact::where('indicator_code', 'budget')
        ->whereNull('district_code')->where('period', 'q2')->first();
    expect($regionQ2->plan_value)->toBeNumericallyClose(1272.6, 0.05);
    expect($regionQ2->expected_value)->toBeNumericallyClose(1390.0, 0.05);
    expect($regionQ2->pct_of_plan)->toBeNumericallyClose(109.2, 0.05);

    $andijanCityYear = ImportStagingIndicatorFact::where('indicator_code', 'budget')
        ->where('district_code', 'd01')->where('period', 'year')->first();
    expect($andijanCityYear)->not->toBeNull();
    expect($andijanCityYear->plan_value)->toBeNumericallyClose(172.2, 0.05);
});
