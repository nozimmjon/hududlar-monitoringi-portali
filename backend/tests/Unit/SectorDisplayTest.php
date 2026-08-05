<?php

use App\Models\SectorTask;
use App\Support\SectorDisplay;

test('tier maps percent to traffic-light buckets', function () {
    expect(SectorDisplay::tier(null))->toBe('wait');
    expect(SectorDisplay::tier(120.0))->toBe('ok');
    expect(SectorDisplay::tier(100.0))->toBe('ok');
    expect(SectorDisplay::tier(99.9))->toBe('warn');
    expect(SectorDisplay::tier(50.0))->toBe('warn');
    expect(SectorDisplay::tier(49.9))->toBe('bad');
    expect(SectorDisplay::tier(0.0))->toBe('bad');
});

test('pshow applies the 99-cap rule', function () {
    expect(SectorDisplay::pshow(null, false))->toBeNull();
    expect(SectorDisplay::pshow(119.6, true))->toBe(120);   // done → real rounded value
    expect(SectorDisplay::pshow(99.6, false))->toBe(99);    // not done → capped at 99
    expect(SectorDisplay::pshow(42.4, false))->toBe(42);
});

test('fmt renders numbers with space thousands and comma decimals', function () {
    expect(SectorDisplay::fmt(null))->toBe('—');
    expect(SectorDisplay::fmt(1234.5))->toBe('1 234,5');
    expect(SectorDisplay::fmt(56.0))->toBe('56');
    expect(SectorDisplay::fmt(0.25))->toBe('0,25');
});

test('taskPct: in_progress is null, multi-line uses line ratio, single-line uses headline_pct', function () {
    $wait = new SectorTask(['status' => 'in_progress', 'lines_total' => 3, 'lines_done' => 0]);
    expect(SectorDisplay::taskPct($wait))->toBeNull();

    $multi = new SectorTask(['status' => 'open', 'lines_total' => 4, 'lines_done' => 1]);
    expect(SectorDisplay::taskPct($multi))->toBe(25.0);

    $single = new SectorTask(['status' => 'done', 'lines_total' => 1, 'lines_done' => 1, 'headline_pct' => '119.05']);
    expect(SectorDisplay::taskPct($single))->toBeNumericallyClose(119.05);
});

test('deadline constants cover the four import buckets in order', function () {
    expect(array_keys(SectorDisplay::DEADLINE_ORDER))->toBe(['q3', 'q4', 'h2', 'year']);
    expect(SectorDisplay::DEADLINE_LABELS['q3'])->toBe('III чорак');
    expect(SectorDisplay::DEADLINE_LABELS['q4'])->toBe('IV чорак');
    expect(SectorDisplay::DEADLINE_LABELS['h2'])->toBe('2-ярим йиллик');
    expect(SectorDisplay::DEADLINE_LABELS['year'])->toBe('Йил якуни');
});
