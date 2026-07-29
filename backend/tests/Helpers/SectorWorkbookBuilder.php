<?php

namespace Tests\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SectorWorkbookBuilder
{
    /**
     * Build a workbook mirroring the real "Вазифалар_2026_тармоқлар_кесимида" layout.
     *
     * @param list<array{0:string,1:string,2:list<array>}> $sheets  [sheetTitle, orgLine, dataRows]
     *        dataRows: [A task_no|null, B line_no, C title|null, D label, E unit, F deadline, G plan, H actual, I pct]
     */
    public static function make(array $sheets, ?string $path = null): string
    {
        $wb = new Spreadsheet();
        $wb->removeSheetByIndex(0);
        foreach ($sheets as $i => [$title, $org, $rows]) {
            $sheet = $wb->createSheet($i);
            $sheet->setTitle($title);
            $sheet->setCellValue('A1', '2026 йил якунига қадар амалга ошириладиган долзарб вазифалар');
            $sheet->setCellValue('A2', $org);
            $sheet->fromArray(['№', '№', 'Кўрсаткич номи', 'Индикатор номи', 'Ўлчов бирлиги', 'Муддати', $org], null, 'A3');
            $sheet->fromArray([null, null, null, null, null, null, 'Режа кўрсаткичи', 'Амалда ижроси', 'Бажарилиши фоизда'], null, 'A4');
            $r = 5;
            foreach ($rows as $row) {
                $sheet->fromArray($row, null, 'A' . $r++);
            }
            $sheet->setCellValue('A' . $r, 'Изоҳлар:');
            $sheet->setCellValue('A' . ($r + 1), '1. Жадвалга фақат кафолат хатларининг «II бўлим» вазифалари киритилди.');
        }
        $path ??= tempnam(sys_get_temp_dir(), 'sector_wb_') . '.xlsx';
        (new Xlsx($wb))->save($path);

        return $path;
    }
}
