<?php

use App\Support\MapLabelLayout;

function mll_geometry(): array
{
    return [
        'viewBox' => '0 0 600 500',
        'cells' => [
            ['code' => 1, 'name' => 'A', 'path' => '', 'cx' => 100.0, 'cy' => 50.0],
            ['code' => 2, 'name' => 'B', 'path' => '', 'cx' => 120.0, 'cy' => 400.0],
            ['code' => 3, 'name' => 'C', 'path' => '', 'cx' => 500.0, 'cy' => 80.0],
            ['code' => 4, 'name' => 'D', 'path' => '', 'cx' => 480.0, 'cy' => 300.0],
        ],
    ];
}

function mll_labels(): array
{
    return [
        1 => ['name' => 'Бир',  'value' => '120%', 'color' => 'ok'],
        2 => ['name' => 'Икки', 'value' => '80%',  'color' => 'bad'],
        3 => ['name' => 'Уч',   'value' => '—',    'color' => 'nd'],
        4 => ['name' => 'Тўрт', 'value' => '150%', 'color' => 'ok'],
    ];
}

test('expands the viewBox by the gutter on both sides', function () {
    $r = MapLabelLayout::build(mll_geometry(), mll_labels(), 230);
    expect($r['viewBox'])->toBe('0 0 1060 500');
    expect($r['mapTranslate'])->toBe(230);
});

test('produces one pill per labeled cell', function () {
    $r = MapLabelLayout::build(mll_geometry(), mll_labels());
    expect($r['pills'])->toHaveCount(4);
});

test('assigns sides by centroid x relative to map center', function () {
    $byCode = collect(MapLabelLayout::build(mll_geometry(), mll_labels())['pills'])->keyBy('code');
    expect($byCode[1]['side'])->toBe('L');
    expect($byCode[2]['side'])->toBe('L');
    expect($byCode[3]['side'])->toBe('R');
    expect($byCode[4]['side'])->toBe('R');
});

test('left pills sit in the gutter and right pills stay within the canvas', function () {
    foreach (MapLabelLayout::build(mll_geometry(), mll_labels(), 230)['pills'] as $p) {
        if ($p['side'] === 'L') {
            expect($p['x'])->toBeLessThan(230.0);
        } else {
            expect($p['x'])->toBeGreaterThan(600.0);
            expect($p['x'] + $p['w'])->toBeLessThanOrEqual(1060.0);
        }
    }
});

test('pills on a side increase in y and follow centroid order', function () {
    $left = collect(MapLabelLayout::build(mll_geometry(), mll_labels())['pills'])->where('side', 'L')->values();
    expect($left[0]['code'])->toBe(1);
    expect($left[1]['code'])->toBe(2);
    expect($left[1]['y'])->toBeGreaterThan($left[0]['y']);
});

test('each leader anchor is the centroid shifted by the gutter', function () {
    $p1 = collect(MapLabelLayout::build(mll_geometry(), mll_labels(), 230)['pills'])->firstWhere('code', 1);
    expect($p1['dotX'])->toBe(330.0);
    expect($p1['dotY'])->toBe(50.0);
});

test('skips cells with no code or no matching label', function () {
    $geo = mll_geometry();
    $geo['cells'][] = ['code' => null, 'name' => 'X', 'path' => '', 'cx' => 50.0, 'cy' => 50.0];
    $geo['cells'][] = ['code' => 9, 'name' => 'Y', 'path' => '', 'cx' => 60.0, 'cy' => 60.0];
    expect(MapLabelLayout::build($geo, mll_labels())['pills'])->toHaveCount(4);
});
