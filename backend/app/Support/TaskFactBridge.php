<?php

namespace App\Support;

use App\Models\IndicatorFact;

/**
 * Bridges task actuals (Амалда ижроси from the tasks workbook) into the
 * dashboard's region-level indicator_facts rows, so module pages show the
 * reported half-year/annual actual instead of the imported Кутилиш forecast.
 *
 * Values are matched by task number (col B) + line_no, guarded by a metric
 * label substring so a silent row shift in the source file cannot feed a
 * wrong number into the dashboard.
 */
class TaskFactBridge
{
    /**
     * indicator_code => fact period => [task_number, line_no, label_needle, scale].
     * `scale` converts the task unit into the fact row's unit
     * (e.g. export: млн долл -> минг доллар = ×1000).
     */
    public const MAP = [
        'budget' => [
            'h1'   => ['117', 0, 'Бюджет даромади', 1],
            'year' => ['121', 0, 'Бюджет даромади', 1],
        ],
        'investment' => [
            'h1'   => ['155', 0, 'инвестиция', 1],
            'year' => ['157', 0, 'инвестициялар ва кредитлар', 1],
        ],
        'export' => [
            'h1'   => ['165', 0, 'Экспорт хажми', 1000],
            'year' => ['167', 0, 'Экспорт хажми', 1000],
        ],
        'unemployment' => [
            'h1'   => ['181', 0, 'Ишсизлик даражаси', 1],
            'year' => ['200', 0, 'Ишсизлик даражаси', 1],
        ],
        'poverty' => [
            'h1'   => ['213', 0, 'Камбағаллик даражаси', 1],
            'year' => ['214', 0, 'Камбағаллик даражаси', 1],
        ],
        'jobs' => [
            'h1'   => ['182', 0, 'доимий ишга жойлаштириладиган', 1],
            'year' => ['201', 0, 'доимий ишга жойлаштириладиган', 1],
        ],
        'legalization' => [
            'h1'   => ['182', 2, 'легаллаш', 1],
            'year' => ['201', 2, 'легаллаш', 1],
        ],
        'microprojects' => [
            'h1'   => ['182', 4, 'ихтисослашув', 1000], // минг дона -> дона
            'year' => ['201', 4, 'ихтисослашув', 1000],
        ],
        'mfy_clear' => [
            'h1'   => ['215', 0, 'холи бўлган маҳаллалар', 1],
        ],
    ];

    /**
     * Push task actuals from a parsed workbook into indicator_facts.
     * Only updates existing region-level rows — never creates fact rows.
     *
     * @param  list<array>  $parsedTasks  TaskWorkbookParser::parse() output
     * @return array{updated:int, notes:list<string>}
     */
    public static function apply(array $parsedTasks, int $year, ?int $regionFilter = null, bool $dryRun = false): array
    {
        $byNumber = [];
        foreach ($parsedTasks as $t) {
            $byNumber[$t['task_number']] = $t;
        }

        $updated = 0;
        $notes = [];

        foreach (self::MAP as $indicator => $periods) {
            foreach ($periods as $factPeriod => [$number, $lineNo, $needle, $scale]) {
                $t = $byNumber[$number] ?? null;
                if ($t === null) {
                    continue; // task not in this file generation
                }

                foreach ($t['regions'] as $code => $regionData) {
                    if ($regionFilter !== null && $code !== $regionFilter) {
                        continue;
                    }
                    $metric = null;
                    foreach ($regionData['metrics'] as $m) {
                        if ($m['line_no'] === $lineNo) {
                            $metric = $m;
                            break;
                        }
                    }
                    if ($metric === null || $metric['actual'] === null) {
                        continue; // no reported actual (yet) for this region
                    }

                    $label = $metric['metric_label'] ?? $t['title'];
                    if (mb_stripos($label, $needle) === false) {
                        $notes[] = "task {$number} line {$lineNo}: label '{$label}' does not contain '{$needle}' — skipped ({$indicator}/{$factPeriod})";
                        continue 2; // label drifted: refuse this mapping entirely
                    }

                    $fact = IndicatorFact::where('region_code', $code)
                        ->where('year', $year)
                        ->whereNull('district_code')
                        ->where('indicator_code', $indicator)
                        ->where('period', $factPeriod)
                        ->first();
                    if ($fact === null) {
                        $notes[] = "no {$indicator}/{$factPeriod} fact row for region {$code} — skipped";
                        continue;
                    }

                    $actual = $metric['actual'] * $scale;
                    $fact->actual_hokimyat = $actual;
                    $plan = $fact->plan_value !== null ? (float) $fact->plan_value : null;
                    if ($plan !== null && $plan != 0.0) {
                        $fact->pct_of_plan = DashboardCatalog::isLowerBetter($indicator)
                            ? ($actual != 0.0 ? $plan / $actual * 100.0 : null)
                            : $actual / $plan * 100.0;
                    }
                    if (! $dryRun) {
                        $fact->save();
                    }
                    $updated++;
                }
            }
        }

        return ['updated' => $updated, 'notes' => array_values(array_unique($notes))];
    }
}
