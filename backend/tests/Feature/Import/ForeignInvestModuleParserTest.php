<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\ForeignInvestModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function foreignInvestParserCtx(): array
{
    $path = base_path('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan foreign_invest workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'foreign_invest')->value('id'),
        'file_name' => '4.2-жадвал (инвестициялар).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('ForeignInvestModuleParser produces 51 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = foreignInvestParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new ForeignInvestModuleParser(
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

    expect(ImportStagingIndicatorFact::where('indicator_code', 'investment')->count())->toBe(51);
    expect(ImportStagingIndicatorFact::where('indicator_code', 'investment')->whereNull('district_code')->count())->toBe(3);
    expect(ImportStagingIndicatorFact::where('indicator_code', 'investment')->whereNotNull('district_code')->count())->toBe(48);

    // Region rollup q1: plan=807.4, actual=880.0, pct=1.1, projects=101
    $q1 = ImportStagingIndicatorFact::where('indicator_code', 'investment')
        ->whereNull('district_code')->where('period', 'q1')->first();
    expect($q1)->not->toBeNull();
    expect($q1->plan_value)->toBeNumericallyClose(807.4, 0.5);
    expect($q1->actual_hokimyat)->toBeNumericallyClose(880.0, 0.5);
    expect($q1->expected_value)->toBeNull();
    expect($q1->count_extra)->toBe(101);
    expect($q1->count_extra_2)->toBeNull();

    // Region rollup h1: plan=1760.8, expected=1783.3, projects=155, jobs=8989
    $h1 = ImportStagingIndicatorFact::where('indicator_code', 'investment')
        ->whereNull('district_code')->where('period', 'h1')->first();
    expect($h1->plan_value)->toBeNumericallyClose(1760.8, 0.5);
    expect($h1->expected_value)->toBeNumericallyClose(1783.3, 0.5);
    expect($h1->actual_hokimyat)->toBeNull();
    expect($h1->count_extra)->toBe(155);
    expect($h1->count_extra_2)->toBe(8989);

    // Region rollup year: plan=3341.7, expected=3508.6
    $year = ImportStagingIndicatorFact::where('indicator_code', 'investment')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($year->plan_value)->toBeNumericallyClose(3341.7, 0.5);
    expect($year->expected_value)->toBeNumericallyClose(3508.6, 0.5);
    expect($year->actual_hokimyat)->toBeNull();
    expect($year->count_extra)->toBeNull();
    expect($year->count_extra_2)->toBeNull();

    // Andijan city (d01) q1: plan=175.2, actual=141.1
    $cityQ1 = ImportStagingIndicatorFact::where('indicator_code', 'investment')
        ->where('district_code', 'd01')->where('period', 'q1')->first();
    expect($cityQ1)->not->toBeNull();
    expect($cityQ1->plan_value)->toBeNumericallyClose(175.2, 0.5);
    expect($cityQ1->actual_hokimyat)->toBeNumericallyClose(141.1, 0.5);
});
