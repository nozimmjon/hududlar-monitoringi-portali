<?php

use App\Console\Commands\ImportRoadmap;
use App\Models\District;
use App\Models\Roadmap;
use App\Models\RoadmapLineProgress;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\RoadmapDocxBuilder;
use Tests\Helpers\RoadmapXlsxBuilder;

uses(RefreshDatabase::class);

/**
 * Хоразм in the xlsx layout: sections I (2 measures) and II (Боғот · Гурлан),
 * 5 indicator lines. Rows 3..11 — the assertions below name them.
 */
function roadmapXlsxRows(array $overrides = []): array
{
    $rows = [
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],                                  // r3
        ['measure', ['A' => '1733-1-0-1', 'B' => 1, 'C' => '«Куловот» каналини реконструкция қилиш.',
            'D' => 'Бажарилиш даражаси', 'E' => '%', 'F' => 100,
            'I' => "2026 йил\nдекабрь", 'J' => "Сув хўжалиги вазирлиги (Ў.Шералиев),\nВилоят ҳокимлиги (Ў.Машарипов)"]],   // r4
        ['measure', ['A' => '1733-1-0-2', 'B' => 2, 'C' => '484,5 млн м3 сувни иқтисод қилиш.',
            'D' => 'Тежалган сув', 'E' => 'млн м3', 'F' => '484,5', 'I' => '2026 йил декабрь', 'J' => 'Чапқирғоқ-Амударё ИТҲБ (Э.Нурметов)']],   // r5
        ['section', 'II. Туманларда амалга ошириладиган лойиҳалар'],                                     // r6
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],                                // r7
        ['measure', ['A' => '1733-2-1733204-1', 'B' => 1,
            'C' => "Суғориш тармоқларини бетонлаштириш, жумладан:\n1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар.",
            'D' => 'Хўжаликлараро каналлар', 'E' => 'км', 'F' => '7,8', 'I' => '2026 йил декабрь', 'J' => 'ИТҲБ (Э.Нурметов)']],   // r8
        ['line', ['D' => 'Ички каналлар', 'E' => 'км', 'F' => 33]],                                       // r9
        ['district', '2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)'],                              // r10
        ['measure', ['A' => '1733-2-1733208-1', 'B' => 1, 'C' => '24 та насос агрегатларини таъмирлаш.',
            'D' => 'Насос агрегатлари', 'E' => 'та', 'F' => 24, 'I' => '2026 йил декабрь', 'J' => 'ИТҲБ']],   // r11
    ];

    foreach ($overrides as $index => $cells) {
        $rows[$index][1] = array_merge($rows[$index][1], $cells);
    }

    return $rows;
}

/** Paths handed out by roadmapXlsx(): $add appends one, $flush empties the list and returns it. */
function roadmapXlsxTempFiles(?string $add = null, bool $flush = false): array
{
    static $paths = [];

    if ($add !== null) {
        $paths[] = $add;
    }
    if ($flush) {
        $collected = $paths;
        $paths     = [];

        return $collected;
    }

    return $paths;
}

function roadmapXlsx(?array $rows = null): string
{
    $stub = tempnam(sys_get_temp_dir(), 'rmimp_');       // the 0-byte twin the .xlsx name is derived from
    $path = $stub . '.xlsx';
    roadmapXlsxTempFiles($stub);
    roadmapXlsxTempFiles($path);

    return RoadmapXlsxBuilder::make($rows ?? roadmapXlsxRows(), $path, 'Хоразм вилояти');
}

/** The same four positions as roadmapXlsxRows(), in the March docx layout (funding column included). */
function roadmapXlsxTwinDocx(string $firstTitle = '«Куловот» каналини реконструкция қилиш.'): string
{
    return RoadmapDocxBuilder::make([
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', [$firstTitle], ['Республика бюджети маблағлари, 32,0 млрд сўм'], ['2026 йил декабрь'], ['Сув хўжалиги вазирлиги']],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ']],
        ['section', 'II. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['Суғориш тармоқларини бетонлаштириш.'], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ']],
        ['district', '2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)'],
        ['measure', ['24 та насос агрегатларини таъмирлаш.'], ['Маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ']],
    ]);
}

function roadmapXlsxMeasure(int $sectionNo, int $seqNo, ?int $districtCode = null): RoadmapMeasure
{
    $q = RoadmapMeasure::where('section_no', $sectionNo)->where('seq_no', $seqNo);
    $q = $districtCode === null ? $q->whereNull('district_id') : $q->whereHas('district', fn ($d) => $d->where('code', $districtCode));

    return $q->firstOrFail();
}

afterEach(function () {
    foreach (roadmapXlsxTempFiles(null, true) as $path) {
        @unlink($path);
    }
});

test('an xlsx road map imports measures and their indicator lines', function () {
    $this->seed();

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx()]);
    $out  = Artisan::output();

    expect($exit)->toBe(0);
    $roadmap = Roadmap::where('region_code', 1733)->firstOrFail();
    expect($roadmap->title_text)->toBe('Сув хўжалиги йўл харитаси: Хоразм вилояти');
    expect($roadmap->approvers_text)->toBeNull();
    expect($roadmap->source_file)->toEndWith('.xlsx');
    expect($roadmap->measures()->count())->toBe(4);
    expect(RoadmapMeasureLine::count())->toBe(5);
    expect(RoadmapLineProgress::count())->toBe(0);

    $bogot = roadmapXlsxMeasure(2, 1, 1733204);
    expect($bogot->title)->toBe('Суғориш тармоқларини бетонлаштириш');
    expect($bogot->detailLines())->toBe(['1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.']);
    expect($bogot->district_head_text)->toBe('туман ҳокими Ж.Назаров');
    expect($bogot->deadline_text)->toBe('2026 йил декабрь');
    expect($bogot->responsible_text)->toBe('ИТҲБ (Э.Нурметов)');
    expect($bogot->funding_text)->toBeNull();                          // the xlsx layout has no funding column
    expect($bogot->lines->pluck('label')->all())->toBe(['Хўжаликлараро каналлар', 'Ички каналлар']);
    expect((float) $bogot->lines->first()->plan_value)->toBe(7.8);
    expect($bogot->lines_total)->toBe(2);
    expect($bogot->status)->toBe('in_progress');

    $first = roadmapXlsxMeasure(1, 1);
    expect($first->deadline_text)->toBe('2026 йил декабрь');
    expect($first->responsible_text)->toBe('Сув хўжалиги вазирлиги (Ў.Шералиев), Вилоят ҳокимлиги (Ў.Машарипов)');
    expect(roadmapXlsxMeasure(1, 2)->lines->first()->unit)->toBe('млн м³');
    expect(RoadmapMeasure::where('status', 'in_progress')->count())->toBe(4);

    expect($out)->toContain('Indicator lines: 5 written');
    expect($out)->toContain('Total: 4 measures, 2 districts');
});

test('re-importing the same xlsx is idempotent: same ids, same lines, nothing removed', function () {
    $this->seed();
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx()]);
    Artisan::output();
    $ids     = RoadmapMeasure::orderBy('source_row')->pluck('id')->all();
    $lineIds = RoadmapMeasureLine::orderBy('id')->pluck('id')->all();

    expect(Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx()]))->toBe(0);
    $out = Artisan::output();

    expect(Roadmap::count())->toBe(1);
    expect(RoadmapMeasure::orderBy('source_row')->pluck('id')->all())->toBe($ids);
    expect(RoadmapMeasureLine::orderBy('id')->pluck('id')->all())->toBe($lineIds);
    expect($out)->toContain('Indicator lines: 5 written, 0 removed');
    expect($out)->not->toContain('measure(s) removed');
});

test('--period writes the «Амалда» and «Изоҳ» values; without it they are ignored with a warning', function () {
    $this->seed();
    $filled = roadmapXlsxRows([
        5 => ['G' => 5, 'H' => 'ярми бажарилди'],            // r8 — Боғот line 1 of 2
        8 => ['G' => 24],                                    // r11 — Гурлан, actual = plan
    ]);

    expect(Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx($filled)]))->toBe(0);
    $ignored = Artisan::output();
    expect($ignored)->toContain('3 «Амалда»/«Изоҳ» value(s) ignored');
    expect($ignored)->toContain('--period=YYYY-MM');
    expect(RoadmapLineProgress::count())->toBe(0);
    expect(roadmapXlsxMeasure(2, 1, 1733208)->status)->toBe('in_progress');

    expect(Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx($filled), '--period' => '2026-09']))->toBe(0);
    $out = Artisan::output();

    expect($out)->toContain('actuals: 2 (period 2026-09)');
    expect(RoadmapLineProgress::count())->toBe(5);
    $gurlan = roadmapXlsxMeasure(2, 1, 1733208);
    expect($gurlan->status)->toBe('done');
    expect($gurlan->latest_period)->toBe('2026-09');
    expect((float) $gurlan->pct)->toBe(100.0);

    $bogot = roadmapXlsxMeasure(2, 1, 1733204);
    expect($bogot->status)->toBe('in_progress');                         // December deadline, September report
    expect($bogot->lines_done)->toBe(0);
    $progress = $bogot->lines->first()->progress->firstWhere('report_period', '2026-09');
    expect((float) $progress->actual_value)->toBe(5.0);
    expect($progress->note)->toBe('ярми бажарилди');
    expect($progress->period_type)->toBe('month');
});

test('a bad --period or --range is rejected before the file is read', function () {
    $this->seed();

    expect(Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx(), '--period' => '2026-9']))->toBe(1);
    expect(Artisan::output())->toContain('--period');
    expect(Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx(), '--range' => 'middle']))->toBe(1);
    expect(Artisan::output())->toContain('--range');
    expect(Roadmap::count())->toBe(0);
});

test('a road map imported from the March docx keeps its ids and funding text when the xlsx arrives', function () {
    $this->seed();
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsxTwinDocx()]);
    Artisan::output();
    $ids = RoadmapMeasure::orderBy('source_row')->pluck('id')->all();
    expect(RoadmapMeasureLine::count())->toBe(0);
    expect(roadmapXlsxMeasure(1, 1)->funding_text)->toBe('Республика бюджети маблағлари, 32,0 млрд сўм');

    $retitled = roadmapXlsxRows([1 => ['C' => '«Куловот» каналини реконструкция қилиш — янги таҳрир.']]);
    expect(Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx($retitled)]))->toBe(0);
    $out = Artisan::output();

    expect(RoadmapMeasure::count())->toBe(4);
    expect(RoadmapMeasure::orderBy('source_row')->pluck('id')->all())->toBe($ids);
    expect(RoadmapMeasureLine::count())->toBe(5);
    expect(roadmapXlsxMeasure(1, 1)->title)->toBe('«Куловот» каналини реконструкция қилиш — янги таҳрир.');
    expect(roadmapXlsxMeasure(1, 1)->funding_text)->toBe('Республика бюджети маблағлари, 32,0 млрд сўм');
    expect($out)->not->toContain('changed their title');               // the measure had no lines yet

    // Now it has lines, so the next retitle at the same position is worth a word.
    $again = roadmapXlsxRows([1 => ['C' => '«Куловот» каналини реконструкция қилиш — учинчи таҳрир.']]);
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx($again)]);
    expect(Artisan::output())->toContain('changed their title');
});

test('--dry-run reports the lines, the ignored actuals and the warnings without writing', function () {
    $this->seed();
    $rows = roadmapXlsxRows([
        1 => ['B' => 6],                                     // r4 — № 6 where the count says 1
        8 => ['G' => 24, 'H' => 'бажарилди'],                // r11
    ]);

    $exit = Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx($rows), '--dry-run' => true]);
    $out  = Artisan::output();

    expect($exit)->toBe(0);
    expect($out)->toContain('lines: 5 (5 with a plan), actuals: 1, notes: 1');
    expect($out)->toContain('r4: № 6 in file, counted 1');
    expect($out)->toContain('Dry run');
    expect(Roadmap::count())->toBe(0);
    expect(RoadmapMeasureLine::count())->toBe(0);
});

test('a structural problem in the xlsx aborts before anything is written', function () {
    $this->seed();
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx()]);
    Artisan::output();

    $broken = roadmapXlsxRows();
    $broken[4] = ['district', '1. Йўқтуман тумани (масъул – туман ҳокими X)'];
    expect(Artisan::call('import:roadmap', ['--region' => 1733, '--file' => roadmapXlsx($broken)]))->toBe(1);

    expect(Artisan::output())->toContain('Йўқтуман тумани');
    expect(RoadmapMeasure::count())->toBe(4);
    expect(RoadmapMeasureLine::count())->toBe(5);
});

test('pickSourceFile takes the single .docx or .xlsx candidate and explains every other case', function () {
    expect(ImportRoadmap::pickSourceFile(['/data/13. Хоразм.xlsx']))
        ->toBe(['file' => '/data/13. Хоразм.xlsx', 'error' => null]);
    expect(ImportRoadmap::pickSourceFile(['/data/13. Хоразм якуний.docx']))
        ->toBe(['file' => '/data/13. Хоразм якуний.docx', 'error' => null]);

    // A .doc next to nothing usable is the one case with its own hint.
    $legacy = ImportRoadmap::pickSourceFile(['/data/9. Сурхондарё.doc']);
    expect($legacy['file'])->toBeNull();
    expect($legacy['error'])->toContain('.doc')->toContain('.docx');

    // A .doc plus a usable file is not ambiguous — the usable one wins.
    expect(ImportRoadmap::pickSourceFile(['/data/9. Сурхондарё.doc', '/data/9. Сурхондарё.xlsx'])['file'])
        ->toBe('/data/9. Сурхондарё.xlsx');

    $none = ImportRoadmap::pickSourceFile([]);
    expect($none['file'])->toBeNull();
    expect($none['error'])->toContain('--file');

    $many = ImportRoadmap::pickSourceFile(['/data/13. Хоразм якуний.docx', '/data/Вилоятлар/13. Хоразм.xlsx']);
    expect($many['file'])->toBeNull();
    expect($many['error'])->toContain('--file')->toContain('13. Хоразм.xlsx');
});
