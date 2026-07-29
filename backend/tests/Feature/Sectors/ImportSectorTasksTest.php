<?php

use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\SectorTaskProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

function sectorFixture(array $overrides = []): string
{
    $rows = $overrides ?: [
        [1, 1, 'Товар маҳсулот ҳажмини етказиш.', 'Товар маҳсулот ҳажми', 'трлн сўм', '2026 йил якуни', 56.7, null, null],
        [2, 2, 'Экспортни таъминлаш.', 'Экспорт ҳажми', 'млн доллар', '2026 йил якуни', 792, null, null],
        [null, 3, null, 'Шундан: тайёр маҳсулот', 'млн доллар', '2026 йил 2-ярим йиллиги', 400, null, null],
    ];

    return SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', '«Ўзбекнефтгаз» АЖ — Бошқарув раиси А. Сангинов имзолаган кафолат хати', $rows],
    ]);
}

test('imports tasks and progress lines for a period', function () {
    $this->seed();
    $file = sectorFixture();

    $exit = Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-H2']);

    expect($exit)->toBe(0);
    $sector = Sector::where('code', 'uzbekneftgaz')->first();
    expect(SectorTask::where('sector_id', $sector->id)->count())->toBe(2);

    $t2 = SectorTask::where('sector_id', $sector->id)->where('task_no', 2)->first();
    expect($t2->title)->toBe('Экспортни таъминлаш.');
    expect($t2->progress()->where('report_period', '2026-H2')->count())->toBe(2);
    expect($t2->status)->toBe('in_progress');        // no actuals reported yet
    expect($t2->lines_total)->toBe(2);               // both lines carry a plan
    expect($t2->lines_done)->toBe(0);
    expect($t2->latest_period)->toBe('2026-H2');
    expect((float) $t2->headline_plan)->toEqualWithDelta(792.0, 0.001);

    $line3 = $t2->progress()->where('line_no', 3)->first();
    expect($line3->deadline_code)->toBe('h2');
    expect($line3->period_type)->toBe('half');
});

test('re-import of the same period is idempotent and updates values', function () {
    $this->seed();

    Artisan::call('import:sector-tasks', ['--file' => sectorFixture(), '--period' => '2026-H2']);
    $updated = sectorFixture([
        [1, 1, 'Товар маҳсулот ҳажмини етказиш.', 'Товар маҳсулот ҳажми', 'трлн сўм', '2026 йил якуни', 60.0, null, null],
        [2, 2, 'Экспортни таъминлаш.', 'Экспорт ҳажми', 'млн доллар', '2026 йил якуни', 792, null, null],
        [null, 3, null, 'Шундан: тайёр маҳсулот', 'млн доллар', '2026 йил 2-ярим йиллиги', 400, null, null],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $updated, '--period' => '2026-H2']);

    expect(SectorTaskProgress::where('report_period', '2026-H2')->count())->toBe(3); // no dupes
    $t1 = SectorTask::where('task_no', 1)->first();
    expect((float) $t1->progress()->where('line_no', 1)->first()->plan_value)->toEqualWithDelta(60.0, 0.001);
});

test('actuals produce recomputed pct and statuses; file pct is ignored', function () {
    $this->seed();
    $file = sectorFixture([
        [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, 120, 1.0],   // done (file pct lies: 1%)
        [2, 2, 'В2.', 'Кўрсаткич Б', 'та', '2026 йил якуни', 100, 55, 99.0],   // open
        [null, 3, null, 'Кўрсаткич В', 'та', '2026 йил якуни', 200, 250, null],
    ]);

    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-Q3']);

    $t1 = SectorTask::where('task_no', 1)->first();
    expect($t1->status)->toBe('done');
    expect((float) $t1->headline_pct)->toEqualWithDelta(120.0, 0.01);          // recomputed, not 1.0

    $t2 = SectorTask::where('task_no', 2)->first();
    expect($t2->status)->toBe('open');                                          // weakest link: line 2 at 55%
    expect($t2->lines_done)->toBe(1);                                           // line 3 is ≥100%
    expect($t2->progress()->where('line_no', 2)->first()->reported_at)->not->toBeNull();
});

test('a later period advances the snapshot, earlier period does not regress it', function () {
    $this->seed();
    Artisan::call('import:sector-tasks', ['--file' => sectorFixture(), '--period' => '2026-Q3']);
    Artisan::call('import:sector-tasks', ['--file' => sectorFixture(), '--period' => '2026-08']);

    // 2026-08 sorts before 2026-Q3 (Q3 closes at month 09) — snapshot stays on Q3.
    expect(SectorTask::where('task_no', 1)->first()->latest_period)->toBe('2026-Q3');
    // History keeps both periods.
    expect(SectorTaskProgress::where('line_no', 1)->count())->toBe(2);
});

test('dry-run writes nothing', function () {
    $this->seed();
    $exit = Artisan::call('import:sector-tasks', ['--file' => sectorFixture(), '--period' => '2026-H2', '--dry-run' => true]);

    expect($exit)->toBe(0);
    expect(SectorTask::count())->toBe(0);
    expect(SectorTaskProgress::count())->toBe(0);
});

test('unknown sheet number aborts the whole import', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['99. Номаълум', 'орг', [[1, 1, 'В', 'а', 'та', '2026 йил якуни', 1, null, null]]],
    ]);

    $exit = Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-H2']);
    expect($exit)->toBe(1);
    expect(SectorTask::count())->toBe(0);
});

test('sheet name mismatching the sector aborts', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Фармацевтика', 'орг', [[1, 1, 'В', 'а', 'та', '2026 йил якуни', 1, null, null]]], // number 1 is Ўзбекнефтгаз
    ]);

    $exit = Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-H2']);
    expect($exit)->toBe(1);
    expect(SectorTask::count())->toBe(0);
});

test('invalid period is rejected', function () {
    $this->seed();
    $exit = Artisan::call('import:sector-tasks', ['--file' => sectorFixture(), '--period' => 'H2-2026']);
    expect($exit)->toBe(1);
});
