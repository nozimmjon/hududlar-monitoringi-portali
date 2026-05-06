<?php

use App\Models\ImportRun;
use App\Models\ImportStagingFoodBalance;
use App\Models\ImportStagingWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 inflation creates a successful run with food_balance + warehouses rows', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/2.1-2.2-жадваллар (инфляция).xlsx'))) {
        $this->markTestSkipped('Andijan inflation data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'inflation',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    expect(ImportStagingFoodBalance::where('import_run_id', $run->id)->count())->toBe(11);
    expect(ImportStagingWarehouse::where('import_run_id', $run->id)->count())->toBe(17);
});
