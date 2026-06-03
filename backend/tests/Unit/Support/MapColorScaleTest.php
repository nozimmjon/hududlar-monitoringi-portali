<?php

use App\Support\MapColorScale;

test('palette extreme 0.0 returns low-tier terracotta for higher-is-better', function () {
    expect(MapColorScale::palette(0.0, false))->toBe('#cf7e6b');
});

test('palette extreme 1.0 returns high-tier green for higher-is-better', function () {
    expect(MapColorScale::palette(1.0, false))->toBe('#6fa888');
});

test('palette 0.0 returns high-tier green for lower-is-better (inverted)', function () {
    expect(MapColorScale::palette(0.0, true))->toBe('#6fa888');
});

test('palette 1.0 returns low-tier terracotta for lower-is-better (inverted)', function () {
    expect(MapColorScale::palette(1.0, true))->toBe('#cf7e6b');
});

test('palette 0.5 returns the wheat midpoint', function () {
    expect(MapColorScale::palette(0.5, false))->toBe('#e8cf8e');
});

test('palette null returns no-data grey', function () {
    expect(MapColorScale::palette(null, false))->toBe(MapColorScale::NO_DATA);
});

test('palette interpolates between stops (0.125 between stop 0 and stop 1)', function () {
    $c = MapColorScale::palette(0.125, false);
    expect($c)->not->toBe('#cf7e6b');
    expect($c)->not->toBe('#e0a878');
    expect($c)->toMatch('/^#[0-9a-f]{6}$/');
});

test('palette clamps out-of-range values', function () {
    expect(MapColorScale::palette(-0.5, false))->toBe('#cf7e6b');
    expect(MapColorScale::palette(1.5, false))->toBe('#6fa888');
});
