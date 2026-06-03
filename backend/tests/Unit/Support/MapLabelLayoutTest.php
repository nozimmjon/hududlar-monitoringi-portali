<?php

use App\Support\MapLabelLayout;

// Four cells, one toward each edge of a shared bounding box (x 80..520, y 40..460 →
// mapW 440, mapH 420, centre (300,250)). Paths give a real bbox to crop to.
function mll_geometry(): array
{
    return [
        'viewBox' => '0 0 600 500',
        'cells' => [
            ['code' => 1, 'name' => 'T', 'cx' => 300.0, 'cy' => 60.0,  'path' => 'M 280 40 L 320 40 L 320 80 L 280 80 Z'],
            ['code' => 2, 'name' => 'R', 'cx' => 480.0, 'cy' => 250.0, 'path' => 'M 440 230 L 520 230 L 520 270 L 440 270 Z'],
            ['code' => 3, 'name' => 'B', 'cx' => 300.0, 'cy' => 440.0, 'path' => 'M 280 420 L 320 420 L 320 460 L 280 460 Z'],
            ['code' => 4, 'name' => 'L', 'cx' => 120.0, 'cy' => 250.0, 'path' => 'M 80 230 L 160 230 L 160 270 L 80 270 Z'],
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

test('produces one pill per labeled cell', function () {
    expect(MapLabelLayout::build(mll_geometry(), mll_labels())['pills'])->toHaveCount(4);
});

test('buckets each cell to the edge its centroid points toward', function () {
    $side = collect(MapLabelLayout::build(mll_geometry(), mll_labels())['pills'])->keyBy('code')->map(fn ($p) => $p['side']);
    expect($side[1])->toBe('top');
    expect($side[2])->toBe('right');
    expect($side[3])->toBe('bottom');
    expect($side[4])->toBe('left');
});

test('crops the viewBox height to the map bbox plus two thin bands', function () {
    // mapH 420 + 2*V_BAND(34) = 488
    $vb = MapLabelLayout::build(mll_geometry(), mll_labels())['viewBox'];
    expect((int) explode(' ', $vb)[3])->toBe(488);
});

test('map offset shifts the bbox top into the top band', function () {
    $r = MapLabelLayout::build(mll_geometry(), mll_labels());
    // ty = V_BAND(34) - minY(40) = -6
    expect($r['mapOffsetY'])->toBe(-6.0);
    expect($r['mapTransform'])->toContain('translate(');
});

test('each leader anchor is the centroid shifted by the map offset', function () {
    $r = MapLabelLayout::build(mll_geometry(), mll_labels());
    $by = collect($r['pills'])->keyBy('code');
    foreach (mll_geometry()['cells'] as $c) {
        expect($by[$c['code']]['dotY'])->toBe(round($c['cy'] + $r['mapOffsetY'], 1));
        expect($by[$c['code']]['dotX'])->toBe(round($c['cx'] + $r['mapOffsetX'], 1));
    }
});

test('top pill sits above the bottom pill, left pill left of the right pill', function () {
    $by = collect(MapLabelLayout::build(mll_geometry(), mll_labels())['pills'])->keyBy('code');
    expect($by[1]['y'])->toBeLessThan($by[3]['y']);   // top above bottom
    expect($by[4]['x'])->toBeLessThan($by[2]['x']);   // left left-of right
});

test('skips cells with no code or no matching label', function () {
    $geo = mll_geometry();
    $geo['cells'][] = ['code' => null, 'name' => 'X', 'cx' => 50.0, 'cy' => 50.0, 'path' => 'M 0 0 L 10 0 L 10 10 Z'];
    $geo['cells'][] = ['code' => 9, 'name' => 'Y', 'cx' => 60.0, 'cy' => 60.0, 'path' => 'M 0 0 L 10 0 L 10 10 Z'];
    expect(MapLabelLayout::build($geo, mll_labels())['pills'])->toHaveCount(4);
});
