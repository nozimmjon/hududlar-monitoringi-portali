<?php
// backend/tests/Feature/Tasks/TasksBoardSortTest.php

use App\Livewire\TasksBoard;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'id' => 1, 'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'folder_name' => '2. Андижон', 'sort_order' => 2,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        'code' => 'macro', 'label' => 'Макро иқтисодиёт', 'sort_order' => 10,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    session(['region_code' => 1703]);
});

test('tasks sort by actual presence first, then deadline bucket', function () {
    // Created deliberately out of expected order; source_paragraph_index follows
    // creation order, so the old sort would show them exactly as created.
    Task::factory()->create([
        'title' => 'Йил якуни амалда йўқ', 'indicator_code' => null,
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
        'headline_plan' => 10, 'headline_actual' => null,
    ]);
    Task::factory()->create([
        'title' => 'Давомида топшириқ', 'indicator_code' => null,
        'period_code' => 'ongoing', 'deadline_text' => '2026 йил давомида',
        'headline_plan' => 10, 'headline_actual' => null,
    ]);
    Task::factory()->create([
        'title' => 'Йил якуни амалда бор', 'indicator_code' => null,
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
        'headline_plan' => 10, 'headline_actual' => 5,
    ]);
    Task::factory()->create([
        'title' => 'Сентябр ойи топшириқ', 'indicator_code' => null,
        'period_code' => 'month', 'deadline_text' => '2026 йил сентябр ойи',
        'headline_plan' => 10, 'headline_actual' => null,
    ]);
    Task::factory()->create([
        'title' => 'Ярим йиллик амалда бор', 'indicator_code' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
        'headline_plan' => 10, 'headline_actual' => 3,
    ]);
    Task::factory()->create([
        'title' => 'Ярим йиллик амалда йўқ', 'indicator_code' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
        'headline_plan' => 10, 'headline_actual' => null,
    ]);

    $titles = Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->set('deadline', 'all')
        ->instance()->tasks->pluck('title')->values()->all();

    expect($titles)->toBe([
        'Ярим йиллик амалда бор',   // actual + h1
        'Йил якуни амалда бор',     // actual + year
        'Ярим йиллик амалда йўқ',   // no actual, h1 bucket
        'Сентябр ойи топшириқ',     // no actual, Q3 month
        'Йил якуни амалда йўқ',     // no actual, year-end
        'Давомида топшириқ',        // no actual, ongoing last
    ]);
});

test('within the same bucket tasks keep source order', function () {
    Task::factory()->create([
        'title' => 'Иккинчи параграф', 'indicator_code' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
        'headline_plan' => 10, 'headline_actual' => null,
        'source_paragraph_index' => 20,
    ]);
    Task::factory()->create([
        'title' => 'Биринчи параграф', 'indicator_code' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
        'headline_plan' => 10, 'headline_actual' => null,
        'source_paragraph_index' => 10,
    ]);

    $titles = Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->set('deadline', 'all')
        ->instance()->tasks->pluck('title')->values()->all();

    expect($titles)->toBe(['Биринчи параграф', 'Иккинчи параграф']);
});
