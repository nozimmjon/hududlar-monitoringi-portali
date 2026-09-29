<?php

use App\Models\RoadmapMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Helpers\RoadmapDocxBuilder;

uses(RefreshDatabase::class);

function templateFixtureImport(int $region = 1733): void
{
    $rows = $region === 1733 ? [
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['measure', ['Илмий тавсиялар ишлаб чиқиш.'], ['Университет маблағлари'], ['2026 йил апрель-октябрь'], ['Университет']],
        ['section', 'II. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['Суғориш тармоқларини бетонлаштириш, жумладан:', '1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.'], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)'],
        ['measure', ['24 та насос агрегатларини таъмирлаш.'], ['Маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ']],
    ] : [
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['12 км канал.'], ['Бюджет'], ['2026 йил декабрь'], ['СХВ']],
    ];
    Artisan::call('import:roadmap', ['--region' => $region, '--file' => RoadmapDocxBuilder::make($rows)]);
}

/** Paths handed out by templateOut(): $add appends one, $flush empties the list and returns it. */
function templateTempFiles(?string $add = null, bool $flush = false): array
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

function templateOut(): string
{
    $stub = tempnam(sys_get_temp_dir(), 'rmtpl_');      // the 0-byte twin the .xlsx name is derived from
    $out  = $stub . '.xlsx';
    templateTempFiles($stub);
    templateTempFiles($out);

    return $out;
}

afterEach(function () {
    foreach (templateTempFiles(null, true) as $path) {
        @unlink($path);
    }
});

test('writes a document-shaped sheet: title with period, header, section/district rows, merged measure blocks, suggested lines', function () {
    $this->seed();
    templateFixtureImport();
    $out = templateOut();

    expect(Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-09', '--out' => $out]))->toBe(0);
    expect(Artisan::output())->toContain($out);

    $book  = IOFactory::load($out);
    $sheet = $book->getSheetByName('Хоразм вилояти');
    // Not `->not->toBeNull()`: Pest's `not` first lets PHPUnit *fail* assertNull(), and PHPUnit
    // builds that failure message by exporting the whole Worksheet graph (~1 GB) — fine on a
    // fresh process, fatal late in the full suite.
    expect($sheet)->toBeInstanceOf(Worksheet::class);
    expect($book->getSheetByName('Йўриқнома'))->toBeInstanceOf(Worksheet::class);
    expect($book->getSheetCount())->toBe(2);

    expect($sheet->getCell('A1')->getValue())->toContain('Хоразм вилояти');
    expect($sheet->getCell('A1')->getValue())->toContain('Ҳисобот даври: 2026-09');
    expect($sheet->getCell('A2')->getValue())->toBe('Калит');
    expect($sheet->getCell('G2')->getValue())->toBe('Амалда');
    expect($sheet->getCell('A3')->getValue())->toBe('I. Вилоятда амалга ошириладиган йирик лойиҳалар');

    expect($sheet->getCell('A4')->getValue())->toBe('1733-1-0-1');
    expect((int) $sheet->getCell('B4')->getValue())->toBe(1);
    expect($sheet->getCell('C4')->getValue())->toBe('484,5 млн м3 сувни иқтисод қилиш.');
    expect($sheet->getCell('D4')->getValue())->toBe('Сувни иқтисод қилиш');
    expect($sheet->getCell('E4')->getValue())->toBe('млн м³');
    expect((float) $sheet->getCell('F4')->getValue())->toBe(484.5);
    expect($sheet->getCell('G4')->getValue())->toBeNull();
    expect($sheet->getCell('I4')->getValue())->toBe('2026 йил декабрь');
    expect($sheet->getCell('D5')->getValue())->toBe('Бажарилиш даражаси');
    expect($sheet->getCell('E5')->getValue())->toBe('%');

    expect($sheet->getCell('A6')->getValue())->toBe('II. Туманларда амалга ошириладиган лойиҳалар');
    expect($sheet->getCell('A7')->getValue())->toBe('1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)');
    expect($sheet->getCell('A8')->getValue())->toBe('1733-2-1733204-1');
    expect($sheet->getCell('D8')->getValue())->toBe('Хўжаликлараро каналлар');
    expect($sheet->getCell('D9')->getValue())->toBe('Ички каналлар');
    expect((float) $sheet->getCell('F9')->getValue())->toBe(33.0);
    expect(array_keys($sheet->getMergeCells()))->toContain('C8:C9', 'A8:A9', 'J8:J9', 'A7:J7', 'A1:J1');
    expect($sheet->getCell('A10')->getValue())->toBe('2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)');
    expect($sheet->getCell('A11')->getValue())->toBe('1733-2-1733208-1');
    expect($sheet->getCell('D11')->getValue())->toBe('Насос агрегатларини таъмирлаш');

    expect($sheet->getColumnDimension('A')->getVisible())->toBeFalse();
    expect($sheet->getProtection()->getSheet())->toBeTrue();
    expect($sheet->getStyle('G8')->getProtection()->getLocked())->toBe(Protection::PROTECTION_UNPROTECTED);
    expect($sheet->getStyle('H8')->getProtection()->getLocked())->toBe(Protection::PROTECTION_UNPROTECTED);
    expect($sheet->getStyle('F8')->getProtection()->getLocked())->not->toBe(Protection::PROTECTION_UNPROTECTED);
    expect($sheet->getStyle('G8')->getFill()->getStartColor()->getRGB())->toBe('FFF2CC');
    expect($sheet->getStyle('A7')->getFill()->getStartColor()->getRGB())->toBe('EAF1FB');
    expect($sheet->getCell('G8')->getDataValidation()->getType())->toBe('decimal');
    expect($sheet->getFreezePane())->toBe('A3');
});

test('stored lines win over suggestions and carry the period actual and note', function () {
    $this->seed();
    templateFixtureImport();
    $m = RoadmapMeasure::where('section_no', 1)->where('seq_no', 2)->firstOrFail();
    $l = $m->lines()->create(['line_no' => 1, 'label' => 'Тавсиялар сони', 'unit' => 'дона', 'plan_value' => 3]);
    $m->lines()->create(['line_no' => 2, 'label' => 'Тақдимот', 'unit' => 'та', 'plan_value' => 1]);
    $l->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 2, 'pct_of_plan' => 66.67, 'note' => 'икки тайёр']);
    $l->progress()->create(['report_period' => '2026-08', 'period_type' => 'month', 'actual_value' => 1, 'pct_of_plan' => 33.33]);
    $out = templateOut();

    Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-09', '--out' => $out]);

    $sheet = IOFactory::load($out)->getSheetByName('Хоразм вилояти');
    expect($sheet->getCell('A5')->getValue())->toBe('1733-1-0-2');
    expect($sheet->getCell('D5')->getValue())->toBe('Тавсиялар сони');
    expect((float) $sheet->getCell('G5')->getValue())->toBe(2.0);
    expect($sheet->getCell('H5')->getValue())->toBe('икки тайёр');
    expect($sheet->getCell('D6')->getValue())->toBe('Тақдимот');
    expect($sheet->getCell('G6')->getValue())->toBeNull();
    expect($sheet->getCell('A7')->getValue())->toBe('II. Туманларда амалга ошириладиган лойиҳалар');   // shifted by one row

    // 4 measures · 6 indicator rows (1 suggested + 2 stored + 2 suggested + 1 suggested) · 4 of them suggested.
    expect(Artisan::output())->toMatch('/Хоразм вилояти\s*\|\s*4\s*\|\s*6\s*\|\s*4\s*\|/');
});

test('--all writes one sheet per loaded region in region order; a region without a road map is an error', function () {
    $this->seed();
    templateFixtureImport(1703);
    templateFixtureImport(1733);
    $out = templateOut();

    expect(Artisan::call('roadmap:template', ['--all' => true, '--period' => '2026-q3', '--out' => $out]))->toBe(0);   // lower-case period is normalised
    $book = IOFactory::load($out);
    expect(array_map(fn ($s) => $s->getTitle(), $book->getAllSheets()))->toBe(['Андижон вилояти', 'Хоразм вилояти', 'Йўриқнома']);
    expect($book->getSheetByName('Андижон вилояти')->getCell('A1')->getValue())->toContain('Ҳисобот даври: 2026-Q3');

    expect(Artisan::call('roadmap:template', ['--region' => 1718, '--period' => '2026-09', '--out' => templateOut()]))->toBe(1);
    expect(Artisan::output())->toContain('import:roadmap');
    expect(Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-9', '--out' => templateOut()]))->toBe(1);
    expect(Artisan::output())->toContain('--period');
});

test('merged measure blocks get explicit row heights so long text is not clipped', function () {
    $this->seed();
    Artisan::call('import:roadmap', ['--region' => 1733, '--file' => RoadmapDocxBuilder::make([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', [str_repeat('Узун матн бўлими. ', 30), '1. 7,8 км канал;', '2. 33 км ички канал.'], ['Бюджет'], ['2026 йил декабрь'], ['СХВ']],
    ])]);
    $out = templateOut();
    Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-09', '--out' => $out]);

    $sheet = IOFactory::load($out)->getSheetByName('Хоразм вилояти');
    expect(array_keys($sheet->getMergeCells()))->toContain('C4:C5');
    expect($sheet->getRowDimension(4)->getRowHeight())->toBeGreaterThan(15.0);
    expect($sheet->getRowDimension(5)->getRowHeight())->toBeGreaterThan(15.0);
    expect($sheet->getCell('A2')->getValue())->toBe('Калит');
    expect($sheet->getStyle('A3')->getFont()->getColor()->getRGB())->not->toBe('999999');   // heading keeps its colour
    expect($sheet->getStyle('A4')->getFont()->getColor()->getRGB())->toBe('999999');         // key cell is grey
    expect($sheet->getPageSetup()->getOrientation())->toBe('landscape');

    // A stored «Изоҳ» comes back from the region on the same merged block; an explicit row height
    // switches auto-fit off, so the note has to be counted too (≈360 chars / 26 per line ≈ 14 lines).
    $m    = RoadmapMeasure::firstOrFail();
    $line = $m->lines()->create(['line_no' => 1, 'label' => 'Канал', 'unit' => 'км', 'plan_value' => 7.8]);
    $m->lines()->create(['line_no' => 2, 'label' => 'Ички канал', 'unit' => 'км', 'plan_value' => 33]);
    $line->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 4, 'pct_of_plan' => 51.28, 'note' => str_repeat('Изоҳ матни. ', 30)]);
    $noted = templateOut();

    Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-09', '--out' => $noted]);

    $sheet = IOFactory::load($noted)->getSheetByName('Хоразм вилояти');
    expect(array_keys($sheet->getMergeCells()))->toContain('C4:C5');
    expect($sheet->getCell('H4')->getValue())->toContain('Изоҳ матни');
    expect($sheet->getRowDimension(4)->getRowHeight())->toBeGreaterThanOrEqual(15.0 * 10);
});

test('option conflicts and unwritable output are reported, not thrown', function () {
    $this->seed();
    templateFixtureImport();

    expect(Artisan::call('roadmap:template', ['--all' => true, '--region' => 1733, '--period' => '2026-09', '--out' => templateOut()]))->toBe(1);
    expect(Artisan::output())->toContain('--all');

    $blocker = tempnam(sys_get_temp_dir(), 'rmblk_');                  // a FILE where a directory would be needed
    expect(Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-09', '--out' => $blocker . '/x/y.xlsx']))->toBe(1);
    expect(Artisan::output())->toContain('Cannot');
    @unlink($blocker);

    expect(Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-09', '--domain' => 'bogus', '--out' => templateOut()]))->toBe(1);
    expect(Artisan::output())->toContain('--domain');
    expect(Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-09', '--year' => '26', '--out' => templateOut()]))->toBe(1);
    expect(Artisan::output())->toContain('--year');
});

test('instruction sheet names the file and warns about CSV', function () {
    $this->seed();
    templateFixtureImport();
    $out = templateOut();
    Artisan::call('roadmap:template', ['--region' => 1733, '--period' => '2026-09', '--out' => $out]);

    $sheet = IOFactory::load($out)->getSheetByName('Йўриқнома');
    expect($sheet->getCell('A2')->getValue())->toContain('Хоразм вилояти');
    expect($sheet->getCell('A2')->getValue())->toContain('2026-09');
    $lines = implode(' ', array_map(fn ($r) => (string) $sheet->getCell("A{$r}")->getValue(), range(4, 13)));
    expect($lines)->toContain('CSV')
        ->and($lines)->toContain('50%')          // «50% эмас — 50»
        ->and($lines)->toContain('1 240');       // thousands separated by a space, not a comma
});
