<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 employment creates a successful run with 204 rows', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx'))) {
        $this->markTestSkipped('Andijan employment data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'employment',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $count = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->whereIn('indicator_code', ['unemployment','poverty','jobs','legalization','mfy_clear','microprojects'])
        ->count();
    expect($count)->toBe(204);
});
