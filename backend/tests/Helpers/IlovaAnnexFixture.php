<?php

namespace Tests\Helpers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds a small annex workbook ("илова жадваллар.xlsx") mirroring the real
 * sheet layouts, including the real file's quirks: aggregate/label rows,
 * blank амалда cells, swapped region row order (17-илова), and a sheet that
 * omits regions (15б-илова).
 */
class IlovaAnnexFixture
{
    public static function make(bool $breakHeader = false, bool $unknownRegion = false): string
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        // --- 4-илова (task 10): district rows under numbered region rows. ---
        $s = new Worksheet($book, '4-илова');
        $book->addSheet($s);
        self::set($s, 2, 1, '2026 йил I чоракда саноат маҳсулотлари ишлаб чиқариш ҳажми паст ўсиш кузатилган ҳудудлар…');
        self::set($s, 4, 1, 'Т/р');
        self::set($s, 4, 2, 'Худуд номи');
        self::set($s, 4, 8, $breakHeader ? 'бошқа устун' : 'Тегишли ҳудуд ўртача кўрсаткичдан фарқ');
        // Andijan: one district above the region average, one below -> actual 1.
        self::row($s, 6, [1, 'Андижон вилояти', 25945, 0.084, 54193.07, 0.0922, 0.0082, 'х']);
        self::row($s, 7, [null, 'Хонобод шаҳри', 1079, 0.073, 1973.75, 0.0782, 0.0052, -0.0139]);
        self::row($s, 8, [null, 'Шаҳрихон тумани', 962, 0.081, 1879.41, 0.0984, 0.0174, 0.0062]);
        // Tashkent city: single district below average -> actual 0.
        self::row($s, 9, [2, 'Тошкент шаҳри', 45945, 0.071, 109587.59, 0.0799, 0.0089, 'х']);
        self::row($s, 10, [null, 'Сергили туман', 4291, 0.01, 10368.93, 0.0721, 0.0621, -0.0078]);

        // --- 7-илова (task 40): факт / режа / бажарилиши triple rows per region. ---
        $s = new Worksheet($book, '7-илова');
        $book->addSheet($s);
        self::set($s, 2, 1, '2026 йил I ярим йилликда шаҳар (туман)ларда туну-кун ишлайдиган (24/7) гавжум кўчалар…');
        self::set($s, 4, 1, 'Т/р');
        self::set($s, 4, 3, 'Туман ва шаҳарлар сони');
        self::set($s, 4, 8, 'Янги ишга тушадиган лойиҳалар ва тадбикорлик фаолияти кенгаядиган субъектларда янги иш ўринлар сони');
        self::row($s, 5, [null, 'ҲУДУДЛАР ЖАМИ', 9, 10, 39.1, 127, 102, 482]);
        self::row($s, 6, [null, 'Режа', 120, 161, 672.0, 2199, 1536, 9783]);
        self::row($s, 7, [null, 'Бажарилиши,%', 0.075, 0.062, 0.058, 0.058, 0.066, 0.049]);
        self::row($s, 8, [1, 'Андижон вилояти', 0, 0, 0, 0, 0, 0]);
        self::row($s, 9, [null, 'режа', 4, 6, 29.7, 77, 99, 500]);
        self::row($s, 10, [null, 'бажарилиши, %', 0, 0, 0, 0, 0, 0]);
        self::row($s, 11, [2, 'Самарқанд вилояти', 6, 6, 30.7, 61, 45, 140]);
        self::row($s, 12, [null, 'режа', 10, 13, 38.1, 135, 61, 663]);
        self::row($s, 13, [null, 'бажарилиши, %', 0.6, 0.46, 0.81, 0.45, 0.74, 0.21]);

        // --- 8-илова (task 46): two план/амалда/фоиз triplets per region row. ---
        $s = new Worksheet($book, '8-илова');
        $book->addSheet($s);
        self::set($s, 2, 1, '2026 йил I ярим йилликда соҳил бўйларида хизматларни ривожлантириш…');
        self::set($s, 4, 1, 'Т/р');
        self::set($s, 4, 3, 'Ўзлаштириладиган инвестициялар хажми (млрд сўм)');
        self::set($s, 4, 6, 'Ташкил этиладиган дам олиш масканлари ва хизмат кўрсатиш шаҳобчалар сони');
        self::row($s, 6, [null, 'ҲУДУДЛАР ЖАМИ', 328.0, 38.1, 0.116, 73, 8, 0.11]);
        self::row($s, 7, [1, 'Андижон вилояти', 8.9, 0, 0, 3, 0, 0]);
        // Жиззах: амалда cells blank -> "no data yet", must stay empty.
        self::row($s, 8, [2, 'Жиззах вилояти', 18.2, null, 0, 3, null, 0]);

        // --- 9-илова (task 48): four triplets per region row. ---
        $s = new Worksheet($book, '9-илова');
        $book->addSheet($s);
        self::set($s, 2, 1, '2026 йил I ярим йилликда йўл бўйларида хизмат соҳасини ривожлантириш…');
        self::set($s, 4, 1, 'Т/р');
        self::set($s, 4, 3, 'Хизмат кўрсатиш комплекслари сони');
        self::set($s, 4, 6, 'Хизмат кўрсатиш комплекслари қиймати (млрд сўм)');
        self::set($s, 4, 9, 'Комплекслардаги хизмат кўрсатиш шаҳобчалар сони');
        self::set($s, 4, 12, 'Хизмат кўрсатиш шаҳобчалари ташкил этиладиган туман (шаҳар) сони');
        self::row($s, 7, [1, 'Андижон вилояти', 5, 0, 0, 12.1, 0, 0, 47, 0, 0, 4, 0, 0]);
        self::row($s, 8, [2, 'Сирдарё вилояти', 4, 4, 1, 13.2, 17.9, 1.36, 61, 31, 0.51, 4, 4, 1]);

        // --- 15б-илова (task 111 headline): own region order, some regions omitted. ---
        $s = new Worksheet($book, '15б-илова');
        $book->addSheet($s);
        self::set($s, 2, 1, 'Республикада 2026 йилнинг II чорагида яширин иқтисодиётни қисқартириш…');
        self::set($s, 4, 1, 'Т/р');
        self::set($s, 4, 3, 'Яширин иқтисодиётга қарши курашиш… ҳисобига қўшимча даромад');
        self::row($s, 7, [1, 'Фарғона вилояти', 207, 210.4, 1.016]);
        self::row($s, 8, [2, 'Андижон вилояти', 117, 117, 1]);

        // --- 17-илова (task 133): wide sheet, объектлар triplet at cols U..W (21..23). ---
        $s = new Worksheet($book, '17-илова');
        $book->addSheet($s);
        self::set($s, 2, 1, '2026 йил I ярим йилликда ижтимоий ва ишлаб чиқариш инфратузилмасини ривожлантириш…');
        self::set($s, 4, 1, 'Т/р');
        self::set($s, 4, 21, 'Фойдаланишга топшириладиган объектлар (бюджет маблағи ҳисобидан) сони');
        // Region order deliberately NOT the standard one (real 17-илова swaps rows).
        $r17 = [
            [1, $unknownRegion ? 'Номаълум вилояти' : 'Қорақалпоғистон Республикаси', 9, 9, 1],
            [2, 'Сирдарё вилояти', 16, 6, 0.375],
            [3, 'Сурхондарё вилояти', 11, 21, 1.9],
            [4, 'Андижон вилояти', null, 10, null],
        ];
        foreach ($r17 as $i => $vals) {
            [$no, $name, $plan, $actual, $pct] = $vals;
            self::set($s, 6 + $i, 1, $no);
            self::set($s, 6 + $i, 2, $name);
            self::set($s, 6 + $i, 21, $plan);
            self::set($s, 6 + $i, 22, $actual);
            self::set($s, 6 + $i, 23, $pct);
        }

        $path = tempnam(sys_get_temp_dir(), 'ilova') . '.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }

    private static function set(Worksheet $sheet, int $row, int $col, mixed $val): void
    {
        if ($val === null) return;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $row, $val);
    }

    /** Writes a full row starting at column A (1-based). */
    private static function row(Worksheet $sheet, int $row, array $vals): void
    {
        foreach ($vals as $i => $val) {
            self::set($sheet, $row, $i + 1, $val);
        }
    }
}
