<?php

namespace App\Support;

use App\Models\Sector;
use App\Models\SectorTask;

/**
 * Shared display logic for the sector pages (/sectors dashboard, drawer,
 * /sectors/{code} detail): tier colors, 99-cap percents, number formatting,
 * deadline buckets, and the detail dataset used by both drawer and page.
 */
class SectorDisplay
{
    /** Deadline bucket labels, keyed by sector_task_progress.deadline_code. */
    public const DEADLINE_LABELS = [
        'q3'   => 'III чорак',
        'q4'   => 'IV чорак',
        'h2'   => '2-ярим йиллик',
        'year' => 'Йил якуни',
    ];

    /** Bucket sort order, earliest deadline first. */
    public const DEADLINE_ORDER = ['q3' => 1, 'q4' => 2, 'h2' => 3, 'year' => 4];

    /** Traffic-light tier for a percent value; null percent = nothing reported. */
    public static function tier(?float $pct): string
    {
        if ($pct === null) {
            return 'wait';
        }
        if ($pct >= 100) {
            return 'ok';
        }

        return $pct >= 50 ? 'warn' : 'bad';
    }

    /** Display percent under the 99-cap rule: only a fully done entity shows ≥ 100. */
    public static function pshow(?float $pct, bool $done): ?int
    {
        if ($pct === null) {
            return null;
        }

        return $done ? (int) round($pct) : min(99, (int) round($pct));
    }

    /** Format a value: space thousands, comma decimals, trailing zeros trimmed. */
    public static function fmt(null|float|string $v): string
    {
        if ($v === null) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
    }

    /** Task-level completion percent (null while nothing is reported). */
    public static function taskPct(SectorTask $task): ?float
    {
        if ($task->status === 'in_progress') {
            return null;
        }
        if ((int) $task->lines_total > 1) {
            return $task->lines_total > 0 ? $task->lines_done / $task->lines_total * 100 : null;
        }

        return $task->headline_pct !== null ? (float) $task->headline_pct : null;
    }

    /**
     * Detail dataset shared by the dashboard drawer and the /sectors/{code} page.
     *
     * @return array{sector: Sector, tasks: \Illuminate\Support\Collection, counts: array, agg: array}
     */
    public static function detailData(Sector $sector, string $filter): array
    {
        $tasks = SectorTask::where('sector_id', $sector->id)
            ->orderBy('task_no')
            ->with('progress')
            ->get();

        $counts = [
            'all'         => $tasks->count(),
            'done'        => $tasks->where('status', 'done')->count(),
            'open'        => $tasks->where('status', 'open')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
        ];

        $linesTotal = (int) $tasks->sum('lines_total');
        $linesDone  = (int) $tasks->sum('lines_done');
        $hasReport  = $tasks->contains(fn (SectorTask $t) => $t->status !== 'in_progress');

        return [
            'sector' => $sector,
            'tasks'  => $filter === 'all' ? $tasks : $tasks->where('status', $filter)->values(),
            'counts' => $counts,
            'agg'    => [
                'lines_total' => $linesTotal,
                'lines_done'  => $linesDone,
                'pct'         => $hasReport && $linesTotal > 0 ? $linesDone / $linesTotal * 100 : null,
            ],
        ];
    }
}
