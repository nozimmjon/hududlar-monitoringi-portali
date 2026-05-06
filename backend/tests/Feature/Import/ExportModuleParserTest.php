<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\ExportModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function exportParserCtx(): array
{
    $path = base_path('../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan export workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'export')->value('id'),
        'file_name' => '5.1-5.2-жадваллар (экспорт).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('ExportModuleParser produces 51 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = exportParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new ExportModuleParser(
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

    expect(ImportStagingIndicatorFact::where('indicator_code', 'export')->count())->toBe(51);
    expect(ImportStagingIndicatorFact::where('indicator_code', 'export')->whereNull('district_code')->count())->toBe(3);
    expect(ImportStagingIndicatorFact::where('indicator_code', 'export')->whereNotNull('district_code')->count())->toBe(48);

    // Region rollup q1: actual=q1_value=196620.25, growth=173.71, count_extra=260
    $q1 = ImportStagingIndicatorFact::where('indicator_code', 'export')
        ->whereNull('district_code')->where('period', 'q1')->first();
    expect($q1)->not->toBeNull();
    expect($q1->plan_value)->toBeNull();
    expect($q1->actual_hokimyat)->toBeNumericallyClose(196620.25, 0.5);
    expect($q1->growth_pct)->toBeNumericallyClose(173.71, 0.05);
    expect($q1->count_extra)->toBe(260);

    // Region rollup h1: expected=361620.88, growth=121, count_extra=275
    $h1 = ImportStagingIndicatorFact::where('indicator_code', 'export')
        ->whereNull('district_code')->where('period', 'h1')->first();
    expect($h1->plan_value)->toBeNull();
    expect($h1->expected_value)->toBeNumericallyClose(361620.88, 0.5);
    expect($h1->actual_hokimyat)->toBeNull();
    expect($h1->growth_pct)->toBeNumericallyClose(121, 0.05);
    expect($h1->count_extra)->toBe(275);

    // Region rollup year: plan=967178.43, expected=976839.0, count_extra=400
    $year = ImportStagingIndicatorFact::where('indicator_code', 'export')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($year->plan_value)->toBeNumericallyClose(967178.43, 0.5);
    expect($year->expected_value)->toBeNumericallyClose(976839.0, 0.5);
    expect($year->actual_hokimyat)->toBeNull();
    expect($year->growth_pct)->toBeNumericallyClose(131.5, 0.05);
    expect($year->count_extra)->toBe(400);
});
