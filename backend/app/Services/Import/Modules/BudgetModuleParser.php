<?php

namespace App\Services\Import\Modules;

use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BudgetModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'budget'; }

    // Column layout verified via tinker against 3-жадвал (бюджет).xlsx тушум sheet:
    //   C(3)=year_plan  D(4)=h1_plan   E(5)=q2_plan
    //   F(6)=year_exp   G(7)=h1_exp    H(8)=q2_exp
    //   I(9)=year(+;-)  J(10)=year%    K(11)=h1(+;-)
    //   L(12)=h1%       M(13)=q2(+;-)  N(14)=q2%
    private const COL_PLAN_YEAR   = 3;   // C
    private const COL_PLAN_H1     = 4;   // D
    private const COL_PLAN_Q2     = 5;   // E
    private const COL_EXP_YEAR    = 6;   // F
    private const COL_EXP_H1      = 7;   // G
    private const COL_EXP_Q2      = 8;   // H
    private const COL_EXEC_H1_PCT = 12;  // L
    private const COL_EXEC_Q2_PCT = 14;  // N

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'budget', 'budget');
        if ($sheet === null) return 0;

        $rollupRow = $this->findRollupRow($sheet);
        if ($rollupRow === null) return 0;

        $count = 0;
        $count += $this->emitEntityRows($ctx, $sheet, $rollupRow, null, $filePath);

        for ($row = $rollupRow + 1; $row <= $rollupRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            if (! $this->isDistrictRow($colA, $colB)) continue;
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
     * Find the rollup row: the row where col A or col B contains a short string ending with
     * 'вилояти' (e.g. 'Андижон вилояти') or 'Республикаси' (Karakalpak).
     */
    private function findRollupRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= 15; $row++) {
            foreach ([1, 2] as $col) {
                $val = $sheet->getCell([$col, $row])->getCalculatedValue();
                if (! is_string($val)) continue;
                $trimmed = trim($val);
                if (mb_strlen($trimmed) > 40) continue;
                if (str_ends_with($trimmed, 'вилояти') || str_ends_with($trimmed, 'Республикаси')) {
                    return $row;
                }
            }
        }
        return null;
    }

    /**
     * District rows have an integer in col A (2..17 in Andijan's budget sheet).
     * Row with col A=1 is the 'ДСБ солиқ тўловчилари' tax-category rollup — not a district.
     * DistrictResolver will return null for non-district strings, so the guard here is
     * simply that col A is a positive integer and col B is a non-empty string.
     */
    private function isDistrictRow(mixed $colA, mixed $colB): bool
    {
        if (! is_string($colB) || trim($colB) === '') return false;
        if (is_int($colA) && $colA > 0) return true;
        if (is_string($colA) && ctype_digit(trim($colA)) && (int) trim($colA) > 0) return true;
        return false;
    }

    /**
     * Emit three IndicatorFactDto rows (year, h1, q2) for the given entity (region or district).
     * Skips a period only when both plan and expected are null/non-numeric.
     */
    private function emitEntityRows(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): int {
        $plans = [
            'year' => $this->numericOrNull($sheet->getCell([self::COL_PLAN_YEAR, $row])->getCalculatedValue()),
            'h1'   => $this->numericOrNull($sheet->getCell([self::COL_PLAN_H1,   $row])->getCalculatedValue()),
            'q2'   => $this->numericOrNull($sheet->getCell([self::COL_PLAN_Q2,   $row])->getCalculatedValue()),
        ];
        $expecteds = [
            'year' => $this->numericOrNull($sheet->getCell([self::COL_EXP_YEAR, $row])->getCalculatedValue()),
            'h1'   => $this->numericOrNull($sheet->getCell([self::COL_EXP_H1,   $row])->getCalculatedValue()),
            'q2'   => $this->numericOrNull($sheet->getCell([self::COL_EXP_Q2,   $row])->getCalculatedValue()),
        ];
        $execPcts = [
            'year' => null,
            'h1'   => $this->numericOrNull($sheet->getCell([self::COL_EXEC_H1_PCT, $row])->getCalculatedValue()),
            'q2'   => $this->numericOrNull($sheet->getCell([self::COL_EXEC_Q2_PCT, $row])->getCalculatedValue()),
        ];

        $count = 0;
        foreach (['year', 'h1', 'q2'] as $period) {
            if ($plans[$period] === null && $expecteds[$period] === null) {
                continue;
            }
            $dto = new IndicatorFactDto(
                regionCode:    $ctx->regionCode(),
                districtCode:  $districtCode,
                year:          $ctx->year,
                indicatorCode: 'budget',
                period:        $period,
                planValue:     $plans[$period],
                expectedValue: $expecteds[$period],
                pctOfPlan:     $execPcts[$period],
                unit:          'млрд сўм',
                sourceLabel:   basename($filePath) . " · {$sheet->getTitle()} · row $row",
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
}
