<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\District;
use App\Models\ImportRun;
use App\Models\Task;
use App\Models\TaskProgress;
use App\Services\Tasks\TaskWorkbookParser;
use App\Support\TaskExecutorResolver;
use App\Support\TaskPeriod;
use App\Support\TaskStatus;
use App\Support\TasksTaxonomy;
use Database\Seeders\SoatoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTaskProgress extends Command
{
    protected $signature = 'import:task-progress
        {--file= : Path to the XLSX (defaults to data/tasks/Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx)}
        {--period= : Report period this file represents, e.g. 2026-Q1 or 2026-04}
        {--region=all : Region slug/code to import, or "all"}
        {--dry-run : Parse and report without writing}';

    protected $description = 'Import monthly/quarterly task plan+actual+% from the all-regions monitoring XLSX.';

    public function handle(): int
    {
        $period = (string) $this->option('period');
        if ($period === '' || ! preg_match('/^\d{4}-(Q[1-4]|\d{2})$/', $period)) {
            $this->error('Provide --period as YYYY-Q1..Q4 or YYYY-MM (e.g. 2026-Q1 or 2026-04).');
            return self::FAILURE;
        }
        $periodType = TaskPeriod::periodType($period);
        $year       = TaskPeriod::yearFromPeriod($period);

        $file = $this->option('file')
            ?: base_path('../data/tasks/Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx');
        if (! is_file($file)) {
            $this->error("Source workbook not found: {$file}");
            return self::FAILURE;
        }

        // Optional region filter -> SOATO code.
        $regionFilter = null;
        $regionOpt = (string) $this->option('region');
        if ($regionOpt !== '' && $regionOpt !== 'all') {
            $regionFilter = ctype_digit($regionOpt)
                ? (int) $regionOpt
                : array_search($regionOpt, SoatoSeeder::REGION_LATIN, true);
            if ($regionFilter === false || ! in_array($regionFilter, TasksTaxonomy::REGION_BLOCKS, true)) {
                $this->error("Unknown region: {$regionOpt}");
                return self::FAILURE;
            }
        }

        $this->info("Parsing {$file} for period {$period}…");
        $tasks = (new TaskWorkbookParser())->parse($file);
        $this->info('Parsed ' . count($tasks) . ' task definitions.');

        if ($this->option('dry-run')) {
            $regionCodes = [];
            $lineCount = 0;
            foreach ($tasks as $t) {
                foreach ($t['regions'] as $code => $regionData) {
                    $regionCodes[$code] = true;
                    $lineCount += count($regionData['metrics']);
                }
            }
            $this->info('Coverage: ' . count($regionCodes) . ' region(s), ' . $lineCount . ' metric lines.');
            $this->warn('Dry run — no changes written.');
            return self::SUCCESS;
        }

        // Districts per region for executor resolution.
        $districtsByRegion = District::all()->groupBy('region_code');
        $unmatched = [];
        $runByRegion = [];
        $writtenByRegion = [];

        DB::transaction(function () use (
            $tasks, $period, $periodType, $year, $regionFilter,
            $districtsByRegion, &$unmatched, &$runByRegion, &$writtenByRegion
        ) {
            foreach ($tasks as $t) {
                foreach ($t['regions'] as $code => $regionData) {
                    if ($regionFilter !== null && $code !== $regionFilter) continue;

                    $run = $runByRegion[$code] ??= ImportRun::create([
                        'region_code'  => $code,
                        'year'         => $year,
                        'trigger_kind' => 'cli',
                        'status'       => ImportRunStatus::Promoting,
                        'started_at'   => now(),
                    ]);

                    $task = Task::updateOrCreate(
                        ['region_code' => $code, 'task_number' => $t['task_number']],
                        [
                            'title'                  => $t['title'],
                            'deadline_text'          => $t['deadline_text'],
                            'period_code'            => $t['period_code'],
                            'executor_text'          => $regionData['executor_text'],
                            'kind'                   => $t['kind'],
                            'cadence'                => $t['cadence'],
                            'data_source'            => $t['data_source'],
                            'report_schedule_text'   => $t['report_schedule_text'],
                            'integration_status'     => $t['integration_status'],
                            'mechanism_text'         => $t['mechanism_text'],
                            'module_code'            => $t['module_code'],
                            'indicator_code'         => $t['indicator_code'],
                            'section_path'           => $t['section_path'],
                            'section_label'          => $t['section_label'],
                            'source_paragraph_index' => $t['source_row'],
                        ]
                    );

                    // Re-sync districts from this file's executor list.
                    $ids = TaskExecutorResolver::districtIds(
                        $regionData['executor_text'],
                        $districtsByRegion->get($code, collect()),
                        $unmatched
                    );
                    $task->districts()->sync($ids);

                    // Replace this period's progress rows (idempotent), then insert.
                    $task->progress()->where('report_period', $period)->delete();
                    foreach ($regionData['metrics'] as $m) {
                        TaskProgress::create([
                            'task_id'       => $task->id,
                            'line_no'       => $m['line_no'],
                            'metric_label'  => $m['metric_label'],
                            'unit'          => $m['unit'],
                            'report_period' => $period,
                            'period_type'   => $periodType,
                            'plan_value'    => $m['plan'],
                            'actual_value'  => $m['actual'],
                            'pct_of_plan'   => $m['pct'],
                            'import_run_id' => $run->id,
                        ]);
                        $writtenByRegion[$code] = ($writtenByRegion[$code] ?? 0) + 1;
                    }

                    // Recompute headline snapshot + binary status from line_no 0.
                    $head = collect($regionData['metrics'])->firstWhere('line_no', 0)
                        ?? ($regionData['metrics'][0] ?? null);
                    // Only advance the headline snapshot if this period is not older
                    // than what the task already shows.
                    $shouldAdvance = $task->latest_period === null
                        || $this->periodSortKey($period) >= $this->periodSortKey($task->latest_period);
                    if ($shouldAdvance) {
                        $task->update([
                            'latest_period'   => $period,
                            'headline_unit'   => $head['unit'] ?? null,
                            'headline_plan'   => $head['plan'] ?? null,
                            'headline_actual' => $head['actual'] ?? null,
                            'headline_pct'    => $head['pct'] ?? null,
                            'status'          => TaskStatus::statusFor(isset($head['pct']) ? (float) $head['pct'] : null),
                        ]);
                    }
                }
            }

            foreach ($runByRegion as $code => $run) {
                $run->update([
                    'status'          => ImportRunStatus::Promoted,
                    'promoted_at'     => now(),
                    'files_processed' => 1,
                    'rows_promoted'   => $writtenByRegion[$run->region_code] ?? 0,
                ]);
            }
        });

        $total = array_sum($writtenByRegion);
        $this->info("Wrote {$total} progress rows across " . count($runByRegion) . ' region(s).');
        if (! empty($unmatched)) {
            $this->warn('Unmatched executor tokens: ' . implode(' | ', array_unique($unmatched)));
        }

        return self::SUCCESS;
    }

    /** Sortable key: quarters map to their closing month (Q1->03, ..., Q4->12). */
    private function periodSortKey(string $period): string
    {
        if (preg_match('/^(\d{4})-Q([1-4])$/', $period, $m)) {
            return $m[1] . '-' . str_pad((string) ((int) $m[2] * 3), 2, '0', STR_PAD_LEFT);
        }
        return $period;
    }
}
