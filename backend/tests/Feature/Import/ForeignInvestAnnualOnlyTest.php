<?php

use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\ForeignInvestModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

function makeForeignInvestParser(): ForeignInvestModuleParser
{
    $issues = new IssueCollector();
    return new ForeignInvestModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        new DistrictResolver($issues),
        new StagingWriter(),
        $issues,
    );
}

test('isAnnualOnlyLayout returns true when no "I чорак" appears in first 10 rows', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A4', 'Ҳудудлар');
    $sheet->setCellValue('B4', 'Хорижий инвестициялар прогнози (ВМҚ-86)');

    expect(invade(makeForeignInvestParser())->isAnnualOnlyLayout($sheet))->toBeTrue();
});

test('isAnnualOnlyLayout returns false when "I чорак" appears in row range', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A4', 'Шаҳар ва туманлар номи');
    $sheet->setCellValue('I4', 'I чорак прогноз');

    expect(invade(makeForeignInvestParser())->isAnnualOnlyLayout($sheet))->toBeFalse();
});

test('isRollupCell accepts standard region with "вилояти" suffix', function () {
    expect(invade(makeForeignInvestParser())->isRollupCell('Андижон вилояти'))->toBeTrue();
});

test('isRollupCell accepts "Жами" (used in karakalpak)', function () {
    expect(invade(makeForeignInvestParser())->isRollupCell('Жами'))->toBeTrue();
});

test('isRollupCell accepts "Қорақалпоғистон Республикаси"', function () {
    expect(invade(makeForeignInvestParser())->isRollupCell('Қорақалпоғистон Республикаси'))->toBeTrue();
});

test('isRollupCell rejects multi-sentence title cells', function () {
    $longTitle = 'Қорақалпоғистон Республикасининг 2026 йил учун инвестиция прогноз кўрсаткичлари тўғрисида МАЪЛУМОТ';
    expect(invade(makeForeignInvestParser())->isRollupCell($longTitle))->toBeFalse();
});

test('isRollupCell rejects non-strings', function () {
    expect(invade(makeForeignInvestParser())->isRollupCell(42))->toBeFalse();
    expect(invade(makeForeignInvestParser())->isRollupCell(null))->toBeFalse();
});
