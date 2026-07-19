<?php

use App\Livewire\TasksBoard;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'code' => 1703, 'name_short' => 'A', 'name_full' => 'Andijan',
        'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        ['code' => 'macro',  'label' => 'M', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'export', 'label' => 'E', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    Task::create(['region_code'=>1703,'task_number'=>'1','title'=>'macro one','executor_text'=>'хокимлик','kind'=>'kpi','module_code'=>'macro','section_path'=>'I','section_label'=>'I','source_paragraph_index'=>1,'headline_plan'=>100]);
    Task::create(['region_code'=>1703,'task_number'=>'2','title'=>'export two','executor_text'=>'хокимлик','kind'=>'measure','module_code'=>'export','section_path'=>'VI','section_label'=>'VI','source_paragraph_index'=>2,'headline_plan'=>100]);
});

test('GET /tasks returns 200 and contains task-card markup', function () {
    $response = $this->get('/tasks');

    $response->assertOk();
    $response->assertSee('task-filter', false);
    $response->assertSee('task-stat-stack', false);
    $response->assertSee('task-card', false);
    $response->assertSee('macro one', false);
    $response->assertSee('export two', false);
});

test('selectModule filter narrows the task list', function () {
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->call('selectModule', 'macro')
        ->assertSee('macro one')
        ->assertDontSee('export two');
});

test('search filters by title via ILIKE', function () {
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->set('search', 'export')
        ->assertSee('export two')
        ->assertDontSee('macro one');
});

test('board shows all tasks including done by default', function () {
    Task::create(['region_code'=>1703,'task_number'=>'3','title'=>'finished three','executor_text'=>'хокимлик','kind'=>'kpi','module_code'=>'macro','section_path'=>'I','section_label'=>'I','source_paragraph_index'=>3,'headline_plan'=>100,'status'=>'done']);

    Livewire::test(TasksBoard::class)
        ->assertSet('status', 'all')
        ->assertSee('finished three')
        ->assertSee('macro one');
});

test('board has no KPI indicator filter', function () {
    Livewire::test(TasksBoard::class)
        ->assertDontSee('KPI / топшириқ йўналиши');
});
