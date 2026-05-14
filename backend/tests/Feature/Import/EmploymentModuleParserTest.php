<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\EmploymentModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function employmentParserCtx(): array
{
    $path = base_path('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan employment workbook not present');
    }
    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create(['region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'employment')->value('id'),
        'file_name' => '6-жадвал (бандлик ва камбағаллик даражаси).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('EmploymentModuleParser produces 204 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = employmentParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new EmploymentModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        $districts,
        $writer,
        $issues,
    );

    $parser->parse($ctx, $path, $rwb->id);

    expect($writer->bufferedCount('import_staging_indicator_facts'))->toBe(204);

    DB::transaction(fn() => $writer->flush());
    $issues->flush();

    expect(ImportStagingIndicatorFact::whereIn('indicator_code', ['unemployment','poverty','jobs','legalization','mfy_clear','microprojects'])->count())->toBe(204);

    // Region rollup unemployment_h1 = 3.84
    $unempH1 = ImportStagingIndicatorFact::where('indicator_code', 'unemployment')
        ->whereNull('district_code')->where('period', 'h1')->first();
    expect($unempH1)->not->toBeNull();
    expect($unempH1->plan_value)->toBeNumericallyClose(3.84, 0.05);
    expect($unempH1->unit)->toBe('%');
    expect($unempH1->is_sentinel)->toBeFalse();

    // Region rollup poverty_year = 2.7 (NOT a sentinel for the rollup)
    $povYear = ImportStagingIndicatorFact::where('indicator_code', 'poverty')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($povYear)->not->toBeNull();
    expect($povYear->plan_value)->toBeNumericallyClose(2.7, 0.05);
    expect($povYear->is_sentinel)->toBeFalse();

    // Region rollup jobs_year = 86.674
    $jobsYear = ImportStagingIndicatorFact::where('indicator_code', 'jobs')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($jobsYear->plan_value)->toBeNumericallyClose(86.674, 0.05);

    // Андижон шаҳри poverty_year IS a sentinel
    $cityPovYear = ImportStagingIndicatorFact::where('indicator_code', 'poverty')
        ->where('district_code', 1703401)->where('period', 'year')->first();
    expect($cityPovYear->is_sentinel)->toBeTrue();
    expect($cityPovYear->plan_value)->toBeNull();
    expect($cityPovYear->sentinel_label)->toContain('холи ҳудуд');
});
