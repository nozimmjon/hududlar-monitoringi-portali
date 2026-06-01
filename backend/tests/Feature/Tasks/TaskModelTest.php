<?php
// backend/tests/Feature/Tasks/TaskModelTest.php

use App\Models\Task;
use App\Models\TaskProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // tasks.region_code FK -> regions.code; seed a minimal region.
    DB::table('regions')->insert([
        'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'folder_name' => '2. Андижон', 'sort_order' => 2,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

test('task has many progress rows ordered access works', function () {
    $task = Task::factory()->create([
        'region_code' => 1703,
        'module_code' => null,
        'indicator_code' => null,
    ]);

    $task->progress()->create([
        'line_no' => 0, 'metric_label' => 'ЯҲМ', 'unit' => 'фоиз',
        'report_period' => '2026-Q1', 'period_type' => 'quarter',
        'plan_value' => 7.2, 'actual_value' => null, 'pct_of_plan' => null,
    ]);

    expect($task->progress()->count())->toBe(1);
    expect($task->headlineProgress('2026-Q1')->metric_label)->toBe('ЯҲМ');
});

test('headlineProgress returns null when no rows for period', function () {
    $task = Task::factory()->create([
        'region_code' => 1703,
        'module_code' => null,
        'indicator_code' => null,
    ]);
    expect($task->headlineProgress('2026-Q1'))->toBeNull();
});
