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

    // A manual edit of the actual is picked up on the next run.
    RoadmapLineProgress::first()->update(['actual_value' => 4, 'pct_of_plan' => 40]);
    Artisan::call('roadmaps:recompute', ['--region' => 1733]);
    expect($m->fresh()->status)->toBe('in_progress');   // below plan, deadline not reached
    expect((float) $m->fresh()->pct)->toBe(40.0);

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
    expect(Artisan::output())->toContain('no status flips');
});
