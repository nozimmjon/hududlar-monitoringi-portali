<?php

namespace App\Console\Commands;

use App\Models\Roadmap;
use App\Services\Roadmaps\MeasureRecomputer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Rebuilds every road-map measure's status/pct/counters from roadmap_line_progress — no re-import. Idempotent. */
class RecomputeRoadmapMeasures extends Command
{
    protected $signature = 'roadmaps:recompute
        {--region= : Limit to one region SOATO code}
        {--dry-run : Report without writing}';

    protected $description = 'Recompute road-map measure status, percent and line counters from the stored progress rows.';

    public function handle(): int
    {
        $query = Roadmap::query()->with(['measures.lines.progress']);
        if ($this->option('region') !== null) {
            $query->where('region_code', (int) $this->option('region'));
        }
        $roadmaps = $query->get();
        if ($roadmaps->isEmpty()) {
            $this->error('No road map matches — nothing to recompute.');

            return self::FAILURE;
        }

        $recomputer = new MeasureRecomputer();
        $flips      = [];
        $counts     = ['done' => 0, 'in_progress' => 0, 'open' => 0];

        DB::beginTransaction();
        try {
            foreach ($roadmaps as $roadmap) {
                foreach ($roadmap->measures as $measure) {
                    $before = $measure->status;
                    $values = $recomputer->recompute($measure, $roadmap->year);
                    if ($before !== $values['status']) {
                        $k = "{$before}→{$values['status']}";
                        $flips[$k] = ($flips[$k] ?? 0) + 1;
                    }
                    $counts[$values['status']] = ($counts[$values['status']] ?? 0) + 1;
                }
            }
            $this->option('dry-run') ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $flipText = $flips === [] ? 'no status flips' : implode(', ', array_map(fn ($k, $v) => "{$v} {$k}", array_keys($flips), $flips));
        $this->info(sprintf('%d road map(s): done: %d, in_progress: %d, open: %d — %s.',
            $roadmaps->count(), $counts['done'], $counts['in_progress'], $counts['open'], $flipText));
        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }
}
