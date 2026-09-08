<?php

use App\Models\District;
use App\Models\Roadmap;
use App\Models\RoadmapLineProgress;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('roadmaps and roadmap_measures tables exist with the expected columns', function () {
    expect(Schema::hasColumns('roadmaps', [
        'id', 'domain', 'region_code', 'year', 'title_text', 'approvers_text', 'source_file', 'imported_at',
    ]))->toBeTrue();
    expect(Schema::hasColumns('roadmap_measures', [
        'id', 'roadmap_id', 'section_no', 'section_title', 'district_id', 'district_head_text', 'seq_no',
        'title', 'details', 'body_raw', 'funding_text', 'deadline_text', 'responsible_text', 'source_row',
    ]))->toBeTrue();
});

test('a roadmap owns its measures, measures resolve district and split by level', function () {
    $this->seed();
    $roadmap = Roadmap::create([
        'region_code' => 1733, 'year' => 2026, 'title_text' => 'Хоразм йўл харитаси',
        'source_file' => '13. Хоразм.docx', 'imported_at' => now(),
    ]);
    expect($roadmap->domain)->toBe('water');
    expect($roadmap->region->name_short)->toBe('Хоразм');

    $bogot = District::where('code', 1733204)->firstOrFail();
    $roadmap->measures()->create([
        'section_no' => 1, 'section_title' => 'Йирик лойиҳалар', 'seq_no' => 1,
        'title' => 'Канал', 'body_raw' => 'Канал', 'source_row' => 2,
    ]);
    $roadmap->measures()->create([
        'section_no' => 5, 'section_title' => 'Туманларда амалга ошириладиган лойиҳалар',
        'district_id' => $bogot->id, 'district_head_text' => 'туман ҳокими Ж.Назаров', 'seq_no' => 1,
        'title' => 'Бетонлаштириш', 'details' => "1. 7,8 км;\n2. 33 км.", 'body_raw' => 'x', 'source_row' => 30,
    ]);

    expect($roadmap->measures()->count())->toBe(2);
    expect($roadmap->measures()->regionLevel()->count())->toBe(1);
    expect($roadmap->measures()->districtLevel()->count())->toBe(1);
    $m = RoadmapMeasure::districtLevel()->first();
    expect($m->district->name_full)->toBe('Боғот тумани');
    expect($m->detailLines())->toBe(['1. 7,8 км;', '2. 33 км.']);
    expect(RoadmapMeasure::regionLevel()->first()->detailLines())->toBe([]);
});

test('deleting a roadmap cascades to its measures', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1]);
    $roadmap->delete();
    expect(RoadmapMeasure::count())->toBe(0);
});

test('a duplicate position is rejected for district-level and region-level rows alike', function () {
    // Each create() below runs inside its own DB::transaction() (a savepoint, since
    // RefreshDatabase already wraps the test in a transaction) so that a failed insert
    // only rolls back to the savepoint instead of aborting the whole test transaction
    // (Postgres marks the enclosing transaction unusable after a failed statement).
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $bogot = District::where('code', 1733204)->firstOrFail();
    $base = ['title' => 't', 'body_raw' => 't', 'source_row' => 1, 'section_title' => 's'];

    DB::transaction(fn () => $roadmap->measures()->create($base + ['section_no' => 1, 'seq_no' => 1]));
    expect(fn () => DB::transaction(fn () => $roadmap->measures()->create($base + ['section_no' => 1, 'seq_no' => 1])))
        ->toThrow(QueryException::class);

    DB::transaction(fn () => $roadmap->measures()->create($base + ['section_no' => 5, 'district_id' => $bogot->id, 'seq_no' => 1]));
    expect(fn () => DB::transaction(fn () => $roadmap->measures()->create($base + ['section_no' => 5, 'district_id' => $bogot->id, 'seq_no' => 1])))
        ->toThrow(QueryException::class);
});

test('monitoring tables and columns exist', function () {
    expect(Schema::hasColumns('roadmap_measure_lines', ['id', 'roadmap_measure_id', 'line_no', 'label', 'unit', 'plan_value']))->toBeTrue();
    expect(Schema::hasColumns('roadmap_line_progress', [
        'id', 'roadmap_measure_line_id', 'report_period', 'period_type', 'actual_value', 'pct_of_plan', 'note', 'reported_at',
    ]))->toBeTrue();
    expect(Schema::hasColumns('roadmap_measures', ['latest_period', 'status', 'pct', 'lines_total', 'lines_done']))->toBeTrue();
});

test('a measure owns ordered lines, a line owns progress, and deletes cascade down', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $m = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1]);
    $m->refresh();
    expect($m->status)->toBe('in_progress');
    expect((int) $m->lines_total)->toBe(0);

    $m->lines()->create(['line_no' => 2, 'label' => 'Ички канал', 'unit' => 'км', 'plan_value' => 33]);
    $l1 = $m->lines()->create(['line_no' => 1, 'label' => 'Хўжаликлараро канал', 'unit' => 'км', 'plan_value' => 7.8]);
    $l1->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 7.8, 'pct_of_plan' => 100, 'note' => 'тайёр']);

    $m->refresh();
    expect($m->lines->pluck('line_no')->all())->toBe([1, 2]);
    expect($m->lines->first()->progress->first()->note)->toBe('тайёр');
    expect(RoadmapLineProgress::first()->line->label)->toBe('Хўжаликлараро канал');

    $m->delete();
    expect(RoadmapMeasureLine::count())->toBe(0);
    expect(RoadmapLineProgress::count())->toBe(0);
});

test('a line position and a progress period are unique', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $m = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1]);
    $line = DB::transaction(fn () => $m->lines()->create(['line_no' => 1, 'label' => 'a']));
    expect(fn () => DB::transaction(fn () => $m->lines()->create(['line_no' => 1, 'label' => 'b'])))->toThrow(QueryException::class);

    DB::transaction(fn () => $line->progress()->create(['report_period' => '2026-09', 'period_type' => 'month']));
    expect(fn () => DB::transaction(fn () => $line->progress()->create(['report_period' => '2026-09', 'period_type' => 'month'])))->toThrow(QueryException::class);
});
