<?php

use App\Models\ImportRun;
use App\Models\Region;
use App\Services\Import\ImportContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ImportContext exposes region code via helper method', function () {
    $this->seed();
    $region = Region::where('code', 'andijan')->firstOrFail();
    $run = ImportRun::create([
        'region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli',
        'status' => 'parsing', 'started_at' => now(),
    ]);

    $ctx = new ImportContext(
        run: $run, region: $region, year: 2026,
        dataPath: '/tmp/data',
    );

    expect($ctx->regionCode())->toBe('andijan');
    expect($ctx->year)->toBe(2026);
    expect($ctx->run->id)->toBe($run->id);
});
