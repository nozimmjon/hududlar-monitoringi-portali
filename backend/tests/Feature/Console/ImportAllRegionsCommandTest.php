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

    $output = Artisan::output();

    expect($exit)->toBe(0);
    // Verify andijan was processed (appears in output or region code 1703)
    $contains_andijan = str_contains($output, 'andijan') || str_contains($output, '1703');
    expect($contains_andijan)->toBeTrue();
    // Summary table must not include other regions
    expect($output)->not->toContain(' bukhara ');
    expect($output)->not->toContain(' navoi ');
});
