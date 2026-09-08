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

test('spelling variants expected in the other regions', function () {
    expect(RoadmapDeadline::month('I чорак', 2026))->toBe('2026-03');
    expect(RoadmapDeadline::month('2026 йил IV чорак', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month('2026 декабр', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month('2026 йил Декабрь', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month("2026\u{00A0}йил\u{00A0}июль", 2026))->toBe('2026-07');
    expect(RoadmapDeadline::month('2026 йил майгача', 2026))->toBe('2026-05');
    expect(RoadmapDeadline::month('2026 йил декабрда', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month('2025 йилдан 2026 йил декабргача', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month('ПҚ-2019 сонли қарорга асосан декабрь', 2026))->toBe('2026-12');   // decree number is not a year
    expect(RoadmapDeadline::month('сув майдонларини кенгайтириш', 2026))->toBe('2026-12');           // «май» inside a word is not May
    expect(RoadmapDeadline::month('2 марта текшириш', 2026))->toBe('2026-12');                       // «марта» = times, not March
    expect(RoadmapDeadline::month('aprel-oktyabr', 2026))->toBe('2026-12');                          // Latin script → year-end (known limitation)
});

test('zero months left and a quarter string in untilLabel', function () {
    expect(RoadmapDeadline::monthsLeft('2026 йил декабрь', 2026, '2026-12'))->toBe(0);
    expect(RoadmapDeadline::untilLabel('2026-Q4'))->toBe('декабргача');
});

test('hyphenated year and genitive month endings', function () {
    expect(RoadmapDeadline::month('2027-йил март', 2026))->toBe('2027-03');
    expect(RoadmapDeadline::month('2026 йил сентябри', 2026))->toBe('2026-09');
    expect(RoadmapDeadline::month('2026 йил октябрида', 2026))->toBe('2026-10');
    expect(RoadmapDeadline::month('декабрнинг охири', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month('сув майдонларини кенгайтириш', 2026))->toBe('2026-12');   // still not May
});

test('years before the road-map year are citations, not deadlines', function () {
    expect(RoadmapDeadline::month('2019-йил 17-июндаги ПФ-5742-сон қарорга асосан декабрь', 2026))->toBe('2026-12');
    expect(RoadmapDeadline::month('2019 йил қарори бўйича 2027 йил март', 2026))->toBe('2027-03');
    expect(RoadmapDeadline::month('2025 йил декабрь', 2026))->toBe('2026-12');   // earlier than the map itself → map year
});
