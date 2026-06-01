<?php
// backend/tests/Feature/Tasks/ImportTaskProgressTest.php

use App\Models\ImportRun;
use App\Models\Task;
use App\Models\TaskProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\TaskWorkbookFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(); // regions, districts, modules, indicators, reporting_years(2026)
    $this->fixture = TaskWorkbookFixture::make();
});

afterEach(function () {
    @unlink($this->fixture);
});

test('imports tasks, progress, districts and status for a period', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-Q1'])
        ->assertSuccessful();

    // Andijan task 1: plan only, no actual -> open
    $t1 = Task::where('region_code', 1703)->where('task_number', '1')->first();
    expect($t1)->not->toBeNull();
    expect($t1->title)->toBe('ЯҲМ ўсишини таъминлаш');
    expect($t1->kind)->toBe('kpi');
    expect($t1->cadence)->toBe('quarterly');
    expect($t1->module_code)->toBe('macro');
    expect($t1->indicator_code)->toBe('grp');
    expect((float) $t1->headline_plan)->toBeNumericallyClose(7.2);
    expect($t1->status)->toBe('open');
    expect($t1->latest_period)->toBe('2026-Q1');

    // Andijan task 2: multi-metric, district executor, headline pct 50 -> open
    $t2 = Task::where('region_code', 1703)->where('task_number', '2')->first();
    expect($t2->kind)->toBe('measure');
    expect($t2->status)->toBe('open');
    expect((float) $t2->headline_pct)->toBeNumericallyClose(50);
    expect($t2->progress()->where('report_period', '2026-Q1')->count())->toBe(2);
    expect($t2->districts->pluck('name_full')->all())->toContain('Шахрихон тумани');

    // Andijan task 3: derived pct 120 -> done
    $t3 = Task::where('region_code', 1703)->where('task_number', '3')->first();
    expect($t3->status)->toBe('done');
    expect((float) $t3->headline_pct)->toBeNumericallyClose(120);
    expect((float) $t3->headline_actual)->toBeNumericallyClose(12);

    // Qoraqalpoq task 1 also imported (region-level executor, no districts)
    $k1 = Task::where('region_code', 1735)->where('task_number', '1')->first();
    expect($k1)->not->toBeNull();
    expect($k1->districts)->toHaveCount(0);

    // ImportRun recorded per region
    expect(ImportRun::where('region_code', 1703)->where('year', 2026)->exists())->toBeTrue();
    expect(ImportRun::where('region_code', 1735)->where('year', 2026)->exists())->toBeTrue();
});

test('re-importing the same period is idempotent', function () {
    $args = ['--file' => $this->fixture, '--period' => '2026-Q1'];
    $this->artisan('import:task-progress', $args)->assertSuccessful();
    $this->artisan('import:task-progress', $args)->assertSuccessful();

    $t2 = Task::where('region_code', 1703)->where('task_number', '2')->first();
    expect($t2->progress()->where('report_period', '2026-Q1')->count())->toBe(2); // not 4
    expect(Task::where('region_code', 1703)->count())->toBe(3); // no duplicate tasks
});

test('a later period appends history without losing the old one', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-Q1'])->assertSuccessful();
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-04'])->assertSuccessful();

    $t2 = Task::where('region_code', 1703)->where('task_number', '2')->first();
    expect($t2->progress()->where('report_period', '2026-Q1')->count())->toBe(2);
    expect($t2->progress()->where('report_period', '2026-04')->count())->toBe(2);
    expect($t2->latest_period)->toBe('2026-04');
});

test('region filter imports only that region', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-Q1', '--region' => 'andijan'])
        ->assertSuccessful();

    expect(Task::where('region_code', 1703)->count())->toBe(3);
    expect(Task::where('region_code', 1735)->count())->toBe(0);
});

test('rejects invalid or missing period', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture])
        ->assertFailed();
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => 'март'])
        ->assertFailed();
});

test('dry run writes nothing', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-Q1', '--dry-run' => true])
        ->assertSuccessful();
    expect(Task::count())->toBe(0);
    expect(TaskProgress::count())->toBe(0);
    expect(ImportRun::count())->toBe(0);
});

test('rejects missing file and unknown region cleanly', function () {
    $this->artisan('import:task-progress', ['--file' => 'C:\\nonexistent\\nope.xlsx', '--period' => '2026-Q1'])
        ->assertFailed();
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-Q1', '--region' => 'atlantis'])
        ->assertFailed();
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-Q1', '--region' => '9999'])
        ->assertFailed();
});

test('records per-region rows_promoted on import runs', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-Q1'])
        ->assertSuccessful();

    $andijanRun = ImportRun::where('region_code', 1703)->where('year', 2026)->latest('id')->first();
    $karakalpakRun = ImportRun::where('region_code', 1735)->where('year', 2026)->latest('id')->first();

    // Andijan: task1 (1 line) + task2 (2 lines) + task3 (1 line) = 4 progress rows.
    expect($andijanRun->rows_promoted)->toBe(4);
    // Qoraqalpoq: task1 only (1 line).
    expect($karakalpakRun->rows_promoted)->toBe(1);
});

test('importing an older period does not regress the headline snapshot', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-04'])->assertSuccessful();
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-Q1'])->assertSuccessful();

    $t2 = Task::where('region_code', 1703)->where('task_number', '2')->first();
    // Q1 (ends March) is older than April; headline must still point at 2026-04.
    expect($t2->latest_period)->toBe('2026-04');
    // But the older period's history rows are still stored.
    expect($t2->progress()->where('report_period', '2026-Q1')->count())->toBe(2);
});
