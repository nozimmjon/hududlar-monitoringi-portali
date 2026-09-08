<?php

use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Services\Roadmaps\MeasureRecomputer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function recomputeFixtureMeasure(string $deadline = '2026 йил декабрь'): RoadmapMeasure
{
    $roadmap = Roadmap::firstOrCreate(['region_code' => 1733, 'year' => 2026], ['title_text' => 't', 'source_file' => 'f']);
    $seq     = $roadmap->measures()->count() + 1;

    return $roadmap->measures()->create([
        'section_no' => 1, 'section_title' => 's', 'seq_no' => $seq, 'title' => 't', 'body_raw' => 't', 'source_row' => $seq,
        'deadline_text' => $deadline,
    ]);
}

test('recompute writes latest period, counts, pct and status from the progress rows', function () {
    $this->seed();
    $m  = recomputeFixtureMeasure();
    $l1 = $m->lines()->create(['line_no' => 1, 'label' => 'a', 'unit' => 'та', 'plan_value' => 24]);
    $l2 = $m->lines()->create(['line_no' => 2, 'label' => 'b', 'unit' => 'та', 'plan_value' => 1]);
    $l1->progress()->create(['report_period' => '2026-08', 'period_type' => 'month', 'actual_value' => 6, 'pct_of_plan' => 25]);
    $l1->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 14, 'pct_of_plan' => 58.3333]);
    $l2->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 1, 'pct_of_plan' => 100]);

    (new MeasureRecomputer())->recompute($m->fresh(), 2026);

    $m->refresh();
    expect($m->latest_period)->toBe('2026-09');
    expect($m->lines_total)->toBe(2);
    expect($m->lines_done)->toBe(1);
    expect((float) $m->pct)->toBeNumericallyClose(79.17, 0.01);
    expect($m->status)->toBe('in_progress');            // December deadline not reached in September
});

test('recompute after the deadline flips a below-plan measure to open; no progress at all stays in_progress', function () {
    $this->seed();
    $m = recomputeFixtureMeasure('2026 йил апрель-октябрь');
    $l = $m->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10]);
    $l->progress()->create(['report_period' => '2026-11', 'period_type' => 'month', 'actual_value' => 3, 'pct_of_plan' => 30]);
    (new MeasureRecomputer())->recompute($m->fresh(), 2026);
    expect($m->fresh()->status)->toBe('open');

    $empty = recomputeFixtureMeasure();
    $empty->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10]);
    (new MeasureRecomputer())->recompute($empty->fresh(), 2026);
    $empty->refresh();
    expect($empty->status)->toBe('in_progress');
    expect($empty->latest_period)->toBeNull();
    expect($empty->lines_total)->toBe(1);
    expect($empty->pct)->toBeNull();
});

test('recompute is a no-op write when nothing changed', function () {
    $this->seed();
    $m = recomputeFixtureMeasure();
    $l = $m->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 24]);
    $l->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 14, 'pct_of_plan' => 58.3333]);
    (new MeasureRecomputer())->recompute($m->fresh(), 2026);
    $updatedAt = $m->fresh()->updated_at;

    $again = $m->fresh();
    $this->travel(1)->minutes();
    (new MeasureRecomputer())->recompute($again, 2026);

    expect($again->wasChanged())->toBeFalse();
    expect((string) $m->fresh()->updated_at)->toBe((string) $updatedAt);

    $this->travelBack();
});

test('lines reporting different periods: the latest period wins and a line without a row for it counts as 0', function () {
    $this->seed();
    $m  = recomputeFixtureMeasure();
    $l1 = $m->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10]);
    $l2 = $m->lines()->create(['line_no' => 2, 'label' => 'b', 'plan_value' => 10]);
    $l1->progress()->create(['report_period' => '2026-Q3', 'period_type' => 'quarter', 'actual_value' => 10, 'pct_of_plan' => 100]);
    $l2->progress()->create(['report_period' => '2026-08', 'period_type' => 'month', 'actual_value' => 10, 'pct_of_plan' => 100]);

    $values = (new MeasureRecomputer())->recompute($m->fresh(), 2026);

    expect($values['latest_period'])->toBe('2026-Q3');       // a quarter outranks the month it closes; 2026-08 is older anyway
    expect($values['lines_done'])->toBe(1);
    expect((float) $values['pct'])->toBe(50.0);              // line 2 has no Q3 row → 0
    expect($values['status'])->toBe('in_progress');          // December deadline not reached by Q3
    expect($m->fresh()->latest_period)->toBe('2026-Q3');
});

test('a malformed stored period names the measure', function () {
    $this->seed();
    $m = recomputeFixtureMeasure();
    $l = $m->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10]);
    $l->progress()->create(['report_period' => '2026-13', 'period_type' => 'month', 'actual_value' => 1]);

    expect(fn () => (new MeasureRecomputer())->recompute($m->fresh(), 2026))
        ->toThrow(InvalidArgumentException::class, "Measure #{$m->id}");
});
