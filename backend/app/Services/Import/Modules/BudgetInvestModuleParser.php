<?php

namespace App\Services\Import\Modules;

use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BudgetInvestModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'budget_invest'; }

    // Column layout verified via tinker against 4.1-жадвал (бюджет инвестка).xlsx sheet '2.Анд'
    // (dim=A1:W41, rollup row=7):
    //   C(3)=объект сони  D(4)=лимит
    //   E(5)=q1 ўзлаш    F(6)=q1 %    G(7)=q1 молиялаштириш   H(8)=q1 %  -- G/H skipped
    //   I(9)=h1 ўзлаш    J(10)=h1 %   K(11)=h1 молиялаштириш  L(12)=h1 % -- K/L skipped
    //   M(13)=year ўзлаш N(14)=year % O(15)=year молиял.       P(16)=year % -- O/P skipped
    //   Q(17)=commission q1 сони  R(18)=commission q1 қиймати
    //   S(19)=commission h1 сони  T(20)=commission h1 қиймати
    //   U(21)=commission year сони  V(22)=commission year қиймати -- only U captured
    private const COL_OBJECTS              = 3;   // C: объект сони
    private const COL_LIMIT                = 4;   // D: лимит (plan)
    private const COL_Q1_ABSORPTION        = 5;   // E: q1 ўзлаштириш
    private const COL_Q1_PCT               = 6;   // F: q1 %
    private const COL_H1_ABSORPTION        = 9;   // I: h1 ўзлаштириш
    private const COL_H1_PCT               = 10;  // J: h1 %
    private const COL_YEAR_ABSORPTION      = 13;  // M: year ўзлаштириш
    private const COL_YEAR_PCT             = 14;  // N: year %
    private const COL_COMMISSIONING_COUNT  = 21;  // U: фойдаланишга топшириш year сони

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'budget_invest', 'budget_invest');
        if ($sheet === null) return 0;

        $rollupRow = $this->findRollupRow($sheet);
        if ($rollupRow === null) return 0;

        $count = 0;
        $count += $this->emitEntityRows($ctx, $sheet, $rollupRow, null, $filePath);

        for ($row = $rollupRow + 1; $row <= $rollupRow + 40; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            $kind = $this->classifyRow($colA, $colB);
            if ($kind === 'skip' || $kind === 'rollup') continue;
            // kind === 'district'
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
     * Find the rollup row: the row where col B contains exactly 'Жами'.
     */
    private function findRollupRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= 15; $row++) {
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            if (is_string($colB) && trim($colB) === 'Жами') return $row;
        }
        return null;
    }

    /**
     * Classify a row by its col A / col B values:
     *   'rollup'   — col B = 'Жами'
     *   'skip'     — ownership/section divider rows (шу жумладан:, *буюртмачилигида:, etc.)
     *   'district' — col A is a positive integer (row number in the district list)
     */
    private function classifyRow(mixed $colA, mixed $colB): string
    {
        if (! is_string($colB) || trim($colB) === '') return 'skip';
        $b = trim($colB);
        if ($b === 'Жами') return 'rollup';
        if (str_contains($b, 'жумладан')) return 'skip';
        if (str_contains($b, 'буюртмачи')) return 'skip';
        if (is_int($colA) || (is_string($colA) && ctype_digit(trim((string) $colA)))) return 'district';
        return 'skip';
    }

    /**
     * Emit three IndicatorFactDto rows (q1, h1, year) for the given entity (region or district).
     * count_extra  = объект сони (number of objects) — all periods
     * count_extra_2 = фойдаланишга топшириш year сони — year period only
     */
    private function emitEntityRows(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): int {
        $objects = $this->intOrNull($sheet->getCell([self::COL_OBJECTS, $row])->getCalculatedValue());
        $limit   = $this->numericOrNull($sheet->getCell([self::COL_LIMIT,   $row])->getCalculatedValue());

        $absorptions = [
            'q1'   => $this->numericOrNull($sheet->getCell([self::COL_Q1_ABSORPTION,   $row])->getCalculatedValue()),
            'h1'   => $this->numericOrNull($sheet->getCell([self::COL_H1_ABSORPTION,   $row])->getCalculatedValue()),
            'year' => $this->numericOrNull($sheet->getCell([self::COL_YEAR_ABSORPTION, $row])->getCalculatedValue()),
        ];
        $pcts = [
            'q1'   => $this->numericOrNull($sheet->getCell([self::COL_Q1_PCT,   $row])->getCalculatedValue()),
            'h1'   => $this->numericOrNull($sheet->getCell([self::COL_H1_PCT,   $row])->getCalculatedValue()),
            'year' => $this->numericOrNull($sheet->getCell([self::COL_YEAR_PCT, $row])->getCalculatedValue()),
        ];
        $commissioning = $this->intOrNull($sheet->getCell([self::COL_COMMISSIONING_COUNT, $row])->getCalculatedValue());

        $count = 0;
        foreach (['q1', 'h1', 'year'] as $period) {
            $dto = new IndicatorFactDto(
                regionCode:    $ctx->regionCode(),
                districtCode:  $districtCode,
                year:          $ctx->year,
                indicatorCode: 'budget_investment',  // indicator catalog code (module_code is 'budget_invest')
                period:        $period,
                planValue:     $limit,
                actualHokimyat: $absorptions[$period],
                pctOfPlan:     $pcts[$period],
                countExtra:    $objects,
                countExtra2:   $period === 'year' ? $commissioning : null,
                unit:          'млн сўм',
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

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (! is_numeric($value)) return null;
        return (int) $value;
    }
}
