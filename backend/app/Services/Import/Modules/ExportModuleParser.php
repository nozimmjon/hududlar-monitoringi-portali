<?php

namespace App\Services\Import\Modules;

use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'export'; }

    // Column layout verified via tinker against
    // '5.1-5.2-жадваллар (экспорт).xlsx' sheet '5-жадвал'
    // (dim=A1:P22, rollup row=6 where col A = 'Андижон вилояти'):
    //
    //   C(3)  = 2026 йил прогнози                           → year_forecast
    //   D(4)  = Январь-март, экспортчи корхона сони          → q1_exporters
    //   E(5)  = Январь-март, экспорт ҳажми                  → q1_value
    //   F(6)  = Январь-март, экспорт ҳажмининг ўсиши %да   → q1_growth
    //   G(7)  = Январь-март, Фарқи                          → IGNORE
    //   H(8)  = Январь-июнь, экспортчи корхона сони         → h1_exporters
    //   I(9)  = Январь-июнь, экспорт ҳажми кутилиш         → h1_expected
    //   J(10) = Январь-июнь, экспорт ҳажмининг ўсиши %да  → h1_growth
    //   K(11) = Январь-июнь, Фарқи                         → IGNORE
    //   L(12) = 2026 йил кутилиш, экспортчи корхона сони   → year_exporters
    //   M(13) = 2026 йил кутилиш, экспорт ҳажми            → year_expected
    //   N(14) = 2026 йил кутилиш, ўсиш %                   → year_growth
    //   O(15) = 2026 йил кутилиш, Фарқи                    → IGNORE
    private const COL_YEAR_FORECAST  = 3;   // C
    private const COL_Q1_EXPORTERS   = 4;   // D
    private const COL_Q1_VALUE       = 5;   // E
    private const COL_Q1_GROWTH      = 6;   // F
    private const COL_H1_EXPORTERS   = 8;   // H
    private const COL_H1_EXPECTED    = 9;   // I
    private const COL_H1_GROWTH      = 10;  // J
    private const COL_YEAR_EXPORTERS = 12;  // L
    private const COL_YEAR_EXPECTED  = 13;  // M
    private const COL_YEAR_GROWTH    = 14;  // N

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'export', 'export');
        if ($sheet === null) return 0;

        $rollupRow = $this->findRollupRow($sheet);
        if ($rollupRow === null) return 0;

        $count = 0;
        $count += $this->emitEntityRows($ctx, $sheet, $rollupRow, null, $filePath);

        for ($row = $rollupRow + 1; $row <= $rollupRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            $kind = $this->classifyRow($colA, $colB);
            if ($kind !== 'district') continue;

            $districtCode = $this->districtResolver->resolve(
                trim((string) $colB), $ctx,
                basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            if ($districtCode === null) continue;

            $count += $this->emitEntityRows($ctx, $sheet, $row, $districtCode, $filePath);
        }
        return $count;
    }

    /**
     * Find the rollup row: the row where col A is a short string ending with 'вилояти'
     * (e.g. 'Андижон вилояти'). Excludes long title/header cells that also contain
     * 'вилояти' as part of a sentence.
     */
    private function findRollupRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= 15; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            if ($this->isRollupCell($colA)) return $row;
        }
        return null;
    }

    /**
     * A rollup cell is a string ≤ 40 chars that ends with 'вилояти' (after trimming).
     * This distinguishes 'Андижон вилояти' (16 chars) from multi-sentence title rows.
     */
    private function isRollupCell(mixed $value): bool
    {
        if (! is_string($value)) return false;
        $trimmed = trim($value);
        return strlen($trimmed) <= 40 && str_ends_with($trimmed, 'вилояти');
    }

    /**
     * Classify a row:
     *   'rollup'   — col A is a short string ending with 'вилояти'
     *   'district' — col A is a positive integer (district sequence number) and col B is non-empty
     *   'skip'     — anything else (header, empty, section divider)
     */
    private function classifyRow(mixed $colA, mixed $colB): string
    {
        if ($this->isRollupCell($colA)) return 'rollup';
        if (! is_string($colB) || trim($colB) === '') return 'skip';
        if (is_int($colA) || (is_string($colA) && ctype_digit(trim((string) $colA)))) return 'district';
        return 'skip';
    }

    /**
     * Emit three IndicatorFactDto rows (q1, h1, year) for the given entity (region or district).
     *
     * Per-period field mapping:
     *   q1:   actual_hokimyat=q1_value, growth_pct=q1_growth, count_extra=q1_exporters
     *         (no plan, no expected)
     *   h1:   expected_value=h1_expected, growth_pct=h1_growth, count_extra=h1_exporters
     *         (no plan, no actual)
     *   year: plan_value=year_forecast, expected_value=year_expected, growth_pct=year_growth,
     *         count_extra=year_exporters (no actual)
     */
    private function emitEntityRows(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): int {
        $sourceLabel = basename($filePath) . " · {$sheet->getTitle()} · row $row";

        $yearForecast  = $this->numericOrNull($sheet->getCell([self::COL_YEAR_FORECAST,  $row])->getCalculatedValue());
        $q1Exporters   = $this->intOrNull(    $sheet->getCell([self::COL_Q1_EXPORTERS,   $row])->getCalculatedValue());
        $q1Value       = $this->numericOrNull($sheet->getCell([self::COL_Q1_VALUE,       $row])->getCalculatedValue());
        $q1Growth      = $this->numericOrNull($sheet->getCell([self::COL_Q1_GROWTH,      $row])->getCalculatedValue());
        $h1Exporters   = $this->intOrNull(    $sheet->getCell([self::COL_H1_EXPORTERS,   $row])->getCalculatedValue());
        $h1Expected    = $this->numericOrNull($sheet->getCell([self::COL_H1_EXPECTED,    $row])->getCalculatedValue());
        $h1Growth      = $this->numericOrNull($sheet->getCell([self::COL_H1_GROWTH,      $row])->getCalculatedValue());
        $yearExporters = $this->intOrNull(    $sheet->getCell([self::COL_YEAR_EXPORTERS, $row])->getCalculatedValue());
        $yearExpected  = $this->numericOrNull($sheet->getCell([self::COL_YEAR_EXPECTED,  $row])->getCalculatedValue());
        $yearGrowth    = $this->numericOrNull($sheet->getCell([self::COL_YEAR_GROWTH,    $row])->getCalculatedValue());

        $rows = [
            ['period' => 'q1',   'plan' => null,          'expected' => null,         'actual' => $q1Value,  'growth' => $q1Growth,   'extra' => $q1Exporters],
            ['period' => 'h1',   'plan' => null,          'expected' => $h1Expected,  'actual' => null,      'growth' => $h1Growth,   'extra' => $h1Exporters],
            ['period' => 'year', 'plan' => $yearForecast, 'expected' => $yearExpected,'actual' => null,      'growth' => $yearGrowth, 'extra' => $yearExporters],
        ];

        $count = 0;
        foreach ($rows as $r) {
            $dto = new IndicatorFactDto(
                regionCode:     $ctx->regionCode(),
                districtCode:   $districtCode,
                year:           $ctx->year,
                indicatorCode:  'export',
                period:         $r['period'],
                planValue:      $r['plan'],
                expectedValue:  $r['expected'],
                actualHokimyat: $r['actual'],
                growthPct:      $r['growth'],
                countExtra:     $r['extra'],
                unit:           'минг доллар',
                sourceLabel:    $sourceLabel,
            );
            $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
            $count++;
        }
        return $count;
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if (! is_numeric($value)) return null;
        return (float) $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (! is_numeric($value)) return null;
        return (int) $value;
    }
}
