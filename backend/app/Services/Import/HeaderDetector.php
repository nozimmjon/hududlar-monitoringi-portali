<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\RegionWorkbookSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HeaderDetector
{
    public function __construct(private IssueCollector $issues) {}

    public function detect(Worksheet $sheet, ImportContext $ctx, int $regionWorkbookSheetId): ?int
    {
        $cached = RegionWorkbookSheet::find($regionWorkbookSheetId);
        if ($cached && $cached->header_row) {
            return $cached->header_row;
        }

        $allRows = $sheet->toArray(null, true, true, false);
        $hasUnitAbove = false;

        $limit = min(15, count($allRows));
        for ($i = 0; $i < $limit; $i++) {
            $rowText = '';
            foreach ($allRows[$i] as $cell) {
                if (is_string($cell)) {
                    $rowText .= ' ' . $cell;
                }
            }

            if (mb_stripos($rowText, 'ҳажм') !== false || mb_stripos($rowText, 'млрд.сўм') !== false) {
                $hasUnitAbove = true;
            }

            $colA = $allRows[$i][0] ?? null;
            if ($hasUnitAbove && (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA))))) {
                $rowNumber = $i + 1;
                if ($cached) {
                    $cached->update(['header_row' => $rowNumber]);
                }
                return $rowNumber;
            }
        }

        $this->issues->add(
            kind: IssueKind::HeaderNotFound,
            severity: IssueSeverity::Blocker,
            detail: "Could not locate data start row in sheet '{$sheet->getTitle()}'",
            regionCode: $ctx->regionCode(),
            sourceLabel: $sheet->getTitle(),
            importRunId: $ctx->run->id,
        );
        return null;
    }
}
