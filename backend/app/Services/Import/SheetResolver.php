<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\RegionWorkbookSheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SheetResolver
{
    private const SIGNATURES = [
        'rollup'                    => ['ЯҲМ', 'асосий иқтисодий кўрсаткич'],
        'district_industry'         => ['Саноат маҳсулотларини ишлаб чиқариш'],
        'district_agriculture'      => ['Қишлоқ хўжалиги маҳсулотларини'],
        'district_services'         => ['Бозор хизматлари'],
        'food_balance'              => ['Балансини асос', 'Маҳсулот номи', 'Ресурс'],
        'warehouses_district_table' => ['Захира омборлари', 'совутгичли омборлар'],
    ];

    public function __construct(private IssueCollector $issues) {}

    public function resolve(
        ImportContext $ctx,
        Spreadsheet $book,
        int $regionWorkbookId,
        string $moduleCode,
        string $logicalKind,
    ): ?Worksheet {
        // 1. Cache lookup
        $cached = RegionWorkbookSheet::where('region_workbook_id', $regionWorkbookId)
            ->where('logical_kind', $logicalKind)->first();
        if ($cached && $book->sheetNameExists($cached->sheet_name)) {
            return $book->getSheetByName($cached->sheet_name);
        }

        // 2. Content scan
        $signatures = self::SIGNATURES[$logicalKind] ?? null;
        if ($signatures === null) {
            $this->issues->add(
                kind: IssueKind::SheetMissing,
                severity: IssueSeverity::Blocker,
                detail: "No signature definition for logical_kind '$logicalKind'",
                regionCode: $ctx->regionCode(),
                importRunId: $ctx->run->id,
            );
            return null;
        }

        $bestSheet = null;
        $bestScore = 0;
        foreach ($book->getAllSheets() as $sheet) {
            $score = $this->scoreSheet($sheet, $signatures);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSheet = $sheet;
            }
        }

        if ($bestSheet === null || $bestScore === 0) {
            $this->issues->add(
                kind: IssueKind::SheetMissing,
                severity: IssueSeverity::Blocker,
                detail: "No sheet matched signatures for logical_kind '$logicalKind' in module '$moduleCode'",
                regionCode: $ctx->regionCode(),
                importRunId: $ctx->run->id,
            );
            return null;
        }

        // 3. Write back to cache
        RegionWorkbookSheet::create([
            'region_workbook_id' => $regionWorkbookId,
            'sheet_name'         => $bestSheet->getTitle(),
            'logical_kind'       => $logicalKind,
            'detection_hints'    => json_encode(['score' => $bestScore, 'signatures' => $signatures], JSON_UNESCAPED_UNICODE),
        ]);

        return $bestSheet;
    }

    private function scoreSheet(Worksheet $sheet, array $signatures): int
    {
        $score = 0;
        for ($row = 1; $row <= 5; $row++) {
            $rowText = '';
            foreach ($sheet->getRowIterator($row, $row) as $r) {
                foreach ($r->getCellIterator() as $cell) {
                    $val = $cell->getValue();
                    if (is_string($val)) $rowText .= ' ' . $val;
                }
            }
            foreach ($signatures as $sig) {
                if (mb_stripos($rowText, $sig) !== false) {
                    $score++;
                }
            }
        }
        return $score;
    }
}
