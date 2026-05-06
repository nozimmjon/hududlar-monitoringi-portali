<?php

use App\Models\ImportRun;
use App\Models\Region;
use App\Services\Import\ImportContext;
use App\Services\Import\WorkbookLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAndijanContext(): ImportContext
{
    $region = Region::where('code', 'andijan')->firstOrFail();
    $run = ImportRun::create([
        'region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli',
        'status' => 'parsing', 'started_at' => now(),
    ]);
    return new ImportContext(
        run: $run, region: $region, year: 2026,
        dataPath: base_path('../data'),
    );
}

test('locate returns the macro file for Andijan', function () {
    $this->seed();
    $ctx = makeAndijanContext();

    if (! is_dir(base_path('../data/2. Андижон'))) {
        $this->markTestSkipped('Andijan data folder not present at ../data/2. Андижон');
    }

    $locator = new WorkbookLocator();
    $files = $locator->locate($ctx, moduleFilter: 'macro');

    expect($files)->toHaveKey('macro');
    expect($files['macro'])->toContain('1.1-1.5-жадваллар (макро).xlsx');
    expect(file_exists($files['macro']))->toBeTrue();
});

test('locate filters out non-requested modules', function () {
    $this->seed();
    $ctx = makeAndijanContext();

    if (! is_dir(base_path('../data/2. Андижон'))) {
        $this->markTestSkipped('Andijan data folder not present');
    }

    $locator = new WorkbookLocator();
    $files = $locator->locate($ctx, moduleFilter: 'macro');

    expect($files)->toHaveCount(1);
    expect($files)->toHaveKey('macro');
});

test('locate registers an import_files row for each found file', function () {
    $this->seed();
    $ctx = makeAndijanContext();

    if (! is_dir(base_path('../data/2. Андижон'))) {
        $this->markTestSkipped('Andijan data folder not present');
    }

    $locator = new WorkbookLocator();
    $locator->locate($ctx, moduleFilter: 'macro');

    expect(\App\Models\ImportFile::where('import_run_id', $ctx->run->id)->count())->toBe(1);
    $file = \App\Models\ImportFile::where('import_run_id', $ctx->run->id)->first();
    expect($file->module_code)->toBe('macro');
    expect($file->sha256)->toHaveLength(64);
    expect($file->size_bytes)->toBeGreaterThan(0);
});
