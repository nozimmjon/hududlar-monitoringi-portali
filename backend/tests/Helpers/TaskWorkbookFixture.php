<?php

namespace Tests\Helpers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TaskWorkbookFixture
{
    /**
     * Build a small workbook matching the real structure and save to a temp .xlsx.
     * @return string absolute path to the temp file (caller deletes if desired)
     */
    public static function make(): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();

        $set = function (int $col, int $row, $val) use ($sheet) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $row, $val);
        };

        // Row 3: descriptor headers + two region block headers (cols 13, 17).
        $set(1, 3, '№');
        $set(3, 3, 'Кўрсаткич номи');
        $set(4, 3, 'Индикатор номи');
        $set(5, 3, 'Ўлчов бирлиги');
        $set(6, 3, 'Муддати');
        $set(7, 3, 'Ижрочи');
        $set(8, 3, 'Топшириқ тури');
        $set(9, 3, 'Маълумот манбаи');
        $set(10, 3, 'Ҳисобот шакилланадиган сана');
        $set(11, 3, 'Амалга ошириш механизми');
        $set(12, 3, 'Интеграция ҳолати');
        $set(13, 3, 'Қорақалпоғистон Республикаси');
        $set(17, 3, 'Андижон вилояти');

        // Row 4: per-block sub-headers (fidelity only; parser ignores them).
        foreach ([13, 17] as $b) {
            $set($b + 0, 4, 'Ижрочи');
            $set($b + 1, 4, 'Режа кўрсаткичи');
            $set($b + 2, 4, 'Амалда ижроси');
            $set($b + 3, 4, 'Бажарилиши фоизда');
        }

        // Row 5: module section (roman I -> macro).
        $set(1, 5, 'I. Макроиқтисодий кўрсаткичлар');
        // Row 6: indicator subsection (1.1 -> grp).
        $set(1, 6, '1.1. Ялпи ҳудудий маҳсулот бўйича мақсадлар');

        // Row 7: TASK 1 (KPI, quarterly, h1, single metric).
        $set(1, 7, 1);
        $set(2, 7, 1);   // col B (global №) same as col A
        $set(3, 7, 'ЯҲМ ўсишини таъминлаш');
        $set(4, 7, 'ЯҲМ ўсиш суръати');
        $set(5, 7, 'фоиз');
        $set(6, 7, "2026 йил\nI ярим йиллик");
        $set(8, 7, 'KPI');
        $set(9, 7, 'Статистика агентлиги');
        $set(10, 7, 'Ҳар чорак якуни билан кейинги ойнинг 25 санаси');
        // Qoraqalpoq block (13): executor + plan only
        $set(13, 7, 'Қорақалпоғистон Республикаси Вазирлар Кенгаши');
        $set(14, 7, 10.2);
        // Andijan block (17): executor + plan only (no actual yet)
        $set(17, 7, 'Андижон вилояти ҳокимлиги');
        $set(18, 7, 7.2);

        // Row 8: TASK 2 (Чора-тадбир, monthly, year, multi-metric, district executor).
        // Col A and col B are both formulas — defect-1 regression: getValue() returns formula string.
        $set(1, 8, '=A7+1');   // col A formula -> calculated = 2
        $set(2, 8, '=B7+1');   // col B formula -> calculated = 2
        $set(3, 8, 'Йирик корхоналарни ишга тушириш');
        $set(4, 8, 'йирик корхона сони');
        $set(5, 8, 'дона');
        $set(6, 8, '2026 йил якуни билан');
        $set(8, 8, 'Чора-тадбирлар');
        $set(9, 8, 'Ҳокимлик');
        $set(10, 8, 'Ҳар ой');
        $set(17, 8, "Андижон вилояти ҳокимлиги,\nШахрихон тумани ҳокимлиги");
        $set(18, 8, 6);   // plan
        $set(19, 8, 3);   // actual
        $set(20, 8, 50);  // pct

        // Row 9: continuation metric line for TASK 2 (col A empty, col D present).
        $set(4, 9, 'қайта тикланадиган ишлаб чиқариш ҳажми');
        $set(5, 9, 'млрд сўм');
        $set(18, 9, 55);   // plan
        $set(19, 9, 55);   // actual
        $set(20, 9, 100);  // pct (done)

        // Row 10: TASK 3 (Чора-тадбир, monthly, year, single metric, pct cell EMPTY -> parser must derive it).
        // Col B = 5, DIFFERENT from col A = 3 -> locks "col B wins as task_number".
        $set(1, 10, 3);
        $set(2, 10, 5);   // col B (unique global №) wins over col A
        $set(3, 10, 'Экспорт ҳажмини ошириш');
        $set(4, 10, 'экспорт ҳажми');
        $set(5, 10, 'млн долл');
        $set(6, 10, '2026 йил якуни билан');
        $set(8, 10, 'Чора-тадбирлар');
        $set(10, 10, 'Ҳар ой');
        $set(17, 10, 'Андижон вилояти ҳокимлиги');
        $set(18, 10, 10);   // plan
        $set(19, 10, 12);   // actual
        // col 20 (pct) intentionally NOT set -> parser derives 120%

        // Row 11: continuation for TASK 3 with NO label (col D empty) but a unit + values.
        $set(5, 11, 'дона');
        $set(18, 11, 300);   // plan
        $set(19, 11, 150);   // actual
        // pct empty -> derived 50

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'taskwb_' . uniqid('', true) . '.xlsx';
        (IOFactory::createWriter($book, 'Xlsx'))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /** A workbook whose region block headers are missing/wrong — for layout-guard tests. */
    public static function makeMissingHeaders(): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('A3', '№');
        $sheet->setCellValue('C3', 'Кўрсаткич номи');
        // No region headers at cols 13/17.
        $sheet->setCellValue('A7', 1);
        $sheet->setCellValue('C7', 'Тест');

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'taskwb_broken_' . uniqid('', true) . '.xlsx';
        (IOFactory::createWriter($book, 'Xlsx'))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
