<?php

namespace Tests\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds a road map in the xlsx layout the regions returned: sheet 0 with the
 * title row, the A–J header row and then one row per spec entry, plus a second
 * «Йўриқнома» sheet the importer must ignore.
 *
 * Row specs (all cell maps are coordinate letter => value, missing cells stay empty):
 *   ['section',  'I. Вилоятда амалга ошириладиган йирик лойиҳалар']   → the text in A
 *   ['section',  '…', ['col' => 'B']]                                  → the text in B
 *   ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)']  → the text in A ('col' works too)
 *   ['measure',  ['C' => 'Каналларни бетонлаштириш.', 'I' => '2026 йил декабрь']]
 *   ['line',     ['D' => 'Хўжаликлараро каналлар', 'E' => 'км', 'F' => '7,8']]
 *   ['blank']
 *   ['raw',      ['A' => '…', 'H' => '…']]                             → whatever the case needs
 *
 * 'measure', 'line' and 'raw' are the same thing — three names so the fixtures read
 * like the layout they describe.
 */
class RoadmapXlsxBuilder
{
    public const HEADER = ['Калит', '№', 'Чора-тадбир', 'Индикатор', 'Ўлчов', 'Режа', 'Амалда', 'Изоҳ', 'Муддат', 'Масъуллар'];

    /** @param list<array> $rows */
    public static function make(array $rows, ?string $path = null, string $region = 'Тест вилояти'): string
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($region);
        $sheet->setCellValue('A1', "Сув хўжалиги йўл харитаси: {$region} ");     // the real files carry the trailing space
        foreach (self::HEADER as $i => $header) {
            $sheet->setCellValue(chr(ord('A') + $i) . '2', $header);
        }

        $r = 3;
        foreach ($rows as $row) {
            foreach (self::cells($row) as $col => $value) {
                if ($value !== null) {
                    $sheet->setCellValue($col . $r, $value);
                }
            }
            $r++;
        }

        $help = $book->createSheet();
        $help->setTitle('Йўриқнома');
        $help->setCellValue('A1', 'Йўриқнома');
        $help->setCellValue('A2', 'Бу варақ импортда эътиборга олинмайди.');
        $help->setCellValue('A3', 'I. Бу сатр бўлим сарлавҳасига ўхшайди, лекин иккинчи варақда.');

        $path ??= tempnam(sys_get_temp_dir(), 'rmxlsx_') . '.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /** @return array<string, mixed> */
    private static function cells(array $row): array
    {
        return match ($row[0]) {
            'blank'               => [],
            'section', 'district' => [($row[2]['col'] ?? 'A') => $row[1]],
            default               => $row[1] ?? [],
        };
    }
}
