<?php

use App\Services\Roadmaps\MeasureRecomputer;

function rmLine(?float $plan, ?float $actual): array
{
    return ['plan' => $plan, 'actual' => $actual, 'pct' => MeasureRecomputer::pctOfPlan($plan, $actual)];
}

test('pct of plan', function () {
    expect(MeasureRecomputer::pctOfPlan(24, 14))->toBeNumericallyClose(58.3333, 1e-3);
    expect(MeasureRecomputer::pctOfPlan(null, 14))->toBeNull();
    expect(MeasureRecomputer::pctOfPlan(24, null))->toBeNull();
    expect(MeasureRecomputer::pctOfPlan(0, 5))->toBeNull();
    expect(MeasureRecomputer::pctOfPlan('7.800000', '7.800000'))->toBe(100.0);   // decimal casts hand back strings
    expect(MeasureRecomputer::pctOfPlan(0.01, 20000))->toBe(999999.9999);          // clamped to what numeric(10,4) can hold
    expect(MeasureRecomputer::pctOfPlan(0.01, -20000))->toBe(-999999.9999);      // negative side clamped too
});

test('nothing reported → in_progress, no percent, counts still truthful', function () {
    $agg = MeasureRecomputer::aggregate([rmLine(24, null), rmLine(1, 0.0)], true);
    expect($agg['status'])->toBe('in_progress');
    expect($agg['reported'])->toBeFalse();
    expect($agg['pct'])->toBeNull();
    expect($agg['lines_total'])->toBe(2);
    expect($agg['lines_done'])->toBe(0);
});

test('every planned line at 100 % → done; percent is the mean of capped line percents', function () {
    $agg = MeasureRecomputer::aggregate([rmLine(7.8, 7.8), rmLine(33, 40)], false);
    expect($agg['status'])->toBe('done');
    expect($agg['pct'])->toBe(100.0);
    expect($agg['lines_done'])->toBe(2);

    $partial = MeasureRecomputer::aggregate([rmLine(24, 14), rmLine(1, 1), rmLine(3, 3)], true);
    expect($partial['pct'])->toBeNumericallyClose(86.11, 0.01);   // (58.33 + 100 + 100) / 3, not the done-share 66.7
    expect($partial['lines_done'])->toBe(2);
});

test('below plan → open once the deadline is reached, else in_progress', function () {
    expect(MeasureRecomputer::aggregate([rmLine(24, 14)], false)['status'])->toBe('in_progress');
    expect(MeasureRecomputer::aggregate([rmLine(24, 14)], true)['status'])->toBe('open');
});

test('lines without a plan are informational: never counted; a missing progress row counts as 0 in the mean', function () {
    $agg = MeasureRecomputer::aggregate([rmLine(null, 5), rmLine(10, 10), ['plan' => 10, 'actual' => null, 'pct' => null]], true);
    expect($agg['lines_total'])->toBe(2);
    expect($agg['lines_done'])->toBe(1);
    expect($agg['pct'])->toBe(50.0);
    expect($agg['status'])->toBe('open');
});

test('no planned lines at all → in_progress and null percent even when something is reported', function () {
    $agg = MeasureRecomputer::aggregate([rmLine(null, 5)], true);
    expect($agg['status'])->toBe('in_progress');
    expect($agg['pct'])->toBeNull();
});

test('a negative line percent contributes 0 to the mean, never a negative ring', function () {
    $agg = MeasureRecomputer::aggregate([rmLine(10, -5), rmLine(10, 10)], true);
    expect($agg['pct'])->toBe(50.0);
    expect($agg['status'])->toBe('open');
});
