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
        $reader->setReadDataOnly(false); // need calculated values for sheet 1.3 industry-drivers rollup formulas
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $count = 0;
        $count += $this->parseRollupSheet($ctx, $book, $regionWorkbookId, $filePath);
        if ($this->isApplicable($ctx, 'industry')) {
            $count += $this->parseDistrictSheet($ctx, $book, $regionWorkbookId, $filePath, 'district_industry',    'industry',    'млрд сўм');
        }
        if ($this->isApplicable($ctx, 'agriculture')) {
            $count += $this->parseDistrictSheet($ctx, $book, $regionWorkbookId, $filePath, 'district_agriculture', 'agriculture', 'млрд сўм');
        }
        if ($this->isApplicable($ctx, 'services')) {
            $count += $this->parseDistrictSheet($ctx, $book, $regionWorkbookId, $filePath, 'district_services',    'services',    'млрд сўм');
        }
        $count += $this->parseIndustryDriversRollup($ctx, $book, $regionWorkbookId, $filePath);
        return $count;
    }

    private function isApplicable(ImportContext $ctx, string $indicatorCode): bool
    {
        $status = \DB::table('region_indicator_availability')
            ->where('region_code', $ctx->regionCode())
            ->where('indicator_code', $indicatorCode)
            ->value('status');
        return $status !== 'not_applicable';
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

    /**
     * Sheet 1.3 has an "Андижон вилояти" rollup row with the industry-growth columns
     * filled in (cols 3-6), but the localization/energy columns (K, L, N, O, Q, R, S, T)
     * use SUM() formulas over per-district rows or are blank. Sum the per-district rows
     * directly so we get region totals consistently across all 14 regions.
     *
     * Emits 6 region-level facts: localization (h1, year), energy_electricity (h1, year),
     * energy_gas (h1, year). Each carries period-aware value/count fields:
     *   localization        → count_extra (projects) + expected_value (mln sum)
     *   energy_electricity  → expected_value (mln kWh)
     *   energy_gas          → expected_value (mln m3)
     */
    private function parseIndustryDriversRollup(
        ImportContext $ctx,
        Spreadsheet $book,
        int $rwbId,
        string $filePath,
    ): int {
        // Sheet 1.3 "Ҳудудий саноат" carries the per-district industry-drivers grid
        // (localization + energy savings) and isn't yet registered with the sheet
        // resolver. Resolve by title with a couple of fallbacks.
        $sheet = null;
        foreach (['1.3. Ҳудудий саноат', '1.3 Ҳудудий саноат', 'Ҳудудий саноат'] as $title) {
            if ($book->getSheetByName($title) !== null) {
                $sheet = $book->getSheetByName($title);
                break;
            }
        }
        if ($sheet === null) {
            foreach ($book->getAllSheets() as $candidate) {
                if (mb_stripos($candidate->getTitle(), 'ҳудудий саноат') !== false) {
                    $sheet = $candidate;
                    break;
                }
            }
        }
        if ($sheet === null) return 0;

        // Header is the row immediately before the first district row. Empirically
        // district rows start at row 8; cap startRow there.
        $startRow = 8;

        // Sum per-district values across cols:
        //   11(K) localization h1 projects, 12(L) localization h1 value (mln sum),
        //   14(N) localization year projects, 15(O) localization year value (mln sum),
        //   17(Q) energy_electricity h1, 18(R) energy_gas h1,
        //   19(S) energy_electricity year, 20(T) energy_gas year.
        $sums = array_fill_keys([11, 12, 14, 15, 17, 18, 19, 20], 0.0);
        $any = array_fill_keys(array_keys($sums), false);

        for ($row = $startRow; $row <= $startRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getValue();
            $colB = $sheet->getCell([2, $row])->getValue();
            if ($colA === null || $colA === '') continue;
            if (! is_string($colB) || trim($colB) === '') continue;
            if (mb_stripos($colB, 'вилояти') !== false) continue;

            $districtCode = $this->districtResolver->resolve(
                $colB, $ctx,
                basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            if ($districtCode === null) continue;

            foreach (array_keys($sums) as $c) {
                $v = $sheet->getCell([$c, $row])->getCalculatedValue();
                if (is_numeric($v)) { $sums[$c] += (float) $v; $any[$c] = true; }
            }
        }

        if (! array_filter($any)) return 0;

        $sourceLabel = basename($filePath) . " · {$sheet->getTitle()} · district sums";
        $emit = function (string $code, string $period, ?int $count, ?float $value, string $unit) use ($ctx, $sourceLabel) {
            $dto = new IndicatorFactDto(
                regionCode:    $ctx->regionCode(),
                districtCode:  null,
                year:          $ctx->year,
                indicatorCode: $code,
                period:        $period,
                expectedValue: $value,
                countExtra:    $count,
                unit:          $unit,
                sourceLabel:   $sourceLabel,
            );
            $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
        };

        $count = 0;
        $emit('localization',       'h1',   (int) round($sums[11]), $sums[12] ?: null, 'млн сўм');         $count++;
        $emit('localization',       'year', (int) round($sums[14]), $sums[15] ?: null, 'млн сўм');         $count++;
        $emit('energy_electricity', 'h1',   null,                   $sums[17] ?: null, 'млн кВт·ч');     $count++;
        $emit('energy_electricity', 'year', null,                   $sums[19] ?: null, 'млн кВт·ч');     $count++;
        $emit('energy_gas',         'h1',   null,                   $sums[18] ?: null, 'млн м³');         $count++;
        $emit('energy_gas',         'year', null,                   $sums[20] ?: null, 'млн м³');         $count++;
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
