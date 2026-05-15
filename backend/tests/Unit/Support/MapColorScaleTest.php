<?php

use App\Support\MapColorScale;

test('palette extreme 0.0 returns red for higher-is-better', function () {
    expect(MapColorScale::palette(0.0, false))->toBe('#d95757');
});

test('palette extreme 1.0 returns green for higher-is-better', function () {
    expect(MapColorScale::palette(1.0, false))->toBe('#4a9b5f');
});

test('palette 0.0 returns green for lower-is-better (inverted)', function () {
    expect(MapColorScale::palette(0.0, true))->toBe('#4a9b5f');
});

test('palette 1.0 returns red for lower-is-better (inverted)', function () {
    expect(MapColorScale::palette(1.0, true))->toBe('#d95757');
});

test('palette 0.5 returns yellow midpoint', function () {
    expect(MapColorScale::palette(0.5, false))->toBe('#e9c63b');
});

test('palette null returns no-data grey', function () {
    expect(MapColorScale::palette(null, false))->toBe(MapColorScale::NO_DATA);
});

test('palette interpolates between stops (0.125 between red and orange)', function () {
    $c = MapColorScale::palette(0.125, false);
    expect($c)->not->toBe('#d95757');
    expect($c)->not->toBe('#f0a356');
    expect($c)->toMatch('/^#[0-9a-f]{6}$/');
});

test('palette clamps out-of-range values', function () {
    expect(MapColorScale::palette(-0.5, false))->toBe('#d95757');
    expect(MapColorScale::palette(1.5, false))->toBe('#4a9b5f');
});
