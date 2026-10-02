<?php

use App\Support\Roadmaps\MeasureDisplay;
use App\Support\Roadmaps\RoadmapOverdue;

$m = fn (string $deadline, string $status) => ['deadline_text' => $deadline, 'status' => $status];

test('counts measures whose deadline month has passed and which are not done', function () use ($m) {
    $measures = [
        $m('2026 йил март', 'in_progress'),      // past, open      → counted
        $m('2026 йил март', 'open'),             // past, open      → counted
        $m('2026 йил март', 'done'),             // past but done   → not counted
        $m('2026 йил декабрь', 'in_progress'),   // future          → not counted
        $m('2026 йил октябрь', 'in_progress'),   // this month      → not counted
    ];

    expect(RoadmapOverdue::count($measures, 2026, '2026-10'))->toBe(2);
});

test('unreadable deadlines fall back to year-end, so they are never overdue mid-year', function () use ($m) {
    expect(RoadmapOverdue::count([$m('муддат кўрсатилмаган', 'in_progress')], 2026, '2026-10'))->toBe(0);
});

test('an empty sheet counts zero', function () {
    expect(RoadmapOverdue::count([], 2026, '2026-10'))->toBe(0);
});

test('the rule agrees with the red deadline chip', function () use ($m) {
    foreach ([['2026 йил март', 'in_progress'], ['2026 йил март', 'done'], ['2026 йил декабрь', 'open']] as [$d, $s]) {
        $chipSaysOver = MeasureDisplay::deadlineChip($d, 2026, $s, '2026-10')['cls'] === 'over';
        expect(RoadmapOverdue::count([$m($d, $s)], 2026, '2026-10') === 1)->toBe($chipSaysOver);
    }
});
