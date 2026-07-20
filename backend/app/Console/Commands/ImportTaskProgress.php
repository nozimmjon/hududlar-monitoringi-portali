<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\District;
use App\Models\ImportRun;
use App\Models\Task;
use App\Models\TaskProgress;
use App\Services\Tasks\TaskWorkbookParser;
use App\Support\TaskExecutorResolver;
use App\Support\TaskFactBridge;
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
        if ($period === '' || ! preg_match('/^\d{4}-(Q[1-4]|H[12]|\d{2})$/', $period)) {
            $this->error('Provide --period as YYYY-Q1..Q4, YYYY-H1/H2 or YYYY-MM (e.g. 2026-Q1, 2026-H1 or 2026-04).');
            return self::FAILURE;
        }
        $periodType = TaskPeriod::periodType($period);
        $year       = TaskPeriod::yearFromPeriod($period);

        if (! DB::table('reporting_years')->where('year', $year)->exists()) {
            $this->error("Reporting year {$year} is not configured (reporting_years table). Seed it before importing.");
            return self::FAILURE;
        }

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

        // Per-region coverage check — a task row must not be silently dropped for a
        // region. Report any region that is not listed for every parsed task row so
        // an operator can tell a genuine "not applicable here" from a parsing gap.
        $totalDefs = count($tasks);
        $gapsByRegion = [];
        foreach (TasksTaxonomy::REGION_BLOCKS as $code) {
            $missing = [];
            foreach ($tasks as $t) {
                if (! isset($t['regions'][$code])) {
                    $missing[] = $t['task_number'];
                }
            }
            if ($missing !== []) {
                $gapsByRegion[$code] = $missing;
            }
        }
        if ($gapsByRegion === []) {
            $this->info("Coverage: all 14 regions list every one of {$totalDefs} task rows.");
        } else {
            foreach ($gapsByRegion as $code => $missing) {
                $present = $totalDefs - count($missing);
                $this->warn("Coverage: region {$code} lists only {$present}/{$totalDefs} task rows — not listed for: " . implode(', ', $missing));
            }
        }

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
            $bridge = TaskFactBridge::apply($tasks, $year, $regionFilter, dryRun: true);
            $this->info("Dashboard facts: would enrich {$bridge['updated']} indicator fact row(s) with task actuals.");
            foreach ($bridge['notes'] as $note) {
                $this->warn('Dashboard facts: ' . $note);
            }
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

                    $task = Task::firstOrNew(['region_code' => $code, 'task_number' => $t['task_number']]);
                    $deadlineOverride = TasksTaxonomy::DEADLINE_OVERRIDES[$t['task_number']] ?? null;
                    $task->fill([
                        'title'                  => $t['title'],
                        'deadline_text'          => $deadlineOverride['deadline_text'] ?? $t['deadline_text'],
                        'period_code'            => $deadlineOverride['period_code'] ?? $t['period_code'],
                        'executor_text'          => $regionData['executor_text'],
                        'module_code'            => $t['module_code'],
                        'indicator_code'         => $t['indicator_code'],
                        'section_path'           => $t['section_path'],
                        'section_label'          => $t['section_label'],
                        'source_paragraph_index' => $t['source_row'],
                    ]);
                    // Metadata columns the economic file generation does not carry come
                    // back as null from the parser — keep whatever an earlier monitoring
                    // import recorded instead of wiping it.
                    foreach (['kind', 'cadence', 'data_source', 'report_schedule_text', 'integration_status', 'mechanism_text'] as $metaKey) {
                        if ($t[$metaKey] !== null) {
                            $task->{$metaKey} = $t[$metaKey];
                        }
                    }
                    $task->kind ??= 'kpi'; // NOT NULL column; economic rows are all numeric indicators
                    $task->save();

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

                    // Recompute the headline snapshot from line_no 0 and the binary
                    // status from ALL planned lines (weakest link — a multi-indicator
                    // task is done only when every planned line is ≥100%).
                    $head = collect($regionData['metrics'])->firstWhere('line_no', 0)
                        ?? ($regionData['metrics'][0] ?? null);
                    $agg = TaskStatus::forTask($t['task_number'], $t['title'], $regionData['metrics']);
                    // Only advance the headline snapshot if this period is not older
                    // than what the task already shows.
                    $shouldAdvance = $task->latest_period === null
                        || TaskPeriod::sortKey($period) >= TaskPeriod::sortKey($task->latest_period);
                    if ($shouldAdvance) {
                        $task->update([
                            'latest_period'   => $period,
                            'headline_unit'   => $head['unit'] ?? null,
                            'headline_plan'   => $head['plan'] ?? null,
                            'headline_actual' => $head['actual'] ?? null,
                            'headline_pct'    => $head['pct'] ?? null,
                            'lines_total'     => $agg['total'],
                            'lines_done'      => $agg['done'],
                            'status'          => $agg['status'],
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

        // Push reported actuals into the dashboard's indicator_facts so module
        // pages show Амалда instead of the stale Кутилиш forecast.
        $bridge = TaskFactBridge::apply($tasks, $year, $regionFilter);
        $this->info("Dashboard facts: enriched {$bridge['updated']} indicator fact row(s) with task actuals.");
        foreach ($bridge['notes'] as $note) {
            $this->warn('Dashboard facts: ' . $note);
        }

        $total = array_sum($writtenByRegion);
        $this->info("Wrote {$total} progress rows across " . count($runByRegion) . ' region(s).');
        if (! empty($unmatched)) {
            $this->warn('Unmatched executor tokens: ' . implode(' | ', array_unique($unmatched)));
        }

        return self::SUCCESS;
    }
}
