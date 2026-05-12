<?php

use App\Models\DataQualityIssue;
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

function employmentSentinelCtx(): array
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

test('Andijan city poverty_year is parsed as a sentinel row', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = employmentSentinelCtx();

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
    DB::transaction(fn() => $writer->flush());
    $issues->flush();

    $row = ImportStagingIndicatorFact::where('region_code', 1703)
        ->where('district_code', 'd01')
        ->where('indicator_code', 'poverty')
        ->where('period', 'year')
        ->first();
    expect($row)->not->toBeNull();
    expect($row->is_sentinel)->toBeTrue();
    expect($row->sentinel_label)->toContain('холи ҳудуд');
    expect($row->plan_value)->toBeNull();
});

test('At least one sentinel issue is raised in data_quality_issues', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = employmentSentinelCtx();

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
    $writer->flush();
    $issues->flush();

    $sentinelIssues = DataQualityIssue::where('issue_kind', 'sentinel')->count();
    expect($sentinelIssues)->toBeGreaterThan(0);
});
