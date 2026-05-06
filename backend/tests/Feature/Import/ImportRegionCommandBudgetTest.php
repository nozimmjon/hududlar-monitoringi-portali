<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 budget creates a successful run with 51 rows', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/3-жадвал (бюджет).xlsx'))) {
        $this->markTestSkipped('Andijan budget data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'budget',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    expect(ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'budget')->count())->toBe(51);
});
