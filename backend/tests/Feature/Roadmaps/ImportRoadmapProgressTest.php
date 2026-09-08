<?php

use App\Models\RoadmapLineProgress;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Helpers\RoadmapDocxBuilder;

uses(RefreshDatabase::class);

function progressFixtureImport(): void
{
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => RoadmapDocxBuilder::make([
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['measure', ['Илмий тавсиялар ишлаб чиқиш.'], ['Университет маблағлари'], ['2026 йил апрель-октябрь'], ['Университет']],
        ['section', 'II. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['Суғориш тармоқларини бетонлаштириш, жумладан:', '1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.'], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)'],
        ['measure', ['24 та насос агрегатларини таъмирлаш.'], ['Маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ']],
    ])]);
}

/** Paths handed out by progressTemplate(): $add appends one, $flush empties the list and returns it. */
function progressTempFiles(?string $add = null, bool $flush = false): array
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

afterEach(function () {
    foreach (progressTempFiles(null, true) as $path) {
        @unlink($path);
    }
});

/** Template rows for this fixture: 4 = I/1 (млн м³ 484,5), 5 = I/2 (% 100), 8–9 = Боғот/1 (км 7,8 · км 33), 11 = Гурлан/1 (та 24). */
function progressTemplate(string $period = '2026-09'): string
{
    $stub = tempnam(sys_get_temp_dir(), 'rmprg_');      // the 0-byte twin the .xlsx name is derived from
    $out  = $stub . '.xlsx';
    progressTempFiles($stub);
    progressTempFiles($out);
    Artisan::call('roadmap:template', ['--region' => 1733, '--period' => $period, '--out' => $out]);

    return $out;
}

/** @param array<string, mixed> $cells coordinate => value on the «Хоразм вилояти» sheet */
function progressFill(string $path, array $cells): void
{
    $book  = IOFactory::load($path);
    $sheet = $book->getSheetByName('Хоразм вилояти');
    foreach ($cells as $coord => $value) {
        $sheet->setCellValue($coord, $value);
    }
    IOFactory::createWriter($book, 'Xlsx')->save($path);
}

function progressMeasure(int $sectionNo, int $seqNo, ?int $districtCode = null): RoadmapMeasure
{
    $q = RoadmapMeasure::where('section_no', $sectionNo)->where('seq_no', $seqNo);
    $q = $districtCode === null ? $q->whereNull('district_id') : $q->whereHas('district', fn ($d) => $d->where('code', $districtCode));

    return $q->firstOrFail();
}

test('round trip: an empty reviewed file defines the lines, the filled file adds actuals; re-import is idempotent', function () {
    $this->seed();
    progressFixtureImport();
    $file = progressTemplate();

    expect(Artisan::call('import:roadmap-progress', ['--file' => $file]))->toBe(0);          // period read from A1
    expect(Artisan::output())->toContain('2026-09');
    expect(RoadmapMeasureLine::count())->toBe(5);
    expect(RoadmapLineProgress::count())->toBe(5);
    expect(RoadmapLineProgress::whereNotNull('actual_value')->count())->toBe(0);
    expect(RoadmapMeasure::where('status', 'in_progress')->count())->toBe(4);
    expect(progressMeasure(2, 1, 1733204)->lines_total)->toBe(2);

    progressFill($file, ['G4' => '300,5', 'H5' => 'ҳали бошланмаган', 'G8' => 7.8, 'G9' => '20', 'G11' => 24]);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $file, '--period' => '2026-09']))->toBe(0);

    $bogot = progressMeasure(2, 1, 1733204);
    expect($bogot->lines_total)->toBe(2);
    expect($bogot->lines_done)->toBe(1);
    expect((float) $bogot->pct)->toBeNumericallyClose(80.3, 0.1);          // (100 + 60.6) / 2
    expect($bogot->status)->toBe('in_progress');                           // December deadline, September report
    expect($bogot->latest_period)->toBe('2026-09');
    $ichki = $bogot->lines()->where('line_no', 2)->firstOrFail()->progress()->where('report_period', '2026-09')->firstOrFail();
    expect((float) $ichki->actual_value)->toBe(20.0);
    expect((float) $ichki->pct_of_plan)->toBeNumericallyClose(60.606, 0.001);
    expect($ichki->period_type)->toBe('month');

    $gurlan = progressMeasure(2, 1, 1733208);
    expect($gurlan->status)->toBe('done');
    expect((float) $gurlan->pct)->toBe(100.0);

    $m1 = progressMeasure(1, 1);
    expect((float) $m1->pct)->toBeNumericallyClose(62.02, 0.01);
    expect($m1->status)->toBe('in_progress');

    $m2 = progressMeasure(1, 2);
    expect($m2->status)->toBe('in_progress');
    expect($m2->pct)->toBeNull();
    expect($m2->lines->first()->progress->first()->note)->toBe('ҳали бошланмаган');

    Artisan::call('import:roadmap-progress', ['--file' => $file]);
    expect(RoadmapMeasureLine::count())->toBe(5);
    expect(RoadmapLineProgress::count())->toBe(5);
    expect((float) progressMeasure(2, 1, 1733208)->pct)->toBe(100.0);
});

test('reviewer edits travel with the file: relabelled, added and removed lines', function () {
    $this->seed();
    progressFixtureImport();
    $file = progressTemplate();
    $book = IOFactory::load($file);
    $sheet = $book->getSheetByName('Хоразм вилояти');
    $sheet->setCellValue('D8', 'Хўжаликлараро каналлар (бетон)');
    $sheet->insertNewRowBefore(10, 1);                                     // third line of the Боғот block, A stays empty
    $sheet->setCellValue('D10', 'Гидропостлар');
    $sheet->setCellValue('E10', 'та');
    $sheet->setCellValue('F10', 4);
    IOFactory::createWriter($book, 'Xlsx')->save($file);

    expect(Artisan::call('import:roadmap-progress', ['--file' => $file]))->toBe(0);
    $bogot = progressMeasure(2, 1, 1733204);
    expect($bogot->lines->pluck('label')->all())->toBe(['Хўжаликлараро каналлар (бетон)', 'Ички каналлар', 'Гидропостлар']);
    expect($bogot->lines_total)->toBe(3);

    // Next month's template carries the stored lines; the reviewer drops the last two rows.
    $next = progressTemplate('2026-10');
    progressFill($next, ['D9' => null, 'E9' => null, 'F9' => null, 'D10' => null, 'E10' => null, 'F10' => null, 'G8' => 7.8]);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $next]))->toBe(0);
    expect(Artisan::output())->toContain('2 line(s) removed');
    $bogot->refresh();
    expect($bogot->lines->pluck('label')->all())->toBe(['Хўжаликлараро каналлар (бетон)']);
    expect($bogot->latest_period)->toBe('2026-10');
    expect($bogot->status)->toBe('done');
});

test('a padded hand-edited key still matches', function () {
    $this->seed();
    progressFixtureImport();
    $file = progressTemplate();
    progressFill($file, ['A11' => '1733-02-1733208-01', 'G11' => 12]);

    expect(Artisan::call('import:roadmap-progress', ['--file' => $file]))->toBe(0);
    expect((float) progressMeasure(2, 1, 1733208)->pct)->toBe(50.0);
});

test('structural problems abort before anything is written', function () {
    $this->seed();
    progressFixtureImport();
    $file = progressTemplate();
    Artisan::call('import:roadmap-progress', ['--file' => $file]);
    $linesBefore = RoadmapMeasureLine::count();

    $bad = progressTemplate();
    progressFill($bad, ['G8' => 'кўп']);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $bad]))->toBe(1);
    expect(Artisan::output())->toContain('G8');

    $unknown = progressTemplate();
    progressFill($unknown, ['A11' => '1733-9-0-9']);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $unknown]))->toBe(1);
    expect(Artisan::output())->toContain('1733-9-0-9');

    expect(Artisan::call('import:roadmap-progress', ['--file' => $file, '--period' => '2026-10']))->toBe(1);
    expect(Artisan::output())->toContain('2026-10');

    $truncated = progressTemplate();
    progressFill($truncated, ['D8' => null, 'E8' => null, 'F8' => null, 'D9' => null, 'E9' => null, 'F9' => null]);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $truncated]))->toBe(1);
    expect(Artisan::output())->toContain('1733-2-1733204-1');

    $noLabel = progressTemplate();
    progressFill($noLabel, ['D11' => null, 'G11' => 5]);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $noLabel]))->toBe(1);
    expect(Artisan::output())->toContain('D11');

    $dupe = progressTemplate();
    progressFill($dupe, ['A11' => '1733-1-0-1']);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $dupe]))->toBe(1);
    expect(Artisan::output())->toContain('иккинчи марта');

    expect(Artisan::call('import:roadmap-progress', ['--file' => 'C:/nope/missing.xlsx']))->toBe(1);

    expect(RoadmapMeasureLine::count())->toBe($linesBefore);
    expect(RoadmapLineProgress::whereNotNull('actual_value')->count())->toBe(0);

    $dry = progressTemplate();
    progressFill($dry, ['G11' => 24]);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $dry, '--dry-run' => true]))->toBe(0);
    expect(Artisan::output())->toContain('Dry run');
    expect(RoadmapLineProgress::whereNotNull('actual_value')->count())->toBe(0);
    expect(progressMeasure(2, 1, 1733208)->status)->toBe('in_progress');
});

test('operator warnings: relabelled history, cleared actuals, measures missing from the file, stray rows', function () {
    $this->seed();
    progressFixtureImport();
    $aug = progressTemplate('2026-08');                 // history in an earlier period, so a relabelling can orphan it
    progressFill($aug, ['G8' => 5, 'G9' => 10, 'G11' => 12, 'G4' => 80]);
    Artisan::call('import:roadmap-progress', ['--file' => $aug]);

    $sep = progressTemplate('2026-09');
    progressFill($sep, ['G8' => 7.8, 'G9' => 20, 'G11' => 24, 'G4' => 100]);
    Artisan::call('import:roadmap-progress', ['--file' => $sep]);

    // A corrected September file: a row inserted at the top of the Боғот block shifts line 1's history
    // under a new label, G4 cleared, and the Гурлан block deleted outright.
    $fix   = progressTemplate('2026-09');               // carries September's own actuals back out
    $book  = IOFactory::load($fix);
    $sheet = $book->getSheetByName('Хоразм вилояти');
    $sheet->insertNewRowBefore(9, 1);
    $sheet->setCellValue('D8', 'Янги биринчи қатор'); $sheet->setCellValue('E8', 'та'); $sheet->setCellValue('F8', 3); $sheet->setCellValue('G8', 1);
    $sheet->setCellValue('D9', 'Хўжаликлараро каналлар'); $sheet->setCellValue('E9', 'км'); $sheet->setCellValue('F9', 7.8); $sheet->setCellValue('G9', 7.8);
    $sheet->setCellValue('G4', null);
    $sheet->removeRow(12, 1);                                           // Гурлан measure row (header at 11 stays)
    IOFactory::createWriter($book, 'Xlsx')->save($fix);

    expect(Artisan::call('import:roadmap-progress', ['--file' => $fix]))->toBe(0);
    $out = Artisan::output();
    expect($out)->toContain('changed their label');
    expect($out)->toContain('1 previously reported');
    expect($out)->toContain('1 of 4 measure(s)');
    expect($out)->toContain('3/4');
    expect(progressMeasure(2, 1, 1733208)->latest_period)->toBe('2026-09');   // untouched

    // A stray value far below the table is an error, not a new line of the last measure.
    $stray = progressTemplate('2026-11');
    progressFill($stray, ['D400' => 'ЖАМИ']);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $stray]))->toBe(1);
    expect(Artisan::output())->toContain('D400');
});

test('a percent-formatted or grouped-comma «Амалда» cell aborts instead of being misread', function () {
    $this->seed();
    progressFixtureImport();
    $pct = progressTemplate();
    $book = IOFactory::load($pct);
    $sheet = $book->getSheetByName('Хоразм вилояти');
    $sheet->setCellValue('G5', 0.5);
    $sheet->getStyle('G5')->getNumberFormat()->setFormatCode('0%');
    IOFactory::createWriter($book, 'Xlsx')->save($pct);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $pct]))->toBe(1);
    expect(Artisan::output())->toContain('фоиз');

    $grouped = progressTemplate();
    progressFill($grouped, ['G4' => '1,240']);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $grouped]))->toBe(1);
    expect(Artisan::output())->toContain('ноаниқ');
    expect(RoadmapLineProgress::whereNotNull('actual_value')->count())->toBe(0);
});

test('sheets that disagree on the period and a keyless continuation row abort', function () {
    $this->seed();
    progressFixtureImport();
    $file = progressTemplate();
    $book = IOFactory::load($file);
    $extra = $book->createSheet();
    $extra->setTitle('Бошқа');
    $extra->setCellValue('A1', 'Сув хўжалиги йўл харитаси — X — 2026 · Ҳисобот даври: 2026-10');
    IOFactory::createWriter($book, 'Xlsx')->save($file);
    expect(Artisan::call('import:roadmap-progress', ['--file' => $file]))->toBe(1);
    expect(Artisan::output())->toContain('2026-10');

    $keyless = progressTemplate();
    progressFill($keyless, ['D3' => 'Калитсиз қатор', 'A3' => null]);   // the section header row loses its text, gains a label
    expect(Artisan::call('import:roadmap-progress', ['--file' => $keyless]))->toBe(1);
    expect(Artisan::output())->toContain('D3');
});
