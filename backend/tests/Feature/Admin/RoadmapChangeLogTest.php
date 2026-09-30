<?php

use App\Models\RoadmapChangeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

test('a change-log entry records who changed what; it survives its line and goes with its measure', function () {
    $this->seed();
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $bogot   = AdminFixture::measure(2, 1, 1733204);
    $line    = AdminFixture::line($bogot, 2);

    $entry = RoadmapChangeLog::record([
        'user_id' => $user->id, 'roadmap_id' => $roadmap->id, 'roadmap_measure_id' => $bogot->id,
        'roadmap_measure_line_id' => $line->id, 'report_period' => '2026-09', 'action' => 'actual',
        'old_value' => null, 'new_value' => '20',
    ]);

    expect($entry->created_at)->not->toBeNull();
    expect($entry->updated_at)->toBeNull();
    expect($entry->user->name)->toBe('Админ');
    expect($entry->line->label)->toBe('Ички каналлар');
    expect($entry->measure->is($bogot))->toBeTrue();
    expect(RoadmapChangeLog::ACTION_LABELS['actual'])->toBe('Амалда');

    $line->delete();
    expect($entry->fresh()->roadmap_measure_line_id)->toBeNull();     // nullOnDelete

    $bogot->delete();
    expect(RoadmapChangeLog::count())->toBe(0);                          // cascadeOnDelete
});
