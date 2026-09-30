<?php

use App\Models\RoadmapChangeLog;
use App\Models\RoadmapLineProgress;
use App\Services\Roadmaps\ImportReport;
use App\Services\Roadmaps\ProgressImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\AdminFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

test('run() with write=false reports and rolls back; with write=true it writes and logs an import entry naming the user and file', function () {
    $roadmap = AdminFixture::roadmap();
    $user    = AdminFixture::user();
    $file    = AdminFixture::template('2026-09', ['G8' => 7.8, 'H8' => 'бетон тайёр']);

    try {
        $dry = (new ProgressImport())->run($file, '2026-09', 2026, 'water', false, $user, 'Хоразм.xlsx');
        expect($dry)->toBeInstanceOf(ImportReport::class);
        expect($dry->ok())->toBeTrue();
        expect($dry->written)->toBeFalse();
        expect($dry->period)->toBe('2026-09');
        expect($dry->file)->toBe('Хоразм.xlsx');
        expect($dry->blocks)->toBe(4);
        expect($dry->regions[0])->toMatchArray(['region' => 'Хоразм вилояти', 'in_file' => 4, 'total' => 4, 'lines' => 5, 'reported' => 1, 'missing' => 0, 'done' => 0, 'in_progress' => 4, 'open' => 0]);
        expect($dry->rows()[0])->toBe(['Хоразм вилояти', '4/4', 5, 0, 1, 0, 4, 0]);
        expect($dry->warnings)->toBe([]);
        expect(RoadmapLineProgress::count())->toBe(0);
        expect(RoadmapChangeLog::count())->toBe(0);

        $wet = (new ProgressImport())->run($file, '2026-09', 2026, 'water', true, $user, 'Хоразм.xlsx');
        expect($wet->ok())->toBeTrue();
        expect($wet->written)->toBeTrue();
        expect(RoadmapLineProgress::count())->toBe(5);
        expect(AdminFixture::line(AdminFixture::measure(2, 1, 1733204), 1)->progress()->where('report_period', '2026-09')->value('note'))->toBe('бетон тайёр');
        $log = RoadmapChangeLog::where('action', 'import')->firstOrFail();
        expect($log)->toMatchArray(['user_id' => $user->id, 'roadmap_id' => $roadmap->id, 'roadmap_measure_id' => null, 'report_period' => '2026-09']);
        expect($log->new_value)->toBe('Хоразм.xlsx · 4 та чора-тадбир · 1 та амалда');
        expect($wet->toArray()['regions'][0]['region'])->toBe('Хоразм вилояти');
        expect($wet->toArray()['ok'])->toBeTrue();
    } finally {
        @unlink($file);
    }
});

test('run() reports a broken file as an error without writing, and refuses a period that disagrees with the sheet', function () {
    AdminFixture::roadmap();
    $bad = AdminFixture::template('2026-09', ['G8' => 'кўп']);
    try {
        $r = (new ProgressImport())->run($bad, null, 2026, 'water', true, null, 'bad.xlsx');
        expect($r->ok())->toBeFalse();
        expect($r->error)->toContain('G8');
        expect($r->written)->toBeFalse();
        expect(RoadmapLineProgress::count())->toBe(0);
        expect(RoadmapChangeLog::count())->toBe(0);

        // A period mismatch needs a file that parses cleanly — $bad always fails at G8 first.
        $mismatch = AdminFixture::template('2026-09');
        try {
            $r2 = (new ProgressImport())->run($mismatch, '2026-10', 2026, 'water', false);
            expect($r2->error)->toContain('does not match');
        } finally {
            @unlink($mismatch);
        }
        expect((new ProgressImport())->run('C:/nowhere/none.xlsx', null, 2026, 'water', false)->error)->toContain('Файл топилмади');
        expect((new ProgressImport())->run($bad, null, 2026, 'gas', false)->error)->toContain('--domain');
    } finally {
        @unlink($bad);
    }
});
