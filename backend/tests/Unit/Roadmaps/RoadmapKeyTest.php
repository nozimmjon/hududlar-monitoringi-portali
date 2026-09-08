<?php

use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\Roman;

test('makes and parses the natural key; region-level rows use district 0', function () {
    expect(RoadmapKey::make(1733, 5, 1733208, 3))->toBe('1733-5-1733208-3');
    expect(RoadmapKey::make(1733, 1, null, 2))->toBe('1733-1-0-2');
    expect(RoadmapKey::parse('1733-5-1733208-3'))->toBe(['region' => 1733, 'section' => 5, 'district' => 1733208, 'seq' => 3]);
    expect(RoadmapKey::parse('1733-1-0-2'))->toBe(['region' => 1733, 'section' => 1, 'district' => 0, 'seq' => 2]);
    expect(RoadmapKey::isKey('1733-5-1733208-3'))->toBeTrue();
    expect(RoadmapKey::isKey('Калит'))->toBeFalse();
    expect(RoadmapKey::isKey(null))->toBeFalse();
    expect(RoadmapKey::isKey('1733-5-1733208'))->toBeFalse();
    expect(fn () => RoadmapKey::parse('x'))->toThrow(InvalidArgumentException::class);
});

test('roman numerals', function () {
    expect(Roman::of(1))->toBe('I');
    expect(Roman::of(4))->toBe('IV');
    expect(Roman::of(6))->toBe('VI');
    expect(Roman::of(9))->toBe('IX');
});
