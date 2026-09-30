<?php

use App\Models\RoadmapChangeLog;
use App\Models\RoadmapLineProgress;
use App\Models\RoadmapMeasureLine;
use App\Services\Roadmaps\ProgressEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

test('correct updates, reorders, adds and removes lines, renumbers, rewrites reported percentages and logs everything', function () {
    AdminFixture::roadmap();
    $user  = AdminFixture::user();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    AdminFixture::report($bogot, '2026-08', [1 => 3.9, 2 => 11.0]);
    $l1 = AdminFixture::line($bogot, 1);
    $l2 = AdminFixture::line($bogot, 2);

    ProgressEntry::correct($bogot, [
        ['id' => $l2->id, 'label' => 'Ички каналлар', 'unit' => 'км', 'plan' => '22'],                      // moved first, plan 33 → 22
        ['id' => null, 'label' => ' Гидропостлар ', 'unit' => 'та', 'plan' => '4'],                          // new (label trimmed)
        ['id' => (string) $l1->id, 'label' => 'Хўжаликлараро каналлар (бетон)', 'unit' => 'км', 'plan' => '7,8'],   // relabelled, moved last
    ], $user);

    $bogot->refresh();
    expect($bogot->lines->map(fn ($l) => [$l->line_no, $l->label, $l->unit, (float) $l->plan_value])->all())->toBe([
        [1, 'Ички каналлар', 'км', 22.0],
        [2, 'Гидропостлар', 'та', 4.0],
        [3, 'Хўжаликлараро каналлар (бетон)', 'км', 7.8],
    ]);
    expect($bogot->lines_total)->toBe(3);
    expect((float) $l2->progress()->where('report_period', '2026-08')->value('pct_of_plan'))->toBeNumericallyClose(50.0, 0.001);   // 11 / 22
    expect((float) $bogot->pct)->toBeNumericallyClose(33.33, 0.01);       // (50 + 0 + 50) / 3 — the new line has no row
    expect(RoadmapChangeLog::orderBy('id')->pluck('action')->all())->toBe(['plan', 'line_added', 'label']);
    expect(RoadmapChangeLog::where('action', 'plan')->firstOrFail())->toMatchArray(['old_value' => '33', 'new_value' => '22', 'roadmap_measure_line_id' => $l2->id, 'user_id' => $user->id]);
    expect(RoadmapChangeLog::where('action', 'line_added')->firstOrFail()->new_value)->toBe('Гидропостлар · та · 4');

    // removing the relabelled line takes its August row with it and says so
    ProgressEntry::correct($bogot, [
        ['id' => $l2->id, 'label' => 'Ички каналлар', 'unit' => 'км', 'plan' => '22'],
        ['id' => AdminFixture::line($bogot, 2)->id, 'label' => 'Гидропостлар', 'unit' => '', 'plan' => ''],   // unit and plan cleared
    ], $user);

    expect(RoadmapMeasureLine::find($l1->id))->toBeNull();
    expect(RoadmapLineProgress::where('roadmap_measure_line_id', $l1->id)->count())->toBe(0);
    $removed = RoadmapChangeLog::where('action', 'line_removed')->firstOrFail();
    expect($removed->old_value)->toBe('Хўжаликлараро каналлар (бетон) · км · 7,8 · 1 ой');
    expect($removed->roadmap_measure_line_id)->toBeNull();
    expect($removed->line_label)->toBe('Хўжаликлараро каналлар (бетон)');
    expect(RoadmapChangeLog::where('action', 'plan')->first()->line_label)->toBe('Ички каналлар');
    $bogot->refresh();
    expect($bogot->lines_total)->toBe(1);                                  // an unplanned line does not count
    expect($bogot->lines->pluck('line_no')->all())->toBe([1, 2]);
    expect(AdminFixture::line($bogot, 2)->unit)->toBeNull();
    expect(AdminFixture::line($bogot, 2)->plan_value)->toBeNull();
    expect(RoadmapChangeLog::orderBy('id')->pluck('action')->all())->toBe(['plan', 'line_added', 'label', 'line_removed', 'unit', 'plan']);
});

test('correct refuses a blank label, a bad plan, a long unit, a foreign line, a duplicate and an empty list, writing nothing', function () {
    AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $l1      = AdminFixture::line($bogot, 1);
    $l2      = AdminFixture::line($bogot, 2);
    $foreign = AdminFixture::line(AdminFixture::measure(2, 1, 1733208), 1);
    $with    = fn (array $second) => [['id' => $l1->id, 'label' => 'Хўжаликлараро каналлар', 'unit' => 'км', 'plan' => '7,8'], $second];

    expect(fn () => ProgressEntry::correct($bogot, $with(['id' => $l2->id, 'label' => '  ', 'unit' => 'км', 'plan' => '33']), $user))
        ->toThrow(InvalidArgumentException::class, '2-қатор: индикатор номи бўш');
    expect(fn () => ProgressEntry::correct($bogot, $with(['id' => $l2->id, 'label' => 'Ички', 'unit' => 'км', 'plan' => 'кўп']), $user))
        ->toThrow(InvalidArgumentException::class, '2-қатор: режа — рақам эмас');
    expect(fn () => ProgressEntry::correct($bogot, $with(['id' => $l2->id, 'label' => 'Ички', 'unit' => str_repeat('а', 49), 'plan' => '1']), $user))
        ->toThrow(InvalidArgumentException::class, '2-қатор: ўлчов');
    expect(fn () => ProgressEntry::correct($bogot, $with(['id' => $foreign->id, 'label' => 'Насос', 'unit' => 'та', 'plan' => '1']), $user))
        ->toThrow(InvalidArgumentException::class, 'тегишли эмас');
    expect(fn () => ProgressEntry::correct($bogot, $with(['id' => $l1->id, 'label' => 'Такрор', 'unit' => 'км', 'plan' => '1']), $user))
        ->toThrow(InvalidArgumentException::class, 'икки марта');
    expect(fn () => ProgressEntry::correct($bogot, [], $user))
        ->toThrow(InvalidArgumentException::class, 'Камида битта');

    expect($bogot->fresh()->lines->pluck('label')->all())->toBe(['Хўжаликлараро каналлар', 'Ички каналлар']);
    expect(RoadmapChangeLog::count())->toBe(0);
});

test('correct is a no-op when nothing moved, adds several lines at once, refuses an over-long label, clears a plan and names the old label on a rename', function () {
    AdminFixture::roadmap();
    $user  = AdminFixture::user();
    $bogot = AdminFixture::measure(2, 1, 1733204);
    $l1    = AdminFixture::line($bogot, 1);          // Хўжаликлараро каналлар · км · 7,8
    $l2    = AdminFixture::line($bogot, 2);          // Ички каналлар · км · 33
    AdminFixture::report($bogot, '2026-08', [1 => 3.9]);      // l1 has a reported month, l2 an empty row
    $same = [
        ['id' => $l1->id, 'label' => 'Хўжаликлараро каналлар', 'unit' => 'км', 'plan' => '7,8'],
        ['id' => $l2->id, 'label' => 'Ички каналлар', 'unit' => 'км', 'plan' => '33'],
    ];

    // Same lines, same order, same values: the lines are rewritten through the renumber offset
    // and land back where they were, and nothing is worth a log entry.
    ProgressEntry::correct($bogot, $same, $user);
    expect(RoadmapChangeLog::count())->toBe(0);
    expect($bogot->fresh()->lines->map(fn ($l) => [$l->line_no, $l->label])->all())
        ->toBe([[1, 'Хўжаликлараро каналлар'], [2, 'Ички каналлар']]);

    // Two new lines in one call: both created, both numbered, both logged.
    ProgressEntry::correct($bogot, [...$same,
        ['id' => null, 'label' => 'Гидропостлар', 'unit' => 'та', 'plan' => '4'],
        ['id' => null, 'label' => 'Насослар', 'unit' => 'та', 'plan' => ''],
    ], $user);
    expect($bogot->fresh()->lines->map(fn ($l) => [$l->line_no, $l->label])->all())
        ->toBe([[1, 'Хўжаликлараро каналлар'], [2, 'Ички каналлар'], [3, 'Гидропостлар'], [4, 'Насослар']]);
    expect(RoadmapChangeLog::where('action', 'line_added')->orderBy('id')->pluck('new_value')->all())
        ->toBe(['Гидропостлар · та · 4', 'Насослар · та · —']);

    // A label past the column's limit is refused by row number, and the rest of the list survives.
    expect(fn () => ProgressEntry::correct($bogot, [['id' => $l1->id, 'label' => str_repeat('а', RoadmapMeasureLine::LABEL_MAX + 1), 'unit' => 'км', 'plan' => '7,8']], $user))
        ->toThrow(InvalidArgumentException::class, '1-қатор: индикатор номи жуда узун');
    expect($bogot->fresh()->lines)->toHaveCount(4);

    // Clear l1's plan (it has a reported August) and drop the two lines nobody ever reported.
    ProgressEntry::correct($bogot, [
        ['id' => $l1->id, 'label' => 'Хўжаликлараро каналлар (бетон)', 'unit' => 'км', 'plan' => ''],
        ['id' => $l2->id, 'label' => 'Ички каналлар', 'unit' => 'км', 'plan' => '33'],
    ], $user);

    $august = RoadmapLineProgress::where('roadmap_measure_line_id', $l1->id)->where('report_period', '2026-08')->firstOrFail();
    expect($august->actual_value)->not->toBeNull();          // the report stays; only the percentage loses its base
    expect($august->pct_of_plan)->toBeNull();
    $bogot->refresh();
    expect($bogot->lines_total)->toBe(1);                    // recomputed: a line without a plan does not count
    expect($bogot->lines->pluck('line_no')->all())->toBe([1, 2]);
    expect(RoadmapChangeLog::where('action', 'line_removed')->orderBy('id')->pluck('old_value')->all())
        ->toBe(['Гидропостлар · та · 4 · 0 ой', 'Насослар · та · — · 0 ой']);

    // A rename's snapshot is taken before the line is written, so line_label holds the OLD name:
    // the history reads under the name the entry was made against, not the one it changed to.
    $rename = RoadmapChangeLog::where('action', 'label')->orderByDesc('id')->firstOrFail();
    expect($rename->line_label)->toBe('Хўжаликлараро каналлар');
    expect([$rename->old_value, $rename->new_value])->toBe(['Хўжаликлараро каналлар', 'Хўжаликлараро каналлар (бетон)']);
});

test('history lists a measure\'s changes newest first with the user', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $gurlan  = AdminFixture::measure(2, 1, 1733208);
    $line    = AdminFixture::line($gurlan, 1);

    ProgressEntry::save($roadmap, '2026-08', [$line->id => ['actual' => '10']], $user);
    ProgressEntry::save($roadmap, '2026-09', [$line->id => ['actual' => '20', 'note' => 'икки']], $user);
    ProgressEntry::correct($gurlan, [['id' => $line->id, 'label' => 'Насос агрегатлари', 'unit' => 'дона', 'plan' => '24']], $user);

    $h = ProgressEntry::history($gurlan);
    expect($h->pluck('action')->all())->toBe(['unit', 'note', 'actual', 'actual']);
    expect($h->first()->user->name)->toBe('Админ');
    expect($h->first()->line->label)->toBe('Насос агрегатлари');
    expect(ProgressEntry::history($gurlan, 2))->toHaveCount(2);
    expect(ProgressEntry::history(AdminFixture::measure(1, 1)))->toHaveCount(0);
});
