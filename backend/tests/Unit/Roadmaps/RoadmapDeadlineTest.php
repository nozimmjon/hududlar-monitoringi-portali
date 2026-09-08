<?php

use App\Support\Roadmaps\RoadmapDeadline;

test('deadline month is parsed from the text, last month of a range wins', function () {
    expect(RoadmapDeadline::month('2026 йил декабрь', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month('2026 йил апрель-октябрь', 2026))->toBe('2026-10');
    expect(RoadmapDeadline::month('2026 йил', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month('июн', 2026))->toBe('2026-06');
    expect(RoadmapDeadline::month('2027 йил март', 2026))->toBe('2027-03');   // year in text overrides
    expect(RoadmapDeadline::month('3-чорак', 2026))->toBe('2026-09');
    expect(RoadmapDeadline::month('йил давомида', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month(null, 2026))->toBe('2026-12');
});

test('reached compares the report period with the deadline month', function () {
    expect(RoadmapDeadline::reached('2026-09', '2026 йил декабрь', 2026))->toBeFalse();
    expect(RoadmapDeadline::reached('2026-12', '2026 йил декабрь', 2026))->toBeTrue();
    expect(RoadmapDeadline::reached('2026-Q4', '2026 йил декабрь', 2026))->toBeTrue();
    expect(RoadmapDeadline::reached('2026-11', '2026 йил апрель-октябрь', 2026))->toBeTrue();
});

test('months left and the «…гача» label', function () {
    expect(RoadmapDeadline::monthsLeft('2026 йил декабрь', 2026, '2026-09'))->toBe(3);
    expect(RoadmapDeadline::monthsLeft('2026 йил апрель-октябрь', 2026, '2026-11'))->toBe(-1);
    expect(RoadmapDeadline::untilLabel('2026-12'))->toBe('декабргача');
    expect(RoadmapDeadline::untilLabel('2026-04'))->toBe('апрелгача');
});
