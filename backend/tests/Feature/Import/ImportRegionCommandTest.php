<?php

use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 macro creates a successful run', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/1.1-1.5-жадваллар (макро).xlsx'))) {
        $this->markTestSkipped('Andijan data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'macro',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->region_code)->toBe(1703);
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);
    expect($run->rows_staged)->toBe(218);
});

test('import:region rejects unknown region', function () {
    $this->seed();
    $exitCode = Artisan::call('import:region', [
        'region_code' => 'atlantis', 'year' => 2026, '--module' => 'macro',
    ]);
    expect($exitCode)->not->toBe(0);
});

test('import:region rejects unknown region slug navoiy', function () {
    // 'navoiy' is not a valid region slug (the slug is 'navoi'); command exits with FAILURE
    $this->seed();
    $exitCode = Artisan::call('import:region', [
        'region_code' => 'navoiy', 'year' => 2026, '--module' => 'macro',
    ]);
    expect($exitCode)->not->toBe(0);
    expect(Artisan::output())->toContain('Unknown region');
});
