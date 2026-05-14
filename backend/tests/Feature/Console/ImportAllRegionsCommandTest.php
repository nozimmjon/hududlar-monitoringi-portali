<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('command is registered with correct signature', function () {
    Artisan::call('list', ['--raw' => true]);
    $registered = Artisan::output();
    expect($registered)->toContain('import:all-regions');
});

test('--only with no matching tokens exits 0 with empty-selection message', function () {
    $exit = Artisan::call('import:all-regions', [
        '--only' => '__none__',
    ]);

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('No regions selected');
});

test('--only=andijan does not iterate other regions', function () {
    // Seed the database to populate regions table
    $this->seed();

    // The command may fail due to missing xlsx files in test env, but the --only filter
    // should still work (only iterate andijan, not other regions).
    $exit = Artisan::call('import:all-regions', [
        '--only' => 'andijan',
        '--no-tasks' => true,
    ]);

    expect($exit)->toBe(0);

    // Verify only andijan (1703) was processed — no runs for other regions
    // (Artisan::output() only captures the last nested sub-call, so we check DB state)
    $allRunCodes = \App\Models\ImportRun::pluck('region_code')->unique()->toArray();
    foreach ($allRunCodes as $code) {
        expect($code)->toBe(1703, "Expected only andijan (1703) runs, found region_code={$code}");
    }
    // Bukhara (1706) and Navoi (1712) must NOT have runs
    expect(\App\Models\ImportRun::where('region_code', 1706)->count())->toBe(0);
    expect(\App\Models\ImportRun::where('region_code', 1712)->count())->toBe(0);
});
