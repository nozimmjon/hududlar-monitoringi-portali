<?php

use App\Enums\ImportRunStatus;
use App\Models\FoodBalance;
use App\Models\IndicatorFact;
use App\Models\ImportRun;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('import:promote moves indicator facts to production table', function () {
    $this->seed();

    $run = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => 'awaiting_review',
        'started_at'   => now(),
        'rows_staged'  => 1,
    ]);

    DB::table('import_staging_indicator_facts')->insert([
        'import_run_id'  => $run->id,
        'region_code'    => 1703,
        'district_code'  => null,
        'year'           => 2026,
        'indicator_code' => 'export',
        'period'         => 'year',
        'plan_value'     => 3341.74,
        'unit'           => 'минг доллар',
        'source_label'   => 'test',
        'staging_status' => 'pending',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $exitCode = Artisan::call('import:promote', ['run_id' => $run->id]);

    expect($exitCode)->toBe(0);
    expect(IndicatorFact::count())->toBe(1);

    $fact = IndicatorFact::first();
    expect($fact->indicator_code)->toBe('export');
    expect($fact->plan_value)->toBeNumericallyClose(3341.74, 0.01);

    $run->refresh();
    expect($run->status->value)->toBe('promoted');
    expect($run->rows_promoted)->toBeGreaterThanOrEqual(1);
    expect($run->promoted_at)->not->toBeNull();
});

test('import:promote fails if run is not awaiting_review', function () {
    $this->seed();

    $run = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => 'promoted',
        'started_at'   => now(),
    ]);

    $exitCode = Artisan::call('import:promote', ['run_id' => $run->id]);

    expect($exitCode)->toBe(1);
    expect(IndicatorFact::count())->toBe(0);

    $run->refresh();
    expect($run->status->value)->toBe('promoted');
});

test('import:promote is idempotent — second run with same key updates value', function () {
    $this->seed();

    $run1 = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => 'awaiting_review',
        'started_at'   => now(),
        'rows_staged'  => 1,
    ]);

    DB::table('import_staging_indicator_facts')->insert([
        'import_run_id'  => $run1->id,
        'region_code'    => 1703,
        'district_code'  => null,
        'year'           => 2026,
        'indicator_code' => 'export',
        'period'         => 'year',
        'plan_value'     => 3341.74,
        'unit'           => 'минг доллар',
        'source_label'   => 'test',
        'staging_status' => 'pending',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    Artisan::call('import:promote', ['run_id' => $run1->id]);

    $run2 = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => 'awaiting_review',
        'started_at'   => now(),
        'rows_staged'  => 1,
    ]);

    DB::table('import_staging_indicator_facts')->insert([
        'import_run_id'  => $run2->id,
        'region_code'    => 1703,
        'district_code'  => null,
        'year'           => 2026,
        'indicator_code' => 'export',
        'period'         => 'year',
        'plan_value'     => 4000.00,
        'unit'           => 'минг доллар',
        'source_label'   => 'test2',
        'staging_status' => 'pending',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $exitCode = Artisan::call('import:promote', ['run_id' => $run2->id]);

    expect($exitCode)->toBe(0);
    expect(IndicatorFact::count())->toBe(1);

    $fact = IndicatorFact::first();
    expect($fact->plan_value)->toBeNumericallyClose(4000.00, 0.01);
});

test('import:promote also promotes food_balance and warehouses', function () {
    $this->seed();

    $run = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => 'awaiting_review',
        'started_at'   => now(),
        'rows_staged'  => 2,
    ]);

    DB::table('import_staging_food_balance')->insert([
        'import_run_id'  => $run->id,
        'region_code'    => 1703,
        'year'           => 2026,
        'product'        => 'Буғдой',
        'source_label'   => 'test',
        'staging_status' => 'pending',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    DB::table('import_staging_warehouses')->insert([
        'import_run_id'      => $run->id,
        'region_code'        => 1703,
        'district_code'      => null,
        'year'               => 2026,
        'reserve_warehouses' => 5,
        'source_label'       => 'test',
        'staging_status'     => 'pending',
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    Artisan::call('import:promote', ['run_id' => $run->id]);

    expect(FoodBalance::count())->toBe(1);
    expect(Warehouse::count())->toBe(1);
});
