<?php

use App\Support\TaskPeriod;

test('cadence detects quarterly before monthly', function () {
    // Contains both "чорак" and "ой" -> must resolve quarterly.
    expect(TaskPeriod::cadenceFor('Ҳар чорак якуни билан кейинги ойнинг 25 санаси'))->toBe('quarterly');
    expect(TaskPeriod::cadenceFor('Ҳар ой якуни билан кейинги ойнинг 25 санаси'))->toBe('monthly');
    expect(TaskPeriod::cadenceFor('Ҳар ой'))->toBe('monthly');
    expect(TaskPeriod::cadenceFor('Ҳар ойда'))->toBe('monthly');
    expect(TaskPeriod::cadenceFor(''))->toBe('quarterly'); // default
});

test('period type parses quarter vs half-year vs month', function () {
    expect(TaskPeriod::periodType('2026-Q1'))->toBe('quarter');
    expect(TaskPeriod::periodType('2026-Q4'))->toBe('quarter');
    expect(TaskPeriod::periodType('2026-H1'))->toBe('half');
    expect(TaskPeriod::periodType('2026-H2'))->toBe('half');
    expect(TaskPeriod::periodType('2026-03'))->toBe('month');
});

test('year is parsed from report period', function () {
    expect(TaskPeriod::yearFromPeriod('2026-Q1'))->toBe(2026);
    expect(TaskPeriod::yearFromPeriod('2026-11'))->toBe(2026);
});

test('deadline text maps to period code', function () {
    expect(TaskPeriod::deadlineToPeriodCode("2026 йил\nI ярим йиллик"))->toBe('h1');
    expect(TaskPeriod::deadlineToPeriodCode('2026 йил якуни билан'))->toBe('year');
    expect(TaskPeriod::deadlineToPeriodCode('2026 йил давомида'))->toBe('ongoing');
    expect(TaskPeriod::deadlineToPeriodCode('2026 йил май ойи'))->toBe('month');
    expect(TaskPeriod::deadlineToPeriodCode(null))->toBeNull();
});

test('deadline bucket maps period code and month to filter keys', function () {
    expect(TaskPeriod::deadlineBucket('h1', '2026 йил I ярим йиллик'))->toBe('h1');
    expect(TaskPeriod::deadlineBucket('month', '2026 йил май ойи'))->toBe('h1');
    expect(TaskPeriod::deadlineBucket('month', '2026 йил сентябр ойи'))->toBe('q3');
    expect(TaskPeriod::deadlineBucket('month', '2026 йил ноябр ойи'))->toBe('q4');
    expect(TaskPeriod::deadlineBucket('year', '2026 йил якуни билан'))->toBe('year');
    expect(TaskPeriod::deadlineBucket('ongoing', '2026 йил давомида'))->toBe('ongoing');
    expect(TaskPeriod::deadlineBucket(null, null))->toBe('none');
});

test('deadline sort rank orders h1 before q3 months before year before ongoing', function () {
    $h1      = TaskPeriod::deadlineSortRank('h1', '2026 йил I ярим йиллик');
    $may     = TaskPeriod::deadlineSortRank('month', '2026 йил май ойи');
    $sep     = TaskPeriod::deadlineSortRank('month', '2026 йил сентябр ойи');
    $nov     = TaskPeriod::deadlineSortRank('month', '2026 йил ноябр ойи');
    $year    = TaskPeriod::deadlineSortRank('year', '2026 йил якуни билан');
    $ongoing = TaskPeriod::deadlineSortRank('ongoing', '2026 йил давомида');
    $none    = TaskPeriod::deadlineSortRank(null, null);

    // H1-and-earlier months share the first bucket.
    expect($may)->toBe($h1);
    // Q3 months after H1, Q4 months after Q3, then year-end, then ongoing, unknown last.
    expect($h1)->toBeLessThan($sep);
    expect($sep)->toBeLessThan($nov);
    expect($nov)->toBeLessThan($year);
    expect($year)->toBeLessThan($ongoing);
    expect($ongoing)->toBeLessThan($none);
});
