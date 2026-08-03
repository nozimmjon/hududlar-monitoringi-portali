<?php

use App\Models\Task;
use App\Models\TaskProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

uses(RefreshDatabase::class);

/**
 * Economic-layout template builder. $rows: list of
 * [colA, colB, title, indicator, unit, deadline, [regionBlockCol => [executor, plan, actual, pct]]]
 * Continuation lines pass null colA/title.
 */
function planSyncWorkbook(array $rows): string
{
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $set = function (int $col, int $row, $val) use ($sheet) {
        if ($val !== null) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $row, $val);
        }
    };

    $set(1, 3, '№'); $set(2, 3, '№'); $set(3, 3, 'Кўрсаткич номи');
    $set(4, 3, 'Индикатор номи'); $set(5, 3, 'Ўлчов бирлиги'); $set(6, 3, 'Муддати');
    $regionHeaders = [
        7 => 'Қорақалпоғистон Респубилкаси', 11 => 'Андижон вилояти', 15 => 'Бухоро вилояти',
        19 => 'Жиззах вилояти', 23 => 'Қашқадарё вилояти', 27 => 'Навоий вилояти',
        31 => 'Наманган вилояти', 35 => 'Самарқанд вилояти', 39 => 'Сирдарё вилояти',
        43 => 'Сурхондарё вилояти', 47 => 'Тошкент вилояти', 51 => 'Фарғона вилояти',
        55 => 'Хоразм вилояти', 59 => 'Тошкент шаҳри',
    ];
    foreach ($regionHeaders as $col => $h) {
        $set($col, 3, $h);
    }

    $r = 5;
    foreach ($rows as [$a, $b, $title, $indicator, $unit, $deadline, $blocks]) {
        $set(1, $r, $a); $set(2, $r, $b); $set(3, $r, $title);
        $set(4, $r, $indicator); $set(5, $r, $unit); $set(6, $r, $deadline);
        foreach ($blocks as $col => [$executor, $plan, $actual, $pct]) {
            $set($col, $r, $executor); $set($col + 1, $r, $plan);
            $set($col + 2, $r, $actual); $set($col + 3, $r, $pct);
        }
        $r++;
    }

    $path = tempnam(sys_get_temp_dir(), 'plansync_') . '.xlsx';
    (new XlsxWriter($book))->save($path);

    return $path;
}

/** Baseline import (H1 progress file WITH actuals) via the regular importer. */
function planSyncBaseline(): void
{
    $file = planSyncWorkbook([
        [1, 1, 'Ялпи ҳудудий маҳсулотни ўсишини таъминлаш.', 'ЯҲМ ўсиш суръати', 'фоиз', '2026 йил I ярим йиллик', [
            11 => ['Андижон вилояти ҳокимлиги', 7.2, 8.8, null],
        ]],
        [2, 2, 'Экспорт ҳажмини ошириш.', 'экспорт ҳажми', 'млн долл', '2026 йил якуни билан', [
            11 => ['Андижон вилояти ҳокимлиги', 100, 40, null],
        ]],
    ]);
    Artisan::call('import:task-progress', ['--file' => $file, '--period' => '2026-H1']);
}

test('changed plan updates plan and pct, keeps the actual, recomputes status', function () {
    $this->seed();
    planSyncBaseline();

    $task = Task::where('task_number', '2')->where('region_code', 1703)->firstOrFail();
    expect((float) $task->headline_plan)->toBe(100.0);
    expect($task->status)->toBe('open'); // 40/100

    // Template: task 2 plan 100 -> 40 (now met), task 1 unchanged.
    $file = planSyncWorkbook([
        [1, 1, 'Ялпи ҳудудий маҳсулотни ўсишини таъминлаш.', 'ЯҲМ ўсиш суръати', 'фоиз', '2026 йил I ярим йиллик', [
            11 => ['Андижон вилояти ҳокимлиги', 7.2, null, null],
        ]],
        [2, 2, 'Экспорт ҳажмини ошириш.', 'экспорт ҳажми', 'млн долл', '2026 йил якуни билан', [
            11 => ['Андижон вилояти ҳокимлиги', 40, null, null],
        ]],
    ]);
    Artisan::call('import:plan-sync', ['--file' => $file, '--period' => '2026-H1']);

    $task->refresh();
    $row = TaskProgress::where('task_id', $task->id)->where('report_period', '2026-H1')->where('line_no', 0)->firstOrFail();
    expect((float) $row->plan_value)->toBe(40.0);
    expect((float) $row->actual_value)->toBe(40.0);      // actual untouched
    expect((float) $row->pct_of_plan)->toBe(100.0);      // recomputed from new plan
    expect((float) $task->headline_plan)->toBe(40.0);
    expect($task->status)->toBe('done');

    // Task 1 (unchanged plan): pct/actual left exactly as imported.
    $t1 = Task::where('task_number', '1')->where('region_code', 1703)->firstOrFail();
    $r1 = TaskProgress::where('task_id', $t1->id)->where('line_no', 0)->firstOrFail();
    expect((float) $r1->plan_value)->toBe(7.2);
    expect((float) $r1->actual_value)->toBe(8.8);
});

test('new task is inserted plan-only and starts as Бажарилмоқда', function () {
    $this->seed();
    planSyncBaseline();

    $file = planSyncWorkbook([
        [1, 1, 'Ялпи ҳудудий маҳсулотни ўсишини таъминлаш.', 'ЯҲМ ўсиш суръати', 'фоиз', '2026 йил I ярим йиллик', [
            11 => ['Андижон вилояти ҳокимлиги', 7.2, null, null],
        ]],
        [2, 2, 'Экспорт ҳажмини ошириш.', 'экспорт ҳажми', 'млн долл', '2026 йил якуни билан', [
            11 => ['Андижон вилояти ҳокимлиги', 100, null, null],
        ]],
        [3, 243, 'Йил якуни билан саноат ҳажмини ошириш.', 'саноат ҳажми', 'млрд сўм', '2026 йил якуни билан', [
            11 => ['Андижон вилояти ҳокимлиги, Шахрихон тумани ҳокимлиги', 500, null, null],
        ]],
    ]);
    Artisan::call('import:plan-sync', ['--file' => $file, '--period' => '2026-H1']);

    $new = Task::where('task_number', '243')->where('region_code', 1703)->firstOrFail();
    expect($new->status)->toBe('in_progress');
    expect($new->latest_period)->toBe('2026-H1');
    expect((float) $new->headline_plan)->toBe(500.0);
    expect($new->headline_actual)->toBeNull();
    expect($new->lines_total)->toBe(1);
    expect($new->districts()->count())->toBeGreaterThan(0); // Шахрихон resolved

    $rows = TaskProgress::where('task_id', $new->id)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->actual_value)->toBeNull();
});

test('new indicator line lands in the existing task latest period', function () {
    $this->seed();
    planSyncBaseline();

    $file = planSyncWorkbook([
        [1, 1, 'Ялпи ҳудудий маҳсулотни ўсишини таъминлаш.', 'ЯҲМ ўсиш суръати', 'фоиз', '2026 йил I ярим йиллик', [
            11 => ['Андижон вилояти ҳокимлиги', 7.2, null, null],
        ]],
        [null, 2, null, 'ЯҲМ (йиллик)', 'млрд сўм', null, [
            11 => [null, 124800, null, null],
        ]],
        [2, 3, 'Экспорт ҳажмини ошириш.', 'экспорт ҳажми', 'млн долл', '2026 йил якуни билан', [
            11 => ['Андижон вилояти ҳокимлиги', 100, null, null],
        ]],
    ]);
    // NB: continuation line makes file task 1 = lines 0..1; file task numbering for
    // export becomes col B 3 -> must NOT clash: baseline task 2 has same title, so ok.
    Artisan::call('import:plan-sync', ['--file' => $file, '--period' => '2026-H1']);

    $t1 = Task::where('task_number', '1')->where('region_code', 1703)->firstOrFail();
    $rows = TaskProgress::where('task_id', $t1->id)->where('report_period', '2026-H1')->orderBy('line_no')->get();
    expect($rows)->toHaveCount(2);
    expect((float) $rows[1]->plan_value)->toBe(124800.0);
    expect($rows[1]->actual_value)->toBeNull();
    expect($t1->fresh()->lines_total)->toBe(2);
});

test('a file carrying actuals is refused', function () {
    $this->seed();
    planSyncBaseline();

    $file = planSyncWorkbook([
        [1, 1, 'Ялпи ҳудудий маҳсулотни ўсишини таъминлаш.', 'ЯҲМ ўсиш суръати', 'фоиз', '2026 йил I ярим йиллик', [
            11 => ['Андижон вилояти ҳокимлиги', 7.2, 9.9, null],
        ]],
    ]);
    $exit = Artisan::call('import:plan-sync', ['--file' => $file, '--period' => '2026-H1']);

    expect($exit)->toBe(1);
    // Nothing changed.
    $t1 = Task::where('task_number', '1')->where('region_code', 1703)->firstOrFail();
    $r1 = TaskProgress::where('task_id', $t1->id)->where('line_no', 0)->firstOrFail();
    expect((float) $r1->actual_value)->toBe(8.8);
});

test('title mismatch skips the task and reports it', function () {
    $this->seed();
    planSyncBaseline();

    $file = planSyncWorkbook([
        [1, 1, 'Бутунлай бошқа топшириқ номи.', 'ЯҲМ ўсиш суръати', 'фоиз', '2026 йил I ярим йиллик', [
            11 => ['Андижон вилояти ҳокимлиги', 999, null, null],
        ]],
    ]);
    Artisan::call('import:plan-sync', ['--file' => $file, '--period' => '2026-H1']);

    expect(Artisan::output())->toContain('title mismatch');
    $t1 = Task::where('task_number', '1')->where('region_code', 1703)->firstOrFail();
    $r1 = TaskProgress::where('task_id', $t1->id)->where('line_no', 0)->firstOrFail();
    expect((float) $r1->plan_value)->toBe(7.2); // untouched
});

test('dry run analyzes without writing', function () {
    $this->seed();
    planSyncBaseline();

    $file = planSyncWorkbook([
        [1, 1, 'Ялпи ҳудудий маҳсулотни ўсишини таъминлаш.', 'ЯҲМ ўсиш суръати', 'фоиз', '2026 йил I ярим йиллик', [
            11 => ['Андижон вилояти ҳокимлиги', 55.5, null, null],
        ]],
    ]);
    Artisan::call('import:plan-sync', ['--file' => $file, '--period' => '2026-H1', '--dry-run' => true]);

    $t1 = Task::where('task_number', '1')->where('region_code', 1703)->firstOrFail();
    $r1 = TaskProgress::where('task_id', $t1->id)->where('line_no', 0)->firstOrFail();
    expect((float) $r1->plan_value)->toBe(7.2); // unchanged
});

test('a second identical run is a no-op', function () {
    $this->seed();
    planSyncBaseline();

    $file = planSyncWorkbook([
        [1, 1, 'Ялпи ҳудудий маҳсулотни ўсишини таъминлаш.', 'ЯҲМ ўсиш суръати', 'фоиз', '2026 йил I ярим йиллик', [
            11 => ['Андижон вилояти ҳокимлиги', 60, null, null],
        ]],
    ]);
    Artisan::call('import:plan-sync', ['--file' => $file, '--period' => '2026-H1']);
    $firstUpdatedAt = TaskProgress::where('line_no', 0)->firstOrFail()->updated_at;

    sleep(1);
    Artisan::call('import:plan-sync', ['--file' => $file, '--period' => '2026-H1']);

    expect(Artisan::output())->toContain('| plan values updated                    | 0');
    expect(TaskProgress::where('line_no', 0)->firstOrFail()->updated_at->timestamp)
        ->toBe($firstUpdatedAt->timestamp);
});
