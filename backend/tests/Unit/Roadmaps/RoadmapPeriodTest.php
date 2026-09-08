<?php

use App\Support\Roadmaps\RoadmapPeriod;

test('validates month and quarter periods only', function () {
    expect(RoadmapPeriod::isValid('2026-09'))->toBeTrue();
    expect(RoadmapPeriod::isValid('2026-Q3'))->toBeTrue();
    expect(RoadmapPeriod::isValid('2026-13'))->toBeFalse();
    expect(RoadmapPeriod::isValid('2026-H1'))->toBeFalse();
    expect(RoadmapPeriod::isValid('2026-9'))->toBeFalse();
});

test('month index orders months and quarters on one axis', function () {
    expect(RoadmapPeriod::monthIndex('2026-09'))->toBe(2026 * 12 + 9);
    expect(RoadmapPeriod::monthIndex('2026-Q3'))->toBe(2026 * 12 + 9);
    expect(RoadmapPeriod::monthIndex('2027-01'))->toBeGreaterThan(RoadmapPeriod::monthIndex('2026-12'));
    expect(fn () => RoadmapPeriod::monthIndex('nope'))->toThrow(InvalidArgumentException::class);
});

test('type, label, latest and fromYearMonth', function () {
    expect(RoadmapPeriod::type('2026-09'))->toBe('month');
    expect(RoadmapPeriod::type('2026-Q3'))->toBe('quarter');
    expect(RoadmapPeriod::label('2026-09'))->toBe('2026 йил сентябрь');
    expect(RoadmapPeriod::label('2026-Q3'))->toBe('2026 йил III чорак');
    expect(RoadmapPeriod::label(null))->toBe('ҳисобот йўқ');
    expect(RoadmapPeriod::latest(['2026-07', '2026-09', '2026-08']))->toBe('2026-09');
    expect(RoadmapPeriod::latest([]))->toBeNull();
    expect(RoadmapPeriod::fromYearMonth(2026, 4))->toBe('2026-04');
});
