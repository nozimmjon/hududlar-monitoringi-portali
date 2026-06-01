<?php
// backend/tests/Feature/Tasks/TasksBoardProgressTest.php

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
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '1', 'title' => 'ЯҲМ ўсиши',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'quarterly', 'status' => 'open',
        'headline_unit' => 'фоиз', 'headline_plan' => 7.2, 'headline_actual' => 3.6,
        'headline_pct' => 50, 'latest_period' => '2026-Q1',
    ]);
});

test('board card shows plan, actual, percent, cadence and last period', function () {
    session(['region_code' => 1703]); // TasksBoard::mount() reads CurrentRegion::code()
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSee('7.2')      // plan
        ->assertSee('3.6')      // actual
        ->assertSee('50')       // pct
        ->assertSee('Чорак')    // cadence label (quarterly)
        ->assertSee('2026-Q1'); // last period
});

test('done task shows done badge and green progress', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '2', 'title' => 'Экспорт ҳажми',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'done',
        'headline_unit' => 'млн долл', 'headline_plan' => 10, 'headline_actual' => 12,
        'headline_pct' => 120, 'latest_period' => '2026-Q1',
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'done')
        ->assertSee('Бажарилди')
        ->assertSee('Экспорт ҳажми')
        ->assertSeeHtml('--task-green')
        // Safe negative assertion: the full title can't appear elsewhere; the seeder
        // string 'ЯҲМ' (indicator label) is never rendered because indicator_code is null.
        ->assertDontSee('ЯҲМ ўсиши'); // open task filtered out in done view
});

test('task without progress data renders without errors', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '3', 'title' => 'Маълумотсиз топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        // no cadence/headline/latest_period at all (all null)
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSee('Маълумотсиз топшириқ')
        ->assertSee('—'); // null values render as em-dash
});
