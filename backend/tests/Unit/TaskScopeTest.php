<?php

use App\Models\Task;
use App\Models\District;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \DB::table('regions')->insert([
        ['code' => 1703, 'name_short' => 'A', 'name_full' => 'Andijan', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 1706, 'name_short' => 'B', 'name_full' => 'Bukhara', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);
    \DB::table('modules')->insert([
        ['code' => 'macro',    'label' => 'M', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'export',   'label' => 'E', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $regionId = \DB::table('regions')->where('code', 1703)->value('id');
    $this->districtId = \DB::table('districts')->insertGetId([
        'region_id' => $regionId, 'region_code' => 1703,
        'code' => 1703209, 'name_short' => 'Бўстон т.',
        'name_full' => 'Бўстон тумани', 'kind' => 'district', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Task::create([
        'region_code' => 1703, 'task_number' => '1',
        'title' => 'macro task', 'executor_text' => 'хокимлик',
        'kind' => 'kpi', 'module_code' => 'macro',
        'period_code' => 'h1', 'section_path' => 'I.1.1',
        'section_label' => '1.1', 'source_paragraph_index' => 1,
    ]);
    $exportTask = Task::create([
        'region_code' => 1703, 'task_number' => '2',
        'title' => 'export task', 'executor_text' => 'Бўстон',
        'kind' => 'measure', 'module_code' => 'export',
        'period_code' => 'year', 'section_path' => 'VI',
        'section_label' => 'VI', 'source_paragraph_index' => 2,
    ]);
    Task::create([
        'region_code' => 1706, 'task_number' => '1',
        'title' => 'other region', 'executor_text' => 'x',
        'kind' => 'kpi', 'module_code' => 'macro',
        'section_path' => 'I', 'section_label' => 'I',
        'source_paragraph_index' => 1,
    ]);

    $exportTask->districts()->attach($this->districtId);
});

test('forRegion narrows to one region', function () {
    expect(Task::forRegion(1703)->count())->toBe(2);
    expect(Task::forRegion(1706)->count())->toBe(1);
});

test('forModule narrows to one module', function () {
    expect(Task::forRegion(1703)->forModule('macro')->count())->toBe(1);
});

test('ofKind filters by kind', function () {
    expect(Task::forRegion(1703)->ofKind('measure')->count())->toBe(1);
});

test('forPeriod filters by period_code', function () {
    expect(Task::forRegion(1703)->forPeriod('year')->count())->toBe(1);
});

test('forDistrict joins through pivot', function () {
    expect(Task::forRegion(1703)->forDistrict($this->districtId)->count())->toBe(1);
});

test('search matches title (ILIKE)', function () {
    expect(Task::forRegion(1703)->search('export')->count())->toBe(1);
});

test('District has tasks() relationship', function () {
    $district = District::find($this->districtId);
    expect($district->tasks()->count())->toBe(1);
});

test('hasPlan keeps tasks with a plan on the headline OR any sub-metric line', function () {
    // beforeEach created 2 region-1703 tasks: no headline_plan, no progress -> excluded.
    expect(Task::forRegion(1703)->hasPlan()->count())->toBe(0);

    // (a) plan on the headline snapshot
    Task::create([
        'region_code' => 1703, 'task_number' => '9',
        'title' => 'headline-planned task', 'executor_text' => 'хокимлик',
        'kind' => 'kpi', 'module_code' => 'macro',
        'section_path' => 'I', 'section_label' => 'I',
        'source_paragraph_index' => 9, 'headline_plan' => 6,
    ]);

    // (b) headline empty but a sub-metric line carries the plan — the real Andijan
    //     shape: line 0 is a category header with no plan, line 1 has the target.
    $subPlanned = Task::create([
        'region_code' => 1703, 'task_number' => '10',
        'title' => 'sub-metric-planned task', 'executor_text' => 'хокимлик',
        'kind' => 'kpi', 'module_code' => 'macro',
        'section_path' => 'I', 'section_label' => 'I',
        'source_paragraph_index' => 10, 'headline_plan' => null,
    ]);
    $subPlanned->progress()->createMany([
        ['line_no' => 0, 'metric_label' => 'категория', 'report_period' => '2026-Q1',
         'period_type' => 'quarter', 'plan_value' => null, 'actual_value' => null],
        ['line_no' => 1, 'metric_label' => 'кўрсаткич', 'report_period' => '2026-Q1',
         'period_type' => 'quarter', 'plan_value' => 5, 'actual_value' => null],
    ]);

    expect(Task::forRegion(1703)->count())->toBe(4);            // all 4 in region 1703
    expect(Task::forRegion(1703)->hasPlan()->count())->toBe(2); // both planned; the 2 plan-less stay out
});
