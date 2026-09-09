<?php

use App\Models\RoadmapMeasure;
use App\Support\Roadmaps\MeasureDisplay;
use Illuminate\Support\Collection;

test('displayed percent caps a not-done measure at 99 and keeps null', function () {
    expect(MeasureDisplay::pshow(100.0, false))->toBe(99);
    expect(MeasureDisplay::pshow(100.0, true))->toBe(100);
    expect(MeasureDisplay::pshow(58.6, false))->toBe(59);
    expect(MeasureDisplay::pshow(null, false))->toBeNull();
});

test('line tier and bar width on the 120 % scale', function () {
    expect(MeasureDisplay::tier(null))->toBe('none');
    expect(MeasureDisplay::tier(10.0))->toBe('red');
    expect(MeasureDisplay::tier(50.0))->toBe('amber');
    expect(MeasureDisplay::tier(100.0))->toBe('green');
    expect(MeasureDisplay::barWidth(null))->toBe(0.0);
    expect(MeasureDisplay::barWidth(60.0))->toBe(50.0);
    expect(MeasureDisplay::barWidth(240.0))->toBe(100.0);
    expect(MeasureDisplay::barWidth(-30.0))->toBe(0.0);
});

test('status and deadline chips', function () {
    expect(MeasureDisplay::statusChip('done'))->toBe(['cls' => 'ok', 'label' => 'Бажарилди']);
    expect(MeasureDisplay::statusChip('in_progress'))->toBe(['cls' => 'wait', 'label' => 'Бажарилмоқда']);
    expect(MeasureDisplay::statusChip('open'))->toBe(['cls' => 'bad', 'label' => 'Бажарилмаган']);

    expect(MeasureDisplay::deadlineChip('2026 йил декабрь', 2026, 'done', '2026-09'))->toBe(['cls' => 'done', 'label' => '2026 йил декабрь']);
    expect(MeasureDisplay::deadlineChip('2026 йил декабрь', 2026, 'in_progress', '2026-09'))->toBe(['cls' => 'due', 'label' => '2026 йил декабрь']);
    expect(MeasureDisplay::deadlineChip('2026 йил декабрь', 2026, 'in_progress', '2026-12'))->toBe(['cls' => 'due', 'label' => '2026 йил декабрь']);
    expect(MeasureDisplay::deadlineChip('2026 йил апрель-октябрь', 2026, 'open', '2026-11'))->toBe(['cls' => 'over', 'label' => '2026 йил апрель-октябрь']);
});

test('sparkline points and ring offset', function () {
    expect(MeasureDisplay::sparkPoints([['period' => '2026-08', 'pct' => 0.0], ['period' => '2026-09', 'pct' => 100.0]]))->toBe('3,25 117,3');
    expect(MeasureDisplay::sparkPoints([['period' => '2026-09', 'pct' => 50.0]]))->toBe('');
    expect(MeasureDisplay::ringOffset(null))->toBe('113.1');
    expect(MeasureDisplay::ringOffset(50))->toBe('56.6');
    expect(MeasureDisplay::ringOffset(100))->toBe('0.0');
    expect(MeasureDisplay::ringOffset(50, 282.7))->toBe('141.4');   // the hero ring (r=45)
});

test('mean percent counts an unreported measure as 0 and ignores measures without lines', function () {
    $measures = new Collection([
        new RoadmapMeasure(['lines_total' => 2, 'pct' => 50]),
        new RoadmapMeasure(['lines_total' => 1, 'pct' => null]),
        new RoadmapMeasure(['lines_total' => 0, 'pct' => 100]),   // no indicators yet — out of the mean
    ]);

    expect(MeasureDisplay::meanPct($measures))->toBe(25);
    expect(MeasureDisplay::meanPct(new Collection([new RoadmapMeasure(['lines_total' => 0, 'pct' => 100])])))->toBeNull();
    expect(MeasureDisplay::meanPct(new Collection()))->toBeNull();
});
