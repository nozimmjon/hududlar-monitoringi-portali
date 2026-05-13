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

test('parse() annual-only branch: karakalpak fixture produces 3 staging rows (1 rollup + 2 districts)', function () {
    $this->seed();

    // The SoatoSeeder seeds region 1735 (karakalpak) and its districts from districts.xlsx.
    // Use real seeded district names so DistrictResolver can resolve them.
    $region = \App\Models\Region::where('code', 1735)->first();
    if ($region === null) {
        test()->markTestSkipped('Karakalpak region (code=1735) not seeded — districts.xlsx may be missing.');
    }

    // Pick 2 real karakalpak districts from the seeded data
    $seededDistricts = DB::table('districts')
        ->where('region_code', 1735)
        ->orderBy('code')
        ->limit(2)
        ->get(['code', 'name_full']);

    if ($seededDistricts->count() < 2) {
        test()->markTestSkipped('Not enough karakalpak districts seeded.');
    }

    $d0Code = (int) $seededDistricts[0]->code;
    $d1Code = (int) $seededDistricts[1]->code;
    $d0Name = $seededDistricts[0]->name_full;  // e.g. "Амударё тумани"
    $d1Name = $seededDistricts[1]->name_full;  // e.g. "Беруний тумани"

    // Build in-memory xlsx and write to temp file
    $tmpDir = sys_get_temp_dir() . '/fi_annual_' . uniqid();
    mkdir($tmpDir, 0777, true);
    $tmpFile = $tmpDir . '/fixture.xlsx';

    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('4.2. Хорижий инвестициялар');
    $sheet->setCellValue('A2', 'Қорақалпоғистон Республикасининг 2026 йил учун инвестиция прогноз кўрсаткичлари');
    $sheet->setCellValue('A4', 'Ҳудудлар');
    $sheet->setCellValue('B4', 'Хорижий инвестициялар прогнози (ВМҚ-86)');
    $sheet->setCellValue('B6', 'млн долл.');
    // Rollup row — "Жами" in col A, value in col B
    $sheet->setCellValue('A7', 'Жами');
    $sheet->setCellValue('B7', 633);
    // District rows — real seeded name_full in col A, value in col B
    $sheet->setCellValue('A8', $d0Name);
    $sheet->setCellValue('B8', 200);
    $sheet->setCellValue('A9', $d1Name);
    $sheet->setCellValue('B9', 433);

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book, 'Xlsx');
    $writer->save($tmpFile);

    // Create DB records
    $rwb = \App\Models\RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'foreign_invest')->value('id'),
        'file_name'         => 'fixture.xlsx',
        'file_path'         => $tmpFile,
        'last_seen_at'      => now(),
    ]);

    $run = \App\Models\ImportRun::create([
        'region_code' => 1735, 'year' => 2026, 'trigger_kind' => 'cli',
        'status' => 'parsing', 'started_at' => now(),
    ]);

    $ctx = new \App\Services\Import\ImportContext(
        run: $run,
        region: $region,
        year: 2026,
        dataPath: base_path('../data'),
    );

    $issues  = new IssueCollector();
    $staging = new StagingWriter();
    $parser  = new ForeignInvestModuleParser(
        new SheetResolver($issues),
        new \App\Services\Import\HeaderDetector($issues),
        new DistrictResolver($issues),
        $staging,
        $issues,
    );

    $count = $parser->parse($ctx, $tmpFile, $rwb->id);

    // Cleanup
    unlink($tmpFile);
    rmdir($tmpDir);

    // 1 rollup row + 2 district rows = 3 total
    expect($count)->toBe(3);
    expect($staging->bufferedCount('import_staging_indicator_facts'))->toBe(3);

    $staging->flush();
    $facts = DB::table('import_staging_indicator_facts')
        ->where('import_run_id', $run->id)
        ->get();

    expect($facts)->toHaveCount(3);

    $rollup = $facts->firstWhere('district_code', null);
    expect($rollup)->not->toBeNull();
    expect($rollup->period)->toBe('year');
    expect((float) $rollup->plan_value)->toBe(633.0);

    $firstDistrict = $facts->first(fn ($r) => (int) $r->district_code === $d0Code);
    expect($firstDistrict)->not->toBeNull(
        "Expected district_code={$d0Code} ({$d0Name}) in staging. " .
        "Got codes: " . $facts->pluck('district_code')->implode(',')
    );
    expect($firstDistrict->period)->toBe('year');
    expect((float) $firstDistrict->plan_value)->toBe(200.0);
});
