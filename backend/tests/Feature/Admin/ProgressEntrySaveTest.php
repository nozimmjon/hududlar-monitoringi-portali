<?php

use App\Models\RoadmapChangeLog;
use App\Models\RoadmapLineProgress;
use App\Services\Roadmaps\ProgressEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());
afterEach(fn () => Carbon::setTestNow());

test('save writes the whole touched measure — edited and carried lines — recomputes it and logs only the changes', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-08', [1 => 3.0, 2 => 10.0], [2 => 'давом этмоқда']);
    $l1 = AdminFixture::line($bogot, 1);
    $l2 = AdminFixture::line($bogot, 2);

    $r = ProgressEntry::save($roadmap, '2026-09', [$l1->id => ['actual' => '7,8']], $user);
    expect($r)->toBe(['measures' => 1, 'changes' => 1]);

    $p1 = $l1->progress()->where('report_period', '2026-09')->firstOrFail();
    $p2 = $l2->progress()->where('report_period', '2026-09')->firstOrFail();
    expect((float) $p1->actual_value)->toBe(7.8);
    expect((float) $p2->actual_value)->toBe(10.0);                       // carried from August
    expect($p2->note)->toBe('давом этмоқда');                             // notes carry too
    expect($p1->period_type)->toBe('month');
    expect($p1->reported_at->toDateString())->toBe(now()->toDateString());
    $bogot->refresh();
    expect($bogot->latest_period)->toBe('2026-09');
    expect($bogot->lines_done)->toBe(1);
    expect((float) $bogot->pct)->toBeNumericallyClose(65.15, 0.01);      // (100 + 30.3) / 2
    expect($bogot->status)->toBe('in_progress');                         // December deadline, September report
    expect(RoadmapLineProgress::where('report_period', '2026-09')->count())->toBe(2);   // the other measures were not touched

    $log = RoadmapChangeLog::all();
    expect($log)->toHaveCount(1);
    expect($log[0])->toMatchArray([
        'user_id' => $user->id, 'roadmap_id' => $roadmap->id, 'roadmap_measure_id' => $bogot->id,
        'roadmap_measure_line_id' => $l1->id, 'line_label' => 'Хўжаликлараро каналлар', 'report_period' => '2026-09', 'action' => 'actual',
        'old_value' => null, 'new_value' => '7,8',
    ]);
});

test('a second save logs the old stored value; clearing a cell logs null; an edit that changes nothing logs nothing', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $gurlan  = AdminFixture::measure(2, 1, 1733208);
    $line    = AdminFixture::line($gurlan, 1);
    AdminFixture::report($gurlan, '2026-09', [1 => 12.0], [1 => 'ярми']);

    ProgressEntry::save($roadmap, '2026-09', [$line->id => ['actual' => '24', 'note' => 'ярми']], $user);   // note unchanged
    ProgressEntry::save($roadmap, '2026-09', [$line->id => ['note' => '']], $user);                          // note cleared
    $r = ProgressEntry::save($roadmap, '2026-09', [$line->id => ['actual' => '24,0']], $user);               // nothing changes
    expect($r)->toBe(['measures' => 1, 'changes' => 0]);

    $rows = RoadmapChangeLog::orderBy('id')->get();
    expect($rows->pluck('action')->all())->toBe(['actual', 'note']);
    expect($rows[0])->toMatchArray(['old_value' => '12', 'new_value' => '24']);
    expect($rows[1])->toMatchArray(['old_value' => 'ярми', 'new_value' => null]);
    $p = $line->progress()->where('report_period', '2026-09')->firstOrFail();
    expect((float) $p->actual_value)->toBe(24.0);
    expect($p->note)->toBeNull();
    expect($gurlan->fresh()->status)->toBe('done');
});

test('a bad number, a negative, a foreign line or a bad period refuses the whole save before anything is written', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $l1 = AdminFixture::line($bogot, 1);
    $l2 = AdminFixture::line($bogot, 2);

    expect(fn () => ProgressEntry::save($roadmap, '2026-09', [$l1->id => ['actual' => '5'], $l2->id => ['actual' => 'кўп']], $user))
        ->toThrow(InvalidArgumentException::class, '«Ички каналлар»: рақам эмас');
    expect(fn () => ProgressEntry::save($roadmap, '2026-09', [$l1->id => ['actual' => '-1']], $user))
        ->toThrow(InvalidArgumentException::class, 'манфий');
    expect(fn () => ProgressEntry::save($roadmap, '2026-09', [999999 => ['actual' => '5']], $user))
        ->toThrow(InvalidArgumentException::class, '#999999');
    expect(fn () => ProgressEntry::save($roadmap, '2026-13', [$l1->id => ['actual' => '5']], $user))
        ->toThrow(InvalidArgumentException::class, '2026-13');
    expect(ProgressEntry::save($roadmap, '2026-09', [], $user))->toBe(['measures' => 0, 'changes' => 0]);
    expect(RoadmapLineProgress::count())->toBe(0);
    expect(RoadmapChangeLog::count())->toBe(0);
});

test('confirmRest writes carried values for every unsaved measure with lines and logs one confirm per measure', function () {
    Carbon::setTestNow('2026-09-15 10:00:00');
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $gurlan  = AdminFixture::measure(2, 1, 1733208);
    $m2      = AdminFixture::measure(1, 2);
    AdminFixture::report($bogot, '2026-08', [1 => 7.8, 2 => 33.0]);
    AdminFixture::report($m2, '2026-08', [1 => 100.0]);
    ProgressEntry::save($roadmap, '2026-09', [AdminFixture::line($gurlan, 1)->id => ['actual' => '24']], $user);   // Гурлан already saved

    expect(ProgressEntry::confirmRest($roadmap, '2026-09', $user))->toBe(3);          // Боғот, I/1 (empty), I/2 — not Гурлан
    expect(RoadmapLineProgress::where('report_period', '2026-09')->count())->toBe(5);
    expect($bogot->fresh()->status)->toBe('done');
    expect($bogot->fresh()->latest_period)->toBe('2026-09');
    expect($m2->fresh()->status)->toBe('open');                                       // June deadline passed, 100 of 484,5
    expect(AdminFixture::measure(1, 1)->latest_period)->toBe('2026-09');              // the empty line was written as null
    expect(AdminFixture::measure(1, 1)->status)->toBe('in_progress');
    $confirms = RoadmapChangeLog::where('action', 'confirm')->get();
    expect($confirms)->toHaveCount(3);
    expect($confirms->firstWhere('roadmap_measure_id', $bogot->id)->new_value)->toBe('2 та қатор');
    expect(ProgressEntry::confirmRest($roadmap, '2026-09', $user))->toBe(0);          // nothing left
});

test('a save touching two measures at once writes and recomputes both', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $gurlan  = AdminFixture::measure(2, 1, 1733208);
    $b1      = AdminFixture::line($bogot, 1);
    $g1      = AdminFixture::line($gurlan, 1);

    $r = ProgressEntry::save($roadmap, '2026-09', [$b1->id => ['actual' => '5'], $g1->id => ['actual' => '10']], $user);

    expect($r)->toBe(['measures' => 2, 'changes' => 2]);
    expect($bogot->fresh()->latest_period)->toBe('2026-09');
    expect($gurlan->fresh()->latest_period)->toBe('2026-09');
});

test('a note-only edit on a line whose actual is carried leaves the actual carried and unlogged', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-08', [1 => 3.0, 2 => 10.0]);
    $l1 = AdminFixture::line($bogot, 1);
    $l2 = AdminFixture::line($bogot, 2);

    $r = ProgressEntry::save($roadmap, '2026-09', [$l1->id => ['note' => 'янги изоҳ']], $user);
    expect($r)->toBe(['measures' => 1, 'changes' => 1]);

    $log = RoadmapChangeLog::all();
    expect($log)->toHaveCount(1);
    expect($log[0]->action)->toBe('note');

    $p1 = $l1->progress()->where('report_period', '2026-09')->firstOrFail();
    $p2 = $l2->progress()->where('report_period', '2026-09')->firstOrFail();
    expect((float) $p1->actual_value)->toBe(3.0);                        // carried, unlogged
    expect($p1->note)->toBe('янги изоҳ');
    expect((float) $p2->actual_value)->toBe(10.0);                       // carried
});

test('confirmRest on a measure with a mix of carried and empty lines writes both, one with a null actual', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-08', [1 => 3.0]);                 // line 2 never reported
    $l1 = AdminFixture::line($bogot, 1);
    $l2 = AdminFixture::line($bogot, 2);

    expect(ProgressEntry::confirmRest($roadmap, '2026-09', $user))->toBe(4);   // every measure in the roadmap

    $p1 = $l1->progress()->where('report_period', '2026-09')->firstOrFail();
    $p2 = $l2->progress()->where('report_period', '2026-09')->firstOrFail();
    expect((float) $p1->actual_value)->toBe(3.0);
    expect($p2->actual_value)->toBeNull();

    $confirm = RoadmapChangeLog::where('action', 'confirm')->where('roadmap_measure_id', $bogot->id)->firstOrFail();
    expect($confirm->new_value)->toBe('2 та қатор');
});

test('a save into September after a Q3 report writes the month but latest_period keeps the quarter tiebreak', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $gurlan  = AdminFixture::measure(2, 1, 1733208);
    AdminFixture::report($gurlan, '2026-Q3', [1 => 6.0]);
    $line = AdminFixture::line($gurlan, 1);

    $r = ProgressEntry::save($roadmap, '2026-09', [$line->id => ['actual' => '12']], $user);
    expect($r)->toBe(['measures' => 1, 'changes' => 1]);

    $p = $line->progress()->where('report_period', '2026-09')->firstOrFail();
    expect((float) $p->actual_value)->toBe(12.0);
    expect($gurlan->fresh()->latest_period)->toBe('2026-Q3');            // RoadmapPeriod::latest()'s tiebreak: a quarter outranks the month it closes
});

test('a period outside the road-map year is refused', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $l1      = AdminFixture::line($bogot, 1);

    expect(fn () => ProgressEntry::save($roadmap, '2031-04', [$l1->id => ['actual' => '5']], $user))
        ->toThrow(InvalidArgumentException::class, '2026 йилнинг');
    expect(RoadmapLineProgress::count())->toBe(0);
    expect(RoadmapChangeLog::count())->toBe(0);
});
