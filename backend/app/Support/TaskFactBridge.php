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
     * indicator_code => fact period => list of writes
     * [task_number, line_no, label_needle, scale, field].
     * `scale` converts the task unit into the fact row's unit
     * (e.g. export: млн долл -> минг доллар = ×1000).
     * `field` 'actual' writes actual_hokimyat (+pct vs fact plan);
     * `field` 'growth' writes growth_pct as ratio (100 + workbook delta %) —
     * macro KPIs report ўсиш суръати as a delta, dashboard stores 104.7-style.
     */
    public const MAP = [
        'budget' => [
            'h1'   => [['117', 0, 'Бюджет даромади', 1, 'actual']],
            'year' => [['121', 0, 'Бюджет даромади', 1, 'actual']],
        ],
        'investment' => [
            'h1'   => [['155', 0, 'инвестиция', 1, 'actual']],
            'year' => [['157', 0, 'инвестициялар ва кредитлар', 1, 'actual']],
        ],
        // Budget-funded investments: the workbook reports млрд сўм, the fact rows
        // are млн сўм, hence the ×1000 scale.
        'budget_investment' => [
            'h1'   => [['125', 0, 'Бюджет маблағлари ҳисобидан инвестиция', 1000, 'actual']],
            'year' => [['129', 0, 'Бюджет маблағлари ҳисобидан инвестиция', 1000, 'actual']],
        ],
        'export' => [
            'h1'   => [['165', 0, 'Экспорт хажми', 1000, 'actual']],
            'year' => [['167', 0, 'Экспорт хажми', 1000, 'actual']],
        ],
        'unemployment' => [
            'h1'   => [['181', 0, 'Ишсизлик даражаси', 1, 'actual']],
            'year' => [['200', 0, 'Ишсизлик даражаси', 1, 'actual']],
        ],
        'poverty' => [
            'h1'   => [['213', 0, 'Камбағаллик даражаси', 1, 'actual']],
            'year' => [['214', 0, 'Камбағаллик даражаси', 1, 'actual']],
        ],
        'jobs' => [
            'h1'   => [['182', 0, 'доимий ишга жойлаштириладиган', 1, 'actual']],
            'year' => [['201', 0, 'доимий ишга жойлаштириладиган', 1, 'actual']],
        ],
        'legalization' => [
            'h1'   => [['182', 2, 'легаллаш', 1, 'actual']],
            'year' => [['201', 2, 'легаллаш', 1, 'actual']],
        ],
        'microprojects' => [
            'h1'   => [['182', 4, 'ихтисослашув', 1000, 'actual']], // минг дона -> дона
            'year' => [['201', 4, 'ихтисослашув', 1000, 'actual']],
        ],
        'mfy_clear' => [
            'h1'   => [['215', 0, 'холи бўлган маҳаллалар', 1, 'actual']],
        ],
        // Macro KPIs: volume line + ўсиш суръати continuation line per task.
        'grp' => [
            'h1'   => [['1', 0, 'ўсиш суръати', 1, 'growth']],
            'year' => [
                ['2', 0, 'Ялпи ҳудудий маҳсулот', 1, 'actual'],
                ['2', 1, 'ўсиш суръати', 1, 'growth'],
            ],
        ],
        'industry' => [
            'h1'   => [
                ['4', 0, 'саноат маҳсулотлари ишлаб чиқари', 1, 'actual'],
                ['4', 1, 'ўсиш', 1, 'growth'],
            ],
            'year' => [
                ['7', 0, 'саноат маҳсулотлари ишлаб чиқари', 1, 'actual'],
                ['7', 1, 'ўсиш', 1, 'growth'],
            ],
        ],
        'agriculture' => [
            'h1'   => [
                ['52', 0, 'хўжалиги маҳсулотлари ҳажми', 1000, 'actual'], // трлн -> млрд
                ['52', 1, 'ўсиш', 1, 'growth'],
            ],
            'year' => [
                ['54', 0, 'хўжалиги маҳсулотлари ҳажми', 1000, 'actual'],
                ['54', 1, 'ўсиш', 1, 'growth'],
            ],
        ],
        'construction' => [
            'h1'   => [
                ['60', 0, 'Қурилиш ишлари ҳажми', 1000, 'actual'], // трлн -> млрд
                ['60', 1, 'ўсиш', 1, 'growth'],
            ],
            'year' => [
                ['64', 0, 'Қурилиш ишлари ҳажми', 1000, 'actual'],
                ['64', 1, 'ўсиш', 1, 'growth'],
            ],
        ],
        'services' => [
            'h1'   => [
                ['36', 0, 'Бозор хизматлари ҳажми', 1000, 'actual'], // трлн -> млрд
                ['36', 1, 'ўсиш', 1, 'growth'],
            ],
            'year' => [
                ['38', 0, 'Бозор хизматлари ҳажми', 1000, 'actual'],
                ['38', 1, 'ўсиш', 1, 'growth'],
            ],
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
            foreach ($periods as $factPeriod => $writes) {
                foreach ($writes as [$number, $lineNo, $needle, $scale, $field]) {
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
                            continue 2; // label drifted: refuse this write for all regions
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

                        if ($field === 'growth') {
                            // ўсиш суръати comes as a delta (8.8) — store the 108.8 ratio.
                            // The promised rate goes to its own column so plan-vs-fact
                            // survives: growth_pct now holds the reported value.
                            $fact->growth_pct = 100.0 + (float) $metric['actual'];
                            if ($metric['plan'] !== null) {
                                $fact->plan_growth_pct = 100.0 + (float) $metric['plan'];
                            }
                        } else {
                            $actual = $metric['actual'] * $scale;
                            $fact->actual_hokimyat = $actual;
                            $plan = $fact->plan_value !== null ? (float) $fact->plan_value : null;
                            if ($plan !== null && $plan != 0.0) {
                                $fact->pct_of_plan = DashboardCatalog::isLowerBetter($indicator)
                                    ? ($actual != 0.0 ? $plan / $actual * 100.0 : null)
                                    : $actual / $plan * 100.0;
                            }
                        }
                        $fact->hokimyat_reported_at = now();
                        if (! $dryRun) {
                            $fact->save();
                        }
                        $updated++;
                    }
                }
            }
        }

        return ['updated' => $updated, 'notes' => array_values(array_unique($notes))];
    }
}
