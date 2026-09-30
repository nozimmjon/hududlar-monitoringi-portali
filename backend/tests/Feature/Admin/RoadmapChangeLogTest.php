<?php

use App\Models\RoadmapChangeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

test('a change-log entry records who changed what; it survives its line, its user and goes with its measure and roadmap', function () {
    $this->seed();
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $line    = AdminFixture::line($bogot, 2);
    $kulovot = AdminFixture::measure(1, 1);    // a different measure under the same roadmap

    $entry = RoadmapChangeLog::record([
        'user_id' => $user->id, 'roadmap_id' => $roadmap->id, 'roadmap_measure_id' => $bogot->id,
        'roadmap_measure_line_id' => $line->id, 'line_label' => $line->label, 'report_period' => '2026-09',
        'action' => 'actual', 'old_value' => null, 'new_value' => '20',
    ]);

    expect($entry->created_at)->not->toBeNull();
    expect(Schema::hasColumn('roadmap_change_log', 'updated_at'))->toBeFalse();
    expect($entry->user->name)->toBe('Админ');
    expect($entry->line->label)->toBe('Ички каналлар');
    expect($entry->line_label)->toBe('Ички каналлар');
    expect($entry->measure->is($bogot))->toBeTrue();
    expect(RoadmapChangeLog::ACTION_LABELS['actual'])->toBe('Амалда');

    $line->delete();
    $entry->refresh();
    expect($entry->roadmap_measure_line_id)->toBeNull();      // nullOnDelete
    expect($entry->line_label)->toBe('Ички каналлар');        // the snapshot outlives the line

    $user->delete();
    expect($entry->fresh()->user_id)->toBeNull();              // nullOnDelete

    // Tied to a different measure, so it is still on hand when $bogot is deleted below — the
    // roadmap-level cascade after that is then checked against a row that actually exists.
    $other = RoadmapChangeLog::record([
        'user_id' => null, 'roadmap_id' => $roadmap->id, 'roadmap_measure_id' => $kulovot->id,
        'roadmap_measure_line_id' => null, 'report_period' => null, 'action' => 'import',
        'old_value' => null, 'new_value' => null,
    ]);

    $bogot->delete();
    expect(RoadmapChangeLog::count())->toBe(1);                // cascadeOnDelete removed $entry, not $other
    expect(RoadmapChangeLog::first()->is($other))->toBeTrue();

    $roadmap->delete();
    expect(RoadmapChangeLog::count())->toBe(0);                // cascadeOnDelete
});

test('record() refuses an unknown action', function () {
    expect(fn () => RoadmapChangeLog::record(['action' => 'nope']))
        ->toThrow(InvalidArgumentException::class);
});
