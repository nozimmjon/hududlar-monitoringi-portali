<?php

use App\Services\Roadmaps\RoadmapParser;

test('roman numerals convert, including Cyrillic look-alikes', function () {
    expect(RoadmapParser::romanToInt('I'))->toBe(1);
    expect(RoadmapParser::romanToInt('IV'))->toBe(4);
    expect(RoadmapParser::romanToInt('V'))->toBe(5);
    expect(RoadmapParser::romanToInt('VI'))->toBe(6);
    expect(RoadmapParser::romanToInt('IX'))->toBe(9);
    expect(RoadmapParser::romanToInt("\u{0406}V"))->toBe(4);    // Cyrillic І typed instead of Latin I
    expect(RoadmapParser::romanToInt("\u{0425}"))->toBe(10);    // Cyrillic Х typed instead of Latin X
    expect(RoadmapParser::romanToInt("\u{0456}v"))->toBe(4);    // lowercase look-alikes upper-cased first
    expect(RoadmapParser::romanToInt('1'))->toBeNull();
    expect(RoadmapParser::romanToInt(''))->toBeNull();
});

test('section headers are recognised by a leading Roman numeral and a dot', function () {
    expect(RoadmapParser::matchSectionHeader('I. Вилоятда амалга ошириладиган йирик лойиҳалар'))
        ->toBe(['no' => 1, 'title' => 'Вилоятда амалга ошириладиган йирик лойиҳалар']);
    expect(RoadmapParser::matchSectionHeader('V.Туманларда амалга ошириладиган лойиҳалар'))
        ->toBe(['no' => 5, 'title' => 'Туманларда амалга ошириладиган лойиҳалар']);
    expect(RoadmapParser::matchSectionHeader('1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'))->toBeNull();
    expect(RoadmapParser::matchSectionHeader('Ирригация тармоқлари'))->toBeNull();
});

test('district headers yield the district name and the hokim text', function () {
    expect(RoadmapParser::matchDistrictHeader('1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'))
        ->toBe(['name' => 'Боғот тумани', 'head' => 'туман ҳокими Ж.Назаров']);
    expect(RoadmapParser::matchDistrictHeader('11. Тупроққалъа тумани (масъул - туман ҳокими А.Жималязов)'))
        ->toBe(['name' => 'Тупроққалъа тумани', 'head' => 'туман ҳокими А.Жималязов']);
    expect(RoadmapParser::matchDistrictHeader('3. Урганч шаҳри'))
        ->toBe(['name' => 'Урганч шаҳри', 'head' => null]);
    expect(RoadmapParser::matchDistrictHeader('1. Қувасой шаҳар (масъул – туман ҳокими З.Тўрақулов)'))
        ->toBe(['name' => 'Қувасой шаҳар', 'head' => 'туман ҳокими З.Тўрақулов']);
    expect(RoadmapParser::matchDistrictHeader('1. 7,8 км хўжаликлараро каналлар;'))->toBeNull();
    expect(RoadmapParser::matchDistrictHeader('II. Халқаро молия'))->toBeNull();
});

test('measure text splits into title and details', function () {
    // «жумладан» on the first line → title before it, rest = details
    expect(RoadmapParser::splitMeasure(['Насос станцияларида ишлари, жумладан:', '24 та насос агрегатлари.', '1 та электродвигател.']))
        ->toBe(['title' => 'Насос станцияларида ишлари', 'details' => "24 та насос агрегатлари.\n1 та электродвигател."]);
    // «жумладан» with list content on the same line → that content becomes the first detail line
    expect(RoadmapParser::splitMeasure(['Бетонлаштириш, жумладан: 7,8 км каналлар;', '33 км ички каналлар.']))
        ->toBe(['title' => 'Бетонлаштириш', 'details' => "7,8 км каналлар;\n33 км ички каналлар."]);
    // multi-line without «жумладан» → first line is the title (trailing colon stripped)
    expect(RoadmapParser::splitMeasure(['Илмий-тадқиқот ишларини бажариш:', '1. Каналларни бетонлаштириш.', '2. Сув сифатини баҳолаш.']))
        ->toBe(['title' => 'Илмий-тадқиқот ишларини бажариш', 'details' => "1. Каналларни бетонлаштириш.\n2. Сув сифатини баҳолаш."]);
    // single line → no details
    expect(RoadmapParser::splitMeasure(['484,5 млн м3 сувни иқтисод қилиш.']))
        ->toBe(['title' => '484,5 млн м3 сувни иқтисод қилиш.', 'details' => null]);
});

test('cell lines join with a comma after a closing bracket or full stop, else a space', function () {
    expect(RoadmapParser::joinLines(['2026 йил', 'декабрь']))->toBe('2026 йил декабрь');
    expect(RoadmapParser::joinLines(['Республика бюджети маблағлари,', '32,0 млрд сўм']))->toBe('Республика бюджети маблағлари, 32,0 млрд сўм');
    expect(RoadmapParser::joinLines(['Сув хўжалиги вазирлиги (Ў.Шералиев),', 'Вилоят ҳокимлиги (Ў.Машарипов),', 'Илмий маслаҳатчи', '(Б.Матякубов)']))
        ->toBe('Сув хўжалиги вазирлиги (Ў.Шералиев), Вилоят ҳокимлиги (Ў.Машарипов), Илмий маслаҳатчи (Б.Матякубов)');
    expect(RoadmapParser::joinLines(['Чапқирғоқ-Амударё', 'ИТҲБ (Э.Нурметов)', 'Туман ҳокимлари,', 'Илмий маслаҳатчи (Б.Матякубов)']))
        ->toBe('Чапқирғоқ-Амударё ИТҲБ (Э.Нурметов), Туман ҳокимлари, Илмий маслаҳатчи (Б.Матякубов)');
    expect(RoadmapParser::joinLines([]))->toBe('');
});
