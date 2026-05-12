<?php

use App\Models\ImportRun;
use App\Models\ImportStagingFoodBalance;
use App\Models\ImportStagingWarehouse;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\InflationModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function inflationParserCtx(): array
{
    $path = base_path('../data/2. Андижон/2.1-2.2-жадваллар (инфляция).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan inflation workbook not present');
    }
    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create(['region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'inflation')->value('id'),
        'file_name' => '2.1-2.2-жадваллар (инфляция).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('InflationModuleParser produces 11 food_balance + 17 warehouses staging rows', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = inflationParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new InflationModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        $districts,
        $writer,
        $issues,
    );

    $parser->parse($ctx, $path, $rwb->id);

    expect($writer->bufferedCount('import_staging_food_balance'))->toBe(11);
    expect($writer->bufferedCount('import_staging_warehouses'))->toBe(17);

    DB::transaction(fn() => $writer->flush());
    $issues->flush();

    expect(ImportStagingFoodBalance::count())->toBe(11);
    expect(ImportStagingWarehouse::count())->toBe(17);

    expect(ImportStagingWarehouse::whereNull('district_code')->count())->toBe(1);
    expect(ImportStagingWarehouse::whereNotNull('district_code')->count())->toBe(16);

    $flour = ImportStagingFoodBalance::where('region_code', 1703)
        ->where('product', 'Ун')->first();
    expect($flour)->not->toBeNull();
    expect($flour->resource_total)->toBeNumericallyClose(430.27, 0.05);
    expect($flour->production)->toBeNumericallyClose(368.34, 0.05);

    $andijanCity = ImportStagingWarehouse::where('region_code', 1703)
        ->where('district_code', 'd01')->first();
    expect($andijanCity)->not->toBeNull();
    expect($andijanCity->reserve_warehouses)->toBe(3);
    expect($andijanCity->cold_storage_count)->toBe(10);

    $rollup = ImportStagingWarehouse::where('region_code', 1703)
        ->whereNull('district_code')->first();
    expect($rollup)->not->toBeNull();
    expect($rollup->reserve_warehouses)->toBe(89);
});
