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

    /** actual / plan × 100, 4 decimals, clamped to PCT_MAX; null when either side is missing or the plan is 0. */
    public static function pctOfPlan(null|float|int|string $plan, null|float|int|string $actual): ?float
    {
        if ($plan === null || $actual === null || (float) $plan == 0.0) {
            return null;
        }

        return min(self::PCT_MAX, round((float) $actual / (float) $plan * 100, 4));
    }

    /**
     * Pure rules over ONE period's lines.
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
                $sum += min(100.0, $l['pct'] !== null ? (float) $l['pct'] : 0.0);
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

    /** Recompute and save one measure (loads lines.progress when not already loaded). */
    public function recompute(RoadmapMeasure $measure, int $roadmapYear): void
    {
        $measure->loadMissing('lines.progress');

        $periods = [];
        foreach ($measure->lines as $line) {
            foreach ($line->progress as $p) {
                $periods[] = $p->report_period;
            }
        }
        $latest = RoadmapPeriod::latest($periods);

        if ($latest === null) {
            $values = [
                'latest_period' => null,
                'status'        => 'in_progress',
                'pct'           => null,
                'lines_total'   => $measure->lines->whereNotNull('plan_value')->count(),
                'lines_done'    => 0,
            ];
        } else {
            $rows = [];
            foreach ($measure->lines as $line) {
                $p      = $line->progress->firstWhere('report_period', $latest);
                $rows[] = ['plan' => $line->plan_value, 'actual' => $p?->actual_value, 'pct' => $p?->pct_of_plan];
            }
            $agg    = self::aggregate($rows, RoadmapDeadline::reached($latest, $measure->deadline_text, $roadmapYear));
            $values = [
                'latest_period' => $latest,
                'status'        => $agg['status'],
                'pct'           => $agg['pct'],
                'lines_total'   => $agg['lines_total'],
                'lines_done'    => $agg['lines_done'],
            ];
        }

        $measure->fill($values);
        if ($measure->isDirty()) {
            $measure->save();
        }
    }
}
