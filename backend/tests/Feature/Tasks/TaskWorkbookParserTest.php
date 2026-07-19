<?php
// backend/tests/Feature/Tasks/TaskWorkbookParserTest.php

use App\Services\Tasks\TaskWorkbookParser;
use Tests\Helpers\TaskWorkbookFixture;

test('parses tasks, sections, regions and multi-metric lines', function () {
    $path = TaskWorkbookFixture::make();
    try {
        $tasks = (new TaskWorkbookParser())->parse($path);
    } finally {
        @unlink($path);
    }

    expect($tasks)->toHaveCount(3);

    $t1 = $tasks[0];
    expect($t1['task_number'])->toBe('1');
    expect($t1['title'])->toBe('ЯҲМ ўсишини таъминлаш');
    expect($t1['kind'])->toBe('kpi');
    expect($t1['module_code'])->toBe('macro');
    expect($t1['indicator_code'])->toBe('grp');
    expect($t1['cadence'])->toBe('quarterly');
    expect($t1['period_code'])->toBe('h1');
    expect($t1['regions'])->toHaveKeys([1735, 1703]);
    expect($t1['regions'][1703]['executor_text'])->toBe('Андижон вилояти ҳокимлиги');
    expect($t1['regions'][1703]['metrics'][0]['plan'])->toBeNumericallyClose(7.2);
    expect($t1['regions'][1703]['metrics'][0]['actual'])->toBeNull();

    $t2 = $tasks[1];
    expect($t2['task_number'])->toBe('2'); // '2' comes from formula =B7+1 -> calculated
    expect($t2['kind'])->toBe('measure');
    expect($t2['cadence'])->toBe('monthly');
    expect($t2['period_code'])->toBe('year');
    expect($t2['regions'][1703]['metrics'])->toHaveCount(2);
    expect($t2['regions'][1703]['metrics'][0]['plan'])->toBeNumericallyClose(6);
    expect($t2['regions'][1703]['metrics'][0]['pct'])->toBeNumericallyClose(50);
    expect($t2['regions'][1703]['metrics'][1]['metric_label'])->toContain('қайта тикланадиган');
    expect($t2['regions'][1703]['metrics'][1]['plan'])->toBeNumericallyClose(55);
    expect($t2['regions'][1703]['metrics'][1]['pct'])->toBeNumericallyClose(100);
    // Qoraqalpoq has no data for task 2 -> region absent.
    expect($t2['regions'])->not->toHaveKey(1735);

    // Task 3: pct cell empty -> derived from actual/plan (12/10 = 120%).
    // col B (unique global №) wins over col A.
    $t3 = $tasks[2];
    expect($t3['task_number'])->toBe('5'); // col B (unique) wins over col A
    expect($t3['regions'][1703]['metrics'][0]['plan'])->toBeNumericallyClose(10);
    expect($t3['regions'][1703]['metrics'][0]['actual'])->toBeNumericallyClose(12);
    expect($t3['regions'][1703]['metrics'][0]['pct'])->toBeNumericallyClose(120);

    expect($t3['regions'][1703]['metrics'])->toHaveCount(2);
    // Unit-only continuation (no label): captured with null label, derived pct 50.
    expect($t3['regions'][1703]['metrics'][1]['metric_label'])->toBeNull();
    expect($t3['regions'][1703]['metrics'][1]['unit'])->toBe('дона');
    expect($t3['regions'][1703]['metrics'][1]['plan'])->toBeNumericallyClose(300);
    expect($t3['regions'][1703]['metrics'][1]['pct'])->toBeNumericallyClose(50);
});

test('parses the economic layout: auto-detect, ratio pct, plan-based region listing', function () {
    $path = TaskWorkbookFixture::makeEconomic();
    try {
        $tasks = (new TaskWorkbookParser())->parse($path);
    } finally {
        @unlink($path);
    }

    expect($tasks)->toHaveCount(7);

    $t1 = $tasks[0];
    expect($t1['task_number'])->toBe('1');
    expect($t1['title'])->toBe('Ялпи ҳудудий маҳсулотни ўсишини таъминлаш.');
    expect($t1['period_code'])->toBe('h1');
    expect($t1['module_code'])->toBe('macro');
    expect($t1['indicator_code'])->toBe('grp');
    // No metadata columns in this file generation -> all null (importer preserves old values).
    expect($t1['kind'])->toBeNull();
    expect($t1['cadence'])->toBeNull();
    expect($t1['data_source'])->toBeNull();
    expect($t1['report_schedule_text'])->toBeNull();

    // Ratio % -> percent: 1 -> 100, formula 8.8/7.2 -> 122.22.
    expect($t1['regions'][1735]['metrics'][0]['pct'])->toBeNumericallyClose(100);
    expect($t1['regions'][1703]['metrics'][0]['plan'])->toBeNumericallyClose(7.2);
    expect($t1['regions'][1703]['metrics'][0]['actual'])->toBeNumericallyClose(8.8);
    expect($t1['regions'][1703]['metrics'][0]['pct'])->toBeNumericallyClose(8.8 / 7.2 * 100);

    // Plan present, actual empty: the formula's 0% is an artifact -> pct null.
    expect($t1['regions'][1708]['metrics'][0]['plan'])->toBeNumericallyClose(5);
    expect($t1['regions'][1708]['metrics'][0]['actual'])->toBeNull();
    expect($t1['regions'][1708]['metrics'][0]['pct'])->toBeNull();

    // «х» plan (Bukhara) and actual-without-plan (Kashkadarya) -> regions excluded.
    expect($t1['regions'])->not->toHaveKey(1706);
    expect($t1['regions'])->not->toHaveKey(1710);

    // Task 2: headline line empty but a sub-line carries the plan -> region kept.
    $t2 = $tasks[1];
    expect($t2['regions'])->toHaveKey(1703);
    expect($t2['regions'][1703]['executor_text'])->toContain('Шахрихон');
    expect($t2['regions'][1703]['metrics'][0]['plan'])->toBeNull();
    expect($t2['regions'][1703]['metrics'][1]['plan'])->toBeNumericallyClose(55);
    expect($t2['regions'][1703]['metrics'][1]['pct'])->toBeNumericallyClose(100);
    expect($t2['regions'])->not->toHaveKey(1735);

    // Task 3: col B wins as task_number; empty pct cell -> derived 120.
    $t3 = $tasks[2];
    expect($t3['task_number'])->toBe('4');
    expect($t3['regions'][1703]['metrics'][0]['pct'])->toBeNumericallyClose(120);

    // Task 68 (инфляция) is lower-is-better: pct = plan/actual, not the file's ratio.
    $t4 = $tasks[3];
    expect($t4['task_number'])->toBe('68');
    // Worse than plan (3.2 > 2.8): file ratio 114% -> must invert to 87.5%.
    expect($t4['regions'][1735]['metrics'][0]['pct'])->toBeNumericallyClose(2.8 / 3.2 * 100);
    // Better than plan (2.4 < 2.9): >100%, reads as done.
    expect($t4['regions'][1703]['metrics'][0]['pct'])->toBeNumericallyClose(2.9 / 2.4 * 100);
    // Zero actual against a zero target: met -> 100%.
    expect($t4['regions'][1708]['metrics'][0]['pct'])->toBeNumericallyClose(100);
});

test('throws on workbook with shifted/missing region headers', function () {
    $path = TaskWorkbookFixture::makeMissingHeaders();
    try {
        expect(fn () => (new TaskWorkbookParser())->parse($path))
            ->toThrow(RuntimeException::class, 'Unexpected workbook layout');
    } finally {
        @unlink($path);
    }
});

test('throws when any two region columns are swapped', function () {
    $path = TaskWorkbookFixture::makeSwappedRegions();
    try {
        expect(fn () => (new TaskWorkbookParser())->parse($path))
            ->toThrow(RuntimeException::class, 'region block columns');
    } finally {
        @unlink($path);
    }
});

test('a region marked «х» (N/A) is still captured as a plan-less entry', function () {
    // Real partner files mark a region N/A with «х» in its executor + plan cells
    // (e.g. Andijan on task #21). Such a region must still be captured — as a
    // plan-less task — not silently dropped. A truly blank block stays absent.
    $path = TaskWorkbookFixture::makeSentinelRegionTask();
    try {
        $tasks = (new TaskWorkbookParser())->parse($path);
    } finally {
        @unlink($path);
    }

    expect($tasks)->toHaveCount(1);
    $t = $tasks[0];

    // Andijan was «х»/«х» (sentinel in executor + plan) -> still listed, plan-less.
    expect($t['regions'])->toHaveKey(1703);
    expect($t['regions'][1703]['executor_text'])->toBe('');
    expect($t['regions'][1703]['metrics'])->toHaveCount(1);
    expect($t['regions'][1703]['metrics'][0]['plan'])->toBeNull();

    // Bukhara had «х» only in the plan cell, executor blank -> also still listed, plan-less.
    expect($t['regions'])->toHaveKey(1706);
    expect($t['regions'][1706]['executor_text'])->toBe('');
    expect($t['regions'][1706]['metrics'][0]['plan'])->toBeNull();

    // The region with real data is unaffected.
    expect($t['regions'][1735]['metrics'][0]['plan'])->toBeNumericallyClose(157);
});
