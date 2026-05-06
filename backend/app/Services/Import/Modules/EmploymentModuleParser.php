<?php

namespace App\Services\Import\Modules;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\Indicator;
use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmploymentModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'employment'; }

    /**
     * Indicator → (h1_col, year_col) mapping.
     * Verified via Step 0 tinker inspection against
     * '6-жадвал (бандлик ва камбағаллик даражаси).xlsx' sheet '6. Камбағаллик'
     * (rollup row=7 where col A = 'ЖАМИ'):
     *
     *   C(3)  = Ишсизлик даражаси, Январь-июнда    → unemployment h1
     *   D(4)  = Ишсизлик даражаси, 2026 йилда      → unemployment year
     *   E(5)  = Камбағаллик даражаси, Январь-июнда → poverty h1
     *   F(6)  = Камбағаллик даражаси, 2026 йилда   → poverty year
     *   G(7)  = Камбағаллик/ишсизликдан холи МФЙ, Январь-июнда → mfy_clear h1
     *   H(8)  = Камбағаллик/ишсизликдан холи МФЙ, 2026 йилда   → mfy_clear year
     *   I(9)  = Доимий ишга жойлаштириш, Январь-июнда → jobs h1
     *   J(10) = Доимий ишга жойлаштириш, 2026 йилда   → jobs year
     *   K(11) = Норасмий бандлар легаллаштириш, Январь-июнда → legalization h1
     *   L(12) = Норасмий бандлар легаллаштириш, 2026 йилда   → legalization year
     *   M(13) = Микролойиҳалар, Январь-июнда → microprojects h1
     *   N(14) = Микролойиҳалар, 2026 йилда   → microprojects year
     */
    private const INDICATOR_COLUMNS = [
        'unemployment'  => [3,  4],   // C, D
        'poverty'       => [5,  6],   // E, F
        'mfy_clear'     => [7,  8],   // G, H
        'jobs'          => [9,  10],  // I, J
        'legalization'  => [11, 12],  // K, L
        'microprojects' => [13, 14],  // M, N
    ];

    private array $unitByIndicator = [];

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());
        $this->loadUnits();

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'employment', 'employment');
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

    private function loadUnits(): void
    {
        if (! empty($this->unitByIndicator)) return;
        foreach (array_keys(self::INDICATOR_COLUMNS) as $code) {
            $unit = Indicator::where('code', $code)->value('default_unit') ?? '';
            $this->unitByIndicator[$code] = $unit;
        }
    }

    /** Rollup detection: col A == 'ЖАМИ' (uppercase, exact match). */
    private function findRollupRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= 15; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            if (is_string($colA) && trim($colA) === 'ЖАМИ') return $row;
        }
        return null;
    }

    private function classifyRow(mixed $colA, mixed $colB): string
    {
        if (is_string($colA) && trim($colA) === 'ЖАМИ') return 'rollup';
        if (! is_string($colB) || trim($colB) === '') return 'skip';
        if (is_int($colA) || (is_string($colA) && ctype_digit(trim((string) $colA)))) return 'district';
        return 'skip';
    }

    /** Emits 12 IndicatorFactDtos (6 indicators × 2 periods) for one entity row. */
    private function emitEntityRows(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): int {
        $sourceLabel = basename($filePath) . " · {$sheet->getTitle()} · row $row";

        $count = 0;
        foreach (self::INDICATOR_COLUMNS as $indicatorCode => [$h1Col, $yearCol]) {
            foreach (['h1' => $h1Col, 'year' => $yearCol] as $period => $col) {
                $rawValue = $sheet->getCell([$col, $row])->getCalculatedValue();
                $isSentinel = $this->isSentinel($rawValue);

                if ($isSentinel) {
                    $this->issueCollector->add(
                        kind: IssueKind::Sentinel,
                        severity: IssueSeverity::Medium,
                        detail: "Sentinel value '{$rawValue}' in {$indicatorCode}/{$period}",
                        regionCode: $ctx->regionCode(),
                        districtCode: $districtCode,
                        indicatorCode: $indicatorCode,
                        year: $ctx->year,
                        period: $period,
                        detectedValue: (string) $rawValue,
                        sourceLabel: $sourceLabel,
                        importRunId: $ctx->run->id,
                    );
                }

                $dto = new IndicatorFactDto(
                    regionCode:    $ctx->regionCode(),
                    districtCode:  $districtCode,
                    year:          $ctx->year,
                    indicatorCode: $indicatorCode,
                    period:        $period,
                    planValue:     $isSentinel ? null : $this->numericOrNull($rawValue),
                    isSentinel:    $isSentinel,
                    sentinelLabel: $isSentinel ? 'холи ҳудуд' : null,
                    unit:          $this->unitByIndicator[$indicatorCode] ?? '',
                    sourceLabel:   $sourceLabel,
                );
                $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
                $count++;
            }
        }
        return $count;
    }

    private function isSentinel(mixed $value): bool
    {
        return is_string($value) && str_contains($value, 'холи ҳудуд');
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if (! is_numeric($value)) return null;
        return (float) $value;
    }
}
