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
