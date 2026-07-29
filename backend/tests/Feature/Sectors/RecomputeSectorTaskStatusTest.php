<?php

use App\Models\SectorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

test('recompute rebuilds status and snapshot from stored progress', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, 120, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-Q3']);

    // Corrupt the snapshot, then recompute.
    $task = SectorTask::where('task_no', 1)->first();
    $task->update(['status' => 'open', 'headline_pct' => null, 'lines_done' => 0, 'latest_period' => null]);

    $exit = Artisan::call('sector-tasks:recompute');

    expect($exit)->toBe(0);
    $task->refresh();
    expect($task->status)->toBe('done');
    expect($task->latest_period)->toBe('2026-Q3');
    expect((float) $task->headline_pct)->toEqualWithDelta(120.0, 0.01);
    expect($task->lines_done)->toBe(1);
});

test('recompute picks the latest period and leaves progress-less tasks untouched', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, 50, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-08']);
    $q3 = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, 120, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $q3, '--period' => '2026-Q3']);

    // A task that never got progress rows must stay untouched by recompute.
    $bare = App\Models\SectorTask::create([
        'sector_id' => App\Models\Sector::where('code', 'nkmk')->first()->id,
        'task_no' => 1, 'title' => 'Прогресссиз', 'status' => 'in_progress',
    ]);

    $task = App\Models\SectorTask::where('title', '!=', 'Прогресссиз')->first();
    $task->update(['latest_period' => null, 'status' => 'open', 'headline_pct' => null]);

    $exit = Artisan::call('sector-tasks:recompute');

    expect($exit)->toBe(0);
    $task->refresh();
    expect($task->latest_period)->toBe('2026-Q3');   // month 2026-08 sorts before Q3 (2026-09)
    expect($task->status)->toBe('done');
    expect((float) $task->headline_pct)->toEqualWithDelta(120.0, 0.01);

    $bare->refresh();
    expect($bare->latest_period)->toBeNull();
    expect($bare->status)->toBe('in_progress');
});
