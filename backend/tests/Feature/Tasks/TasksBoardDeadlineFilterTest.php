<?php
// backend/tests/Feature/Tasks/TasksBoardDeadlineFilterTest.php

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

    Task::factory()->create([
        'title' => 'Ярим йиллик топшириқ', 'indicator_code' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
        'headline_plan' => 10,
    ]);
    Task::factory()->create([
        'title' => 'Май ойи топшириқ', 'indicator_code' => null,
        'period_code' => 'month', 'deadline_text' => '2026 йил май ойи',
        'headline_plan' => 10,
    ]);
    Task::factory()->create([
        'title' => 'Сентябр ойи топшириқ', 'indicator_code' => null,
        'period_code' => 'month', 'deadline_text' => '2026 йил сентябр ойи',
        'headline_plan' => 10,
    ]);
    Task::factory()->create([
        'title' => 'Йил якуни топшириқ', 'indicator_code' => null,
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
        'headline_plan' => 10, 'status' => 'done',
    ]);
    Task::factory()->create([
        'title' => 'Давомида топшириқ', 'indicator_code' => null,
        'period_code' => 'ongoing', 'deadline_text' => '2026 йил давомида',
        'headline_plan' => 10,
    ]);
    // Legacy rows from before the H2 template: «II ярим йиллик» was stored as
    // h1 and «III чорак» as null — the bucket must be derived from the text.
    Task::factory()->create([
        'title' => 'Иккинчи ярим йиллик топшириқ', 'indicator_code' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил II ярим йиллик',
        'headline_plan' => 10,
    ]);
    Task::factory()->create([
        'title' => 'Учинчи чорак топшириқ', 'indicator_code' => null,
        'period_code' => null, 'deadline_text' => '2026 йил III чорак',
        'headline_plan' => 10,
    ]);
});

function boardTitles(string $deadline): array
{
    return Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->set('deadline', $deadline)
        ->instance()->tasks->pluck('title')->values()->all();
}

test('board opens on all deadlines by default', function () {
    $titles = Livewire::test(TasksBoard::class)
        ->assertSet('deadline', 'all')
        ->set('status', 'all')
        ->instance()->tasks->pluck('title')->values()->all();

    expect($titles)->toHaveCount(7);
});

test('h1 bucket keeps half-year and first-half month deadlines', function () {
    expect(boardTitles('h1'))->toBe(['Ярим йиллик топшириқ', 'Май ойи топшириқ']);
});

test('q3 bucket keeps third-quarter months and III чорак deadlines', function () {
    expect(boardTitles('q3'))->toBe(['Сентябр ойи топшириқ', 'Учинчи чорак топшириқ']);
});

test('h2 bucket keeps II ярим йиллик deadlines even with a legacy h1 code', function () {
    expect(boardTitles('h2'))->toBe(['Иккинчи ярим йиллик топшириқ']);
});

test('h1 bucket does not swallow II ярим йиллик tasks', function () {
    expect(boardTitles('h1'))->not->toContain('Иккинчи ярим йиллик топшириқ');
});

test('year bucket keeps only year-end deadlines', function () {
    expect(boardTitles('year'))->toBe(['Йил якуни топшириқ']);
});

test('ongoing bucket keeps only davomida deadlines', function () {
    expect(boardTitles('ongoing'))->toBe(['Давомида топшириқ']);
});

test('all shows every task', function () {
    expect(boardTitles('all'))->toHaveCount(7);
});

test('totals respect the deadline filter', function () {
    $totals = Livewire::test(TasksBoard::class)
        ->set('deadline', 'year')
        ->instance()->totals;

    expect($totals['total'])->toBe(1)
        ->and($totals['done'])->toBe(1);
});

test('deadline options list only buckets present in data, in deadline order', function () {
    $options = Livewire::test(TasksBoard::class)
        ->instance()->deadlineOptions;

    expect(array_keys($options))->toBe(['h1', 'q3', 'h2', 'year', 'ongoing']);
});

test('clear filters resets deadline to the all default', function () {
    Livewire::test(TasksBoard::class)
        ->set('deadline', 'year')
        ->call('clearFilters')
        ->assertSet('deadline', 'all');
});
