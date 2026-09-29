<?php

use App\Models\Roadmap;
use App\Models\RoadmapLineProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('roadmaps:recompute rebuilds status/pct from progress rows and reports counts; --dry-run writes nothing', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $m = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1, 'deadline_text' => '2026 йил декабрь']);
    $l = $m->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10]);
    $l->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 10, 'pct_of_plan' => 100]);

    expect(Artisan::call('roadmaps:recompute', ['--dry-run' => true]))->toBe(0);
    expect($m->fresh()->status)->toBe('in_progress');
    expect(Artisan::output())->toContain('Dry run');

    expect(Artisan::call('roadmaps:recompute'))->toBe(0);
    $m->refresh();
    expect($m->status)->toBe('done');
    expect((float) $m->pct)->toBe(100.0);
    // Artisan::output() drains its buffer on read (Symfony BufferedOutput::fetch()), so capture once and reuse.
    $output = Artisan::output();
    expect($output)->toContain('done: 1');
    expect($output)->toContain('in_progress→done');
    expect($output)->toContain('1 updated');
    expect($output)->toContain('[1733]');

    // A manual edit of the actual is picked up on the next run.
    RoadmapLineProgress::first()->update(['actual_value' => 4, 'pct_of_plan' => 40]);
    Artisan::call('roadmaps:recompute', ['--region' => 1733]);
    expect($m->fresh()->status)->toBe('in_progress');   // below plan, deadline not reached
    expect((float) $m->fresh()->pct)->toBe(40.0);

    // …and so is a plan edited after the fact: pct_of_plan is stored, so the repair is the
    // whole point of this command being the universal fix-up tool.
    $l->update(['plan_value' => 8]);
    expect(Artisan::call('roadmaps:recompute', ['--region' => 1733]))->toBe(0);
    expect(Artisan::output())->toContain('1 reported percentage(s) recomputed');
    expect((float) RoadmapLineProgress::first()->pct_of_plan)->toBe(50.0);
    expect((float) $m->fresh()->pct)->toBe(50.0);

    expect(Artisan::call('roadmaps:recompute', ['--region' => 9999]))->toBe(1);
});

test('roadmaps:recompute touches each measure at most once and does not rewrite unchanged rows', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $m = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1]);
    $m->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10])
        ->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 10, 'pct_of_plan' => 100]);
    Artisan::call('roadmaps:recompute');
    $updatedAt = (string) $m->fresh()->updated_at;

    $this->travel(1)->minutes();
    Artisan::call('roadmaps:recompute');
    $this->travelBack();

    expect((string) $m->fresh()->updated_at)->toBe($updatedAt);
    $output = Artisan::output();
    expect($output)->toContain('no status flips');
    expect($output)->toContain('0 updated');
});

test('--region scopes the run and each road map is recomputed with its own year', function () {
    $this->seed();
    $khorezm = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $andijan = Roadmap::create(['region_code' => 1703, 'year' => 2027, 'title_text' => 't', 'source_file' => 'f']);
    foreach ([$khorezm, $andijan] as $roadmap) {
        $m = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1, 'deadline_text' => 'декабрь']);
        $m->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10])
            ->progress()->create(['report_period' => '2026-12', 'period_type' => 'month', 'actual_value' => 4, 'pct_of_plan' => 40]);
    }

    expect(Artisan::call('roadmaps:recompute', ['--region' => 1733]))->toBe(0);
    expect($khorezm->measures()->first()->status)->toBe('open');            // 2026 map: December 2026 reached → verdict
    expect($andijan->measures()->first()->latest_period)->toBeNull();       // untouched by the scoped run

    expect(Artisan::call('roadmaps:recompute'))->toBe(0);
    expect($andijan->measures()->first()->status)->toBe('in_progress');     // 2027 map: its December is a year away
    expect(Artisan::output())->toContain('2 road map(s) [1703, 1733]');

    expect(Artisan::call('roadmaps:recompute', ['--region' => '17x']))->toBe(1);
    expect(Artisan::output())->toContain('--region');
});

test('a malformed stored period aborts with an operator message and writes nothing', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    $good = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 't', 'body_raw' => 't', 'source_row' => 1]);
    $good->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10])
        ->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 10, 'pct_of_plan' => 100]);
    $bad = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 2, 'title' => 't', 'body_raw' => 't', 'source_row' => 2]);
    $bad->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10])
        ->progress()->create(['report_period' => '2026-13', 'period_type' => 'month', 'actual_value' => 1]);

    expect(Artisan::call('roadmaps:recompute'))->toBe(1);
    $output = Artisan::output();
    expect($output)->toContain("Measure #{$bad->id}");
    expect($output)->toContain('nothing written');
    expect($good->fresh()->status)->toBe('in_progress');                      // the good measure's write was rolled back too
});
