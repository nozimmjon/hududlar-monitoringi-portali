<?php

use App\Models\District;
use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
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
    $bogot   = District::where('code', 1733204)->firstOrFail();
    $base    = ['title' => 't', 'body_raw' => 't', 'source_row' => 1, 'section_title' => 's'];

    DB::transaction(fn () => $roadmap->measures()->create($base + ['section_no' => 1, 'seq_no' => 1]));
    expect(fn () => DB::transaction(fn () => $roadmap->measures()->create($base + ['section_no' => 1, 'seq_no' => 1])))
        ->toThrow(QueryException::class);

    DB::transaction(fn () => $roadmap->measures()->create($base + ['section_no' => 5, 'district_id' => $bogot->id, 'seq_no' => 1]));
    expect(fn () => DB::transaction(fn () => $roadmap->measures()->create($base + ['section_no' => 5, 'district_id' => $bogot->id, 'seq_no' => 1])))
        ->toThrow(QueryException::class);
});
