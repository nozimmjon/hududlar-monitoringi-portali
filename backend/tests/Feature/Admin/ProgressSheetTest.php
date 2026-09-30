<?php

use App\Services\Roadmaps\ProgressSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());
afterEach(fn () => Carbon::setTestNow());

test('a line is saved for the period, carried from the latest earlier value, or empty; measure states and counts follow', function () {
    $roadmap = AdminFixture::roadmap();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-07', [1 => 3.0, 2 => 10.0], [1 => 'июль изоҳи']);
    AdminFixture::report($bogot, '2026-08', [1 => 5.0]);                 // line 2 not reported in August (null row)

    $sheet = ProgressSheet::build($roadmap, '2026-09');
    $byId  = collect($sheet['measures'])->keyBy('id');
    $m     = $byId[$bogot->id];
    expect($m['state'])->toBe('carry');
    expect($m['lines'][0])->toMatchArray(['state' => 'carry', 'actual' => 5.0, 'note' => null, 'source_period' => '2026-08', 'saved_actual' => null, 'saved_note' => null]);
    expect($m['lines'][1])->toMatchArray(['state' => 'carry', 'actual' => 10.0, 'source_period' => '2026-07']);   // August's null row is skipped
    expect($m['lines'][0])->toMatchArray(['id' => AdminFixture::line($bogot, 1)->id, 'line_no' => 1, 'label' => 'Хўжаликлараро каналлар', 'unit' => 'км', 'plan' => 7.8]);
    expect($m)->toMatchArray(['seq_no' => 1, 'district_name' => 'Боғот тумани', 'district_code' => 1733204, 'district_head' => 'туман ҳокими Ж.Назаров', 'section_no' => 2, 'status' => 'in_progress', 'deadline_reached' => false]);
    expect($m['details'])->toBe(['1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.']);

    // a saved row for the period wins even when its actual is null
    $m8 = collect(ProgressSheet::build($roadmap, '2026-08')['measures'])->keyBy('id')[$bogot->id];
    expect($m8['state'])->toBe('saved');
    expect($m8['lines'][0])->toMatchArray(['state' => 'saved', 'actual' => 5.0, 'saved_actual' => 5.0, 'source_period' => null]);
    expect($m8['lines'][1])->toMatchArray(['state' => 'saved', 'actual' => null, 'saved_actual' => null]);

    // untouched measures are empty; the June deadline of I/2 is reached in September
    expect($byId[AdminFixture::measure(1, 1)->id]['state'])->toBe('empty');
    expect($byId[AdminFixture::measure(1, 2)->id]['deadline_reached'])->toBeTrue();
    expect($sheet['counts'])->toBe(['saved' => 0, 'carry' => 1, 'empty' => 3, 'confirmable' => 4]);
    expect(array_column($sheet['measures'], 'seq_no'))->toBe([1, 2, 1, 1]);     // document order
});

test('a quarter carries as its last month; periods run from January to the current month, newest first', function () {
    $roadmap = AdminFixture::roadmap();
    $gurlan  = AdminFixture::measure(2, 1, 1733208);
    AdminFixture::report($gurlan, '2026-Q2', [1 => 6.0]);

    $july = collect(ProgressSheet::build($roadmap, '2026-07')['measures'])->keyBy('id')[$gurlan->id]['lines'][0];
    expect($july)->toMatchArray(['state' => 'carry', 'actual' => 6.0, 'source_period' => '2026-Q2']);
    $june = collect(ProgressSheet::build($roadmap, '2026-06')['measures'])->keyBy('id')[$gurlan->id]['lines'][0];
    expect($june['state'])->toBe('empty');                                // the quarter closes June — not "before" June

    Carbon::setTestNow('2026-09-15 10:00:00');
    expect(ProgressSheet::currentPeriod())->toBe('2026-09');
    expect(ProgressSheet::periodOptions(2026))->toBe(['2026-09', '2026-08', '2026-07', '2026-06', '2026-05', '2026-04', '2026-03', '2026-02', '2026-01']);
    Carbon::setTestNow('2025-12-01 10:00:00');                            // before the road-map year: at least January
    expect(ProgressSheet::periodOptions(2026))->toBe(['2026-01']);
    Carbon::setTestNow('2026-09-30 20:30:00');                            // 01:30 on 1 October in Tashkent
    expect(ProgressSheet::currentPeriod())->toBe('2026-10');
});
