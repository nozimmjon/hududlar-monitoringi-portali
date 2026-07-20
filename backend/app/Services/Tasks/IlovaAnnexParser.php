<?php

namespace App\Services\Tasks;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parses the half-year annex workbook ("илова жадваллар.xlsx") the partner sends
 * with the economic file. Only sheets that carry per-region actuals missing from
 * the main H1 import are read — the rest of the file duplicates data the economic
 * workbook already provided.
 *
 * Region ROW ORDER IS NOT STABLE across sheets (17-илова swaps Сирдарё/Сурхондарё,
 * 15б-илова omits Жиззах and uses its own order, several sheets omit Тошкент шаҳри),
 * so regions are matched strictly by name, never by row position. A row that looks
 * like a region (numbered, named) but cannot be matched aborts the parse.
 *
 * Header-cell guards verify each sheet's columns still mean what this parser
 * assumes; a moved/renamed column aborts instead of importing wrong numbers.
 */
class IlovaAnnexParser
{
    /** Region name stem (lowercase) => SOATO code. Тошкент is disambiguated separately. */
    private const REGION_STEMS = [
        'қорақалпоғ' => 1735,
        'андижон'    => 1703,
        'бухоро'     => 1706,
        'жиззах'     => 1708,
        'қашқадарё'  => 1710,
        'навоий'     => 1712,
        'наманган'   => 1714,
        'самарқанд'  => 1718,
        'сирдарё'    => 1724,
        'сурхондарё' => 1722,
        'фарғона'    => 1730,
        'хоразм'     => 1733,
    ];

    /**
     * Simple per-region-row sheets: each matched region row carries
     * line_no => [planCol, actualCol] (0-based) cell pairs, with an optional
     * third element scaling both values (ratio columns store 0.077 for 7.7%).
     * Guards: [headerRow (1-based), col (0-based), lowercase substring].
     */
    private const COLUMN_SHEETS = [
        '2-илова' => [
            'task'   => '4',
            'guards' => [[4, 2, 'прогноз'], [4, 4, 'амалда'], [5, 5, 'ўсиш']],
            'lines'  => [1 => [3, 5, 100]], // growth-rate line; volume (line 0) already imported
        ],
        '8-илова' => [
            'task'   => '46',
            'guards' => [[4, 2, 'инвестициялар'], [4, 5, 'дам олиш']],
            'lines'  => [0 => [2, 3], 1 => [5, 6]],
        ],
        '9-илова' => [
            'task'   => '48',
            'guards' => [[4, 2, 'комплекслари сони'], [4, 5, 'комплекслари қиймати'], [4, 8, 'шаҳобчалар'], [4, 11, 'туман']],
            'lines'  => [0 => [2, 3], 1 => [5, 6], 2 => [8, 9], 3 => [11, 12]],
        ],
        '15б-илова' => [
            'task'   => '111',
            'guards' => [[4, 2, 'яширин']],
            'lines'  => [0 => [2, 3]],
        ],
        '17-илова' => [
            'task'   => '133',
            'guards' => [[4, 20, 'фойдаланишга топшириладиган объектлар']],
            'lines'  => [0 => [20, 21]],
        ],
    ];

    /** 7-илова (task 40): факт row per region followed by a "режа" row; lines 0..5 in cols 2..7. */
    private const BLOCK_SHEET = '7-илова';
    private const BLOCK_TASK = '40';
    private const BLOCK_GUARDS = [[4, 2, 'туман ва шаҳарлар сони'], [4, 7, 'иш ўринлар']];
    private const BLOCK_FIRST_COL = 2;
    private const BLOCK_LINES = 6;

    /** 4-илова (task 10): actual = count of districts whose col-7 diff from region average is positive. */
    private const DISTRICT_SHEET = '4-илова';
    private const DISTRICT_TASK = '10';
    private const DISTRICT_GUARDS = [[4, 7, 'ўртача кўрсаткичдан фарқ']];
    private const DISTRICT_DIFF_COL = 7;

    /**
     * @return array{tasks: array<string, array<int, array<int, array{plan: ?float, actual: ?float}>>>, warnings: string[]}
     *   tasks: task_number => region_code => line_no => ['plan' => ?float, 'actual' => ?float]
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        $tasks = [];
        $warnings = [];

        foreach (self::COLUMN_SHEETS as $sheetName => $cfg) {
            $rows = $this->sheetRows($book->getSheetByName($sheetName), $sheetName);
            $this->assertGuards($rows, $cfg['guards'], $sheetName);
            $tasks[$cfg['task']] = $this->parseColumnSheet($rows, $cfg['lines'], $sheetName);
        }

        $rows = $this->sheetRows($book->getSheetByName(self::BLOCK_SHEET), self::BLOCK_SHEET);
        $this->assertGuards($rows, self::BLOCK_GUARDS, self::BLOCK_SHEET);
        $tasks[self::BLOCK_TASK] = $this->parseBlockSheet($rows);

        $rows = $this->sheetRows($book->getSheetByName(self::DISTRICT_SHEET), self::DISTRICT_SHEET);
        $this->assertGuards($rows, self::DISTRICT_GUARDS, self::DISTRICT_SHEET);
        $tasks[self::DISTRICT_TASK] = $this->parseDistrictSheet($rows, $warnings);

        return ['tasks' => $tasks, 'warnings' => $warnings];
    }

    /** @return array<int, array<int, mixed>> 1-based row => 0-based col => calculated value */
    private function sheetRows(?Worksheet $sheet, string $name): array
    {
        if ($sheet === null) {
            throw new \RuntimeException("Sheet \"{$name}\" not found in the annex workbook — layout changed, refusing to import.");
        }

        $rows = $sheet->toArray(null, true, false, false);

        return $rows === [] ? [] : array_combine(range(1, count($rows)), $rows);
    }

    /** @param array<int, array{0: int, 1: int, 2: string}> $guards */
    private function assertGuards(array $rows, array $guards, string $sheetName): void
    {
        foreach ($guards as [$row, $col, $needle]) {
            $cell = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($rows[$row][$col] ?? ''))));
            if (! str_contains($cell, $needle)) {
                throw new \RuntimeException(
                    "Sheet \"{$sheetName}\": expected header containing \"{$needle}\" at row {$row}, column " . ($col + 1)
                    . ' — columns moved or renamed, refusing to import.'
                );
            }
        }
    }

    /** @param array<int, array{0: int, 1: int}> $lines line_no => [planCol, actualCol] */
    private function parseColumnSheet(array $rows, array $lines, string $sheetName): array
    {
        $out = [];
        foreach ($rows as $row) {
            $code = $this->regionRowCode($row, $sheetName);
            if ($code === null) continue;

            foreach ($lines as $lineNo => $cols) {
                [$planCol, $actualCol] = $cols;
                $scale = $cols[2] ?? 1;
                $plan = $this->num($row[$planCol] ?? null);
                $actual = $this->num($row[$actualCol] ?? null);
                $out[$code][$lineNo] = [
                    'plan'   => $plan === null ? null : $plan * $scale,
                    'actual' => $actual === null ? null : $actual * $scale,
                ];
            }
        }

        return $out;
    }

    private function parseBlockSheet(array $rows): array
    {
        $out = [];
        $rowNums = array_keys($rows);
        foreach ($rowNums as $i => $r) {
            $code = $this->regionRowCode($rows[$r], self::BLOCK_SHEET);
            if ($code === null) continue;

            $next = $rows[$rowNums[$i + 1] ?? -1] ?? [];
            $planRow = str_contains(mb_strtolower(trim((string) ($next[1] ?? ''))), 'режа') ? $next : [];

            for ($line = 0; $line < self::BLOCK_LINES; $line++) {
                $col = self::BLOCK_FIRST_COL + $line;
                $out[$code][$line] = [
                    'plan'   => $this->num($planRow[$col] ?? null),
                    'actual' => $this->num($rows[$r][$col] ?? null),
                ];
            }
        }

        return $out;
    }

    private function parseDistrictSheet(array $rows, array &$warnings): array
    {
        $out = [];
        $current = null;
        foreach ($rows as $row) {
            $code = $this->regionRowCode($row, self::DISTRICT_SHEET);
            if ($code !== null) {
                $current = $code;
                $out[$code][0] = ['plan' => null, 'actual' => 0.0];
                continue;
            }

            // District row: unnumbered, named, inside a region block.
            $name = trim((string) ($row[1] ?? ''));
            if ($current === null || $name === '' || trim((string) ($row[0] ?? '')) !== '') {
                continue;
            }
            $diff = $this->num($row[self::DISTRICT_DIFF_COL] ?? null);
            if ($diff === null) {
                $warnings[] = self::DISTRICT_SHEET . ": district \"{$name}\" has no numeric diff value — not counted.";
                continue;
            }
            if ($diff > 0) {
                $out[$current][0]['actual'] += 1.0;
            }
        }

        return $out;
    }

    /**
     * SOATO code when the row is a numbered region row (col 0 numeric, col 1 a known
     * region name); null for aggregate/label/district rows. Unknown region names on
     * numbered rows abort — silently skipping one would drop its data.
     */
    private function regionRowCode(array $row, string $sheetName): ?int
    {
        if (! is_numeric($row[0] ?? null)) return null;

        $raw = trim(preg_replace('/\s+/u', ' ', (string) ($row[1] ?? '')));
        if ($raw === '') return null;

        $code = self::regionCode($raw);
        if ($code === null) {
            throw new \RuntimeException("Sheet \"{$sheetName}\": unrecognised region name \"{$raw}\" — refusing to import.");
        }

        return $code;
    }

    /** SOATO code for a region label, or null for aggregates/unknown names. */
    public static function regionCode(string $raw): ?int
    {
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $raw)));
        if ($name === '' || str_contains($name, 'жами') || str_contains($name, 'ҳаммаси') || str_contains($name, 'республика бўйича')) {
            return null;
        }

        foreach (self::REGION_STEMS as $stem => $code) {
            if (str_contains($name, $stem)) {
                return $code;
            }
        }

        if (str_contains($name, 'тошкент')) {
            if (preg_match('/шаҳ|(^|\s)ш\.?($|\s)/u', $name)) return 1726;
            if (str_contains($name, 'вил')) return 1727;
        }

        return null;
    }

    private function num(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) return (float) $v;
        if (is_string($v)) {
            $s = str_replace(',', '.', trim($v));
            if ($s !== '' && is_numeric($s)) return (float) $s;
        }

        return null;
    }
}
