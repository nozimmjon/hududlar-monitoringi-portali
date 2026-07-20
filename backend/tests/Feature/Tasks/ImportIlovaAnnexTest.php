<?php
// backend/tests/Feature/Tasks/ImportIlovaAnnexTest.php

use App\Models\Task;
use App\Models\TaskProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\IlovaAnnexFixture;

uses(RefreshDatabase::class);

function annexTask(int $region, string $num, array $attrs = []): Task
{
    return Task::create(array_merge([
        'region_code'            => $region,
        'task_number'            => $num,
        'title'                  => "Task {$num}",
        'executor_text'          => 'Ҳокимлик',
        'kind'                   => 'kpi',
        'section_path'           => '1',
        'section_label'          => 'Бўлим',
        'source_paragraph_index' => 1,
        'period_code'            => 'h1',
        'deadline_text'          => '2026 йил I ярим йиллик',
    ], $attrs));
}

function annexRow(Task $t, int $line, string $period, ?float $plan, ?float $actual, array $attrs = []): TaskProgress
{
    return TaskProgress::create(array_merge([
        'task_id'       => $t->id,
        'line_no'       => $line,
        'report_period' => $period,
        'period_type'   => str_contains($period, 'H') ? 'half' : 'quarter',
        'plan_value'    => $plan,
        'actual_value'  => $actual,
    ], $attrs));
}

beforeEach(function () {
    $this->seed();
    $this->fixture = IlovaAnnexFixture::make();

    // Task 10 — Andijan H1 row awaiting the district count. No task for Тошкент ш.
    $t = annexTask(1703, '10', ['latest_period' => '2026-H1', 'headline_plan' => 2, 'headline_unit' => 'фоиз']);
    annexRow($t, 0, '2026-H1', 2, null, ['unit' => 'фоиз', 'metric_label' => 'Паст ўсиш туманлар']);

    // Task 40 — Andijan plans, no actuals; Самарқанд has a conflicting line-0 actual.
    $t = annexTask(1703, '40', ['latest_period' => '2026-H1']);
    foreach ([4, 6, 29.7, 77, 99, 500] as $line => $plan) {
        annexRow($t, $line, '2026-H1', $plan, null);
    }
    $t = annexTask(1718, '40', ['latest_period' => '2026-H1', 'headline_plan' => 10, 'headline_actual' => 5]);
    annexRow($t, 0, '2026-H1', 10, 5);

    // Task 46 — Andijan two lines empty; Жиззах has blank амалда in the file.
    $t = annexTask(1703, '46', ['latest_period' => '2026-H1']);
    annexRow($t, 0, '2026-H1', 8.9, null);
    annexRow($t, 1, '2026-H1', 3, null);
    $t = annexTask(1708, '46', ['latest_period' => '2026-H1']);
    annexRow($t, 0, '2026-H1', 18.2, null);

    // Task 48 — Andijan zeros incoming; Сирдарё gets a 100% line 0.
    $t = annexTask(1703, '48', ['latest_period' => '2026-H1']);
    foreach ([5, 12.1, 47, 4] as $line => $plan) {
        annexRow($t, $line, '2026-H1', $plan, null);
    }
    $t = annexTask(1724, '48', ['latest_period' => '2026-H1']);
    annexRow($t, 0, '2026-H1', 4, null);

    // Task 111 — Andijan headline empty (sub-line already imported); Фарғона already filled.
    $t = annexTask(1703, '111', ['latest_period' => '2026-H1']);
    annexRow($t, 0, '2026-H1', null, null, ['metric_label' => 'Яширин иқтисодиёт жами', 'unit' => 'млрд сўм']);
    annexRow($t, 1, '2026-H1', 22, 27, ['pct_of_plan' => 122.7273]);
    $t = annexTask(1730, '111', ['latest_period' => '2026-H1', 'headline_plan' => 207, 'headline_actual' => 210.4]);
    annexRow($t, 0, '2026-H1', 207, 210.4, ['pct_of_plan' => 101.64]);

    // Task 133 — Andijan has only a Q1 row (no plan); ҚР has an H1 row with plan; Сирдарё too.
    $t = annexTask(1703, '133', ['latest_period' => '2026-Q1']);
    annexRow($t, 0, '2026-Q1', null, null, ['metric_label' => 'Фойдаланишга топшириладиган объектлар сони', 'unit' => 'дона']);
    $t = annexTask(1735, '133', ['latest_period' => '2026-H1', 'headline_plan' => 9]);
    annexRow($t, 0, '2026-H1', 9, null);
    $t = annexTask(1724, '133', ['latest_period' => '2026-H1', 'headline_plan' => 16]);
    annexRow($t, 0, '2026-H1', 16, null);
    // Сурхондарё present in the file but has no task row -> reported, skipped.
});

afterEach(function () {
    @unlink($this->fixture);
});

test('fills missing actuals, keeps existing values, creates rows, advances headlines', function () {
    $this->artisan('import:ilova', ['--file' => $this->fixture, '--period' => '2026-H1'])
        ->assertSuccessful();

    // Task 10: Andijan = 1 district above average of 2 planned -> 50%, open.
    $t = Task::where('region_code', 1703)->where('task_number', '10')->first();
    $row = $t->progress()->where('report_period', '2026-H1')->where('line_no', 0)->first();
    expect((float) $row->actual_value)->toBeNumericallyClose(1);
    expect((float) $row->pct_of_plan)->toBeNumericallyClose(50);
    expect((float) $t->headline_actual)->toBeNumericallyClose(1);
    expect($t->status)->toBe('open');

    // Task 40 Andijan: explicit zeros written on all six lines — data stored,
    // but zero progress means the task reads Бажарилмоқда.
    $t = Task::where('region_code', 1703)->where('task_number', '40')->first();
    $rows = $t->progress()->where('report_period', '2026-H1')->orderBy('line_no')->get();
    expect($rows)->toHaveCount(6);
    foreach ($rows as $row) {
        expect((float) $row->actual_value)->toBeNumericallyClose(0);
        expect((float) $row->pct_of_plan)->toBeNumericallyClose(0);
    }
    expect($t->status)->toBe('in_progress');

    // Task 40 Самарқанд: DB actual 5 conflicts with the file's 6 -> kept.
    $t = Task::where('region_code', 1718)->where('task_number', '40')->first();
    $row = $t->progress()->where('report_period', '2026-H1')->where('line_no', 0)->first();
    expect((float) $row->actual_value)->toBeNumericallyClose(5);
    // Lines 1..5 did not exist -> created with the file's режа + факт.
    $line3 = $t->progress()->where('report_period', '2026-H1')->where('line_no', 3)->first();
    expect((float) $line3->plan_value)->toBeNumericallyClose(135);
    expect((float) $line3->actual_value)->toBeNumericallyClose(61);

    // Task 46: Andijan explicit zeros; Жиззах blank stays empty.
    $t = Task::where('region_code', 1703)->where('task_number', '46')->first();
    expect((float) $t->progress()->where('report_period', '2026-H1')->where('line_no', 0)->value('actual_value'))
        ->toBeNumericallyClose(0);
    $t = Task::where('region_code', 1708)->where('task_number', '46')->first();
    expect($t->progress()->where('report_period', '2026-H1')->where('line_no', 0)->value('actual_value'))->toBeNull();

    // Task 48 Сирдарё: line 0 hits 100%, but the file also creates lines 1-3 and
    // line 2 is at ~51% -> weakest link keeps the task open (3 of 4 lines done).
    $t = Task::where('region_code', 1724)->where('task_number', '48')->first();
    expect((float) $t->headline_pct)->toBeNumericallyClose(100);
    expect($t->lines_total)->toBe(4);
    expect($t->lines_done)->toBe(3);
    expect($t->status)->toBe('open');

    // Task 111 Andijan: headline plan+actual from 15б -> 100% done; sub-line untouched.
    $t = Task::where('region_code', 1703)->where('task_number', '111')->first();
    $row = $t->progress()->where('report_period', '2026-H1')->where('line_no', 0)->first();
    expect((float) $row->plan_value)->toBeNumericallyClose(117);
    expect((float) $row->actual_value)->toBeNumericallyClose(117);
    expect($t->status)->toBe('done');
    expect((float) $t->progress()->where('report_period', '2026-H1')->where('line_no', 1)->value('actual_value'))
        ->toBeNumericallyClose(27);

    // Task 111 Фарғона: file equals DB -> untouched.
    $t = Task::where('region_code', 1730)->where('task_number', '111')->first();
    expect((float) $t->headline_actual)->toBeNumericallyClose(210.4);

    // Task 133 Andijan: H1 row created from the Q1 template, actual without plan, headline advanced.
    $t = Task::where('region_code', 1703)->where('task_number', '133')->first();
    $row = $t->progress()->where('report_period', '2026-H1')->where('line_no', 0)->first();
    expect($row)->not->toBeNull();
    expect($row->metric_label)->toBe('Фойдаланишга топшириладиган объектлар сони');
    expect($row->unit)->toBe('дона');
    expect((float) $row->actual_value)->toBeNumericallyClose(10);
    expect($row->plan_value)->toBeNull();
    expect($row->pct_of_plan)->toBeNull();
    expect($t->latest_period)->toBe('2026-H1');
    expect((float) $t->headline_actual)->toBeNumericallyClose(10);
    expect($t->status)->toBe('open');

    // Task 133 ҚР (name-matched despite 17-илова's shuffled order): 9/9 -> done.
    $t = Task::where('region_code', 1735)->where('task_number', '133')->first();
    expect((float) $t->headline_pct)->toBeNumericallyClose(100);
    expect($t->status)->toBe('done');

    // Task 133 Сирдарё: 6 of 16 -> 37.5%.
    $t = Task::where('region_code', 1724)->where('task_number', '133')->first();
    expect((float) $t->headline_pct)->toBeNumericallyClose(37.5);
});

test('is idempotent — second run changes nothing', function () {
    $this->artisan('import:ilova', ['--file' => $this->fixture, '--period' => '2026-H1'])->assertSuccessful();
    $before = TaskProgress::orderBy('id')->get()->map->only(['id', 'plan_value', 'actual_value', 'pct_of_plan'])->all();

    $this->artisan('import:ilova', ['--file' => $this->fixture, '--period' => '2026-H1'])->assertSuccessful();
    $after = TaskProgress::orderBy('id')->get()->map->only(['id', 'plan_value', 'actual_value', 'pct_of_plan'])->all();

    expect($after)->toBe($before);
});

test('dry-run writes nothing', function () {
    $this->artisan('import:ilova', ['--file' => $this->fixture, '--period' => '2026-H1', '--dry-run' => true])
        ->assertSuccessful();

    $t = Task::where('region_code', 1703)->where('task_number', '40')->first();
    expect($t->progress()->where('report_period', '2026-H1')->whereNotNull('actual_value')->count())->toBe(0);
    expect(Task::where('region_code', 1703)->where('task_number', '133')->value('latest_period'))->toBe('2026-Q1');
});

test('refuses a non-half-year period', function () {
    $this->artisan('import:ilova', ['--file' => $this->fixture, '--period' => '2026-Q2'])
        ->assertFailed();
});

test('aborts when a guarded header column moved', function () {
    $broken = IlovaAnnexFixture::make(breakHeader: true);
    $this->artisan('import:ilova', ['--file' => $broken, '--period' => '2026-H1'])->assertFailed();
    expect(TaskProgress::whereNotNull('actual_value')->where('report_period', '2026-H1')->where('line_no', 0)
        ->whereHas('task', fn ($q) => $q->where('task_number', '10'))->count())->toBe(0);
    @unlink($broken);
});

test('aborts on an unrecognised region name', function () {
    $broken = IlovaAnnexFixture::make(unknownRegion: true);
    $this->artisan('import:ilova', ['--file' => $broken, '--period' => '2026-H1'])->assertFailed();
    @unlink($broken);
});
