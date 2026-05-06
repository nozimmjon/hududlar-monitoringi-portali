<?php

namespace App\Services\Import\Modules;

use App\Models\RegionWorkbookSheet;
use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class MacroModuleParser extends ModuleParser
{
    private const INDICATOR_BY_LABEL = [
        'ЯҲМ'                            => 'grp',
        'Саноат маҳсулотлари'            => 'industry',
        'Қишлоқ хўжалиги маҳсулотлари'   => 'agriculture',
        'Қурилиш ишлари'                 => 'construction',
        'Бозор хизматлари'               => 'services',
    ];

    private const PERIOD_COLUMNS = [
        'q1'   => ['value' => 3, 'growth' => 4],
        'h1'   => ['value' => 5, 'growth' => 6],
        'm9'   => ['value' => 7, 'growth' => 8],
        'year' => ['value' => 9, 'growth' => 10],
    ];

    public function moduleCode(): string { return 'macro'; }

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $count = 0;
        $count += $this->parseRollupSheet($ctx, $book, $regionWorkbookId, $filePath);
        $count += $this->parseDistrictSheet($ctx, $book, $regionWorkbookId, $filePath, 'district_industry',    'industry',    'млрд сўм');
        $count += $this->parseDistrictSheet($ctx, $book, $regionWorkbookId, $filePath, 'district_agriculture', 'agriculture', 'млрд сўм');
        $count += $this->parseDistrictSheet($ctx, $book, $regionWorkbookId, $filePath, 'district_services',    'services',    'млрд сўм');
        return $count;
    }

    private function parseRollupSheet(ImportContext $ctx, Spreadsheet $book, int $rwbId, string $filePath): int
    {
        $sheet = $this->sheetResolver->resolve($ctx, $book, $rwbId, 'macro', 'rollup');
        if ($sheet === null) return 0;

        $rwSheet = RegionWorkbookSheet::where('region_workbook_id', $rwbId)
            ->where('logical_kind', 'rollup')->firstOrFail();
        $startRow = $this->headerDetector->detect($sheet, $ctx, $rwSheet->id);
        if ($startRow === null) return 0;

        $count = 0;
        // 5 indicators in rows starting at $startRow; scan up to +5 to account for gaps
        for ($row = $startRow; $row <= $startRow + 5; $row++) {
            $colB = $sheet->getCell([2, $row])->getValue();
            if (! is_string($colB)) continue;

            $label = trim($colB);
            $indicator = self::INDICATOR_BY_LABEL[$label] ?? null;
            if ($indicator === null) continue;

            foreach (self::PERIOD_COLUMNS as $period => $cols) {
                $value = $sheet->getCell([$cols['value'], $row])->getValue();
                $growth = $sheet->getCell([$cols['growth'], $row])->getValue();
                if (! is_numeric($value)) continue;

                $dto = new IndicatorFactDto(
                    regionCode:     $ctx->regionCode(),
                    districtCode:   null,
                    year:           $ctx->year,
                    indicatorCode:  $indicator,
                    period:         $period,
                    planValue:      (float) $value,
                    actualHokimyat: $period === 'q1' ? (float) $value : null,
                    growthPct:      is_numeric($growth) ? (float) $growth : null,
                    unit:           'млрд сўм',
                    sourceLabel:    basename($filePath) . " · {$sheet->getTitle()} · row $row",
                );
                $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
                $count++;
            }
        }
        return $count;
    }

    private function parseDistrictSheet(
        ImportContext $ctx,
        Spreadsheet $book,
        int $rwbId,
        string $filePath,
        string $logicalKind,
        string $indicatorCode,
        string $unit,
    ): int {
        $sheet = $this->sheetResolver->resolve($ctx, $book, $rwbId, 'macro', $logicalKind);
        if ($sheet === null) return 0;

        $rwSheet = RegionWorkbookSheet::where('region_workbook_id', $rwbId)
            ->where('logical_kind', $logicalKind)->firstOrFail();
        $startRow = $this->headerDetector->detect($sheet, $ctx, $rwSheet->id);
        if ($startRow === null) return 0;

        $count = 0;
        // Scan up to 30 rows to cover all 16 districts plus any gaps or summary rows
        for ($row = $startRow; $row <= $startRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getValue();
            $colB = $sheet->getCell([2, $row])->getValue();

            // Col A is non-null in district rows (integer on first row, formula string on
            // subsequent rows when readDataOnly=true prevents formula evaluation).
            // Skip rows where col A is null/empty or col B is not a string.
            if ($colA === null || $colA === '') continue;
            if (! is_string($colB) || trim($colB) === '') continue;

            // Skip region-level rollup rows (e.g. "Андижон вилояти", "Анджижон вилояти").
            // These have col A as a plain text string (not a number/formula).
            if (mb_stripos($colB, 'вилояти') !== false) continue;

            $districtCode = $this->districtResolver->resolve(
                $colB,
                $ctx,
                basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            if ($districtCode === null) continue;

            foreach (self::PERIOD_COLUMNS as $period => $cols) {
                $value = $sheet->getCell([$cols['value'], $row])->getValue();
                $growth = $sheet->getCell([$cols['growth'], $row])->getValue();
                if (! is_numeric($value)) continue;

                $dto = new IndicatorFactDto(
                    regionCode:     $ctx->regionCode(),
                    districtCode:   $districtCode,
                    year:           $ctx->year,
                    indicatorCode:  $indicatorCode,
                    period:         $period,
                    planValue:      (float) $value,
                    actualHokimyat: $period === 'q1' ? (float) $value : null,
                    growthPct:      is_numeric($growth) ? (float) $growth : null,
                    unit:           $unit,
                    sourceLabel:    basename($filePath) . " · {$sheet->getTitle()} · row $row",
                );
                $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
                $count++;
            }
        }
        return $count;
    }
}
