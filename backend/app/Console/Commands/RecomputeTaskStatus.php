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
        $flipped = ['done→open' => 0, 'open→done' => 0];
        $updated = 0;

        DB::beginTransaction();
        try {
            Task::with('progress')->chunkById(200, function ($tasks) use (&$flipped, &$updated) {
                foreach ($tasks as $task) {
                    $lines = $task->latest_period === null
                        ? collect()
                        : $task->progress->where('report_period', $task->latest_period);

                    $agg = TaskStatus::aggregate($lines->map(
                        fn ($l) => ['plan' => $l->plan_value, 'pct' => $l->pct_of_plan]
                    ));

                    $dirty = $task->status !== $agg['status']
                        || (int) $task->lines_total !== $agg['total']
                        || (int) $task->lines_done !== $agg['done'];
                    if (! $dirty) {
                        continue;
                    }

                    if ($task->status === 'done' && $agg['status'] === 'open') $flipped['done→open']++;
                    if ($task->status === 'open' && $agg['status'] === 'done') $flipped['open→done']++;

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

        $this->info("Updated {$updated} task(s): {$flipped['done→open']} flipped done→open, {$flipped['open→done']} flipped open→done.");
        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }
}
