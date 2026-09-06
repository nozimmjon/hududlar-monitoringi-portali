<?php

use App\Services\Roadmaps\DocxTableReader;
use Tests\Helpers\RoadmapDocxBuilder;

test('reads body blocks in order: approvers table, title paragraphs, road-map table', function () {
    $file = RoadmapDocxBuilder::make([
        ['section', 'I. Йирик лойиҳалар'],
        ['measure', ['Канал', "Сатр икки\u{00A0}NBSP билан"], ['Республика бюджети,', '32,0 млрд сўм'], ['2026 йил', 'декабрь'], ['СХВ (Ў.Шералиев)']],
    ]);

    $blocks = (new DocxTableReader())->read($file);

    expect($blocks[0]['type'])->toBe('tbl');
    expect($blocks[0]['rows'])->toHaveCount(1);
    expect($blocks[0]['rows'][0])->toHaveCount(3);
    expect($blocks[0]['rows'][0][0])->toBe(['ТАСДИҚЛАЙМАН Университет ректори']);

    expect($blocks[1])->toBe(['type' => 'p', 'text' => '2026 йилда Тест вилоятида сув хўжалиги соҳасида амалга ошириладиган']);
    expect($blocks[3])->toBe(['type' => 'p', 'text' => '“ЙЎЛ ХАРИТАСИ”']);

    $tbl = $blocks[4];
    expect($tbl['type'])->toBe('tbl');
    expect($tbl['rows'][0])->toBe(array_map(fn ($h) => [$h], RoadmapDocxBuilder::HEADER));
    expect($tbl['rows'][1])->toBe([['I. Йирик лойиҳалар']]);                 // merged → ONE cell
    expect($tbl['rows'][2])->toBe([
        [],                                                                  // empty Т/р cell
        ['Канал', 'Сатр икки NBSP билан'],                                   // NBSP → space
        ['Республика бюджети,', '32,0 млрд сўм'],
        ['2026 йил', 'декабрь'],
        ['СХВ (Ў.Шералиев)'],
    ]);
    expect($blocks[5]['type'])->toBe('tbl');                                 // trailing signers table
});

test('soft breaks (w:br) split lines exactly like paragraphs', function () {
    $file = RoadmapDocxBuilder::make([
        ['measure', ["Бетонлаштириш, жумладан:\n1. 7,8 км;\n2. 33 км."], ['x'], ['y'], ['z']],
    ]);

    $rows = (new DocxTableReader())->read($file)[4]['rows'];

    expect($rows[1][1])->toBe(['Бетонлаштириш, жумладан:', '1. 7,8 км;', '2. 33 км.']);
});

test('a non-docx file is rejected with a clear message', function () {
    $file = tempnam(sys_get_temp_dir(), 'notdocx_');
    file_put_contents($file, 'hello');

    expect(fn () => (new DocxTableReader())->read($file))
        ->toThrow(RuntimeException::class, 'docx');
});

test('Word auto-numbering (w:numPr) and tab-stop definitions contribute no text', function () {
    $body = '<w:tbl><w:tr>'
        . '<w:tc><w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="3"/></w:numPr><w:tabs><w:tab w:val="left" w:pos="720"/></w:tabs></w:pPr></w:p></w:tc>'
        . '<w:tc><w:p><w:pPr><w:tabs><w:tab w:val="left" w:pos="720"/></w:tabs></w:pPr><w:r><w:t>Матн</w:t></w:r></w:p><w:p><w:r><w:t>Иккинчи</w:t></w:r></w:p></w:tc>'
        . '</w:tr></w:tbl>';
    $file = RoadmapDocxBuilder::makeRaw($body);

    $rows = (new DocxTableReader())->read($file)[0]['rows'];

    expect($rows)->toBe([[[], ['Матн', 'Иккинчи']]]);
});

test('run-level tabs become a space and no-break hyphens a dash; empty document.xml is rejected', function () {
    $body = '<w:p><w:r><w:t>Чап</w:t></w:r><w:r><w:tab/></w:r><w:r><w:t>Ўнг</w:t></w:r><w:r><w:noBreakHyphen/></w:r><w:r><w:t>дефис</w:t></w:r></w:p>';
    expect((new DocxTableReader())->read(RoadmapDocxBuilder::makeRaw($body)))
        ->toBe([['type' => 'p', 'text' => 'Чап Ўнг-дефис']]);

    $empty = tempnam(sys_get_temp_dir(), 'roadmap_') . '.docx';
    $zip = new ZipArchive();
    $zip->open($empty, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '');
    $zip->close();
    expect(fn () => (new DocxTableReader())->read($empty))->toThrow(RuntimeException::class, 'бўш');
});
