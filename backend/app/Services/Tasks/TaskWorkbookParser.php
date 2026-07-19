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
     * Two known file generations share the row grammar but differ in columns:
     * - 'monitoring' (Ҳудудий кўрсаткичлар назорати): metadata cols H..L,
     *   region blocks from col 13, % column holds percent values.
     * - 'economic' (Иқтисодий кўрсаткичлар): no metadata cols, region blocks
     *   from col 7, % column holds a RATIO (1.0 = 100%).
     */
    private string $layout = 'monitoring';

    /** @var array<int,int> block start col => SOATO region code, per detected layout */
    private array $blocks = TasksTaxonomy::REGION_BLOCKS;

    /**
     * @return list<array{
     *   task_number:string, title:string, deadline_text:?string, period_code:?string,
     *   kind:?string, data_source:?string, report_schedule_text:?string, cadence:?string,
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

        $this->detectLayout($sheet);

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
                // The economic file has no metadata columns (cols 7+ are region data);
                // those fields stay null so the importer preserves existing values.
                $hasMeta = $this->layout === 'monitoring';
                $tasks[] = [
                    'task_number'          => (string) (int) (float) $taskNumber,
                    'title'                => $c,
                    'deadline_text'        => $this->normWs($deadline) ?: null,
                    'period_code'          => TaskPeriod::deadlineToPeriodCode($deadline),
                    'kind'                 => $hasMeta ? (str_starts_with($this->str($sheet, 8, $row), 'KPI') ? 'kpi' : 'measure') : null,
                    'data_source'          => $hasMeta ? ($this->str($sheet, 9, $row) ?: null) : null,
                    'report_schedule_text' => $hasMeta ? ($this->str($sheet, 10, $row) ?: null) : null,
                    'cadence'              => $hasMeta ? TaskPeriod::cadenceFor($this->str($sheet, 10, $row)) : null,
                    'mechanism_text'       => $hasMeta ? ($this->str($sheet, 11, $row) ?: null) : null,
                    'integration_status'   => $hasMeta ? ($this->str($sheet, 12, $row) ?: null) : null,
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

        // Economic files mark "not applicable for this region" with an empty or
        // «х» Режа кўрсаткичи. Such regions must not carry the task at all —
        // keep a region only when at least one of its lines has a numeric plan.
        // (Monitoring files keep their historical plan-less entries; visibility
        // is handled by Task::scopeHasPlan.)
        if ($this->layout === 'economic') {
            foreach ($tasks as &$t) {
                $t['regions'] = array_filter(
                    $t['regions'],
                    function (array $r): bool {
                        foreach ($r['metrics'] as $m) {
                            if ($m['plan'] !== null) return true;
                        }
                        return false;
                    }
                );
            }
            unset($t);
        }

        return array_values($tasks);
    }

    /** Read each region block's metric (+ executor on task rows) into $task['regions']. */
    private function captureRegions(Worksheet $sheet, int $row, array &$task, int $lineNo, bool $isTaskRow): void
    {
        $metricLabel = $this->str($sheet, 4, $row) ?: null; // col D
        $unit        = $this->str($sheet, 5, $row) ?: null; // col E

        foreach ($this->blocks as $col => $code) {
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

            if (in_array($task['task_number'], TasksTaxonomy::LOWER_IS_BETTER_TASKS, true)) {
                // Lower-is-better indicator: the file's % cell is actual/plan and
                // reads >100% exactly when the region did WORSE than the plan.
                // Recompute as plan/actual on both layouts.
                if ($actual === null || $plan === null) {
                    $pct = null;
                } elseif ($actual == 0.0) {
                    $pct = 100.0; // held at/below a zero target — met
                } else {
                    $pct = $plan / $actual * 100.0;
                }
            } elseif ($this->layout === 'economic') {
                // The economic % column is a ratio (1.0 = 100%), and its formula
                // yields 0 when Амалда ижроси is still empty — that 0 is an
                // artifact, not a reported 0% execution.
                $pct = $actual !== null && $pctCell !== null ? $pctCell * 100.0 : null;
                if ($pct === null && $plan !== null && $actual !== null && $plan != 0.0) {
                    $pct = $actual / $plan * 100.0;
                }
            } else {
                $pct = $pctCell;
                if ($pct === null && $plan !== null && $actual !== null && $plan != 0.0) {
                    $pct = $actual / $plan * 100.0;
                }
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

    /**
     * Pick the file generation by where the first region block header sits
     * (row 3: col 13 = monitoring, col 7 = economic), then enforce that every
     * region block header is in its expected column — refuse shifted/reordered files.
     */
    private function detectLayout(Worksheet $sheet): void
    {
        if (mb_strpos($this->str($sheet, 13, 3), 'Қорақалпоғистон') !== false) {
            $this->layout = 'monitoring';
            $this->blocks = TasksTaxonomy::REGION_BLOCKS;
            $this->assertAnchors($sheet, TasksTaxonomy::REGION_HEADER_ANCHORS, 53);
        } elseif (mb_strpos($this->str($sheet, 7, 3), 'Қорақалпоғистон') !== false) {
            $this->layout = 'economic';
            $this->blocks = TasksTaxonomy::ECONOMIC_REGION_BLOCKS;
            $this->assertAnchors($sheet, TasksTaxonomy::ECONOMIC_REGION_HEADER_ANCHORS, 47);
        } else {
            throw new \RuntimeException(
                'Unexpected workbook layout — no Қорақалпоғистон region header in row 3 at column 13 '
                . '(monitoring file) or column 7 (economic file).'
            );
        }
    }

    /** @param array<int,string> $anchors */
    private function assertAnchors(Worksheet $sheet, array $anchors, int $tashkentRegionCol): void
    {
        $problems = [];
        foreach ($anchors as $col => $needle) {
            $h = $this->str($sheet, $col, 3);
            if (mb_strpos($h, $needle) === false) {
                $problems[] = "column {$col}: expected '{$needle}', found '{$h}'";
            }
        }
        // Distinguish Тошкент вилояти from Тошкент шаҳри:
        // the вилоят block's header must NOT contain "шаҳри".
        $h = $this->str($sheet, $tashkentRegionCol, 3);
        if (mb_strpos($h, 'шаҳри') !== false) {
            $problems[] = "column {$tashkentRegionCol}: expected Тошкент вилояти, found '{$h}'";
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
