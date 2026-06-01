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

test('throws on workbook with shifted/missing region headers', function () {
    $path = TaskWorkbookFixture::makeMissingHeaders();
    try {
        expect(fn () => (new TaskWorkbookParser())->parse($path))
            ->toThrow(RuntimeException::class, 'Unexpected workbook layout');
    } finally {
        @unlink($path);
    }
});
