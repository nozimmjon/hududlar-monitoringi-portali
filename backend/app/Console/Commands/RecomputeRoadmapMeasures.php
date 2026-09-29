<?php

namespace App\Console\Commands;

use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Services\Roadmaps\MeasureLineSync;
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
        $region = $this->option('region');
        if ($region !== null && ! ctype_digit((string) $region)) {
            $this->error('--region must be a SOATO code, e.g. 1733.');

            return self::FAILURE;
        }

        $query = Roadmap::query();
        if ($region !== null) {
            $query->where('region_code', (int) $region);
        }
        $ids = $query->orderBy('region_code')->pluck('id');
        if ($ids->isEmpty()) {
            $this->error('No road map matches — nothing to recompute.');

            return self::FAILURE;
        }

        $recomputer  = new MeasureRecomputer();
        $flips       = [];
        $counts      = ['done' => 0, 'in_progress' => 0, 'open' => 0];
        $regionCodes = [];
        $measures    = 0;
        $updated     = 0;
        $repct       = 0;

        DB::beginTransaction();
        try {
            foreach ($ids as $id) {
                $roadmap       = Roadmap::with(['measures.lines.progress'])->findOrFail($id);
                $regionCodes[] = $roadmap->region_code;
                foreach ($roadmap->measures as $measure) {
                    $before = $measure->status;
                    $repct += self::repairPercentages($measure);
                    $values = $recomputer->recompute($measure, $roadmap->year);
                    $measures++;
                    if ($measure->wasChanged()) {
                        $updated++;
                    }
                    if ($before !== $values['status']) {
                        $k = "{$before}→{$values['status']}";
                        $flips[$k] = ($flips[$k] ?? 0) + 1;
                    }
                    $counts[$values['status']]++;
                }
            }
            $this->option('dry-run') ? DB::rollBack() : DB::commit();
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            $this->error($e->getMessage() . ' — nothing written.');

            return self::FAILURE;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $flipText = $flips === [] ? 'no status flips' : implode(', ', array_map(fn ($k, $v) => "{$v} {$k}", array_keys($flips), $flips));
        $this->info(sprintf('%d road map(s) [%s], %d measure(s) — %d updated; done: %d, in_progress: %d, open: %d — %s.',
            count($ids), implode(', ', $regionCodes), $measures, $updated, $counts['done'], $counts['in_progress'], $counts['open'], $flipText));
        if ($repct > 0) {
            $this->warn("{$repct} reported percentage(s) recomputed from the stored plan and «Амалда».");
        }
        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }

    /**
     * pct_of_plan is written once, when the «Амалда» value is imported, so a plan edited
     * afterwards — by hand, or by an import that only redefined the lines — leaves stale
     * percentages behind. This is the universal repair: derive every row again from the
     * plan and actual it stores now. Rows already correct are left untouched, so a second
     * run rewrites nothing.
     */
    private static function repairPercentages(RoadmapMeasure $measure): int
    {
        $fixed = 0;
        foreach ($measure->lines as $line) {
            foreach ($line->progress as $progress) {
                $pct = MeasureRecomputer::pctOfPlan($line->plan_value, $progress->actual_value);
                if (! MeasureLineSync::differs($progress->pct_of_plan, $pct)) {
                    continue;
                }
                $progress->fill(['pct_of_plan' => $pct])->save();
                $fixed++;
            }
        }

        return $fixed;
    }
}
