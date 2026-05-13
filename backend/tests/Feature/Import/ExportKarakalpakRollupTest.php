<?php

use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\ExportModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function makeExportParser(): ExportModuleParser
{
    $issues = new IssueCollector();
    return new ExportModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        new DistrictResolver($issues),
        new StagingWriter(),
        $issues,
    );
}

test('isRollupCell accepts "Қорақалпоғистон Республикаси" (Karakalpak region name)', function () {
    $result = invade(makeExportParser())->isRollupCell('Қорақалпоғистон Республикаси');

    expect($result)->toBeTrue();
});

test('isRollupCell accepts "Жами" (Karakalpak total label)', function () {
    $result = invade(makeExportParser())->isRollupCell('Жами');

    expect($result)->toBeTrue();
});
