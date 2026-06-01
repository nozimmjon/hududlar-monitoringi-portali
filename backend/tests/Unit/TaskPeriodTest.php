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

test('period type parses quarter vs month', function () {
    expect(TaskPeriod::periodType('2026-Q1'))->toBe('quarter');
    expect(TaskPeriod::periodType('2026-Q4'))->toBe('quarter');
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
