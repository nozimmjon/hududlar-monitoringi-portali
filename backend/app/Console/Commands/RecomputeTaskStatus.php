<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Support\TaskStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds every task's status and planned-line counters from the progress rows
 * already in task_progress — no re-import needed. Weakest-link rule: a task with
 * several planned indicator lines is done only when ALL of them are ≥100%.
 *
 * Run once after deploying the multi-indicator status change; safe to re-run
 * any time (idempotent).
 */
class RecomputeTaskStatus extends Command
{
    protected $signature = 'tasks:recompute {--dry-run : Report changes without writing}';

    protected $description = 'Recompute task status and line counters (weakest link over all planned lines) from task_progress.';

    public function handle(): int
    {
        $flipped = [];
        $updated = 0;

        DB::beginTransaction();
        try {
            Task::with('progress')->chunkById(200, function ($tasks) use (&$flipped, &$updated) {
                foreach ($tasks as $task) {
                    $lines = $task->latest_period === null
                        ? collect()
                        : $task->progress->where('report_period', $task->latest_period);

                    $agg = TaskStatus::forTask($task->task_number, $task->title, $lines->map(
                        fn ($l) => ['plan' => $l->plan_value, 'actual' => $l->actual_value, 'pct' => $l->pct_of_plan]
                    ));

                    $dirty = $task->status !== $agg['status']
                        || (int) $task->lines_total !== $agg['total']
                        || (int) $task->lines_done !== $agg['done'];
                    if (! $dirty) {
                        continue;
                    }

                    if ($task->status !== $agg['status']) {
                        $key = $task->status . '→' . $agg['status'];
                        $flipped[$key] = ($flipped[$key] ?? 0) + 1;
                    }

                    $task->update([
                        'status'      => $agg['status'],
                        'lines_total' => $agg['total'],
                        'lines_done'  => $agg['done'],
                    ]);
                    $updated++;
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
        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }
}
