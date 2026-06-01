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

test('card expands to show all metric lines and responsible districts', function () {
    DB::table('districts')->insert([
        'region_id' => 1, 'region_code' => 1703, 'code' => 1703230,
        'name_short' => 'Шаҳрихон т.', 'name_full' => 'Шаҳрихон тумани',
        'kind' => 'district', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '7', 'title' => 'Кўп кўрсаткичли топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 6, 'headline_actual' => 3,
        'headline_pct' => 50, 'latest_period' => '2026-Q1',
    ]);
    $task->progress()->createMany([
        ['line_no' => 0, 'metric_label' => 'йирик корхона сони', 'unit' => 'дона',
         'report_period' => '2026-Q1', 'period_type' => 'quarter', 'plan_value' => 6, 'actual_value' => 3, 'pct_of_plan' => 50],
        ['line_no' => 1, 'metric_label' => 'қайта тикланадиган ишлаб чиқариш', 'unit' => 'млрд сўм',
         'report_period' => '2026-Q1', 'period_type' => 'quarter', 'plan_value' => 55, 'actual_value' => 55, 'pct_of_plan' => 100],
    ]);
    $task->districts()->sync(DB::table('districts')->where('code', 1703230)->pluck('id'));

    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSee('Батафсил')
        ->assertSee('қайта тикланадиган ишлаб чиқариш')
        ->assertSee('млрд сўм')
        ->assertSee('Шаҳрихон тумани');
});

test('task without progress data renders without errors', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '3', 'title' => 'Маълумотсиз топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        // plan set so the card is visible; actual/pct/cadence stay null -> still render as em-dash
        'headline_plan' => 6,
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSee('Маълумотсиз топшириқ')
        ->assertSee('—'); // null values render as em-dash
});

test('a task with no plan is hidden from the board list and the count', function () {
    // No-plan task in the active region — must not appear, must not be counted.
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '99', 'title' => 'Режасиз топшириқ',
        'module_code' => 'macro', 'indicator_code' => null, 'status' => 'open',
        'headline_plan' => null, 'latest_period' => '2026-Q1',
    ]);

    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSee('ЯҲМ ўсиши')             // planned task (from beforeEach) still visible
        ->assertDontSee('Режасиз топшириқ')   // no-plan task hidden from the list
        ->assertSee('1 та');                  // list count chip excludes the no-plan task
});

test('a no-plan task does not contribute its module to the filter options', function () {
    DB::table('modules')->insert([
        'code' => 'export', 'label' => 'Экспорт', 'sort_order' => 20,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // Only this (hidden) task uses the export module; export must not appear in the filter.
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '98', 'title' => 'Режасиз экспорт',
        'module_code' => 'export', 'indicator_code' => null, 'status' => 'open',
        'headline_plan' => null, 'latest_period' => '2026-Q1',
    ]);

    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSeeHtml('value="macro"')      // macro has a planned task -> offered
        ->assertDontSeeHtml('value="export"'); // export only has a no-plan task -> not offered
});
