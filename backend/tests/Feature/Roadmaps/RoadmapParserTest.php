<?php

use App\Services\Roadmaps\DocxTableReader;
use App\Services\Roadmaps\RoadmapParser;
use Tests\Helpers\RoadmapDocxBuilder;

/** Districts of the fake region: name → id. Mirrors the real resolver's contract. */
function fakeDistricts(): Closure
{
    $map = ['боғот' => 11, 'тупроққалъа' => 12, 'тупроққала' => 12, 'урганч ш.' => 13];

    return function (string $name) use ($map): ?int {
        $key = mb_strtolower(trim($name));
        $key = preg_replace('/\s+тумани$/u', '', $key);
        $key = preg_replace('/\s+(шаҳри|шаҳар)$/u', ' ш.', $key);

        return $map[$key] ?? null;
    };
}

function parseFixture(array $rows): array
{
    $blocks = (new DocxTableReader())->read(RoadmapDocxBuilder::make($rows));

    return (new RoadmapParser(fakeDistricts()))->parse($blocks);
}

function standardRows(): array
{
    return [
        ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
        ['measure', ['«Куловот» каналини реконструкция қилиш лойиҳасида илмий-техник кузатиш.'], ['Республика бюджети маблағлари,', '32,0 млрд сўм'], ['2026 йил', 'декабрь'], ['Сув хўжалиги вазирлиги (Ў.Шералиев),', 'Вилоят ҳокимлиги (Ў.Машарипов)']],
        ['measure', ['484,5 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['Чапқирғоқ-Амударё', 'ИТҲБ (Э.Нурметов)']],
        ['section', 'II. Дуал таълимни ташкил қилиш'],
        ['measure', ['Талабаларни амалиётга юбориш:', '1. 9 нафар механизация.', '2. 5 нафар гидротехника.'], ['Университет маблағлари'], ['2026 йил апрель-октябрь'], ['Университет (Б.Мирзаев)']],
        ['section', 'III. Туманларда амалга ошириладиган лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['Суғориш тармоқларини бетонлаштириш, жумладан:', '1. 7,8 км хўжаликлараро каналлар;', '2. 33 км ички каналлар.'], ['Республика ва маҳаллий бюджет'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['measure', ['44 млн м3 сувни иқтисод қилиш.'], ['Маблағ талаб этилмайди'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '2. Тупроққала тумани (масъул – туман ҳокими А.Жималязов)'],
        ['measure', ['1 280 гектар ерда сув тежовчи технологиялар.'], ['Банк кредити'], ['2026 йил декабрь'], ['ИТҲБ (Э.Нурметов)']],
        ['district', '3. Урганч шаҳар'],
        ['measure', ['Шаҳар ичи каналларини тозалаш.'], ['Маҳаллий бюджет'], ['2026 йил декабрь'], ['Шаҳар ҳокимлиги']],
    ];
}

test('parses title, approvers and every measure with its section/district position', function () {
    $out = parseFixture(standardRows());

    expect($out['title_text'])->toBe('2026 йилда Тест вилоятида сув хўжалиги соҳасида амалга ошириладиган тадбирларнинг илмий ечимларига қаратилган “ЙЎЛ ХАРИТАСИ”');
    expect($out['approvers_text'])->toBe('ТАСДИҚЛАЙМАН Университет ректори | ТАСДИҚЛАЙМАН Сув хўжалиги вазири | ТАСДИҚЛАЙМАН Вилоят ҳокими');

    $m = $out['measures'];
    expect($m)->toHaveCount(7);

    expect($m[0])->toMatchArray([
        'section_no' => 1, 'section_title' => 'Вилоятда амалга ошириладиган йирик лойиҳалар',
        'district_id' => null, 'district_head_text' => null, 'seq_no' => 1,
        'title' => '«Куловот» каналини реконструкция қилиш лойиҳасида илмий-техник кузатиш.',
        'details' => null,
        'funding_text' => 'Республика бюджети маблағлари, 32,0 млрд сўм',
        'deadline_text' => '2026 йил декабрь',
        'responsible_text' => 'Сув хўжалиги вазирлиги (Ў.Шералиев), Вилоят ҳокимлиги (Ў.Машарипов)',
        'source_row' => 2,
    ]);
    expect($m[1]['seq_no'])->toBe(2);
    expect($m[1]['responsible_text'])->toBe('Чапқирғоқ-Амударё ИТҲБ (Э.Нурметов)');

    expect($m[2])->toMatchArray(['section_no' => 2, 'seq_no' => 1, 'title' => 'Талабаларни амалиётга юбориш']);
    expect($m[2]['details'])->toBe("1. 9 нафар механизация.\n2. 5 нафар гидротехника.");

    expect($m[3])->toMatchArray([
        'section_no' => 3, 'district_id' => 11, 'district_head_text' => 'туман ҳокими Ж.Назаров', 'seq_no' => 1,
        'title' => 'Суғориш тармоқларини бетонлаштириш',
        'details' => "1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар.",
        'body_raw' => "Суғориш тармоқларини бетонлаштириш, жумладан:\n1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар.",
    ]);
    expect($m[4])->toMatchArray(['district_id' => 11, 'seq_no' => 2]);
    expect($m[5])->toMatchArray(['district_id' => 12, 'district_head_text' => 'туман ҳокими А.Жималязов', 'seq_no' => 1]);   // seq resets per district
    expect($m[6])->toMatchArray(['district_id' => 13, 'district_head_text' => null, 'seq_no' => 1, 'title' => 'Шаҳар ичи каналларини тозалаш.']); // city row, no parenthesis
});

test('the Т/р cell is ignored even when it holds literal numbers; seq_no is counted by the parser', function () {
    $out = parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['raw', [['1.'], ['Канал реконструкцияси.'], ['Бюджет'], ['2026 йил декабрь'], ['СХВ']]],
        ['raw', [['7.'], ['Насослар таъмири.'], ['Бюджет'], ['2026 йил декабрь'], ['СХВ']]],
    ]);

    expect($out['measures'])->toHaveCount(2);
    expect($out['measures'][0])->toMatchArray(['seq_no' => 1, 'title' => 'Канал реконструкцияси.']);
    expect($out['measures'][1])->toMatchArray(['seq_no' => 2, 'title' => 'Насослар таъмири.']);
});

test('consecutive sections may repeat a title (Андижон IV and V)', function () {
    $out = parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['a'], ['b'], ['c'], ['d']],
        ['section', 'II. Вилоятнинг хусусиятидан келиб чиқиб амалга ошириладиган лойиҳалар'],
        ['measure', ['e'], ['b'], ['c'], ['d']],
        ['section', 'III. Вилоятнинг хусусиятидан келиб чиқиб амалга ошириладиган лойиҳалар'],
        ['measure', ['f'], ['b'], ['c'], ['d']],
    ]);

    expect(array_column($out['measures'], 'section_no'))->toBe([1, 2, 3]);
    expect($out['measures'][2]['section_title'])->toBe('Вилоятнинг хусусиятидан келиб чиқиб амалга ошириладиган лойиҳалар');
});

test('an unknown district aborts with the offending name', function () {
    $rows = standardRows();
    $rows[6] = ['district', '1. Йўқтуман тумани (масъул – туман ҳокими X)'];

    expect(fn () => parseFixture($rows))->toThrow(RuntimeException::class, 'Йўқтуман тумани');
});

test('a district header outside the district section aborts', function () {
    expect(fn () => parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
        ['measure', ['x'], ['y'], ['z'], ['w']],
    ]))->toThrow(RuntimeException::class, 'туманлар бўлимидан ташқарида');
});

test('a measure before any district header inside the district section aborts', function () {
    expect(fn () => parseFixture([
        ['section', 'I. Туманларда амалга ошириладиган лойиҳалар'],
        ['measure', ['x'], ['y'], ['z'], ['w']],
    ]))->toThrow(RuntimeException::class, 'туман сарлавҳасидан олдин');
});

test('non-consecutive section numbers abort', function () {
    expect(fn () => parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['x'], ['y'], ['z'], ['w']],
        ['section', 'III. Дуал таълим'],
    ]))->toThrow(RuntimeException::class, 'кутилган 2');
});

test('an unrecognised merged row aborts instead of being skipped', function () {
    expect(fn () => parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['raw', [['Изоҳ: бу қатор нима эканини билмаймиз']]],
    ]))->toThrow(RuntimeException::class, 'танилмаган');
});

test('fully empty rows are skipped and a measure row with only the body cell is still a measure', function () {
    $out = parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['raw', [[], [], [], [], []]],
        ['raw', [[], ['Фақат матн'], [], [], []]],
    ]);

    expect($out['measures'])->toHaveCount(1);
    expect($out['measures'][0])->toMatchArray(['title' => 'Фақат матн', 'funding_text' => null, 'deadline_text' => null, 'responsible_text' => null]);
});

test('a multi-cell row with an empty body cell aborts', function () {
    expect(fn () => parseFixture([
        ['section', 'I. Йирик лойиҳалар'],
        ['raw', [['1.'], [], ['Бюджет'], ['2026'], ['СХВ']]],
    ]))->toThrow(RuntimeException::class, '2-устун');
});

test('a document without a second table aborts', function () {
    $blocks = [['type' => 'tbl', 'rows' => [[['a']]]], ['type' => 'p', 'text' => 'title']];

    expect(fn () => (new RoadmapParser(fakeDistricts()))->parse($blocks))
        ->toThrow(RuntimeException::class, '2 та жадвал');
});
