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
        'deadline_text' => '2026 йил якунигача',   // feeds the Муддат context field
        'cadence' => 'quarterly', 'status' => 'open',
        'headline_unit' => 'фоиз', 'headline_plan' => 7.2, 'headline_actual' => 3.6,
        'headline_pct' => 50, 'latest_period' => '2026-Q1',
    ]);
});

test('board card shows labeled plan, actual, percent and context', function () {
    session(['region_code' => 1703]); // TasksBoard::mount() reads CurrentRegion::code()
    Livewire::test(TasksBoard::class)
        ->set('deadline', 'all')
        ->set('status', 'all')
        ->assertSee('Режа')               // stat-strip labels
        ->assertSee('Амалда')
        ->assertSee('Бажарилиш')
        ->assertSee('7,2')                 // plan value, decimal comma
        ->assertSee('3,6')                 // actual value, decimal comma
        ->assertSee('50')                  // pct value (50%)
        ->assertSeeHtml('task-pct--amber')
        ->assertSee('Муддат')              // context label
        ->assertSee('2026 йил якунигача')  // deadline value
        ->assertSee('Йўналиш')             // module label heading
        ->assertSee('Макро иқтисодиёт')    // module value
        ->assertSee('ҳолат:')              // period caption label
        ->assertSee('2026-Q1');            // period value
});

test('percent under 50 uses the red tier', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '9', 'title' => 'Орқада топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 100, 'headline_actual' => 30,
        'headline_pct' => 30, 'latest_period' => '2026-Q1',
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('deadline', 'all')
        ->set('status', 'all')
        ->set('search', 'Орқада')              // isolate this card
        ->assertSeeHtml('task-pct--red')        // pct value gets the red modifier
        ->assertSeeHtml('var(--task-red)');     // progress bar fill var
});

test('percent between 50 and 99 uses the amber tier', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '10', 'title' => 'Ярим топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 100, 'headline_actual' => 70,
        'headline_pct' => 70, 'latest_period' => '2026-Q1',
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('deadline', 'all')
        ->set('status', 'all')
        ->set('search', 'Ярим')
        ->assertSeeHtml('task-pct--amber')
        ->assertSeeHtml('var(--task-amber)');
});

test('done task shows done badge and green tier', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '2', 'title' => 'Экспорт ҳажми',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'done',
        'headline_unit' => 'млн долл', 'headline_plan' => 10, 'headline_actual' => 12,
        'headline_pct' => 120, 'latest_period' => '2026-Q1',
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('deadline', 'all')
        ->set('status', 'done')
        ->assertSee('Бажарилди')
        ->assertSee('Экспорт ҳажми')
        ->assertSeeHtml('task-pct--green')      // pct >= 100 -> green tier
        ->assertSeeHtml('--task-green')         // progress bar fill var
        // open task filtered out in done view; its indicator label 'ЯҲМ' never renders
        ->assertDontSee('ЯҲМ ўсиши');
});

test('multi-indicator card shows line counts and lists every line in the breakdown', function () {
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
        'lines_total' => 2, 'lines_done' => 1,
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
        ->set('deadline', 'all')
        ->set('status', 'all')
        ->assertSee('Батафсил')
        ->assertSee('Қамров')                              // scope/cadence caption
        ->assertSee('Даврийлик')
        ->assertSee('Ойлик')                               // monthly cadence
        ->assertSee('Ижрочи ҳудудлар')                     // districts group label
        ->assertSee('Шаҳрихон тумани')                     // district chip
        ->assertSee('Индикаторлар')                        // card face shows counts, not line-0 numbers
        ->assertSee('қайта тикланадиган ишлаб чиқариш')    // sub-metric (line_no 1) shown
        ->assertSee('млрд сўм')
        ->assertSeeHtml('tl-pill--green')                  // its 100% pill is the green tier
        ->assertSee('йирик корхона сони');                 // line 0 listed in the breakdown too
});

test('detail sub-metric pills use the per-line tier (red, amber, none)', function () {
    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '8', 'title' => 'Кўп тоифали кўрсаткичлар',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 10, 'headline_actual' => 4,
        'headline_pct' => 40, 'latest_period' => '2026-Q1',
    ]);
    $task->progress()->createMany([
        ['line_no' => 0, 'metric_label' => 'бош кўрсаткич', 'unit' => 'дона',
         'report_period' => '2026-Q1', 'period_type' => 'quarter', 'plan_value' => 10, 'actual_value' => 4, 'pct_of_plan' => 40],
        ['line_no' => 1, 'metric_label' => 'орқада кўрсаткич', 'unit' => 'дона',
         'report_period' => '2026-Q1', 'period_type' => 'quarter', 'plan_value' => 100, 'actual_value' => 30, 'pct_of_plan' => 30],
        ['line_no' => 2, 'metric_label' => 'ярим кўрсаткич', 'unit' => 'дона',
         'report_period' => '2026-Q1', 'period_type' => 'quarter', 'plan_value' => 100, 'actual_value' => 70, 'pct_of_plan' => 70],
        ['line_no' => 3, 'metric_label' => 'маълумотсиз кўрсаткич', 'unit' => 'дона',
         'report_period' => '2026-Q1', 'period_type' => 'quarter', 'plan_value' => 50, 'actual_value' => null, 'pct_of_plan' => null],
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('deadline', 'all')
        ->set('status', 'all')
        ->set('search', 'Кўп тоифали')
        ->assertSeeHtml('tl-pill--red')      // 30% sub-line
        ->assertSeeHtml('tl-pill--amber')    // 70% sub-line
        ->assertSeeHtml('tl-pill--none');    // null-pct sub-line
});

test('task without progress data renders without errors', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '3', 'title' => 'Маълумотсиз топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        // headline_plan set so the card passes hasPlan() filter; actual/pct/cadence null -> em-dash
        'headline_plan' => 6,
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('deadline', 'all')
        ->set('status', 'all')
        ->assertSee('Маълумотсиз топшириқ')
        ->assertSee('—'); // null plan/actual/pct render as em-dash
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
        ->set('deadline', 'all')
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
        ->set('deadline', 'all')
        ->set('status', 'all')
        ->assertSeeHtml('value="macro"')      // macro has a planned task -> offered
        ->assertDontSeeHtml('value="export"'); // export only has a no-plan task -> not offered
});

test('open task just under 100 percent shows 99 and amber, never 100 or green', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '11', 'title' => 'Деярли тайёр топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 100, 'headline_actual' => 99.6,
        'headline_pct' => 99.6, 'latest_period' => '2026-Q1',
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('deadline', 'all')
        ->set('status', 'all')
        ->set('search', 'Деярли')
        ->assertSee('99%')
        ->assertDontSee('100%')
        ->assertSeeHtml('task-pct--amber')
        ->assertDontSeeHtml('task-pct--green');
});

test('plan-only task shows an empty 0 percent track but keeps em-dash, not 0 percent', function () {
    // Plan loaded, actual not yet reported -> headline_pct null. Bar must still render
    // as an empty grey 0% track; the percent cell stays '—' (not '0%').
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '12', 'title' => 'Режа бор амал йўқ топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 50,
        'headline_actual' => null, 'headline_pct' => null, 'latest_period' => '2026-Q1',
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('deadline', 'all')
        ->set('status', 'all')
        ->set('search', 'Режа бор амал йўқ')
        ->assertSeeHtml('--w:0%')           // empty progress track is rendered
        ->assertSeeHtml('var(--grey)')      // neutral colour for the no-data tier
        ->assertSeeHtml('task-pct--none')   // percent cell is neutral
        ->assertSee('—');                   // percent shows em-dash, not "0%"
});

test('task planned only via a sub-metric line still shows an empty 0% track', function () {
    // Real Andijan shape: headline snapshot empty, plan lives on a sub-metric line.
    // hasPlan() keeps it in the list, so the card must still render a (grey, empty) track.
    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '13', 'title' => 'Фақат ост-сатр режа топшириғи',
        'module_code' => 'macro', 'indicator_code' => null, 'status' => 'open',
        'headline_plan' => null, 'headline_actual' => null, 'headline_pct' => null,
        'latest_period' => '2026-Q1',
    ]);
    $task->progress()->create([
        'line_no' => 1, 'metric_label' => 'ост-сатр кўрсаткич', 'unit' => 'дона',
        'report_period' => '2026-Q1', 'period_type' => 'quarter',
        'plan_value' => 12, 'actual_value' => null, 'pct_of_plan' => null,
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('deadline', 'all')
        ->set('status', 'all')
        ->set('search', 'Фақат ост-сатр')
        ->assertSee('Фақат ост-сатр режа топшириғи')  // visible (planned via sub-line)
        ->assertSeeHtml('--w:0%')                       // empty track now rendered for it
        ->assertSeeHtml('var(--grey)')                  // neutral colour
        ->assertSee('—');                               // empty headline cells
});
