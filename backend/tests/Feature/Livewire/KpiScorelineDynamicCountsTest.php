<?php

use App\Livewire\Dashboard\KpiScoreline;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'sort_order' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('regions')->insert([
        'code' => 1706, 'name_short' => 'Бухоро', 'name_full' => 'Бухоро вилояти',
        'sort_order' => 3, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        ['code' => 'macro',      'label' => 'Макро',   'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'budget',     'label' => 'Бюджет',  'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'employment', 'label' => 'Бандлик', 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $baseIndicator = [
        'scope'          => 'region',
        'default_unit'   => '%',
        'lower_is_better' => false,
        'has_growth_pct'  => false,
        'has_pct_of_plan' => false,
        'has_sentinel'    => false,
        'sort_order'      => 0,
        'created_at'      => now(),
        'updated_at'      => now(),
    ];
    DB::table('indicators')->insert([
        array_merge($baseIndicator, ['code' => 'grp',      'label_short' => 'ЯҲМ',         'label_full' => 'Яхлит ҳудудий маҳсулот', 'module_code' => 'macro',      'sort_order' => 1]),
        array_merge($baseIndicator, ['code' => 'industry', 'label_short' => 'Саноат',      'label_full' => 'Саноат',                  'module_code' => 'macro',      'sort_order' => 2]),
        array_merge($baseIndicator, ['code' => 'budget',   'label_short' => 'Бюджет',      'label_full' => 'Бюджет тушумлари',        'module_code' => 'budget',     'sort_order' => 1]),
        array_merge($baseIndicator, ['code' => 'jobs',     'label_short' => 'Иш ўринлари', 'label_full' => 'Иш ўринлари',            'module_code' => 'employment', 'sort_order' => 1, 'default_unit' => 'та']),
    ]);

    Task::query()->delete();
});

test('counts macro+grp tasks when module has indicator-tagged tasks', function () {
    // 5 grp open tasks for andijan macro
    Task::factory()->count(5)->create([
        'region_code'    => 1703,
        'module_code'    => 'macro',
        'indicator_code' => 'grp',
        'status'         => 'open',
    ]);
    // 2 grp done tasks
    Task::factory()->count(2)->create([
        'region_code'    => 1703,
        'module_code'    => 'macro',
        'indicator_code' => 'grp',
        'status'         => 'done',
    ]);
    // 3 industry noise (different indicator_code — must be excluded)
    Task::factory()->count(3)->create([
        'region_code'    => 1703,
        'module_code'    => 'macro',
        'indicator_code' => 'industry',
        'status'         => 'open',
    ]);

    Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp'])
        ->assertViewHas('total', 7)
        ->assertViewHas('done', 2)
        ->assertViewHas('open', 5)
        ->assertViewHas('pct', 29);
});

test('counts module-only tasks when module has no indicator_code', function () {
    // 3 budget tasks with indicator_code = null (budget is not in MODULES_WITH_INDICATOR_TASKS)
    Task::factory()->count(3)->create([
        'region_code'    => 1703,
        'module_code'    => 'budget',
        'indicator_code' => null,
        'status'         => 'open',
    ]);

    Livewire::test(KpiScoreline::class, ['module' => 'budget', 'kpi' => 'budget'])
        ->assertViewHas('total', 3)
        ->assertViewHas('done', 0)
        ->assertViewHas('pct', 0);
});

test('renders zero counts when module has no tasks', function () {
    Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp'])
        ->assertViewHas('total', 0)
        ->assertViewHas('done', 0)
        ->assertViewHas('open', 0)
        ->assertViewHas('pct', 0);
});

test('ignores tasks from other regions', function () {
    // 4 bukhara tasks — must not appear for andijan
    Task::factory()->count(4)->create([
        'region_code'    => 1706,
        'module_code'    => 'macro',
        'indicator_code' => 'grp',
        'status'         => 'open',
    ]);

    Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp'])
        ->assertViewHas('total', 0);
});

test('falls back to kpi code when Indicator record is missing', function () {
    DB::table('indicators')->where('code', 'grp')->delete();

    Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp'])
        ->assertViewHas('scope', 'grpга оид чора-тадбирлар');
});
