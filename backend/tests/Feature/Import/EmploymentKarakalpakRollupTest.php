<?php

use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\EmploymentModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function makeEmploymentParser(): EmploymentModuleParser
{
    $issues = new IssueCollector();
    return new EmploymentModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        new DistrictResolver($issues),
        new StagingWriter(),
        $issues,
    );
}

test('findRollupRow finds row when col A == "ЖАМИ" (standard Andijan layout)', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A7', 'ЖАМИ');

    $result = invade(makeEmploymentParser())->findRollupRow($sheet);

    expect($result)->toBe(7);
});

test('findRollupRow finds row when col B == "ЖАМИ" (Karakalpak layout)', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('B6', 'ЖАМИ');

    $result = invade(makeEmploymentParser())->findRollupRow($sheet);

    expect($result)->toBe(6);
});

test('classifyRow returns "rollup" when col B == "ЖАМИ" (Karakalpak col B layout)', function () {
    $result = invade(makeEmploymentParser())->classifyRow(null, 'ЖАМИ');

    expect($result)->toBe('rollup');
});
