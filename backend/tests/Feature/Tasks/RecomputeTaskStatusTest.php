<?php
// backend/tests/Feature/Tasks/RecomputeTaskStatusTest.php

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

function statusTask(string $num, array $attrs, array $lines): Task
{
    $task = Task::factory()->create(array_merge([
        'region_code' => 1703, 'task_number' => $num, 'latest_period' => '2026-H1',
    ], $attrs));
    foreach ($lines as $i => [$plan, $actual, $pct]) {
        $task->progress()->create([
            'line_no' => $i, 'report_period' => '2026-H1', 'period_type' => 'half',
            'plan_value' => $plan, 'actual_value' => $actual, 'pct_of_plan' => $pct,
        ]);
    }

    return $task;
}

test('recomputes weakest-link status and line counters from progress rows', function () {
    // Line 0 done, line 1 not -> wrongly 'done' today, must flip to open with 1/2.
    $multi = statusTask('1', ['status' => 'done', 'headline_pct' => 110], [
        [10, 11, 110], [20, 5, 25],
    ]);
    // All planned lines done -> stays done, 2/2.
    $allDone = statusTask('2', ['status' => 'done', 'headline_pct' => 100], [
        [10, 10, 100], [7, 9, 128.57],
    ]);
    // Single-line done task keeps its status, 1/1.
    $single = statusTask('3', ['status' => 'done', 'headline_pct' => 120], [
        [10, 12, 120],
    ]);
    // Planned line without any pct (no data) blocks done, even at 100% on line 0.
    $noData = statusTask('4', ['status' => 'done', 'headline_pct' => 100], [
        [10, 10, 100], [5, null, null],
    ]);
    // Unplanned informational line is not counted.
    $info = statusTask('5', ['status' => 'open', 'headline_pct' => 100], [
        [10, 10, 100], [null, 3, null],
    ]);
    // Nothing reported at all -> Бажарилмоқда.
    $fresh = statusTask('6', ['status' => 'open'], [
        [10, null, null], [5, null, null],
    ]);

    $this->artisan('tasks:recompute')->assertSuccessful();

    expect($multi->fresh())->status->toBe('open')->lines_total->toBe(2)->lines_done->toBe(1);
    expect($allDone->fresh())->status->toBe('done')->lines_total->toBe(2)->lines_done->toBe(2);
    expect($single->fresh())->status->toBe('done')->lines_total->toBe(1)->lines_done->toBe(1);
    expect($noData->fresh())->status->toBe('open')->lines_total->toBe(2)->lines_done->toBe(1);
    expect($info->fresh())->status->toBe('done')->lines_total->toBe(1)->lines_done->toBe(1);
    expect($fresh->fresh())->status->toBe('in_progress')->lines_total->toBe(2)->lines_done->toBe(0);
});

test('dry-run reports but writes nothing', function () {
    $multi = statusTask('1', ['status' => 'done', 'headline_pct' => 110], [
        [10, 11, 110], [20, 5, 25],
    ]);

    $this->artisan('tasks:recompute', ['--dry-run' => true])->assertSuccessful();

    expect($multi->fresh())->status->toBe('done')->lines_total->toBe(0);
});
