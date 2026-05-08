<?php

use App\Models\Task;
use App\Models\District;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \DB::table('regions')->insert([
        ['code' => 'andijan',  'name_short' => 'A', 'name_full' => 'Andijan', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'bukhara',  'name_short' => 'B', 'name_full' => 'Bukhara', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);
    \DB::table('modules')->insert([
        ['code' => 'macro',    'label' => 'M', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'export',   'label' => 'E', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $regionId = \DB::table('regions')->where('code', 'andijan')->value('id');
    $this->districtId = \DB::table('districts')->insertGetId([
        'region_id' => $regionId, 'region_code' => 'andijan',
        'code' => 'boston', 'name_short' => 'Бўстон т.',
        'name_full' => 'Бўстон тумани', 'kind' => 'district', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Task::create([
        'region_code' => 'andijan', 'task_number' => '1',
        'title' => 'macro task', 'executor_text' => 'хокимлик',
        'kind' => 'kpi', 'module_code' => 'macro',
        'period_code' => 'h1', 'section_path' => 'I.1.1',
        'section_label' => '1.1', 'source_paragraph_index' => 1,
    ]);
    $exportTask = Task::create([
        'region_code' => 'andijan', 'task_number' => '2',
        'title' => 'export task', 'executor_text' => 'Бўстон',
        'kind' => 'measure', 'module_code' => 'export',
        'period_code' => 'year', 'section_path' => 'VI',
        'section_label' => 'VI', 'source_paragraph_index' => 2,
    ]);
    Task::create([
        'region_code' => 'bukhara', 'task_number' => '1',
        'title' => 'other region', 'executor_text' => 'x',
        'kind' => 'kpi', 'module_code' => 'macro',
        'section_path' => 'I', 'section_label' => 'I',
        'source_paragraph_index' => 1,
    ]);

    $exportTask->districts()->attach($this->districtId);
});

test('forRegion narrows to one region', function () {
    expect(Task::forRegion('andijan')->count())->toBe(2);
    expect(Task::forRegion('bukhara')->count())->toBe(1);
});

test('forModule narrows to one module', function () {
    expect(Task::forRegion('andijan')->forModule('macro')->count())->toBe(1);
});

test('ofKind filters by kind', function () {
    expect(Task::forRegion('andijan')->ofKind('measure')->count())->toBe(1);
});

test('forPeriod filters by period_code', function () {
    expect(Task::forRegion('andijan')->forPeriod('year')->count())->toBe(1);
});

test('forDistrict joins through pivot', function () {
    expect(Task::forRegion('andijan')->forDistrict($this->districtId)->count())->toBe(1);
});

test('search matches title (ILIKE)', function () {
    expect(Task::forRegion('andijan')->search('export')->count())->toBe(1);
});

test('District has tasks() relationship', function () {
    $district = District::find($this->districtId);
    expect($district->tasks()->count())->toBe(1);
});
