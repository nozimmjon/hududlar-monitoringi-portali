<?php

use App\Support\TaskStatus;

test('status is done at or above 100 percent', function () {
    expect(TaskStatus::statusFor(100.0))->toBe('done');
    expect(TaskStatus::statusFor(150.0))->toBe('done');
});

test('status is open below 100 percent or when missing', function () {
    expect(TaskStatus::statusFor(99.99))->toBe('open');
    expect(TaskStatus::statusFor(0.0))->toBe('open');
    expect(TaskStatus::statusFor(null))->toBe('open');
});

test('aggregate is in_progress when no line has any actual', function () {
    expect(TaskStatus::aggregate([
        ['plan' => 10.0, 'actual' => null, 'pct' => null],
        ['plan' => 5.0, 'actual' => null, 'pct' => null],
    ]))->toBe(['status' => 'in_progress', 'total' => 2, 'done' => 0]);
    // No lines at all -> nothing reported.
    expect(TaskStatus::aggregate([]))->toBe(['status' => 'in_progress', 'total' => 0, 'done' => 0]);
});

test('aggregate treats zero actuals as no real progress yet', function () {
    // All actuals explicit 0 -> nothing achieved -> Бажарилмоқда.
    expect(TaskStatus::aggregate([
        ['plan' => 10.0, 'actual' => 0.0, 'pct' => 0.0],
    ]))->toBe(['status' => 'in_progress', 'total' => 1, 'done' => 0]);
    // A single non-zero actual anywhere makes it a real report -> open.
    expect(TaskStatus::aggregate([
        ['plan' => 10.0, 'actual' => 0.0, 'pct' => 0.0],
        ['plan' => 5.0, 'actual' => 2.0, 'pct' => 40.0],
    ]))->toBe(['status' => 'open', 'total' => 2, 'done' => 0]);
});

test('aggregate is done only when every planned line is at 100', function () {
    expect(TaskStatus::aggregate([
        ['plan' => 10.0, 'actual' => 12.0, 'pct' => 120.0],
        ['plan' => 5.0, 'actual' => 5.0, 'pct' => 100.0],
    ]))->toBe(['status' => 'done', 'total' => 2, 'done' => 2]);
    expect(TaskStatus::aggregate([
        ['plan' => 10.0, 'actual' => 12.0, 'pct' => 120.0],
        ['plan' => 5.0, 'actual' => 2.0, 'pct' => 40.0],
    ]))->toBe(['status' => 'open', 'total' => 2, 'done' => 1]);
});

test('aggregate ignores unplanned lines but counts their actuals as data', function () {
    expect(TaskStatus::aggregate([
        ['plan' => null, 'actual' => 3.0, 'pct' => null],
    ]))->toBe(['status' => 'open', 'total' => 0, 'done' => 0]);
});

test('continuous tasks stay in_progress whatever the numbers say', function () {
    $title = 'Паст ўсиш кузатилган шаҳар ва туманлари бўйича ишланиб, тегилши ҳудуд кўрсаткичларидан паст бўлмаган даражада ўсишига эришиш.';

    // Reported 100% would normally be 'done'; a continuous task never closes mid-year.
    expect(TaskStatus::forTask('10', $title, [
        ['plan' => 1.0, 'actual' => 1.0, 'pct' => 100.0],
    ]))->toBe(['status' => 'in_progress', 'total' => 1, 'done' => 1]);

    // Partial progress would normally be 'open'.
    expect(TaskStatus::forTask('10', $title, [
        ['plan' => 2.0, 'actual' => 1.0, 'pct' => 50.0],
    ])['status'])->toBe('in_progress');
});

test('the continuous-task override is guarded by the title', function () {
    // Same number, different task (workbook renumbering) -> normal rules apply.
    expect(TaskStatus::forTask('10', 'Бошқа мутлақо бошқа топшириқ', [
        ['plan' => 1.0, 'actual' => 1.0, 'pct' => 100.0],
    ])['status'])->toBe('done');

    // Ordinary task, ordinary aggregate.
    expect(TaskStatus::forTask('4', 'Саноат маҳсулотлари', [
        ['plan' => 10.0, 'actual' => 4.0, 'pct' => 40.0],
    ])['status'])->toBe('open');
});

test('ongoing-until-done tasks report unfinished work as in_progress', function () {
    $title = 'Яширин иқтисодиётга қарши курашиш, солиқ тўловчилар иқтисодий фаоллигини ошириш ҳамда солиқ қарздорликларини ундириш ҳисобига қўшимча даромад ундириш топшириғини тўлиқ таъминлаш.';

    // Partial: normally 'open' -> reported as ongoing work.
    expect(TaskStatus::forTask('111', $title, [
        ['plan' => 10.0, 'actual' => 6.0, 'pct' => 60.0],
        ['plan' => 5.0, 'actual' => 5.0, 'pct' => 100.0],
    ])['status'])->toBe('in_progress');

    // Fully met: stays done — unlike a continuous task.
    expect(TaskStatus::forTask('111', $title, [
        ['plan' => 10.0, 'actual' => 12.0, 'pct' => 120.0],
    ])['status'])->toBe('done');

    // Nothing reported: already in_progress by the normal rule.
    expect(TaskStatus::forTask('111', $title, [
        ['plan' => 10.0, 'actual' => null, 'pct' => null],
    ])['status'])->toBe('in_progress');
});

test('the ongoing-until-done override is guarded by the title too', function () {
    expect(TaskStatus::forTask('111', 'Бутунлай бошқа топшириқ', [
        ['plan' => 10.0, 'actual' => 6.0, 'pct' => 60.0],
    ])['status'])->toBe('open');
});

test('the half-year unemployment measures task is ongoing-until-done', function () {
    $h1   = 'Биринчи ярим йилда ишсизлик даражаси камайтириш чоралари кўриш.';
    $year = 'Йил якунига қадар ишсизлик даражаси камайтириш чоралари кўриш.';
    $partial = [['plan' => 10.0, 'actual' => 6.0, 'pct' => 60.0]];

    expect(TaskStatus::forTask('182', $h1, $partial)['status'])->toBe('in_progress');
    expect(TaskStatus::forTask('182', $h1, [['plan' => 10.0, 'actual' => 11.0, 'pct' => 110.0]])['status'])->toBe('done');

    // The year-end sibling (#201) shares wording but is not overridden.
    expect(TaskStatus::forTask('201', $year, $partial)['status'])->toBe('open');
});

test('a below-plan verdict is deferred while the deadline has not been reached', function () {
    $partial = [['plan' => 5.0, 'actual' => 8.5, 'pct' => 59.0]];

    // Year-end promise running behind at H1 -> still being worked on.
    expect(TaskStatus::forTask('70', 'Асосий озиқ-овқат маҳсулотлари йиллик нархларини барқарор сақлаш.', $partial,
        'year', '2026 йил якуни билан', '2026-H1')['status'])->toBe('in_progress');

    // Same task once the year has closed -> the verdict is due.
    expect(TaskStatus::forTask('70', 'Асосий озиқ-овқат маҳсулотлари йиллик нархларини барқарор сақлаш.', $partial,
        'year', '2026 йил якуни билан', '2026-H2')['status'])->toBe('open');

    // H1 deadline at H1 -> due, verdict applies.
    expect(TaskStatus::forTask('5', 'Бир топшириқ', $partial,
        'h1', '2026 йил I ярим йиллик', '2026-H1')['status'])->toBe('open');

    // Q3 deadline is not due at H1 but is due at Q3.
    expect(TaskStatus::forTask('6', 'Бошқа топшириқ', $partial,
        'q3', 'III чорак', '2026-H1')['status'])->toBe('in_progress');
    expect(TaskStatus::forTask('6', 'Бошқа топшириқ', $partial,
        'q3', 'III чорак', '2026-Q3')['status'])->toBe('open');

    // Early completion still closes before the deadline.
    expect(TaskStatus::forTask('70', 'Асосий озиқ-овқат маҳсулотлари йиллик нархларини барқарор сақлаш.',
        [['plan' => 5.0, 'actual' => 3.0, 'pct' => 166.0]],
        'year', '2026 йил якуни билан', '2026-H1')['status'])->toBe('done');

    // No deadline context (legacy callers) -> old verdict-always behavior.
    expect(TaskStatus::forTask('70', 'Асосий озиқ-овқат маҳсулотлари йиллик нархларини барқарор сақлаш.', $partial)['status'])
        ->toBe('open');
});
