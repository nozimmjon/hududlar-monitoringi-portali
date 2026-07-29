<?php

namespace App\Services\Tasks;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Parses the all-sectors guarantee-letter tasks workbook
 * ("Вазифалар_2026_тармоқлар_кесимида.xlsx", one sheet per enterprise).
 *
 * Layout per sheet: row 1 title, row 2 org+signer, rows 3–4 headers, data from
 * row 5 (col A filled = new task, blank = continuation), "Изоҳлар:" ends the sheet.
 * Column I (Бажарилиши фоизда) is deliberately never read — pct is recomputed.
 */
class SectorWorkbookParser
{
    /** Header needles verified per sheet: [cellRow, colIndex, needle] */
    private const HEADER_CHECKS = [
        [2, 2, 'кўрсаткич номи'],   // C3
        [2, 3, 'индикатор номи'],   // D3
        [2, 4, 'ўлчов бирлиги'],    // E3
        [2, 5, 'муддат'],           // F3
        [3, 6, 'режа'],             // G4
        [3, 7, 'амалда'],           // H4
        [3, 8, 'фоиз'],             // I4
    ];

    /**
     * @return array{
     *   sheets: list<array{sort_order: int, sheet_title: string, tasks: list<array{
     *     task_no: int, title: string, lines: list<array{
     *       line_no: int, metric_label: string, unit: ?string,
     *       deadline_text: ?string, deadline_code: string, plan: ?float, actual: ?float
     *     }>
     *   }>}>,
     *   warnings: list<string>,
     * }
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $wb = $reader->load($path);

        $out = ['sheets' => [], 'warnings' => []];
        foreach ($wb->getAllSheets() as $sheet) {
            $title = self::clean($sheet->getTitle());
            if (! preg_match('/^(\d{1,2})\s*\./u', $title, $m)) {
                throw new RuntimeException("Лист '{$title}': сарлавҳада тартиб рақами йўқ — тармоққа мослаб бўлмайди.");
            }

            $rows = $sheet->rangeToArray('A1:I' . $sheet->getHighestDataRow(), null, true, false);
            $this->verifyHeader($title, $rows);

            $out['sheets'][] = [
                'sort_order'  => (int) $m[1],
                'sheet_title' => $title,
                'tasks'       => $this->parseRows($title, $rows, $out['warnings']),
            ];
        }

        return $out;
    }

    private function verifyHeader(string $title, array $rows): void
    {
        foreach (self::HEADER_CHECKS as [$rowIdx, $colIdx, $needle]) {
            $cell = mb_strtolower(self::clean((string) ($rows[$rowIdx][$colIdx] ?? '')));
            if (mb_strpos($cell, $needle) === false) {
                $cellName = chr(65 + $colIdx) . ($rowIdx + 1);
                throw new RuntimeException(
                    "Лист '{$title}': {$cellName} катагида '{$needle}' кутилган эди — жадвал тузилмаси ўзгарган, импорт тўхтатилди."
                    . " (Кутилган устунлар: Кўрсаткич номи / Индикатор номи / Ўлчов бирлиги / Муддати / Режа / Амалда / фоизда)"
                );
            }
        }
    }

    /** @param list<string> $warnings */
    private function parseRows(string $title, array $rows, array &$warnings): array
    {
        $tasks = [];
        $current = null;

        foreach ($rows as $i => $row) {
            if ($i < 4) {
                continue; // rows 1–4: title, org, headers
            }

            $a = self::clean((string) ($row[0] ?? ''));
            if (mb_stripos($a, 'изоҳ') !== false) {
                break;
            }

            $b = self::clean((string) ($row[1] ?? ''));
            $d = self::clean((string) ($row[3] ?? ''));

            if ($a !== '') {
                // Column A filled always starts a new task, even if this row
                // itself carries no indicator (B/D empty) — otherwise its
                // continuation lines would misattribute to the previous task.
                if ($current !== null) {
                    $tasks[] = $current;
                }
                $c = self::clean((string) ($row[2] ?? ''));
                if ($c === '') {
                    if ($d === '') {
                        $warnings[] = "Лист '{$title}', вазифа №{$a}: Кўрсаткич номи (C) ва Индикатор номи (D) бўш — '(номаълум)' ишлатилди.";
                        $c = '(номаълум)';
                    } else {
                        $warnings[] = "Лист '{$title}', вазифа №{$a}: Кўрсаткич номи (C) бўш — индикатор номи ишлатилди.";
                        $c = $d;
                    }
                }
                $current = ['task_no' => (int) $a, 'title' => $c, 'lines' => []];
            } elseif ($current === null) {
                if ($b === '' && $d === '') {
                    continue; // spacer row
                }
                $warnings[] = "Лист '{$title}', қатор " . ($i + 1) . ": вазифа рақамисиз индикатор қатори ташлаб кетилди.";
                continue;
            }

            if ($b === '' || $d === '') {
                continue; // no indicator on this row — task started, but no line to append
            }

            $deadlineText = self::clean((string) ($row[5] ?? ''));
            $current['lines'][] = [
                'line_no'       => (int) $b,
                'metric_label'  => $d,
                'unit'          => self::clean((string) ($row[4] ?? '')) ?: null,
                'deadline_text' => $deadlineText ?: null,
                'deadline_code' => $this->deadlineCode($deadlineText, $title, $warnings),
                'plan'          => self::num($row[6] ?? null),
                'actual'        => self::num($row[7] ?? null),
                // column I ($row[8]) deliberately ignored — pct is recomputed downstream
            ];
        }
        if ($current !== null) {
            $tasks[] = $current;
        }

        return $tasks;
    }

    /** @param list<string> $warnings */
    private function deadlineCode(string $text, string $title, array &$warnings): string
    {
        $t = mb_strtolower($text);
        $t = str_replace(['і', 'ѵ'], ['i', 'v'], $t); // Cyrillic lookalikes → Latin (hand-edited future files)
        if (mb_strpos($t, 'якун') !== false) {
            return 'year';
        }
        // Assumes the H2-2026 workbook season, where "ярим йил" always means the
        // second half — an H1-season workbook would need a 1-/2- prefix check
        // (e.g. "1-ярим йил" vs "2-ярим йил") before this could be reused as-is.
        if (mb_strpos($t, 'ярим йил') !== false) {
            return 'h2';
        }
        if (mb_strpos($t, 'iv') !== false) {
            return 'q4';
        }
        if (mb_strpos($t, 'iii') !== false) {
            return 'q3';
        }

        $warnings[] = "Лист '{$title}': номаълум муддат '{$text}' — 'year' деб олинди.";

        return 'year';
    }

    private static function clean(string $v): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $v)));
    }

    private static function num(mixed $v): ?float
    {
        if ($v === null) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $v));

        return is_numeric($s) ? (float) $s : null;
    }
}
