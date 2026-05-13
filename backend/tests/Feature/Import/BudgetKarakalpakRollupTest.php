<?php

use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\BudgetModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function makeBudgetParser(): BudgetModuleParser
{
    $issues = new IssueCollector();
    return new BudgetModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        new DistrictResolver($issues),
        new StagingWriter(),
        $issues,
    );
}

test('findRollupRow returns row for "Андижон вилояти" (standard вилояти suffix)', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('B5', 'Андижон вилояти');

    $result = invade(makeBudgetParser())->findRollupRow($sheet);

    expect($result)->toBe(5);
});

test('findRollupRow returns row for "Қорақалпоғистон Республикаси" (Karakalpak)', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A7', 'Қорақалпоғистон Республикаси');

    $result = invade(makeBudgetParser())->findRollupRow($sheet);

    expect($result)->toBe(7);
});
