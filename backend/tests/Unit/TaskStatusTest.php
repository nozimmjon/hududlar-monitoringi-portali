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
