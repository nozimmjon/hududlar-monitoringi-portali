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

    Task::create(['region_code'=>1703,'task_number'=>'1','title'=>'macro one','executor_text'=>'хокимлик','kind'=>'kpi','module_code'=>'macro','section_path'=>'I','section_label'=>'I','source_paragraph_index'=>1]);
    Task::create(['region_code'=>1703,'task_number'=>'2','title'=>'export two','executor_text'=>'хокимлик','kind'=>'measure','module_code'=>'export','section_path'=>'VI','section_label'=>'VI','source_paragraph_index'=>2]);
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

test('selectModule resets indicator to all', function () {
    Livewire::test(TasksBoard::class)
        ->set('indicator', 'industry')
        ->call('selectModule', 'export')
        ->assertSet('indicator', 'all');
});
