<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('task_districts pivot table exists with task_id and district_id', function () {
    expect(Schema::hasTable('task_districts'))->toBeTrue();
    expect(Schema::hasColumns('task_districts', ['task_id', 'district_id']))->toBeTrue();
});

test('task_districts has composite primary key', function () {
    \DB::table('regions')->insert([
        'code' => 'andijan', 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = \DB::table('regions')->where('code', 'andijan')->value('id');

    $districtId = \DB::table('districts')->insertGetId([
        'region_id' => $regionId, 'region_code' => 'andijan',
        'code' => 'andijan_city', 'name_short' => 'Андижон ш.',
        'name_full' => 'Андижон шаҳри', 'kind' => 'city', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $taskId = \DB::table('tasks')->insertGetId([
        'region_code' => 'andijan', 'task_number' => '1', 'title' => 'x',
        'executor_text' => 'x', 'kind' => 'kpi', 'section_path' => 'I',
        'section_label' => 'I', 'source_paragraph_index' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    \DB::table('task_districts')->insert(['task_id' => $taskId, 'district_id' => $districtId]);

    expect(fn () => \DB::table('task_districts')->insert([
        'task_id' => $taskId, 'district_id' => $districtId,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
