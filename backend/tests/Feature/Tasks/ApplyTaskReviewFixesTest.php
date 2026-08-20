<?php

use App\Models\Task;
use App\Models\TaskProgress;
use App\Support\TaskReviewFixes;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

function makeReviewTask(array $attrs = []): Task
{
    return Task::create(array_merge([
        'region_code'   => 1703,
        'task_number'   => '999',
        'title'         => 'Оддий топшириқ',
        'executor_text' => 'Вилоят ҳокимлиги',
        'section_path'  => 'I',
        'section_label' => 'I',
        'source_paragraph_index' => 1,
        'kind'          => 'kpi',
        'status'        => 'in_progress',
        'lines_total'   => 1,
        'lines_done'    => 0,
        'latest_period' => '2026-H1',
    ], $attrs));
}

test('spelling fixes rewrite task titles and metric labels', function () {
    $task = makeReviewTask([
        'title' => 'Жорий йилнинг биричи ярим йиллигида саноат маҳсулотларни ишлаб чиқариш хажми',
    ]);
    TaskProgress::create([
        'task_id' => $task->id, 'line_no' => 0, 'report_period' => '2026-H1', 'period_type' => 'half',
        'metric_label' => 'Тадбикорлик субъектлари учун инфртузилма ярмакалар', 'unit' => 'млн долл',
    ]);

    $counts = TaskReviewFixes::apply();

    expect($counts['text'])->toBeGreaterThan(0);
    expect($task->fresh()->title)
        ->toBe('Жорий йилнинг биринчи ярим йиллигида саноат маҳсулотларини ишлаб чиқариш ҳажми');
    $line = TaskProgress::where('task_id', $task->id)->first();
    expect($line->metric_label)->toBe('Тадбиркорлик субъектлари учун инфратузилма ярмаркалар');
    expect($line->unit)->toBe('млн доллар');
});

test('the Andijan export plan is lifted to the letter target only from the known-bad value', function () {
    $task = makeReviewTask(['task_number' => '295', 'headline_plan' => 967]);
    TaskProgress::create([
        'task_id' => $task->id, 'line_no' => 0, 'report_period' => '2026-H1', 'period_type' => 'half',
        'metric_label' => 'Шундан, маҳсулотлар (маҳаллий корхоналар) экспорти', 'unit' => 'млн доллар',
        'plan_value' => 967,
    ]);

    TaskReviewFixes::apply();
    $line = TaskProgress::where('task_id', $task->id)->first();
    expect((float) $line->plan_value)->toBe(1000.0);
    expect((float) $task->fresh()->headline_plan)->toBe(1000.0);

    // A partner file that already carries a different (corrected) plan is untouched.
    $line->update(['plan_value' => 1050]);
    TaskReviewFixes::apply();
    expect((float) $line->fresh()->plan_value)->toBe(1050.0);
});

test('apply is idempotent', function () {
    $task = makeReviewTask([
        'task_number' => '121',
        'title'       => 'Йил якуни билан бюджет даромадлари прогнози бажариш',
    ]);
    TaskProgress::create([
        'task_id' => $task->id, 'line_no' => 0, 'report_period' => '2026-H1', 'period_type' => 'half',
        'metric_label' => 'Бюджет даромади миқдори (йиллик)', 'unit' => 'млрд сўм', 'plan_value' => 5299,
    ]);

    TaskReviewFixes::apply();

    // Renamed to tax receipts and the budget-revenues line added exactly once.
    expect(TaskProgress::where('task_id', $task->id)->where('line_no', 0)->value('metric_label'))
        ->toBe('Солиқ тушумлари миқдори (йиллик)');
    expect(TaskProgress::where('task_id', $task->id)->where('line_no', 4)->count())->toBe(1);

    $second = TaskReviewFixes::apply();
    expect($second)->toBe(['text' => 0, 'units' => 0, 'values' => 0]);
    expect(TaskProgress::where('task_id', $task->id)->where('line_no', 4)->count())->toBe(1);
});
