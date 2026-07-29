<?php

use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\SectorTaskProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('sector task tables exist with expected columns', function () {
    foreach (['sector_id', 'task_no', 'title', 'status', 'lines_total', 'lines_done',
              'latest_period', 'headline_unit', 'headline_plan', 'headline_actual', 'headline_pct'] as $col) {
        expect(Schema::hasColumn('sector_tasks', $col))->toBeTrue("sector_tasks missing {$col}");
    }
    foreach (['sector_task_id', 'line_no', 'metric_label', 'unit', 'deadline_text', 'deadline_code',
              'report_period', 'period_type', 'plan_value', 'actual_value', 'pct_of_plan', 'reported_at'] as $col) {
        expect(Schema::hasColumn('sector_task_progress', $col))->toBeTrue("sector_task_progress missing {$col}");
    }
});

test('unique keys hold: task per sector, line per period', function () {
    $this->seed(Database\Seeders\SectorSeeder::class);
    $sector = Sector::where('code', 'nkmk')->first();

    $task = SectorTask::create(['sector_id' => $sector->id, 'task_no' => 1, 'title' => 'Т', 'status' => 'in_progress']);
    expect(fn () => SectorTask::create(['sector_id' => $sector->id, 'task_no' => 1, 'title' => 'Д', 'status' => 'open']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

test('progress line unique per period, cascade delete with task', function () {
    $this->seed(Database\Seeders\SectorSeeder::class);
    $sector = Sector::where('code', 'nkmk')->first();
    $task = SectorTask::create(['sector_id' => $sector->id, 'task_no' => 1, 'title' => 'Т', 'status' => 'in_progress']);

    SectorTaskProgress::create([
        'sector_task_id' => $task->id, 'line_no' => 1, 'metric_label' => 'Олтин', 'unit' => 'тонна',
        'deadline_text' => '2026 йил якуни', 'deadline_code' => 'year',
        'report_period' => '2026-H2', 'period_type' => 'half', 'plan_value' => 98.5,
    ]);
    // Wrapped in DB::transaction() so Postgres uses a SAVEPOINT here (RefreshDatabase already
    // has an outer transaction open); otherwise the expected QueryException aborts the whole
    // outer transaction and the assertions below would fail with "current transaction is aborted".
    expect(fn () => \DB::transaction(fn () => SectorTaskProgress::create([
        'sector_task_id' => $task->id, 'line_no' => 1, 'metric_label' => 'Олтин', 'unit' => 'тонна',
        'deadline_text' => '2026 йил якуни', 'deadline_code' => 'year',
        'report_period' => '2026-H2', 'period_type' => 'half', 'plan_value' => 99,
    ])))->toThrow(Illuminate\Database\QueryException::class);

    $task->delete();
    expect(SectorTaskProgress::count())->toBe(0);
});
