<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\BudgetInvestModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function budgetInvestParserCtx(): array
{
    $path = base_path('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan budget_invest workbook not present');
    }
    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create(['region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'budget_invest')->value('id'),
        'file_name' => '4.1-жадвал (бюджет инвестка).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('BudgetInvestModuleParser produces 51 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = budgetInvestParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new BudgetInvestModuleParser(
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

    expect(ImportStagingIndicatorFact::where('indicator_code', 'budget_investment')->count())->toBe(51);

    $rollup = ImportStagingIndicatorFact::where('indicator_code', 'budget_investment')->whereNull('district_code');
    expect($rollup->count())->toBe(3);
    $districtRows = ImportStagingIndicatorFact::where('indicator_code', 'budget_investment')->whereNotNull('district_code');
    expect($districtRows->count())->toBe(48);

    // Region rollup q1: limit=950279.86, q1_absorption=177548.80, q1_pct=18.68, objects=100
    $q1 = ImportStagingIndicatorFact::where('indicator_code', 'budget_investment')
        ->whereNull('district_code')->where('period', 'q1')->first();
    expect($q1)->not->toBeNull();
    expect($q1->plan_value)->toBeNumericallyClose(950279.86, 0.5);
    expect($q1->actual_hokimyat)->toBeNumericallyClose(177548.80, 0.5);
    expect($q1->pct_of_plan)->toBeNumericallyClose(18.68, 0.05);
    expect($q1->count_extra)->toBe(100);
    expect($q1->count_extra_2)->toBeNull();

    // Region rollup year: actual=1024641.5, pct=107.8, count_extra_2=96
    $year = ImportStagingIndicatorFact::where('indicator_code', 'budget_investment')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($year->plan_value)->toBeNumericallyClose(950279.86, 0.5);
    expect($year->actual_hokimyat)->toBeNumericallyClose(1024641.5, 0.5);
    expect($year->pct_of_plan)->toBeNumericallyClose(107.8, 0.05);
    expect($year->count_extra)->toBe(100);
    expect($year->count_extra_2)->toBe(96);

    // Andijan city (1703401) q1: limit=128147.1, q1_absorption=6566.9, objects=13
    $cityQ1 = ImportStagingIndicatorFact::where('indicator_code', 'budget_investment')
        ->where('district_code', 1703401)->where('period', 'q1')->first();
    expect($cityQ1)->not->toBeNull();
    expect($cityQ1->plan_value)->toBeNumericallyClose(128147.1, 0.5);
    expect($cityQ1->actual_hokimyat)->toBeNumericallyClose(6566.9, 0.5);
    expect($cityQ1->count_extra)->toBe(13);
});
