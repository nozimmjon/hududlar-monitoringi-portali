<?php

namespace App\Services\Import\Modules;

use App\Models\RegionWorkbookSheet;
use App\Services\Import\ImportContext;
use App\Support\Import\FoodBalanceDto;
use App\Support\Import\WarehouseDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InflationModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'inflation'; }

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        // Load without readDataOnly so SUM formulas in the rollup row evaluate correctly.
        $reader = IOFactory::createReaderForFile($filePath);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $count = 0;
        $count += $this->parseFoodBalance($ctx, $book, $regionWorkbookId, $filePath);
        $count += $this->parseWarehouses($ctx, $book, $regionWorkbookId, $filePath);
        return $count;
    }

    private function parseFoodBalance(
        ImportContext $ctx, Spreadsheet $book, int $rwbId, string $filePath,
    ): int {
        // SheetResolver creates the RegionWorkbookSheet record; we ignore its header_row
        // because HeaderDetector cannot detect the food_balance start row (col A values
        // are dotted strings like '1.' — neither is_int nor ctype_digit).
        $sheet = $this->sheetResolver->resolve($ctx, $book, $rwbId, 'inflation', 'food_balance');
        if ($sheet === null) return 0;

        $startRow = $this->findFoodBalanceStartRow($sheet);
        if ($startRow === null) return 0;

        $count = 0;
        for ($row = $startRow; $row <= $startRow + 25; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();

            // Col A in this sheet uses dotted numbering: '1.', '2.', '8.1.', etc.
            // Accept any string that starts with one or more digits (optionally dotted sub-index).
            if (! is_string($colA) || ! preg_match('/^\d+(\.\d+)*\.?$/', trim($colA))) continue;
            if (! is_string($colB) || trim($colB) === '') continue;

            $product = trim($colB);
            // Extract leading integer from '8.1.' → 8, '1.' → 1
            $sortOrder = (int) $colA;

            $resourceTotal    = $this->numericOrNull($sheet->getCell([3, $row])->getCalculatedValue());
            $yearStartStock   = $this->numericOrNull($sheet->getCell([4, $row])->getCalculatedValue());
            $production       = $this->numericOrNull($sheet->getCell([5, $row])->getCalculatedValue());
            $importVolume     = $this->numericOrNull($sheet->getCell([6, $row])->getCalculatedValue());
            $useTotal         = $this->numericOrNull($sheet->getCell([7, $row])->getCalculatedValue());
            $useHousehold     = $this->numericOrNull($sheet->getCell([8, $row])->getCalculatedValue());
            $useProcessing    = $this->numericOrNull($sheet->getCell([9, $row])->getCalculatedValue());
            $useOther         = $this->numericOrNull($sheet->getCell([10, $row])->getCalculatedValue());
            $perCapitaNorm    = $this->numericOrNull($sheet->getCell([11, $row])->getCalculatedValue());
            $perCapitaBalance = $this->numericOrNull($sheet->getCell([12, $row])->getCalculatedValue());

            $localSupplyRatio = ($production !== null && $useTotal !== null && $useTotal > 0)
                ? $production / $useTotal
                : null;

            $dto = new FoodBalanceDto(
                regionCode:        $ctx->regionCode(),
                year:              $ctx->year,
                product:           $product,
                productSortOrder:  $sortOrder,
                resourceTotal:     $resourceTotal,
                yearStartStock:    $yearStartStock,
                production:        $production,
                importVolume:      $importVolume,
                useTotal:          $useTotal,
                useHousehold:      $useHousehold,
                useProcessing:     $useProcessing,
                useOther:          $useOther,
                perCapitaNorm:     $perCapitaNorm,
                perCapitaBalance:  $perCapitaBalance,
                localSupplyRatio:  $localSupplyRatio,
                yearEndStock:      null,
                sourceLabel:       basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            $this->stagingWriter->buffer('import_staging_food_balance', $dto->toStagingRow($ctx->run->id));
            $count++;
        }
        return $count;
    }

    private function parseWarehouses(
        ImportContext $ctx, Spreadsheet $book, int $rwbId, string $filePath,
    ): int {
        // SheetResolver creates RegionWorkbookSheet record; HeaderDetector can't handle this
        // sheet either (no 'ҳажм'/'млрд.сўм' trigger row), so we detect start row ourselves.
        $sheet = $this->sheetResolver->resolve($ctx, $book, $rwbId, 'inflation', 'warehouses_district_table');
        if ($sheet === null) return 0;

        $startRow = $this->findWarehouseStartRow($sheet);
        if ($startRow === null) return 0;

        $count = 0;

        // Region rollup row sits at startRow - 1.
        // Col A = "Анджижон вилояти" (string with 'вилояти'), col B = null.
        // Cols C-K contain SUM formulas that evaluate to actual totals (formula evaluation
        // is enabled because this parser loads without setReadDataOnly(true)).
        $rollupRow = $startRow - 1;
        $rollupColA = $sheet->getCell([1, $rollupRow])->getCalculatedValue();
        $rollupColB = $sheet->getCell([2, $rollupRow])->getCalculatedValue();
        $hasRollup = (is_string($rollupColA) && str_contains($rollupColA, 'вилояти'))
                  || (is_string($rollupColB) && str_contains($rollupColB, 'вилояти'));
        if ($hasRollup) {
            $this->emitWarehouseRow($ctx, $sheet, $rollupRow, null, $filePath);
            $count++;
        }

        for ($row = $startRow; $row <= $startRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();

            // District rows have integer in col A (2, 3, 4, ..., 17)
            $isDistrict = is_int($colA) || (is_string($colA) && ctype_digit(trim($colA)));
            if (! $isDistrict) continue;
            if (! is_string($colB) || trim($colB) === '') continue;

            $districtCode = $this->districtResolver->resolve(
                $colB, $ctx,
                basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            if ($districtCode === null) continue;

            $this->emitWarehouseRow($ctx, $sheet, $row, $districtCode, $filePath);
            $count++;
        }
        return $count;
    }

    private function emitWarehouseRow(
        ImportContext $ctx, Worksheet $sheet, int $row, ?string $districtCode, string $filePath,
    ): void {
        $dto = new WarehouseDto(
            regionCode:               $ctx->regionCode(),
            districtCode:             $districtCode,
            year:                     $ctx->year,
            reserveWarehouses:        $this->intOrNull($sheet->getCell([3, $row])->getCalculatedValue()),
            reserveCapacityT:         $this->intOrNull($sheet->getCell([4, $row])->getCalculatedValue()),
            coldStorageCount:         $this->intOrNull($sheet->getCell([5, $row])->getCalculatedValue()),
            coldStorageCapacityT:     $this->intOrNull($sheet->getCell([6, $row])->getCalculatedValue()),
            newSmallColdCount:        $this->intOrNull($sheet->getCell([7, $row])->getCalculatedValue()),
            newSmallColdCapacityT:    $this->intOrNull($sheet->getCell([8, $row])->getCalculatedValue()),
            newSmallColdMfys:         $this->intOrNull($sheet->getCell([9, $row])->getCalculatedValue()),
            newLargeColdCount:        $this->intOrNull($sheet->getCell([10, $row])->getCalculatedValue()),
            newLargeColdCapacityT:    $this->intOrNull($sheet->getCell([11, $row])->getCalculatedValue()),
            sourceLabel:              basename($filePath) . " · {$sheet->getTitle()} · row $row",
        );
        $this->stagingWriter->buffer('import_staging_warehouses', $dto->toStagingRow($ctx->run->id));
    }

    /**
     * Find the first data row in 1.1 Баланс.
     * Data rows have col A matching dotted numbering like '1.', '2.', '8.1.', etc.
     * and col B as a non-empty product name string.
     */
    private function findFoodBalanceStartRow(Worksheet $sheet): ?int
    {
        for ($row = 4; $row <= 15; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            if (is_string($colA)
                && preg_match('/^\d+(\.\d+)*\.?$/', trim($colA))
                && is_string($colB)
                && trim($colB) !== ''
            ) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Find the first data row in 1.2 Омборлар.
     * District rows have an integer in col A (2, 3, ..., 17).
     * The rollup row (one row above) has a string in col A containing 'вилояти'.
     */
    private function findWarehouseStartRow(Worksheet $sheet): ?int
    {
        for ($row = 4; $row <= 15; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            if (is_int($colA) || (is_string($colA) && ctype_digit(trim((string) $colA)))) {
                return $row;
            }
        }
        return null;
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
