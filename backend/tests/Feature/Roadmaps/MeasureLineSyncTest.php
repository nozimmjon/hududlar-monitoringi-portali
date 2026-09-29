<?php

use App\Models\Roadmap;
use App\Models\RoadmapLineProgress;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use App\Services\Roadmaps\MeasureLineSync;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** One region-level measure of an otherwise empty Хоразм road map. */
function lineSyncMeasure(): RoadmapMeasure
{
    $roadmap = Roadmap::create([
        'domain' => 'water', 'region_code' => 1733, 'year' => 2026,
        'title_text' => 'Сув хўжалиги йўл харитаси: Хоразм вилояти', 'source_file' => 'тест.xlsx',
    ]);

    return $roadmap->measures()->create([
        'section_no' => 1, 'section_title' => 'Йирик лойиҳалар', 'seq_no' => 1,
        'title' => 'Каналларни бетонлаштириш', 'body_raw' => 'Каналларни бетонлаштириш',
        'deadline_text' => '2026 йил декабрь', 'source_row' => 4,
    ]);
}

/** @return list<array{label:string, unit:?string, plan:?float, actual:?float, note:?string}> */
function lineSyncLines(array ...$lines): array
{
    return array_map(fn (array $l) => $l + ['label' => 'Индикатор', 'unit' => 'км', 'plan' => 10.0, 'actual' => null, 'note' => null], $lines);
}

test('an empty line list leaves the stored lines and their history alone', function () {
    $this->seed();
    $measure = lineSyncMeasure();
    MeasureLineSync::sync($measure, lineSyncLines(['label' => 'Биринчи'], ['label' => 'Иккинчи']), '2026-08', 2026);
    $measure->refresh();

    $result = MeasureLineSync::sync($measure, [], null, 2026);

    // A file that lost its indicator rows is likelier truncated than a measure that stopped being measured.
    expect($result)->toMatchArray(['lines' => 0, 'removed' => 0, 'reported' => 0]);
    expect(RoadmapMeasureLine::count())->toBe(2);
    expect(RoadmapLineProgress::count())->toBe(2);
    expect($measure->refresh()->lines_total)->toBe(2);
    expect($measure->latest_period)->toBe('2026-08');
});

test('a definitions-only sync keeps an earlier period’s actuals', function () {
    $this->seed();
    $measure = lineSyncMeasure();
    MeasureLineSync::sync($measure, lineSyncLines(['label' => 'Биринчи', 'actual' => 10.0, 'note' => 'тайёр']), '2026-08', 2026);
    $measure->refresh();
    expect($measure->status)->toBe('done');

    // The same definitions, no period: the plan may be corrected, the reported past is not touched.
    $result = MeasureLineSync::sync($measure, lineSyncLines(['label' => 'Биринчи', 'plan' => 12.0]), null, 2026);

    expect($result)->toMatchArray(['lines' => 1, 'removed' => 0, 'reported' => 0, 'cleared' => 0]);
    expect(RoadmapLineProgress::count())->toBe(1);
    $progress = RoadmapLineProgress::firstOrFail();
    expect((float) $progress->actual_value)->toBe(10.0);
    expect($progress->note)->toBe('тайёр');
    expect($progress->report_period)->toBe('2026-08');

    $measure->refresh();
    expect((float) $measure->lines->first()->plan_value)->toBe(12.0);
    expect($measure->latest_period)->toBe('2026-08');
    // `pct_of_plan` is frozen at the moment the actual was imported (10/10 = 100 %), and a
    // definitions-only sync writes no progress — so a corrected plan does not re-judge a past
    // period. The next report of that period recomputes it; this is the same rule the template
    // pipeline has always followed.
    expect($measure->status)->toBe('done');
    expect((float) RoadmapLineProgress::firstOrFail()->pct_of_plan)->toBe(100.0);
});

test('lines beyond the new count are removed with their history, and a relabel is reported', function () {
    $this->seed();
    $measure = lineSyncMeasure();
    MeasureLineSync::sync(
        $measure,
        lineSyncLines(['label' => 'Биринчи', 'actual' => 4.0], ['label' => 'Иккинчи'], ['label' => 'Учинчи']),
        '2026-08',
        2026,
    );
    $measure->refresh();
    expect(RoadmapMeasureLine::count())->toBe(3);
    expect(RoadmapLineProgress::count())->toBe(3);

    $result = MeasureLineSync::sync($measure, lineSyncLines(['label' => 'Биринчи (бетон)']), '2026-09', 2026);

    expect($result)->toMatchArray(['lines' => 1, 'removed' => 2, 'reported' => 0, 'relabeled' => 1]);
    expect($result['blank_advance'])->toBeTrue();
    expect(RoadmapMeasureLine::count())->toBe(1);
    expect(RoadmapLineProgress::count())->toBe(2);        // line 1 keeps August and gains an empty September
    expect($measure->refresh()->lines->first()->label)->toBe('Биринчи (бетон)');
    expect($measure->latest_period)->toBe('2026-09');
});
