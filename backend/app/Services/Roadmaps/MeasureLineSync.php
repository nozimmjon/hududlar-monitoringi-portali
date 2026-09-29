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
 *
 * $write = false computes every counter from the loaded relations without saving,
 * deleting or recomputing anything — the dry run previews the same damage report. With
 * $write = false the passed measure and its lines are left dirty in memory — re-read them
 * before a real sync.
 */
final class MeasureLineSync
{
    /**
     * @param list<array{label: string, unit: ?string, plan: ?float, actual: ?float, note: ?string}> $lines
     * @return array{lines: int, removed: int, reported: int, status: string, relabeled: int, cleared: int, repct: int, latest_period: ?string, blank_advance: bool}
     */
    public static function sync(RoadmapMeasure $measure, array $lines, ?string $period, int $roadmapYear, bool $write = true): array
    {
        $was       = $measure->latest_period;      // to spot an unfilled template registering a new period
        $anyValue  = false;
        $reported  = 0;
        $relabeled = 0;
        $cleared   = 0;
        $repct     = 0;
        $stored    = $measure->lines->keyBy('line_no');

        foreach ($lines as $i => $l) {
            $no       = $i + 1;
            $line     = $stored->get($no);
            $progress = null;
            $oldPlan  = $line?->plan_value;
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

            $existing = $line;
            $line ??= new RoadmapMeasureLine(['roadmap_measure_id' => $measure->id, 'line_no' => $no]);
            $line->fill(['label' => $l['label'], 'unit' => $l['unit'], 'plan_value' => $l['plan']]);
            if ($write) {
                $line->save();
            }

            // A corrected plan makes every percentage already reported against the old one a lie;
            // the period being written below is rewritten from the new plan anyway, so skip it.
            if ($existing !== null && self::differs($oldPlan, $l['plan'])) {
                foreach ($existing->progress as $p) {
                    if ($period !== null && $p->report_period === $period) {
                        continue;
                    }
                    $pct = MeasureRecomputer::pctOfPlan($l['plan'], $p->actual_value);
                    if (! self::differs($p->pct_of_plan, $pct)) {
                        continue;
                    }
                    $repct++;
                    if ($write) {
                        $p->fill(['pct_of_plan' => $pct])->save();
                    }
                }
            }

            if ($period === null) {
                continue;                                       // definitions only — «Амалда»/«Изоҳ» stay untouched
            }
            if ($l['actual'] !== null) {
                $reported++;
                $anyValue = true;
            }
            if (! $write) {
                continue;
            }

            $progress ??= $line->progress()->make(['report_period' => $period]);   // a fresh line has no progress to load
            $progress->fill([
                'period_type'  => RoadmapPeriod::type($period),
                'actual_value' => $l['actual'],
                'pct_of_plan'  => MeasureRecomputer::pctOfPlan($l['plan'], $l['actual']),
                'note'         => $l['note'],
                'reported_at'  => now()->toDateString(),
            ])->save();
        }

        $beyond  = $measure->lines()->where('line_no', '>', count($lines));
        $removed = $lines === [] ? 0 : ($write ? $beyond->delete() : $beyond->count());

        if ($write) {
            $measure->unsetRelation('lines');
            $values = (new MeasureRecomputer())->recompute($measure, $roadmapYear);
            $latest = $values['latest_period'];
            $status = $values['status'];
        } else {
            $latest = RoadmapPeriod::latest(self::previewPeriods($measure, $lines, $period));
            $status = $measure->status;
        }

        return [
            'lines'         => count($lines),
            'removed'       => $removed,
            'reported'      => $reported,
            'status'        => $status,
            'relabeled'     => $relabeled,
            'cleared'       => $cleared,
            'repct'         => $repct,
            'latest_period' => $latest,
            // it had a reported period, now it has a newer, empty one
            'blank_advance' => $was !== null && ! $anyValue && $latest !== $was,
        ];
    }

    /**
     * The report periods the measure would carry after this sync: what the surviving lines
     * already hold, plus the period being written. Only the dry run needs it — a real sync
     * reads them back from the rows it just wrote.
     *
     * @param  list<array<string,mixed>> $lines
     * @return list<string>
     */
    private static function previewPeriods(RoadmapMeasure $measure, array $lines, ?string $period): array
    {
        $periods = [];
        foreach ($measure->lines as $line) {
            if ($lines !== [] && $line->line_no > count($lines)) {
                continue;                                       // this one would be deleted
            }
            foreach ($line->progress as $p) {
                $periods[] = $p->report_period;
            }
        }
        if ($period !== null && $lines !== []) {
            $periods[] = $period;
        }

        return $periods;
    }

    /** Null-aware numeric comparison: the decimal casts hand back strings like «10.000000». */
    public static function differs(null|string|int|float $a, null|string|int|float $b): bool
    {
        if ($a === null || $b === null) {
            return $a !== $b;
        }

        return (float) $a !== (float) $b;
    }
}
