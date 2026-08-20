<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Task;
use App\Models\TaskProgress;
use App\Services\Tasks\TaskWorkbookParser;
use App\Support\TaskExecutorResolver;
use App\Support\TaskPeriod;
use App\Support\TaskStatus;
use App\Support\TasksTaxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Plan-only sync from an updated all-regions template (e.g. the H2 «Шаблон» file).
 *
 * Contract, per the operator's instruction:
 *  - existing tasks: update plan values that changed; NEVER touch actual values;
 *    pct/status/headline are recomputed only where a plan changed;
 *  - new tasks / new indicator lines: inserted as plan-only rows (no actuals →
 *    a new task starts as Бажарилмоқда);
 *  - a file that carries ANY actual value is refused — this command is for
 *    plan templates only (use import:task-progress for progress files).
 */
class ImportPlanSync extends Command
{
    protected $signature = 'import:plan-sync
        {--file= : Path to the template XLSX}
        {--period= : Report period for NEW tasks/lines, e.g. 2026-H1}
        {--dry-run : Analyze and report without writing}';

    protected $description = 'Sync plan values from an updated tasks template; insert new tasks (plans only, actuals untouched).';

    /** Relative tolerance for "the plan did not change". */
    private const EPS = 1e-6;

    public function handle(): int
    {
        $period = (string) $this->option('period');
        if (! preg_match('/^\d{4}-(Q[1-4]|H[12]|\d{2})$/', $period)) {
            $this->error('Provide --period as YYYY-Q1..Q4, YYYY-H1/H2 or YYYY-MM (used for NEW tasks/lines).');

            return self::FAILURE;
        }
        $year = TaskPeriod::yearFromPeriod($period);
        if (! DB::table('reporting_years')->where('year', $year)->exists()) {
            $this->error("Reporting year {$year} is not configured (reporting_years table).");

            return self::FAILURE;
        }

        $file = (string) $this->option('file');
        if ($file === '' || ! is_file($file)) {
            $this->error("Source workbook not found: {$file}");

            return self::FAILURE;
        }

        $this->info("Parsing {$file}…");
        $parsed = (new TaskWorkbookParser())->parse($file);
        $this->info('Parsed ' . count($parsed) . ' task definitions.');

        // Plans-only contract: refuse a file that carries actuals.
        $actuals = 0;
        foreach ($parsed as $t) {
            foreach ($t['regions'] as $r) {
                foreach ($r['metrics'] as $m) {
                    if ($m['actual'] !== null) {
                        $actuals++;
                    }
                }
            }
        }
        if ($actuals > 0) {
            $this->error("The file carries {$actuals} actual value(s) — this command syncs PLANS only. Use import:task-progress for progress files.");

            return self::FAILURE;
        }

        $dbTasks = Task::query()->get(['id', 'region_code', 'task_number', 'title', 'latest_period'])
            ->groupBy('task_number');
        $districtsByRegion = District::all()->groupBy('region_code');

        $stats = [
            'plans_updated' => 0, 'lines_added' => 0, 'tasks_created' => 0,
            'regions_added' => 0, 'tasks_recomputed' => 0, 'skipped_title' => [], 'skipped_empty' => [],
        ];
        $changes = [];   // detailed audit rows
        $unmatched = [];
        $dryRun = (bool) $this->option('dry-run');

        $work = function () use ($parsed, $dbTasks, $districtsByRegion, $period, &$stats, &$changes, &$unmatched, $dryRun): void {
            foreach ($parsed as $t) {
                $no = $t['task_number'];

                if (! $dbTasks->has($no)) {
                    if ($t['regions'] === []) {
                        $stats['skipped_empty'][] = $no;
                        continue;
                    }
                    foreach ($t['regions'] as $code => $regionData) {
                        $stats['tasks_created']++;
                        $changes[] = ['kind' => 'new_task', 'task' => $no, 'region' => $code,
                            'lines' => count($regionData['metrics'])];
                        if (! $dryRun) {
                            $this->createTask($t, $code, $regionData, $period, $districtsByRegion, $unmatched);
                        }
                    }
                    continue;
                }

                // Existing task number: titles must agree before we touch plans.
                $dbTitle = $this->norm($dbTasks[$no]->first()->title);
                if ($this->norm($t['title']) !== $dbTitle) {
                    $stats['skipped_title'][] = $no;
                    continue;
                }

                foreach ($t['regions'] as $code => $regionData) {
                    $dbTask = $dbTasks[$no]->firstWhere('region_code', $code);

                    if ($dbTask === null) {
                        // Existing number, but this region was not listed before.
                        if (array_filter($regionData['metrics'], fn ($m) => $m['plan'] !== null) === []) {
                            continue;
                        }
                        $stats['regions_added']++;
                        $changes[] = ['kind' => 'new_region', 'task' => $no, 'region' => $code,
                            'lines' => count($regionData['metrics'])];
                        if (! $dryRun) {
                            $this->createTask($t, $code, $regionData, $period, $districtsByRegion, $unmatched);
                        }
                        continue;
                    }

                    $target = $dbTask->latest_period ?? $period;
                    $rows = TaskProgress::query()
                        ->where('task_id', $dbTask->id)
                        ->where('report_period', $target)
                        ->get()
                        ->keyBy('line_no');

                    $dirty = false;
                    foreach ($regionData['metrics'] as $m) {
                        $row = $rows->get($m['line_no']);
                        if ($row === null) {
                            $stats['lines_added']++;
                            $dirty = true;
                            $changes[] = ['kind' => 'new_line', 'task' => $no, 'region' => $code,
                                'line' => $m['line_no'], 'label' => $m['metric_label'], 'plan' => $m['plan']];
                            if (! $dryRun) {
                                TaskProgress::create([
                                    'task_id'       => $dbTask->id,
                                    'line_no'       => $m['line_no'],
                                    'metric_label'  => $m['metric_label'],
                                    'unit'          => $m['unit'],
                                    'report_period' => $target,
                                    'period_type'   => TaskPeriod::periodType($target),
                                    'plan_value'    => $m['plan'],
                                    'actual_value'  => null,
                                    'pct_of_plan'   => null,
                                ]);
                            }
                            continue;
                        }

                        $dbPlan = $row->plan_value === null ? null : (float) $row->plan_value;
                        if ($this->samePlan($dbPlan, $m['plan'])) {
                            continue;
                        }

                        $stats['plans_updated']++;
                        $dirty = true;
                        $actual = $row->actual_value === null ? null : (float) $row->actual_value;
                        $changes[] = ['kind' => 'plan_change', 'task' => $no, 'region' => $code,
                            'line' => $m['line_no'], 'label' => $row->metric_label,
                            'old' => $dbPlan, 'new' => $m['plan'], 'has_actual' => $actual !== null];
                        if (! $dryRun) {
                            $row->update([
                                'plan_value'  => $m['plan'],
                                'pct_of_plan' => $this->pct($no, $m['plan'], $actual),
                            ]);
                        }
                    }

                    if ($dirty && ! $dryRun) {
                        $stats['tasks_recomputed']++;
                        $this->recompute($dbTask->fresh(), $t['title'], $target);
                    } elseif ($dirty) {
                        $stats['tasks_recomputed']++;
                    }
                }
            }
        };

        $dryRun ? $work() : DB::transaction($work);

        // Audit report file (also written on dry-run — it IS the review artifact).
        $reportPath = storage_path('app/plan-sync-' . now()->format('Ymd_His') . ($dryRun ? '-dryrun' : '') . '.json');
        file_put_contents($reportPath, json_encode(
            ['file' => $file, 'period_for_new' => $period, 'dry_run' => $dryRun, 'stats' => $stats, 'changes' => $changes],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ));

        $this->table(['metric', 'count'], [
            ['plan values updated', $stats['plans_updated']],
            ['new indicator lines', $stats['lines_added']],
            ['new tasks (per region)', $stats['tasks_created']],
            ['new regions on existing tasks', $stats['regions_added']],
            ['tasks recomputed (pct/status/headline)', $stats['tasks_recomputed']],
        ]);
        if ($stats['skipped_title'] !== []) {
            $this->warn('Skipped (title mismatch, verify manually): ' . implode(', ', $stats['skipped_title']));
        }
        if ($stats['skipped_empty'] !== []) {
            $this->warn('Skipped (no numeric plan in any region): ' . implode(', ', $stats['skipped_empty']));
        }
        if ($unmatched !== []) {
            $this->warn('Unmatched executor tokens: ' . implode(' | ', array_unique($unmatched)));
        }
        $this->info(($dryRun ? 'DRY RUN — no changes written. ' : '') . "Audit report: {$reportPath}");

        return self::SUCCESS;
    }

    /** Create a Task + plan-only progress rows for one region of a parsed definition. */
    private function createTask(array $t, int $code, array $regionData, string $period, $districtsByRegion, array &$unmatched): void
    {
        $override = TasksTaxonomy::DEADLINE_OVERRIDES[$t['task_number']] ?? null;
        $task = Task::firstOrNew(['region_code' => $code, 'task_number' => $t['task_number']]);
        $task->fill([
            'title'                  => $t['title'],
            'deadline_text'          => $override['deadline_text'] ?? $t['deadline_text'],
            'period_code'            => $override['period_code'] ?? $t['period_code'],
            'executor_text'          => $regionData['executor_text'],
            'module_code'            => $t['module_code'],
            'indicator_code'         => $t['indicator_code'],
            'section_path'           => $t['section_path'],
            'section_label'          => $t['section_label'],
            'source_paragraph_index' => $t['source_row'],
        ]);
        $task->kind ??= 'kpi';
        $task->save();

        $task->districts()->sync(TaskExecutorResolver::districtIds(
            $regionData['executor_text'],
            $districtsByRegion->get($code, collect()),
            $unmatched
        ));

        foreach ($regionData['metrics'] as $m) {
            TaskProgress::query()->updateOrCreate(
                ['task_id' => $task->id, 'line_no' => $m['line_no'], 'report_period' => $period],
                [
                    'metric_label' => $m['metric_label'],
                    'unit'         => $m['unit'],
                    'period_type'  => TaskPeriod::periodType($period),
                    'plan_value'   => $m['plan'],
                    'actual_value' => null,
                    'pct_of_plan'  => null,
                ]
            );
        }

        $this->recompute($task, $t['title'], $period);
    }

    /** Rebuild pct-independent aggregates (status, counters, headline) from stored rows. */
    private function recompute(Task $task, string $title, string $period): void
    {
        $stored = $task->progress()
            ->where('report_period', $period)
            ->orderBy('line_no')
            ->get()
            ->map(fn ($r) => [
                'line_no' => $r->line_no,
                'unit'    => $r->unit,
                'plan'    => $r->plan_value,
                'actual'  => $r->actual_value,
                'pct'     => $r->pct_of_plan,
            ]);
        $head = $stored->firstWhere('line_no', 0) ?? $stored->first();
        $agg = TaskStatus::forTask($task->task_number, $title, $stored, $task->period_code, $task->deadline_text, $period);

        $task->update([
            'latest_period'   => $task->latest_period ?? $period,
            'headline_unit'   => $head['unit'] ?? null,
            'headline_plan'   => $head['plan'] ?? null,
            'headline_actual' => $head['actual'] ?? null,
            'headline_pct'    => $head['pct'] ?? null,
            'lines_total'     => $agg['total'],
            'lines_done'      => $agg['done'],
            'status'          => $agg['status'],
        ]);
    }

    /** Recompute a line's pct for a NEW plan against the UNCHANGED stored actual. */
    private function pct(string $taskNumber, ?float $plan, ?float $actual): ?float
    {
        if (in_array($taskNumber, TasksTaxonomy::LOWER_IS_BETTER_TASKS, true)) {
            if ($actual === null || $plan === null) {
                return null;
            }

            return $actual == 0.0 ? 100.0 : $plan / $actual * 100.0;
        }
        if ($plan === null || $plan == 0.0 || $actual === null) {
            return null;
        }

        return $actual / $plan * 100.0;
    }

    private function samePlan(?float $a, ?float $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return abs($a - $b) < self::EPS * max(1.0, abs($a));
    }

    private function norm(string $s): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
    }
}
