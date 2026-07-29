<?php

namespace App\Console\Commands;

use App\Models\SectorTask;
use App\Support\TaskPeriod;
use App\Support\TaskStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecomputeSectorTaskStatus extends Command
{
    protected $signature = 'sector-tasks:recompute';
    protected $description = 'Rebuild sector task statuses and headline snapshots from stored progress (no re-import).';

    public function handle(): int
    {
        $updated = 0;

        DB::transaction(function () use (&$updated) {
            foreach (SectorTask::with('progress')->get() as $task) {
                if ($task->progress->isEmpty()) continue;

                $latest = $task->progress
                    ->pluck('report_period')
                    ->unique()
                    ->sortBy(fn (string $p) => TaskPeriod::sortKey($p))
                    ->last();

                $lines = $task->progress
                    ->where('report_period', $latest)
                    ->sortBy('line_no')
                    ->map(fn ($r) => [
                        'line_no' => $r->line_no,
                        'unit'    => $r->unit,
                        'plan'    => $r->plan_value,
                        'actual'  => $r->actual_value,
                        'pct'     => $r->pct_of_plan,
                    ])
                    ->values();

                $agg  = TaskStatus::aggregate($lines);
                $head = $lines->first();
                $task->update([
                    'latest_period'   => $latest,
                    'headline_unit'   => $head['unit'] ?? null,
                    'headline_plan'   => $head['plan'] ?? null,
                    'headline_actual' => $head['actual'] ?? null,
                    'headline_pct'    => $head['pct'] ?? null,
                    'lines_total'     => $agg['total'],
                    'lines_done'      => $agg['done'],
                    'status'          => $agg['status'],
                ]);
                $updated++;
            }
        });

        $this->info("Recomputed {$updated} sector task(s).");
        return self::SUCCESS;
    }
}
