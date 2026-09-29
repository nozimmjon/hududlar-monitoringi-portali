<?php

namespace App\Services\Roadmaps;

use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use App\Support\Roadmaps\RoadmapPeriod;

/**
 * Writes one measure's indicator lines (and, when a period is given, that period's
 * progress) and recomputes the measure. Shared by import:roadmap-progress (the filled
 * template) and import:roadmap (the xlsx road-map layout, whose «Индикатор» rows carry
 * the same definitions).
 *
 * Line identity is the row position — line_no 1..n in the order given — so an inserted
 * row moves every following line's reported history onto the next indicator; the
 * `relabeled` counter exists so the caller can say that out loud.
 *
 * $lines must be the measure's COMPLETE line set: anything beyond count($lines) is
 * deleted. An empty $lines is the one exception — it leaves the stored lines alone,
 * because a file that lost its indicator rows is far more often truncated than a
 * measure that genuinely stopped being measured.
 *
 * The caller must have `lines.progress` loaded (or accept the lazy load); the relation
 * is unset before recomputing, so the fresh rows are the ones that count.
 */
final class MeasureLineSync
{
    /**
     * @param list<array{label: string, unit: ?string, plan: ?float, actual: ?float, note: ?string}> $lines
     * @return array{lines: int, removed: int, reported: int, status: string, relabeled: int, cleared: int, latest_period: ?string, blank_advance: bool}
     */
    public static function sync(RoadmapMeasure $measure, array $lines, ?string $period, int $roadmapYear): array
    {
        $was       = $measure->latest_period;      // to spot an unfilled template registering a new period
        $anyValue  = false;
        $reported  = 0;
        $relabeled = 0;
        $cleared   = 0;
        $stored    = $measure->lines->keyBy('line_no');

        foreach ($lines as $i => $l) {
            $no       = $i + 1;
            $line     = $stored->get($no);
            $progress = null;
            if ($line !== null) {
                if ($line->label !== $l['label']
                    && $line->progress->contains(fn ($p) => $p->report_period !== $period && $p->actual_value !== null)) {
                    $relabeled++;
                }
                $progress = $period === null ? null : $line->progress->firstWhere('report_period', $period);
                if ($progress !== null && $progress->actual_value !== null && $l['actual'] === null) {
                    $cleared++;
                }
            }

            $line ??= new RoadmapMeasureLine(['roadmap_measure_id' => $measure->id, 'line_no' => $no]);
            $line->fill(['label' => $l['label'], 'unit' => $l['unit'], 'plan_value' => $l['plan']])->save();

            if ($period === null) {
                continue;                                       // definitions only — «Амалда»/«Изоҳ» stay untouched
            }

            $progress ??= $line->progress()->make(['report_period' => $period]);   // a fresh line has no progress to load
            $progress->fill([
                'period_type'  => RoadmapPeriod::type($period),
                'actual_value' => $l['actual'],
                'pct_of_plan'  => MeasureRecomputer::pctOfPlan($l['plan'], $l['actual']),
                'note'         => $l['note'],
                'reported_at'  => now()->toDateString(),
            ])->save();

            if ($l['actual'] !== null) {
                $reported++;
                $anyValue = true;
            }
        }

        $removed = $lines === [] ? 0 : $measure->lines()->where('line_no', '>', count($lines))->delete();

        $measure->unsetRelation('lines');
        $values = (new MeasureRecomputer())->recompute($measure, $roadmapYear);

        return [
            'lines'         => count($lines),
            'removed'       => $removed,
            'reported'      => $reported,
            'status'        => $values['status'],
            'relabeled'     => $relabeled,
            'cleared'       => $cleared,
            'latest_period' => $values['latest_period'],
            // it had a reported period, now it has a newer, empty one
            'blank_advance' => $was !== null && ! $anyValue && $values['latest_period'] !== $was,
        ];
    }
}
