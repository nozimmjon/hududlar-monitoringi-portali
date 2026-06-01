<?php

namespace App\Services\Tasks;

use App\Support\TaskPeriod;
use App\Support\TasksTaxonomy;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TaskWorkbookParser
{
    private const FIRST_DATA_ROW = 5;

    /**
     * @return list<array{
     *   task_number:string, title:string, deadline_text:?string, period_code:?string,
     *   kind:string, data_source:?string, report_schedule_text:?string, cadence:string,
     *   mechanism_text:?string, integration_status:?string, module_code:?string,
     *   indicator_code:?string, section_path:string, section_label:string, source_row:int,
     *   regions: array<int, array{executor_text:string, metrics: list<array{
     *     line_no:int, metric_label:?string, unit:?string, plan:?float, actual:?float, pct:?float
     *   }>}>
     * }>
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        // Full read mode required for getCalculatedValue() to resolve formula cells.
        $book = $reader->load($path);
        $sheet = $book->getActiveSheet();

        $this->assertLayout($sheet);

        $maxRow = $sheet->getHighestDataRow();
        $tasks = [];
        $current = null;          // index of the in-progress task in $tasks, or null
        $module = null; $indicator = null; $path0 = ''; $label = '';

        $romanRe   = '/^(VII|VI|IV|V|III|II|I)\./u';
        $numericRe = '/^(\d+)\.(\d+)\./u';

        for ($row = self::FIRST_DATA_ROW; $row <= $maxRow; $row++) {
            $a = $this->str($sheet, 1, $row);
            $c = $this->str($sheet, 3, $row);
            $d = $this->str($sheet, 4, $row);
            $e = $this->str($sheet, 5, $row);

            // Section header rows (col A carries a marker, col C empty).
            if ($a !== '' && $c === '' && ! $this->isIntToken($a)) {
                if (preg_match($romanRe, $a, $m)) {
                    $module    = TasksTaxonomy::ROMAN_TO_MODULE[$m[1]] ?? null;
                    $indicator = null;
                    $path0     = $m[1];
                    $label     = $a;
                } elseif (preg_match($numericRe, $a, $m)) {
                    $key       = $m[1] . '.' . $m[2];
                    $indicator = TasksTaxonomy::NUMERIC_TO_INDICATOR[$key] ?? null;
                    $prefix    = $path0 !== '' ? explode('.', $path0)[0] : '';
                    $path0     = ($prefix !== '' ? $prefix . '.' : '') . $key;
                    $label     = $a;
                } else {
                    $label = $a; // free-text sub-label, keep module/indicator
                }
                continue;
            }

            // New task row: integer col A + non-empty title.
            if ($this->isIntToken($a) && $c !== '') {
                $deadline = $this->str($sheet, 6, $row);
                // Col B (the second № column) is the unique task identifier in the source;
                // col A restarts/duplicates in places. Fall back to A when B is unusable.
                $b = $this->str($sheet, 2, $row);
                $taskNumber = $this->isIntToken($b) ? $b : $a;
                $tasks[] = [
                    'task_number'          => (string) (int) (float) $taskNumber,
                    'title'                => $c,
                    'deadline_text'        => $this->normWs($deadline) ?: null,
                    'period_code'          => TaskPeriod::deadlineToPeriodCode($deadline),
                    'kind'                 => str_starts_with($this->str($sheet, 8, $row), 'KPI') ? 'kpi' : 'measure',
                    'data_source'          => $this->str($sheet, 9, $row) ?: null,
                    'report_schedule_text' => $this->str($sheet, 10, $row) ?: null,
                    'cadence'              => TaskPeriod::cadenceFor($this->str($sheet, 10, $row)),
                    'mechanism_text'       => $this->str($sheet, 11, $row) ?: null,
                    'integration_status'   => $this->str($sheet, 12, $row) ?: null,
                    'module_code'          => $module,
                    'indicator_code'       => $indicator,
                    'section_path'         => $path0,
                    'section_label'        => $label,
                    'source_row'           => $row,
                    'regions'              => [],
                ];
                $current = count($tasks) - 1;
                $this->captureRegions($sheet, $row, $tasks[$current], 0, true);
                continue;
            }

            // Continuation metric line (col A empty, col D label OR col E unit present).
            if ($a === '' && ($d !== '' || $e !== '') && $current !== null) {
                $nextLine = $this->maxLineNo($tasks[$current]) + 1;
                $this->captureRegions($sheet, $row, $tasks[$current], $nextLine, false);
                continue;
            }
        }

        $book->disconnectWorksheets();
        return array_values($tasks);
    }

    /** Read each region block's metric (+ executor on task rows) into $task['regions']. */
    private function captureRegions(Worksheet $sheet, int $row, array &$task, int $lineNo, bool $isTaskRow): void
    {
        $metricLabel = $this->str($sheet, 4, $row) ?: null; // col D
        $unit        = $this->str($sheet, 5, $row) ?: null; // col E

        foreach (TasksTaxonomy::REGION_BLOCKS as $col => $code) {
            $executor  = $this->str($sheet, $col + 0, $row);
            $planRaw   = $this->str($sheet, $col + 1, $row);
            $actualRaw = $this->str($sheet, $col + 2, $row);
            $pctRaw    = $this->str($sheet, $col + 3, $row);
            $plan      = $this->num($sheet, $col + 1, $row);
            $actual    = $this->num($sheet, $col + 2, $row);
            $pctCell   = $this->num($sheet, $col + 3, $row);

            // A region is listed for a task when ANY cell in its task-row block is
            // filled — a real executor/value OR a deliberate «х»/«-» N/A marker in any
            // of the four cells (partner files mark N/A in the executor cell, the plan
            // cell, or both). A fully blank block means the region is not listed for
            // this task and stays absent.
            $blockFilled     = $isTaskRow && ($executor !== '' || $planRaw !== '' || $actualRaw !== '' || $pctRaw !== '');
            $hasRealExecutor = $isTaskRow && $executor !== '' && ! $this->isSentinel($executor);
            $hasValue        = $plan !== null || $actual !== null || $pctCell !== null;
            if (! $blockFilled && ! $hasValue) continue;

            $pct = $pctCell;
            if ($pct === null && $plan !== null && $actual !== null && $plan != 0.0) {
                $pct = $actual / $plan * 100.0;
            }

            if (! isset($task['regions'][$code])) {
                $task['regions'][$code] = ['executor_text' => '', 'metrics' => []];
            }
            if ($hasRealExecutor && $task['regions'][$code]['executor_text'] === '') {
                $task['regions'][$code]['executor_text'] = $executor;
            }
            $task['regions'][$code]['metrics'][] = [
                'line_no'      => $lineNo,
                'metric_label' => $metricLabel,
                'unit'         => $unit,
                'plan'         => $plan,
                'actual'       => $actual,
                'pct'          => $pct,
            ];
        }
    }

    private function maxLineNo(array $task): int
    {
        $max = 0;
        foreach ($task['regions'] as $r) {
            foreach ($r['metrics'] as $m) {
                $max = max($max, $m['line_no']);
            }
        }
        return $max;
    }

    /** Every region block header must be in its expected column — refuse reordered/shifted files. */
    private function assertLayout(Worksheet $sheet): void
    {
        $problems = [];
        foreach (TasksTaxonomy::REGION_HEADER_ANCHORS as $col => $needle) {
            $h = $this->str($sheet, $col, 3);
            if (mb_strpos($h, $needle) === false) {
                $problems[] = "column {$col}: expected '{$needle}', found '{$h}'";
            }
        }
        // Distinguish Тошкент вилояти (col 53) from Тошкент шаҳри (col 65):
        // col 53 must NOT contain "шаҳри".
        $col53 = $this->str($sheet, 53, 3);
        if (mb_strpos($col53, 'шаҳри') !== false) {
            $problems[] = "column 53: expected Тошкент вилояти, found '{$col53}'";
        }

        if ($problems !== []) {
            throw new \RuntimeException(
                'Unexpected workbook layout — the region block columns may have shifted or been reordered: '
                . implode('; ', $problems)
            );
        }
    }

    /** Raw cell value with formula evaluation; falls back to cached/null when calc fails. */
    private function cellValue(Worksheet $sheet, int $col, int $row): mixed
    {
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row);
        try {
            return $cell->getCalculatedValue();
        } catch (\Throwable) {
            try {
                return $cell->getOldCalculatedValue();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function str(Worksheet $sheet, int $col, int $row): string
    {
        $v = $this->cellValue($sheet, $col, $row);
        return $v === null ? '' : trim(str_replace("\u{00A0}", ' ', (string) $v));
    }

    private function num(Worksheet $sheet, int $col, int $row): ?float
    {
        $v = $this->cellValue($sheet, $col, $row);
        if ($v === null) return null;
        if (is_int($v) || is_float($v)) return (float) $v;
        $s = trim((string) $v);
        if ($s === '' || $this->isSentinel($s)) return null;
        $s = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $s);
        return is_numeric($s) ? (float) $s : null;
    }

    private function isSentinel(string $s): bool
    {
        $t = mb_strtolower(trim($s));
        return in_array($t, ['х', 'x', '-', '—', '–'], true);
    }

    private function isIntToken(string $s): bool
    {
        return $s !== '' && preg_match('/^\d+(\.0+)?$/', $s) === 1;
    }

    private function normWs(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $s)));
    }
}
