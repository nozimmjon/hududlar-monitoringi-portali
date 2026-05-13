<?php

namespace App\Services\Import\Modules;

use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ForeignInvestModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'foreign_invest'; }

    // Column layout verified via tinker against
    // '4.2-жадвал (инвестициялар).xlsx' sheet '4,2-хорижий инв'
    // (dim=A1:AJ24, rollup row=7 where col A = 'Андижон вилояти'):
    //
    //   G(7)  = Хорижий инвестициялар прогнози (ВМҚ-86)  → year_forecast
    //   H(8)  = ўсиш %                                   → (skipped)
    //   I(9)  = I чорак прогноз                          → q1_plan
    //   L(12) = амалда (тезкор) млн долл.                → q1_actual
    //   M(13) = бажарилиш %                              → q1_pct
    //   O(15) = I ярим йиллик прогноз млн долл.          → h1_plan
    //   R(18) = кутилиш (тезкор) млн долл.               → h1_expected
    //   S(19) = бажарилиш %                              → h1_pct
    //   U(21) = 2026 йил кутилиш (тезкор) млн долл.      → year_expected
    //   V(22) = бажарилиш %                              → year_pct
    //   X(24) = I-чоракда ишга тушган лойиҳалар сони      → q1_projects
    //   [(27) = I-ярим йиллик (кутилиш) сони              → h1_projects
    //   ](29) = I-ярим йиллик иш ўрни та                 → h1_jobs
    private const COL_YEAR_FORECAST  = 7;   // G
    private const COL_Q1_PLAN        = 9;   // I
    private const COL_Q1_ACTUAL      = 12;  // L
    private const COL_Q1_PCT         = 13;  // M
    private const COL_H1_PLAN        = 15;  // O
    private const COL_H1_EXPECTED    = 18;  // R
    private const COL_H1_PCT         = 19;  // S
    private const COL_YEAR_EXPECTED  = 21;  // U
    private const COL_YEAR_PCT       = 22;  // V
    private const COL_Q1_PROJECTS    = 24;  // X
    private const COL_H1_PROJECTS    = 27;  // [
    private const COL_H1_JOBS        = 29;  // ]

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'foreign_invest', 'foreign_invest');
        if ($sheet === null) return 0;

        if ($this->isAnnualOnlyLayout($sheet)) {
            return $this->parseAnnualOnly($ctx, $sheet, $filePath);
        }

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
     * A rollup cell is a short string (≤ 40 chars) that ends with 'вилояти',
     * 'Республикаси', or is exactly 'Жами'. This covers standard region rollups
     * (e.g. 'Андижон вилояти'), karakalpak republic rollup, and karakalpak total row.
     */
    private function isRollupCell(mixed $value): bool
    {
        if (! is_string($value)) return false;
        $trimmed = trim($value);
        if (mb_strlen($trimmed) > 40) return false;
        return str_ends_with($trimmed, 'вилояти')
            || str_ends_with($trimmed, 'Республикаси')
            || $trimmed === 'Жами';
    }

    /**
     * Classify a row:
     *   'rollup'   — col A is a short string ending with 'вилояти'
     *   'district' — col A is a positive integer (district sequence number)
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
     * Per-period field mapping (per-plan context):
     *   q1:   plan_value=q1_plan, actual_hokimyat=q1_actual, count_extra=q1_projects
     *   h1:   plan_value=h1_plan, expected_value=h1_expected, count_extra=h1_projects, count_extra_2=h1_jobs
     *   year: plan_value=year_forecast, expected_value=year_expected
     */
    private function emitEntityRows(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): int {
        $sourceLabel = basename($filePath) . " · {$sheet->getTitle()} · row $row";

        $q1Plan      = $this->numericOrNull($sheet->getCell([self::COL_Q1_PLAN,       $row])->getCalculatedValue());
        $q1Actual    = $this->numericOrNull($sheet->getCell([self::COL_Q1_ACTUAL,     $row])->getCalculatedValue());
        $q1Pct       = $this->ratioToPercent($sheet->getCell([self::COL_Q1_PCT,       $row])->getCalculatedValue());
        $q1Projects  = $this->intOrNull(    $sheet->getCell([self::COL_Q1_PROJECTS,   $row])->getCalculatedValue());

        $h1Plan      = $this->numericOrNull($sheet->getCell([self::COL_H1_PLAN,       $row])->getCalculatedValue());
        $h1Expected  = $this->numericOrNull($sheet->getCell([self::COL_H1_EXPECTED,   $row])->getCalculatedValue());
        $h1Pct       = $this->ratioToPercent($sheet->getCell([self::COL_H1_PCT,       $row])->getCalculatedValue());
        $h1Projects  = $this->intOrNull(    $sheet->getCell([self::COL_H1_PROJECTS,   $row])->getCalculatedValue());
        $h1Jobs      = $this->intOrNull(    $sheet->getCell([self::COL_H1_JOBS,       $row])->getCalculatedValue());

        $yearForecast = $this->numericOrNull($sheet->getCell([self::COL_YEAR_FORECAST, $row])->getCalculatedValue());
        $yearExpected = $this->numericOrNull($sheet->getCell([self::COL_YEAR_EXPECTED, $row])->getCalculatedValue());
        $yearPct      = $this->ratioToPercent($sheet->getCell([self::COL_YEAR_PCT,     $row])->getCalculatedValue());

        $rows = [
            ['period' => 'q1',   'plan' => $q1Plan,        'expected' => null,          'actual' => $q1Actual,  'pct' => $q1Pct,   'extra' => $q1Projects, 'extra2' => null],
            ['period' => 'h1',   'plan' => $h1Plan,        'expected' => $h1Expected,   'actual' => null,       'pct' => $h1Pct,   'extra' => $h1Projects, 'extra2' => $h1Jobs],
            ['period' => 'year', 'plan' => $yearForecast,  'expected' => $yearExpected, 'actual' => null,       'pct' => $yearPct, 'extra' => null,        'extra2' => null],
        ];

        $count = 0;
        foreach ($rows as $r) {
            $dto = new IndicatorFactDto(
                regionCode:     $ctx->regionCode(),
                districtCode:   $districtCode,
                year:           $ctx->year,
                indicatorCode:  'investment',
                period:         $r['period'],
                planValue:      $r['plan'],
                expectedValue:  $r['expected'],
                actualHokimyat: $r['actual'],
                pctOfPlan:      $r['pct'],
                countExtra:     $r['extra'],
                countExtra2:    $r['extra2'],
                unit:           'млн доллар',
                sourceLabel:    $sourceLabel,
            );
            $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
            $count++;
        }
        return $count;
    }

    private function parseAnnualOnly(ImportContext $ctx, Worksheet $sheet, string $filePath): int
    {
        $rollupRow = $this->findRollupRow($sheet);
        if ($rollupRow === null) return 0;

        $count = 0;
        $count += $this->emitAnnualRow($ctx, $sheet, $rollupRow, null, $filePath);

        for ($row = $rollupRow + 1; $row <= $rollupRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            if (! is_string($colA) || trim($colA) === '') continue;
            if (! is_numeric($colB)) continue;

            $districtCode = $this->districtResolver->resolve(
                trim($colA), $ctx,
                basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            if ($districtCode === null) continue;

            $count += $this->emitAnnualRow($ctx, $sheet, $row, $districtCode, $filePath);
        }
        return $count;
    }

    private function emitAnnualRow(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?int $districtCode,
        string $filePath,
    ): int {
        $value = $this->numericOrNull($sheet->getCell([2, $row])->getCalculatedValue());
        if ($value === null) return 0;

        $dto = new IndicatorFactDto(
            regionCode:     $ctx->regionCode(),
            districtCode:   $districtCode,
            year:           $ctx->year,
            indicatorCode:  'investment',
            period:         'year',
            planValue:      $value,
            expectedValue:  null,
            actualHokimyat: null,
            pctOfPlan:      null,
            countExtra:     null,
            countExtra2:    null,
            unit:           'млн доллар',
            sourceLabel:    basename($filePath) . " · {$sheet->getTitle()} · row $row",
        );
        $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
        return 1;
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

    /**
     * Excel percent-formatted cells (e.g. 105%) deliver as 1.05 from PhpSpreadsheet.
     * Multiply by 100 so DB stores percentage units consistently with other parsers.
     */
    private function ratioToPercent(mixed $value): ?float
    {
        $num = $this->numericOrNull($value);
        return $num === null ? null : $num * 100;
    }

    /**
     * Returns true when the sheet has no "I чорак" header in the first 10 rows,
     * indicating a karakalpak-style annual-only layout instead of the standard
     * quarterly layout used by Andijan and other regions.
     */
    private function isAnnualOnlyLayout(Worksheet $sheet): bool
    {
        $rows = $sheet->toArray(null, true, true, false);
        $limit = min(10, count($rows));
        for ($i = 0; $i < $limit; $i++) {
            foreach ($rows[$i] as $cell) {
                if (is_string($cell) && mb_stripos($cell, 'I чорак') !== false) {
                    return false;
                }
            }
        }
        return true;
    }
}
