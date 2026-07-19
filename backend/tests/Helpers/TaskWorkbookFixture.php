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

        // Row 3: descriptor headers + all 14 region block headers.
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

        // Row 3 region block headers — all 14 must be present for the layout guard.
        $regionHeaders = [
            13 => 'Қорақалпоғистон Республикаси', 17 => 'Андижон вилояти',
            21 => 'Бухоро вилояти', 25 => 'Жиззах вилояти', 29 => 'Қашқадарё вилояти',
            33 => 'Навоий вилояти', 37 => 'Наманган вилояти', 41 => 'Самарқанд вилояти',
            45 => 'Сирдарё вилояти', 49 => 'Сурхондарё вилояти', 53 => 'Тошкент филояти',
            57 => 'Фарғона вилояти', 61 => 'Хоразм вилояти', 65 => 'Тошкент шаҳри',
        ];
        foreach ($regionHeaders as $col => $header) {
            $set($col, 3, $header);
        }

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

    /**
     * Build a small workbook in the "Иқтисодий кўрсаткичлар" (economic) layout:
     * no metadata cols H..L, region blocks from col 7, % column holds a RATIO.
     * Covers the real shapes: «х»/empty plan (region excluded), actual-without-plan
     * (region excluded), plan-only (pct 0 artifact -> null), headline-empty with a
     * planned sub-line (region kept), ratio pct as a formula cell.
     */
    public static function makeEconomic(): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $set = function (int $col, int $row, $val) use ($sheet) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $row, $val);
        };

        // Row 3: descriptor headers + all 14 region block headers at cols 7..59.
        $set(1, 3, '№');
        $set(2, 3, '№');
        $set(3, 3, 'Кўрсаткич номи');
        $set(4, 3, 'Индикатор номи');
        $set(5, 3, 'Ўлчов бирлиги');
        $set(6, 3, 'Муддати');
        $regionHeaders = [
            7  => 'Қорақалпоғистон Респубилкаси', // real file's typo — anchor still matches
            11 => 'Андижон вилояти', 15 => 'Бухоро вилояти', 19 => 'Жиззах вилояти',
            23 => 'Қашқадарё вилояти', 27 => 'Навоий вилояти', 31 => 'Наманган вилояти',
            35 => 'Самарқанд вилояти', 39 => 'Сирдарё вилояти', 43 => 'Сурхондарё вилояти',
            47 => 'Тошкент вилояти', 51 => 'Фарғона вилояти', 55 => 'Хоразм вилояти',
            59 => 'Тошкент шаҳри',
        ];
        foreach ($regionHeaders as $col => $header) {
            $set($col, 3, $header);
        }
        // Row 4: per-block sub-headers (fidelity only).
        foreach ([7, 11] as $b) {
            $set($b + 0, 4, 'Ижрочи');
            $set($b + 1, 4, 'Режа кўрсаткичи');
            $set($b + 2, 4, 'Амалда ижроси');
            $set($b + 3, 4, 'Бажарилиши фоизда');
        }

        $set(1, 5, 'I. Макроиқтисодий кўрсаткичлар');
        $set(1, 6, '1.1. Ялпи ҳудудий маҳсулот бўйича мақсадлар');

        // Row 7: TASK 1 — half-year KPI-style indicator.
        $set(1, 7, 1);
        $set(2, 7, 1);
        $set(3, 7, 'Ялпи ҳудудий маҳсулотни ўсишини таъминлаш.');
        $set(4, 7, 'Ялпи ҳудудий маҳсулот ўсиш суръати');
        $set(5, 7, 'фоиз');
        $set(6, 7, "2026 йил\nI ярим йиллик");
        // Qoraqalpoq (7): plan == actual -> ratio 1 (100%).
        $set(7, 7, 'Қорақалпоғистон Республикаси Вазирлар Кенгаши');
        $set(8, 7, 10.2);
        $set(9, 7, 10.2);
        $set(10, 7, 1);
        // Andijan (11): ratio pct as a FORMULA cell (real file shape) -> 8.8/7.2.
        $set(11, 7, 'Андижон вилояти ҳокимлиги');
        $set(12, 7, 7.2);
        $set(13, 7, 8.8);
        $set(14, 7, '=M7/L7');
        // Bukhara (15): «х» plan -> region must be excluded for this task.
        $set(15, 7, 'х');
        $set(16, 7, 'х');
        // Jizzakh (19): plan only, actual empty, pct formula artifact 0 -> pct null.
        $set(19, 7, 'Жиззах вилояти ҳокимлиги');
        $set(20, 7, 5);
        $set(22, 7, 0);
        // Kashkadarya (23): actual WITHOUT any plan -> region must be excluded.
        $set(23, 7, 'Қашқадарё вилояти ҳокимлиги');
        $set(25, 7, 3);
        $set(26, 7, 0);

        // Row 8: TASK 2 — headline line has no numbers, only the executor…
        $set(1, 8, 2);
        $set(2, 8, 2);
        $set(3, 8, 'Йирик корхоналарни ишга тушириш');
        $set(4, 8, 'йирик корхона сони');
        $set(5, 8, 'дона');
        $set(6, 8, '2026 йил якуни билан');
        $set(11, 8, "Андижон вилояти ҳокимлиги,\nШахрихон тумани ҳокимлиги");
        // Row 9: …but this continuation line carries the plan -> region must be KEPT.
        $set(2, 9, 3);
        $set(4, 9, 'қайта тикланадиган ишлаб чиқариш ҳажми');
        $set(5, 9, 'млрд сўм');
        $set(12, 9, 55);
        $set(13, 9, 55);
        $set(14, 9, 1);

        // Row 10: TASK 3 — col B (4) differs from col A (3), B must win; pct cell empty -> derived.
        $set(1, 10, 3);
        $set(2, 10, 4);
        $set(3, 10, 'Экспорт ҳажмини ошириш');
        $set(4, 10, 'экспорт ҳажми');
        $set(5, 10, 'млн долл');
        $set(6, 10, '2026 йил якуни билан');
        $set(11, 10, 'Андижон вилояти ҳокимлиги');
        $set(12, 10, 10);
        $set(13, 10, 12);

        // Row 11: TASK 68 — a lower-is-better indicator (инфляция даражаси):
        // % must be recomputed as plan/actual, ignoring the file's actual/plan ratio.
        $set(1, 11, 4);
        $set(2, 11, 68);
        $set(3, 11, 'Биринчи ярим йилликда инфляция даражаси прогнозидан ошмаслик');
        $set(4, 11, 'Инфляция даражаси (ярим йиллик)');
        $set(5, 11, 'фоиз');
        $set(6, 11, "2026 йил\nI ярим йиллик");
        // Qoraqalpoq: worse than plan (3.2 > 2.8) — file ratio says 114%, must become 87.5%.
        $set(7, 11, 'Қорақалпоғистон Республикаси Вазирлар Кенгаши');
        $set(8, 11, 2.8);
        $set(9, 11, 3.2);
        $set(10, 11, '=I11/H11');
        // Andijan: better than plan (2.4 < 2.9) -> 120.83%, done.
        $set(11, 11, 'Андижон вилояти ҳокимлиги');
        $set(12, 11, 2.9);
        $set(13, 11, 2.4);
        $set(14, 11, '=M11/L11');
        // Jizzakh: zero actual against a zero hold-the-price target -> 100%.
        $set(19, 11, 'Жиззах вилояти ҳокимлиги');
        $set(20, 11, 0);
        $set(21, 11, 0);

        // Rows 12–14: tasks the TaskFactBridge maps onto dashboard indicator_facts.
        // Row 12: TASK 117 — budget H1 actual (млрд сўм, scale 1).
        $set(1, 12, 5);
        $set(2, 12, 117);
        $set(3, 12, 'Биринчи ярим йиллик якуни билан бюджет даромадлари прогнозини бажариш');
        $set(4, 12, 'Бюджет даромади миқдори (1-ярим йиллик)');
        $set(5, 12, 'млрд сўм');
        $set(6, 12, "2026 йил / I ярим йиллик");
        $set(11, 12, 'Андижон вилояти ҳокимлиги');
        $set(12, 12, 2599);
        $set(13, 12, 2628.7145758079);
        $set(14, 12, '=M12/L12');

        // Row 13: TASK 165 — export H1 actual (млн долл -> минг доллар, scale 1000).
        $set(1, 13, 6);
        $set(2, 13, 165);
        $set(3, 13, 'Биринчи ярим йилликда экспорт ҳажмини ошириш.');
        $set(4, 13, 'Экспорт хажми');
        $set(5, 13, 'млн долл');
        $set(6, 13, "2026 йил / I ярим йиллик");
        $set(11, 13, 'Андижон вилояти ҳокимлиги');
        $set(12, 13, 361.6);
        $set(13, 13, 512.00440906254);
        $set(14, 13, '=M13/L13');

        // Row 14: TASK 181 — unemployment level (lower-is-better, fed to facts as-is).
        $set(1, 14, 7);
        $set(2, 14, 181);
        $set(3, 14, 'Биринчи ярим йилда ишсизлик даражасини камайтириш.');
        $set(4, 14, 'Ишсизлик даражаси');
        $set(5, 14, 'фоиз');
        $set(6, 14, "2026 йил / I ярим йиллик");
        $set(11, 14, 'Андижон вилояти ҳокимлиги');
        $set(12, 14, 3.8);
        $set(13, 14, 4.4501433700843);
        $set(14, 14, '=M14/L14');

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'taskwb_econ_' . uniqid('', true) . '.xlsx';
        (IOFactory::createWriter($book, 'Xlsx'))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /** A workbook where two region blocks are swapped — must be rejected by the layout guard. */
    public static function makeSwappedRegions(): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $set = function (int $col, int $row, $val) use ($sheet) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $row, $val);
        };

        $headers = [
            13 => 'Қорақалпоғистон Республикаси', 17 => 'Андижон вилояти',
            21 => 'Жиззах вилояти', 25 => 'Бухоро вилояти', // <-- swapped
            29 => 'Қашқадарё вилояти', 33 => 'Навоий вилояти', 37 => 'Наманган вилояти',
            41 => 'Самарқанд вилояти', 45 => 'Сирдарё вилояти', 49 => 'Сурхондарё вилояти',
            53 => 'Тошкент филояти', 57 => 'Фарғона вилояти', 61 => 'Хоразм вилояти',
            65 => 'Тошкент шаҳри',
        ];
        foreach ($headers as $col => $header) {
            $set($col, 3, $header);
        }
        $set(1, 7, 1);
        $set(3, 7, 'Тест');

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'taskwb_swapped_' . uniqid('', true) . '.xlsx';
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

    /**
     * One task where Qoraqalpoq has real data but Andijan's block is the «х» N/A
     * sentinel (executor + plan both «х»). Mirrors real task #21 in the partner file.
     */
    public static function makeSentinelRegionTask(): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $set = function (int $col, int $row, $val) use ($sheet) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $row, $val);
        };

        // Row 3: descriptor headers + all 14 region block headers (layout guard).
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
        $regionHeaders = [
            13 => 'Қорақалпоғистон Республикаси', 17 => 'Андижон вилояти',
            21 => 'Бухоро вилояти', 25 => 'Жиззах вилояти', 29 => 'Қашқадарё вилояти',
            33 => 'Навоий вилояти', 37 => 'Наманган вилояти', 41 => 'Самарқанд вилояти',
            45 => 'Сирдарё вилояти', 49 => 'Сурхондарё вилояти', 53 => 'Тошкент филояти',
            57 => 'Фарғона вилояти', 61 => 'Хоразм вилояти', 65 => 'Тошкент шаҳри',
        ];
        foreach ($regionHeaders as $col => $header) {
            $set($col, 3, $header);
        }

        $set(1, 5, 'I. Макроиқтисодий кўрсаткичлар');
        $set(1, 6, '1.1. Ялпи ҳудудий маҳсулот бўйича мақсадлар');

        // Task row 7.
        $set(1, 7, 1);
        $set(2, 7, 1);
        $set(3, 7, 'Аукцион савдолари');
        $set(4, 7, 'аукцион сони');
        $set(5, 7, 'дона');
        $set(6, 7, '2026 йил якуни билан');
        $set(8, 7, 'Чора-тадбирлар');
        // Qoraqalpoq (13): real executor + plan.
        $set(13, 7, 'Қорақалпоғистон Республикаси Вазирлар Кенгаши');
        $set(14, 7, 157);
        // Andijan (17): «х» N/A sentinel in the executor cell + plan cell.
        $set(17, 7, 'х');
        $set(18, 7, 'х');
        // Bukhara (21): N/A marked only in the plan cell, executor blank — the other
        // real shape (e.g. Tashkent-city tasks #54/#56 in the partner file).
        $set(22, 7, 'х');

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'taskwb_sentinel_' . uniqid('', true) . '.xlsx';
        (IOFactory::createWriter($book, 'Xlsx'))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
