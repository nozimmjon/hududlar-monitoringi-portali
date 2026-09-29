<?php

use App\Services\Roadmaps\XlsxRoadmapParser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Helpers\RoadmapXlsxBuilder;

/** districts.id by name, the way ImportRoadmap's resolver would answer for a region. */
const XLSX_DISTRICTS = [
    'Боғот тумани'   => 204,
    'Гурлан тумани'  => 208,
    'Қувасой шаҳар'  => 300,
    'Боёвут тумани'  => 400,
    'Тошкент тумани' => 500,
];

/** Paths handed out by xlsxFile(): $add appends one, $flush empties the list and returns it. */
function xlsxTempFiles(?string $add = null, bool $flush = false): array
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

function xlsxFile(array $rows): string
{
    $stub = tempnam(sys_get_temp_dir(), 'rmxp_');       // the 0-byte twin the .xlsx name is derived from
    $path = $stub . '.xlsx';
    xlsxTempFiles($stub);
    xlsxTempFiles($path);

    return RoadmapXlsxBuilder::make($rows, $path);
}

function xlsxParse(string $file, string $range = 'null', bool $withStyles = false, bool $shifted = true): array
{
    $resolve = fn (string $name): ?int => XLSX_DISTRICTS[trim($name)] ?? null;

    return (new XlsxRoadmapParser($resolve, $range, $withStyles, $shifted))->parseFile($file);
}

/** The message of the RuntimeException $fn is expected to throw. */
function xlsxError(callable $fn): string
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    throw new RuntimeException('Expected a parse error, none was thrown.');
}

afterEach(function () {
    foreach (xlsxTempFiles(null, true) as $path) {
        @unlink($path);
    }
});

test('title and section headers: column A or B, Cyrillic look-alike numerals, consecutive numbering', function () {
    $parsed = xlsxParse(xlsxFile([
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],                     // r3
        ['measure', ['B' => 1, 'C' => 'Биринчи тадбир.', 'D' => 'Бажарилиш даражаси', 'E' => '%', 'F' => 100]],
        ['section', "\u{0406}I. Халқаро молия институтлари", ['col' => 'B']],                // r5 — Cyrillic І, in B
        ['measure', ['B' => 1, 'C' => 'Иккинчи тадбир.', 'D' => 'Канал', 'E' => 'км', 'F' => 12]],
    ]));

    expect($parsed['title_text'])->toBe('Сув хўжалиги йўл харитаси: Тест вилояти');
    expect($parsed['approvers_text'])->toBeNull();
    expect($parsed['warnings'])->toBe([]);
    expect(array_column($parsed['measures'], 'section_no'))->toBe([1, 2]);
    expect(array_column($parsed['measures'], 'section_title'))
        ->toBe(['Вилоятда амалга ошириладиган йирик лойиҳалар', 'Халқаро молия институтлари']);
    expect(array_column($parsed['measures'], 'source_row'))->toBe([4, 6]);
    expect($parsed['measures'][0]['district_id'])->toBeNull();
});

test('a gap in the section numbering aborts', function () {
    $file = xlsxFile([
        ['section', 'I. Биринчи'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => '%', 'F' => 100]],
        ['section', 'III. Учинчи'],
    ]);

    expect(xlsxError(fn () => xlsxParse($file)))->toContain('3');
});

test('district headers: numbered or not, in A or B, unclosed parenthesis, шаҳар form, hokim text', function () {
    $parsed = xlsxParse(xlsxFile([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],                                // r3
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],                          // r4
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 1]],
        ['district', '13. Тошкент тумани (масъул - туман ҳокими С.Эргашев', ['col' => 'B']],        // r6 — unclosed, in B
        ['measure', ['C' => 'б', 'D' => 'и', 'E' => 'км', 'F' => 2]],
        ['district', 'Боёвут тумани (масъул-туман ҳокими Н.Хазратқулов'],                            // r8 — no number
        ['measure', ['C' => 'в', 'D' => 'и', 'E' => 'км', 'F' => 3]],
        ['district', '15.Қувасой шаҳар'],                                                            // r10 — no space, no paren
        ['measure', ['C' => 'г', 'D' => 'и', 'E' => 'км', 'F' => 4]],
        ['raw', ['A' => '1733-5-1733221-4', 'B' => '16. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)']],   // r12 — stale key above the header
        ['measure', ['C' => 'д', 'D' => 'и', 'E' => 'км', 'F' => 5]],
    ]));

    expect(array_column($parsed['measures'], 'district_id'))->toBe([204, 500, 400, 300, 208]);
    expect(array_column($parsed['measures'], 'district_head_text'))->toBe([
        'туман ҳокими Ж.Назаров',
        'туман ҳокими С.Эргашев',
        'туман ҳокими Н.Хазратқулов',
        null,
        'туман ҳокими Р.Ўразбоев',
    ]);
    expect(array_column($parsed['measures'], 'seq_no'))->toBe([1, 1, 1, 1, 1]);
});

test('an unresolved district, a repeated one and a district row outside the district section all abort', function () {
    $unknown = xlsxFile([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Йўқтуман тумани (масъул – туман ҳокими X)'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 1]],
    ]);
    $message = xlsxError(fn () => xlsxParse($unknown));
    expect($message)->toContain('Йўқтуман тумани')->and($message)->toContain('4');

    $twice = xlsxFile([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 1]],
        ['district', '2. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['C' => 'б', 'D' => 'и', 'E' => 'км', 'F' => 2]],
    ]);
    expect(xlsxError(fn () => xlsxParse($twice)))->toContain('Боғот тумани');

    $outside = xlsxFile([
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 1]],
    ]);
    expect(xlsxError(fn () => xlsxParse($outside)))->toContain('Боғот тумани');
});

test('seq_no counts the rows: column B is only checked, column A never read, gaps warn', function () {
    $parsed = xlsxParse(xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['A' => 'AND-1-0-7', 'B' => 6, 'C' => 'Бир.', 'D' => 'и', 'E' => '%', 'F' => 100]],   // r4 — № 6, counted 1
        ['measure', ['A' => '1733-9-9-9', 'C' => 'Икки.', 'D' => 'и', 'E' => '%', 'F' => 100]],           // r5 — no №
        ['measure', ['B' => '3', 'C' => 'Уч.', 'D' => 'и', 'E' => '%', 'F' => 100]],                      // r6 — № 3 = counted 3
    ]));

    expect(array_column($parsed['measures'], 'seq_no'))->toBe([1, 2, 3]);
    expect($parsed['warnings'])->toBe(['r4: № 6 in file, counted 1']);
});

test('measure text splits into title and details; deadline collapses, responsibles join', function () {
    $parsed = xlsxParse(xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', [
            'C' => "Суғориш тармоқларини бетонлаштириш, жумладан:\n  1. 7,8 км хўжаликлараро каналлар;\n\n2. 33 км ички каналлар.",
            'D' => 'Хўжаликлараро каналлар', 'E' => 'км', 'F' => '7,8',
            'I' => "2026 йил\nдавомида",
            'J' => "Сув хўжалиги вазирлиги (Ў.Шералиев)\nВилоят ҳокимлиги (Ў.Машарипов),\nИлмий маслаҳатчи",
        ]],
        ['measure', ['C' => 'Ягона сатр.', 'D' => 'и', 'E' => '%', 'F' => 100, 'I' => 'Ҳар ойда бир маротаба']],
    ]));

    $m = $parsed['measures'][0];
    expect($m['body_raw'])->toBe("Суғориш тармоқларини бетонлаштириш, жумладан:\n1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар.");
    expect($m['title'])->toBe('Суғориш тармоқларини бетонлаштириш');
    expect($m['details'])->toBe("1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар.");
    expect($m['deadline_text'])->toBe('2026 йил давомида');
    expect($m['responsible_text'])->toBe('Сув хўжалиги вазирлиги (Ў.Шералиев), Вилоят ҳокимлиги (Ў.Машарипов), Илмий маслаҳатчи');

    expect($parsed['measures'][1]['details'])->toBeNull();
    expect($parsed['measures'][1]['deadline_text'])->toBe('Ҳар ойда бир маротаба');
    expect($parsed['measures'][1]['responsible_text'])->toBeNull();
});

test('the measure row carries the first indicator line, the following rows continue it', function () {
    $parsed = xlsxParse(xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'Бетонлаштириш.', 'D' => 'Хўжаликлараро каналлар', 'E' => 'км', 'F' => '7,8', 'H' => 'республика бюджети']],
        ['line', ['D' => 'Ички каналлар', 'E' => 'км', 'F' => 33]],
        ['line', ['D' => 'Гидропостлар', 'E' => 'та', 'F' => '1 240', 'G' => '15848', 'H' => 'ярми бажарилди']],
        ['measure', ['C' => 'Иккинчи.', 'D' => 'Бажарилиш даражаси', 'E' => '%', 'F' => 100]],
    ]));

    expect($parsed['measures'][0]['lines'])->toBe([
        ['label' => 'Хўжаликлараро каналлар', 'unit' => 'км', 'plan' => 7.8, 'actual' => null, 'note' => 'республика бюджети'],
        ['label' => 'Ички каналлар', 'unit' => 'км', 'plan' => 33.0, 'actual' => null, 'note' => null],
        ['label' => 'Гидропостлар', 'unit' => 'та', 'plan' => 1240.0, 'actual' => 15848.0, 'note' => 'ярми бажарилди'],
    ]);
    expect($parsed['measures'][1]['lines'])->toHaveCount(1);
    expect($parsed['measures'][0])->not->toHaveKey('funding_text');
});

test('units are normalised: м3 becomes м³, a capitalised Га becomes га', function () {
    $parsed = xlsxParse(xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'млн м3', 'F' => 1]],
        ['line', ['D' => 'и', 'E' => 'млн м 3', 'F' => 2]],
        ['line', ['D' => 'и', 'E' => 'Га', 'F' => 3]],
        ['line', ['D' => 'и', 'E' => ' та ', 'F' => 4]],
        ['line', ['D' => 'и', 'F' => 5]],
    ]));

    expect(array_column($parsed['measures'][0]['lines'], 'unit'))->toBe(['млн м³', 'млн м³', 'га', 'та', null]);
});

test('plans and actuals: decimal comma, grouped spaces, plain integers; a range is dropped with a warning', function () {
    $rows = [
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'кВт', 'F' => '18-25']],
        ['line', ['D' => 'и', 'E' => 'км', 'F' => 15848]],
        ['line', ['D' => 'и', 'E' => 'км']],
    ];

    $dropped = xlsxParse(xlsxFile($rows));
    expect(array_column($dropped['measures'][0]['lines'], 'plan'))->toBe([null, 15848.0, null]);
    expect($dropped['warnings'])->toBe(['r4: plan «18-25» is a range — stored without a plan']);

    expect(xlsxParse(xlsxFile($rows), 'lower')['measures'][0]['lines'][0]['plan'])->toBe(18.0);
    expect(xlsxParse(xlsxFile($rows), 'upper')['measures'][0]['lines'][0]['plan'])->toBe(25.0);

    $bad = xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 'кўп']],
    ]);
    expect(xlsxError(fn () => xlsxParse($bad)))->toContain('F4');
});

test('an indicator row without a label, and one before any measure, abort with different reasons', function () {
    $noLabel = xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 1]],
        ['line', ['E' => 'км', 'F' => 2]],
    ]);

    expect(xlsxError(fn () => xlsxParse($noLabel)))->toBe('D5: индикатор номи бўш.');

    $orphan = xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['line', ['D' => 'Индикатор', 'E' => 'км', 'F' => 2]],
    ]);

    expect(xlsxError(fn () => xlsxParse($orphan)))->toBe('D4: индикатор қатори чора-тадбирсиз.');
});

test('«туманидаги» in a header position is not a district header', function () {
    expect(XlsxRoadmapParser::matchDistrictHeader('1. Боғот туманидаги каналлар'))->toBeNull();
    expect(XlsxRoadmapParser::matchDistrictHeader('Қувасой шаҳарча'))->toBeNull();
    expect(XlsxRoadmapParser::matchDistrictHeader('1. Боғот тумани'))->toBe(['name' => 'Боғот тумани', 'head' => null]);

    // Header position, no measure text: nothing recognises it, so the row is reported, never
    // silently swallowed as a district (the real files say «… туманидаги …» inside C, a measure).
    $stray = xlsxFile([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 1]],
        ['raw', ['A' => 'Боғот туманидаги ишлар', 'I' => '2026 йил декабрь']],
    ]);
    expect(xlsxError(fn () => xlsxParse($stray)))->toContain('6-қатор');

    $inMeasure = xlsxParse(xlsxFile([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['C' => 'Боғот туманидаги каналларни тозалаш.', 'D' => 'и', 'E' => 'км', 'F' => 1]],
    ]));
    expect($inMeasure['measures'])->toHaveCount(1);
    expect($inMeasure['measures'][0]['title'])->toBe('Боғот туманидаги каналларни тозалаш.');
});

test('numbers a spreadsheet can distort are refused, naming the cell', function () {
    $rows = fn (mixed $plan, ?string $format = null) => [
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', array_filter(['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => $plan, 'F_format' => $format], fn ($v) => $v !== null)],
    ];

    expect(xlsxError(fn () => xlsxParse(xlsxFile($rows('1,240')))))->toContain('F4')->toContain('ноаниқ');
    expect(xlsxError(fn () => xlsxParse(xlsxFile($rows('9 999 999 999 999')))))->toContain('F4')->toContain('жуда катта');
    expect(xlsxError(fn () => xlsxParse(xlsxFile($rows(true)))))->toContain('мантиқий');

    // A percent-formatted 0,5 means 50, not 0,5 — but only a styled read can tell.
    expect(xlsxParse(xlsxFile($rows(0.5, '0%')))['measures'][0]['lines'][0]['plan'])->toBe(0.5);
    expect(xlsxError(fn () => xlsxParse(xlsxFile($rows(0.5, '0%')), withStyles: true)))->toContain('F4')->toContain('фоиз');
    expect(xlsxError(fn () => xlsxParse(xlsxFile($rows(45000, 'dd.mm.yyyy')), withStyles: true)))->toContain('сана');
});

test('thousands separated by a no-break space still read as one number', function () {
    $parsed = xlsxParse(xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'га', 'F' => "1\u{00A0}240"]],
        ['line', ['D' => 'и', 'E' => 'га', 'F' => "83\u{202F}000", 'G' => "15\u{2009}848,5"]],
    ]));

    expect(array_column($parsed['measures'][0]['lines'], 'plan'))->toBe([1240.0, 83000.0]);
    expect($parsed['measures'][0]['lines'][1]['actual'])->toBe(15848.5);
});

test('a stray key alone in A is a blank row, and blank rows do not close the measure block', function () {
    $parsed = xlsxParse(xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'а', 'D' => 'Биринчи', 'E' => 'км', 'F' => 1]],
        ['blank'],
        ['raw', ['A' => '1733-5-1733208-5']],                     // a key the region left behind
        ['line', ['D' => 'Иккинчи', 'E' => 'км', 'F' => 2]],
        ['raw', ['A' => '7']],                                    // a bare № typed into A
        ['measure', ['C' => 'б', 'D' => 'Учинчи', 'E' => 'км', 'F' => 3]],
    ]));

    expect($parsed['measures'])->toHaveCount(2);
    expect(array_column($parsed['measures'][0]['lines'], 'label'))->toBe(['Биринчи', 'Иккинчи']);
    expect($parsed['measures'][1]['seq_no'])->toBe(2);
});

test('a row shifted one column left is read as a measure with a «Бажарилиш даражаси» line', function () {
    $rows = [
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'а', 'D' => 'Биринчи', 'E' => 'км', 'F' => 1]],
        ['raw', ['D' => 'Гидротехник иншоотни тиклаш', 'E' => 'Бажарилиш даражаси', 'F' => '%']],
        ['measure', ['C' => 'б', 'D' => 'Учинчи', 'E' => 'км', 'F' => 3]],
    ];

    $parsed = xlsxParse(xlsxFile($rows));
    expect($parsed['measures'])->toHaveCount(3);
    expect($parsed['measures'][1]['title'])->toBe('Гидротехник иншоотни тиклаш');
    expect($parsed['measures'][1]['seq_no'])->toBe(2);
    expect($parsed['measures'][1]['lines'])
        ->toBe([['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0, 'actual' => null, 'note' => null]]);
    expect($parsed['measures'][2]['seq_no'])->toBe(3);
    expect($parsed['warnings'])->toBe(['r5: columns shifted left — read D as the measure text']);

    // --no-shifted: the same row is then just an indicator line whose plan reads «%».
    expect(xlsxError(fn () => xlsxParse(xlsxFile($rows), shifted: false)))->toContain('F5');
});

test('a measure without indicator lines is kept with a warning', function () {
    $parsed = xlsxParse(xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'Индикаторсиз тадбир.', 'I' => '2026 йил декабрь']],
    ]));

    expect($parsed['measures'][0]['lines'])->toBe([]);
    expect($parsed['warnings'])->toBe(['r4: measure without indicator lines']);
});

test('an unclassifiable row aborts, naming the row and its filled cells', function () {
    $file = xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 1]],
        ['raw', ['G' => 5, 'H' => 'изоҳ']],
    ]);

    $message = xlsxError(fn () => xlsxParse($file));
    expect($message)->toContain('5')->and($message)->toContain('G');
});

test('a measure before any section header aborts', function () {
    $file = xlsxFile([
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 1]],
    ]);

    expect(xlsxError(fn () => xlsxParse($file)))->toContain('3');
});

test('an unrecognised header row aborts before any row is read', function () {
    $file = xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 1]],
    ]);
    $book = IOFactory::load($file);
    $book->getSheet(0)->setCellValue('D2', 'Ўлчов');
    IOFactory::createWriter($book, 'Xlsx')->save($file);
    $book->disconnectWorksheets();

    expect(xlsxError(fn () => xlsxParse($file)))->toContain('header row');
});

test('only the first sheet is read — the instruction sheet is ignored', function () {
    $parsed = xlsxParse(xlsxFile([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['C' => 'а', 'D' => 'и', 'E' => 'км', 'F' => 1]],
    ]));

    expect($parsed['measures'])->toHaveCount(1);       // the «Йўриқнома» sheet opens with a section-shaped line
    expect($parsed['warnings'])->toBe([]);
});
