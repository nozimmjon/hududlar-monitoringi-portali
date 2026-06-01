<?php
// backend/tests/Feature/Schema/TaskProgressTableTest.php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('tasks table gains progress + metadata columns', function () {
    foreach ([
        'cadence', 'data_source', 'report_schedule_text', 'integration_status',
        'mechanism_text', 'latest_period', 'headline_unit', 'headline_plan',
        'headline_actual', 'headline_pct',
    ] as $col) {
        expect(Schema::hasColumn('tasks', $col))->toBeTrue();
    }
});

test('task_progress table exists with expected columns', function () {
    expect(Schema::hasTable('task_progress'))->toBeTrue();
    foreach ([
        'id', 'task_id', 'line_no', 'metric_label', 'unit', 'report_period',
        'period_type', 'plan_value', 'actual_value', 'pct_of_plan',
        'reported_at', 'import_run_id', 'created_at', 'updated_at',
    ] as $col) {
        expect(Schema::hasColumn('task_progress', $col))->toBeTrue();
    }
});
