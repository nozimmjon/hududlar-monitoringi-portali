<?php

namespace App\Services\Roadmaps;

use App\Models\RoadmapMeasure;
use App\Support\Roadmaps\RoadmapDeadline;
use App\Support\Roadmaps\RoadmapPeriod;
use App\Support\TaskStatus;

/**
 * The single place that turns indicator lines + one period's progress into the
 * measure's denormalised columns (latest_period, status, pct, lines_total, lines_done).
 * Used by import:roadmap-progress and roadmaps:recompute; the page only reads.
 */
final class MeasureRecomputer
{
    /** Largest value the numeric(10,4) pct_of_plan column can hold — a typo like 20 000 against a plan of 0.01 must not abort the import. */
    public const PCT_MAX = 999999.9999;

    /** actual / plan × 100, 4 decimals, clamped to ±PCT_MAX; null when either side is missing or the plan is 0. */
    public static function pctOfPlan(null|float|int|string $plan, null|float|int|string $actual): ?float
    {
        if ($plan === null || $actual === null || (float) $plan == 0.0) {
            return null;
        }

        return max(-self::PCT_MAX, min(self::PCT_MAX, round((float) $actual / (float) $plan * 100, 4)));
    }

    /**
     * Pure rules over ONE period's lines. Each line contributes min(100, max(0, pct ?? 0))
     * to the mean.
     *
     * @param  list<array{plan: float|int|string|null, actual: float|int|string|null, pct: float|int|string|null}> $lines
     * @return array{reported: bool, lines_total: int, lines_done: int, pct: ?float, status: string}
     */
    public static function aggregate(array $lines, bool $deadlineReached): array
    {
        $agg      = TaskStatus::aggregate($lines);          // weakest link: in_progress / done / open
        $reported = false;
        $planned  = [];
        foreach ($lines as $l) {
            if ($l['actual'] !== null && (float) $l['actual'] != 0.0) {
                $reported = true;
            }
            if ($l['plan'] !== null) {
                $planned[] = $l;
            }
        }

        $pct = null;
        if ($reported && $planned !== []) {
            $sum = 0.0;
            foreach ($planned as $l) {
                $sum += max(0.0, min(100.0, $l['pct'] !== null ? (float) $l['pct'] : 0.0));
            }
            $pct = round($sum / count($planned), 2);
        }

        $status = $agg['status'];
        if ($agg['total'] === 0) {
            $status = 'in_progress';                          // nothing planned yet → nothing can be behind or done
        } elseif ($status === 'open' && ! $deadlineReached) {
            $status = 'in_progress';                          // running behind before the deadline = still in progress
        }

        return [
            'reported'    => $reported,
            'lines_total' => $agg['total'],
            'lines_done'  => $agg['done'],
            'pct'         => $pct,
            'status'      => $status,
        ];
    }

    /**
     * Recompute and save one measure (loads lines.progress when not already loaded).
     *
     * Uses the loaded `lines.progress` relations (`loadMissing` only fills them when
     * absent): a caller that has just written lines or progress MUST
     * `$measure->unsetRelation('lines')` first, and batch callers should eager-load
     * `measures.lines.progress` — otherwise this costs two queries per measure. A line
     * with no progress row for the latest period counts as unreported (0 %) for that
     * period.
     *
     * @return array{latest_period: ?string, status: string, pct: ?float, lines_total: int, lines_done: int}
     */
    public function recompute(RoadmapMeasure $measure, int $roadmapYear): array
    {
        $measure->loadMissing('lines.progress');

        $periods = [];
        foreach ($measure->lines as $line) {
            foreach ($line->progress as $p) {
                $periods[] = $p->report_period;
            }
        }

        try {
            $latest = RoadmapPeriod::latest($periods);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException("Measure #{$measure->id}: {$e->getMessage()}", 0, $e);
        }

        $rows = [];
        foreach ($measure->lines as $line) {
            $p      = $line->progress->firstWhere('report_period', $latest);   // no row for the latest period → unreported for it (counts as 0 in the mean)
            $rows[] = ['plan' => $line->plan_value, 'actual' => $p?->actual_value, 'pct' => $p?->pct_of_plan];
        }

        $agg    = self::aggregate($rows, $latest !== null && RoadmapDeadline::reached($latest, $measure->deadline_text, $roadmapYear));
        $values = [
            'latest_period' => $latest,
            'status'        => $agg['status'],
            'pct'           => $agg['pct'],
            'lines_total'   => $agg['lines_total'],
            'lines_done'    => $agg['lines_done'],
        ];

        $measure->fill($values);
        if ($measure->isDirty()) {
            $measure->save();
        }

        return $values;
    }
}
