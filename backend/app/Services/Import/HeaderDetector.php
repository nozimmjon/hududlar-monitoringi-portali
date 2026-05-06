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

        $hasUnitAbove = false;
        for ($row = 1; $row <= 15; $row++) {
            $colA = $sheet->getCell([1, $row])->getValue();
            $rowText = '';
            foreach ($sheet->getRowIterator($row, $row) as $r) {
                foreach ($r->getCellIterator() as $cell) {
                    $v = $cell->getValue();
                    if (is_string($v)) $rowText .= ' ' . $v;
                }
            }
            if (mb_stripos($rowText, 'ҳажм') !== false || mb_stripos($rowText, 'млрд.сўм') !== false) {
                $hasUnitAbove = true;
            }

            if ($hasUnitAbove && (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA))))) {
                if ($cached) {
                    $cached->update(['header_row' => $row]);
                }
                return $row;
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
