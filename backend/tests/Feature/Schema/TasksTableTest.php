<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('tasks table exists with all expected columns', function () {
    expect(Schema::hasTable('tasks'))->toBeTrue();

    $expected = [
        'id', 'region_code', 'guarantee_letter_id', 'task_number',
        'title', 'deadline_text', 'period_code', 'executor_text',
        'kind', 'module_code', 'indicator_code', 'section_path',
        'section_label', 'source_paragraph_index', 'status',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('tasks', $column))
            ->toBeTrue("column {$column} missing on tasks table");
    }
});

test('tasks table enforces unique (region_code, task_number)', function () {
    \DB::table('regions')->insert([
        'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'sort_order' => 1, 'has_districts' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    \DB::table('tasks')->insert([
        'region_code' => 1703, 'task_number' => '1', 'title' => 'a',
        'executor_text' => 'x', 'kind' => 'kpi', 'section_path' => 'I',
        'section_label' => 'I', 'source_paragraph_index' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => \DB::table('tasks')->insert([
        'region_code' => 1703, 'task_number' => '1', 'title' => 'b',
        'executor_text' => 'x', 'kind' => 'kpi', 'section_path' => 'I',
        'section_label' => 'I', 'source_paragraph_index' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
