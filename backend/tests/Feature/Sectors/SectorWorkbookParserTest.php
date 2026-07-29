<?php

use App\Services\Tasks\SectorWorkbookParser;
use Tests\Helpers\SectorWorkbookBuilder;

test('parses tasks, continuation lines, units and deadlines', function () {
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', '«Ўзбекнефтгаз» АЖ — Бошқарув раиси А. Сангинов имзолаган кафолат хати', [
            [1, 1, 'Товар маҳсулот ҳажмини етказиш.', 'Товар маҳсулот ҳажми', 'трлн сўм', '2026 йил якуни', 56.7, null, null],
            [2, 2, 'Углеводород қазиб чиқариш.', 'Табиий газ', 'млрд куб метр', '2026 йил якуни', 24.7, null, null],
            [null, 3, null, 'Суюқ углеводородлар', 'минг тонна', '2026 йил 2-ярим йиллиги', 1188, null, null],
            [null, 4, null, 'Шундан: нефть', 'минг тонна', '2026 йил III-чорак', 62.9, null, null],
        ]],
    ]);

    $parsed = (new SectorWorkbookParser())->parse($file);

    expect($parsed['sheets'])->toHaveCount(1);
    $sheet = $parsed['sheets'][0];
    expect($sheet['sort_order'])->toBe(1);
    expect($sheet['tasks'])->toHaveCount(2);

    $t2 = $sheet['tasks'][1];
    expect($t2['task_no'])->toBe(2);
    expect($t2['title'])->toBe('Углеводород қазиб чиқариш.');
    expect($t2['lines'])->toHaveCount(3);
    expect($t2['lines'][1]['metric_label'])->toBe('Суюқ углеводородлар');
    expect($t2['lines'][1]['line_no'])->toBe(3);
    expect($t2['lines'][1]['deadline_code'])->toBe('h2');
    expect($t2['lines'][2]['deadline_code'])->toBe('q3');
    expect($t2['lines'][0]['plan'])->toEqualWithDelta(24.7, 0.001);
    expect($t2['lines'][0]['actual'])->toBeNull();
});

test('normalizes all deadline spellings', function () {
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В', 'а', 'та', '2026 йил якуни', 1, null, null],
            [null, 2, null, 'б', 'та', '2026 йил 2-ярим йиллиги', 1, null, null],
            [null, 3, null, 'в', 'та', '2026 йил III-чорак', 1, null, null],
            [null, 4, null, 'г', 'та', '2026 йил III чорак', 1, null, null],
            [null, 5, null, 'д', 'та', '2026 йил IV чорак', 1, null, null],
            [null, 6, null, 'е', 'та', '2026 йил IV-чорак', 1, null, null],
            [null, 7, null, 'ж', 'та', "2026 йил \u{406}\u{406}\u{406} чорак", 1, null, null],
        ]],
    ]);

    $lines = (new SectorWorkbookParser())->parse($file)['sheets'][0]['tasks'][0]['lines'];
    expect(array_column($lines, 'deadline_code'))->toBe(['year', 'h2', 'q3', 'q3', 'q4', 'q4', 'q3']);
});

test('stops at Изоҳлар and keeps sheet-global line numbers', function () {
    $file = SectorWorkbookBuilder::make([
        ['16. Фармацевтика', 'орг', [
            [1, 1, 'В1', 'Ишлаб чиқариш', 'трлн сўм', '2026 йил якуни', 8.5, null, null],
        ]],
    ]);

    $parsed = (new SectorWorkbookParser())->parse($file);
    expect($parsed['sheets'][0]['sort_order'])->toBe(16);
    expect($parsed['sheets'][0]['tasks'])->toHaveCount(1);
});

test('rejects layout drift in header rows', function () {
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [[1, 1, 'В', 'а', 'та', '2026 йил якуни', 1, null, null]]],
    ]);
    // Sabotage the header: swap column D label
    $wb = PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    $wb->getSheet(0)->setCellValue('D3', 'Бошқа устун');
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($wb))->save($file);

    expect(fn () => (new SectorWorkbookParser())->parse($file))
        ->toThrow(RuntimeException::class, 'Индикатор номи');
});

test('rejects sheet title without a leading number', function () {
    $file = SectorWorkbookBuilder::make([
        ['Номаълум лист', 'орг', [[1, 1, 'В', 'а', 'та', '2026 йил якуни', 1, null, null]]],
    ]);

    expect(fn () => (new SectorWorkbookParser())->parse($file))
        ->toThrow(RuntimeException::class);
});

test('task-starting row without indicator still starts a new task (no misattribution)', function () {
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 10, null, null],
            [2, 2, 'В2.', null, null, null, null, null, null],               // task 2 header row, no D
            [null, 3, null, 'Кўрсаткич Б', 'та', '2026 йил якуни', 20, null, null], // must belong to task 2
        ]],
    ]);

    $tasks = (new SectorWorkbookParser())->parse($file)['sheets'][0]['tasks'];
    expect($tasks)->toHaveCount(2);
    expect($tasks[0]['lines'])->toHaveCount(1);
    expect($tasks[1]['task_no'])->toBe(2);
    expect($tasks[1]['title'])->toBe('В2.');
    expect($tasks[1]['lines'])->toHaveCount(1);
    expect($tasks[1]['lines'][0]['metric_label'])->toBe('Кўрсаткич Б');
});

test('reads actual values and ignores the file pct column', function () {
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В', 'а', 'та', '2026 йил якуни', 100, 55, 999],
        ]],
    ]);

    $line = (new SectorWorkbookParser())->parse($file)['sheets'][0]['tasks'][0]['lines'][0];
    expect($line['plan'])->toEqualWithDelta(100.0, 0.001);
    expect($line['actual'])->toEqualWithDelta(55.0, 0.001);
    expect($line)->not->toHaveKey('pct'); // parser never surfaces column I
});
