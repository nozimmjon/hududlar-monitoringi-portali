<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\MacroModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function macroParserCtx(): array
{
    $path = base_path('../data/2. Андижон/1.1-1.5-жадваллар (макро).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan macro workbook not present');
    }
    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create(['region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'macro')->value('id'),
        'file_name' => '1.1-1.5-жадваллар (макро).xlsx', 'file_path' => $path, 'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('MacroModuleParser produces 212 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = macroParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new MacroModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        $districts,
        $writer,
        $issues,
    );

    $parser->parse($ctx, $path, $rwb->id);

    expect($writer->bufferedCount('import_staging_indicator_facts'))->toBe(212);

    DB::transaction(fn() => $writer->flush());
    $issues->flush();

    $rollup = ImportStagingIndicatorFact::whereNull('district_code')->count();
    expect($rollup)->toBe(20);

    $district = ImportStagingIndicatorFact::whereNotNull('district_code')->count();
    expect($district)->toBe(192);

    $grpYear = ImportStagingIndicatorFact::where('region_code', 1703)
        ->whereNull('district_code')->where('indicator_code','grp')->where('period','year')->first();
    expect($grpYear->plan_value)->toBeNumericallyClose(124778.117923571, 1e-6);

    $industryQ1 = ImportStagingIndicatorFact::where('region_code', 1703)
        ->where('district_code','d01')->where('indicator_code','industry')->where('period','q1')->first();
    expect($industryQ1->plan_value)->toBeNumericallyClose(4600.872899834, 1e-6);
});
