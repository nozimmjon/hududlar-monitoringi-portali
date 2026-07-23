<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Support\TaskStatus;
use App\Support\TasksTaxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds every task's status and planned-line counters from the progress rows
 * already in task_progress — no re-import needed. Weakest-link rule: a task with
 * several planned indicator lines is done only when ALL of them are ≥100%.
 *
 * With --pct it also recomputes each line's pct_of_plan from plan+actual (needed
 * after a manual actual edit, since pct is a stored column set at import). That is
 * OFF by default and best scoped with --task/--region, because it overwrites the
 * workbook's own percentages with a plain plan/actual ratio.
 *
 * Safe to re-run any time (idempotent).
 */
class RecomputeTaskStatus extends Command
{
    protected $signature = 'tasks:recompute
        {--pct : Also recompute each line pct_of_plan and the headline from plan+actual}
        {--task= : Limit to this task_number (with --pct)}
        {--region= : Limit to this region SOATO code (with --pct)}
        {--dry-run : Report changes without writing}';

    protected $description = 'Recompute task status/line counters (and optionally line percentages) from task_progress.';

    public function handle(): int
    {
        $withPct = (bool) $this->option('pct');
        $taskNumber = $this->option('task');
        $regionCode = $this->option('region');

        $flipped = [];
        $updated = 0;
        $pctRows = 0;

        $query = Task::with('progress');
        if ($withPct) {
            if ($taskNumber !== null) $query->where('task_number', (string) $taskNumber);
            if ($regionCode !== null) $query->where('region_code', (int) $regionCode);
        }

        DB::beginTransaction();
        try {
            $query->chunkById(200, function ($tasks) use (&$flipped, &$updated, &$pctRows, $withPct) {
                foreach ($tasks as $task) {
                    $period = $task->latest_period;
                    $lines = $period === null
                        ? collect()
                        : $task->progress->where('report_period', $period);

                    if ($withPct) {
                        $pctRows += $this->recomputeLinePercents($task, $lines);
                        $this->refreshHeadline($task, $lines);
                    }

                    $agg = TaskStatus::forTask($task->task_number, $task->title, $lines->map(
                        fn ($l) => ['plan' => $l->plan_value, 'actual' => $l->actual_value, 'pct' => $l->pct_of_plan]
                    ));

                    $dirty = $task->status !== $agg['status']
                        || (int) $task->lines_total !== $agg['total']
                        || (int) $task->lines_done !== $agg['done'];

                    if ($task->status !== $agg['status']) {
                        $key = $task->status . '→' . $agg['status'];
                        $flipped[$key] = ($flipped[$key] ?? 0) + 1;
                    }

                    if ($dirty) {
                        $task->status = $agg['status'];
                        $task->lines_total = $agg['total'];
                        $task->lines_done = $agg['done'];
                        $updated++;
                    }

                    if ($task->isDirty()) {
                        $task->save();
                    }
                }
            });

            if ($this->option('dry-run')) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $flips = $flipped === []
            ? 'no status flips'
            : implode(', ', array_map(fn ($k, $v) => "{$v} {$k}", array_keys($flipped), $flipped));
        $this->info("Updated {$updated} task(s): {$flips}.");
        if ($withPct) {
            $this->info("Recomputed {$pctRows} line percentage(s) from plan+actual.");
        }
        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }

    /**
     * pct_of_plan = actual/plan (или plan/actual for lower-is-better tasks), where
     * both are present. Returns how many rows changed. Mirrors the import formula.
     */
    private function recomputeLinePercents(Task $task, $lines): int
    {
        $lower = in_array($task->task_number, TasksTaxonomy::LOWER_IS_BETTER_TASKS, true);
        $changed = 0;

        foreach ($lines as $line) {
            $plan = $line->plan_value !== null ? (float) $line->plan_value : null;
            $actual = $line->actual_value !== null ? (float) $line->actual_value : null;

            if ($plan === null || $actual === null) {
                $pct = null; // nothing to compare
            } elseif ($lower) {
                $pct = $actual != 0.0 ? round($plan / $actual * 100, 4) : null;
            } else {
                $pct = $plan != 0.0 ? round($actual / $plan * 100, 4) : null;
            }

            $current = $line->pct_of_plan !== null ? round((float) $line->pct_of_plan, 4) : null;
            if ($current !== $pct) {
                $line->pct_of_plan = $pct;
                $line->save();
                $changed++;
            }
        }

        return $changed;
    }

    /** Refresh the task's line-0 headline snapshot from the (possibly edited) rows. */
    private function refreshHeadline(Task $task, $lines): void
    {
        $head = $lines->firstWhere('line_no', 0) ?? $lines->first();
        if ($head === null) {
            return;
        }

        $task->headline_unit = $head->unit;
        $task->headline_plan = $head->plan_value;
        $task->headline_actual = $head->actual_value;
        $task->headline_pct = $head->pct_of_plan;
    }
}
