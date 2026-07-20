<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Task;
use App\Models\TaskProgress;
use App\Services\Tasks\IlovaAnnexParser;
use App\Support\TaskPeriod;
use App\Support\TaskStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills actual values the half-year economic import left empty, from the annex
 * workbook ("илова жадваллар.xlsx"). Covered tasks: 10 (low-growth districts,
 * counted from 4-илова), 40 (24/7 streets, 7-илова), 46 (соҳил бўйлари, 8-илова),
 * 48 (йўл бўйлари, 9-илова), 111 headline (15б-илова), 133 (17-илова).
 *
 * Gap-filling only: a non-null value already in the DB is never overwritten —
 * a conflicting file value is reported and skipped. Explicit zeros in the file
 * ARE written (a closed half-year with no execution is a real 0%, not "no data").
 */
class ImportIlovaAnnex extends Command
{
    protected $signature = 'import:ilova
        {--file= : Path to the annex XLSX (defaults to data/илова жадваллар.xlsx)}
        {--period= : Half-year period the file represents, e.g. 2026-H1}
        {--dry-run : Parse and report without writing}';

    protected $description = 'Fill missing task actuals for a half-year from the annex tables workbook (илова жадваллар).';

    public function handle(): int
    {
        $period = (string) $this->option('period');
        if (! preg_match('/^\d{4}-H[12]$/', $period)) {
            $this->error('Provide --period as YYYY-H1 or YYYY-H2 (the annex file is a half-year snapshot).');
            return self::FAILURE;
        }
        $periodType = TaskPeriod::periodType($period);
        $year       = TaskPeriod::yearFromPeriod($period);

        if (! DB::table('reporting_years')->where('year', $year)->exists()) {
            $this->error("Reporting year {$year} is not configured (reporting_years table). Seed it before importing.");
            return self::FAILURE;
        }

        $file = $this->option('file') ?: base_path('../data/илова жадваллар.xlsx');
        if (! is_file($file)) {
            $this->error("Source workbook not found: {$file}");
            return self::FAILURE;
        }

        $this->info("Parsing {$file} for period {$period}…");
        try {
            $parsed = (new IlovaAnnexParser())->parse($file);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        foreach ($parsed['warnings'] as $w) {
            $this->warn($w);
        }

        $stats = [];   // task_number => ['filled' => n, 'plans' => n, 'created' => n, 'regions' => set]
        $skips = [];   // human-readable skip/conflict notes
        $runByRegion = [];
        $writtenByRegion = [];

        DB::beginTransaction();
        try {
            foreach ($parsed['tasks'] as $taskNumber => $regions) {
                $stat = ['filled' => 0, 'plans' => 0, 'created' => 0, 'regions' => []];

                foreach ($regions as $code => $lines) {
                    $task = Task::where('region_code', $code)->where('task_number', (string) $taskNumber)->first();
                    if ($task === null) {
                        $skips[] = "Task {$taskNumber}: no task row for region {$code} — run the regular H1 import first.";
                        continue;
                    }

                    $run = $runByRegion[$code] ??= ImportRun::create([
                        'region_code'  => $code,
                        'year'         => $year,
                        'trigger_kind' => 'cli',
                        'status'       => ImportRunStatus::Promoting,
                        'started_at'   => now(),
                    ]);

                    $touched = false;
                    foreach ($lines as $lineNo => $vals) {
                        $changed = $this->applyLine($task, $lineNo, $vals, $period, $periodType, $run->id, $taskNumber, $code, $stat, $skips);
                        if ($changed) {
                            $writtenByRegion[$code] = ($writtenByRegion[$code] ?? 0) + 1;
                        }
                        $touched = $touched || $changed;
                    }

                    if ($touched) {
                        $stat['regions'][$code] = true;
                        $this->refreshHeadline($task, $period);
                    }
                }

                $stats[$taskNumber] = $stat;
            }

            foreach ($runByRegion as $code => $run) {
                $run->update([
                    'status'          => ImportRunStatus::Promoted,
                    'promoted_at'     => now(),
                    'files_processed' => 1,
                    'rows_promoted'   => $writtenByRegion[$code] ?? 0,
                ]);
            }

            if ($this->option('dry-run')) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        foreach ($stats as $taskNumber => $stat) {
            $this->info(sprintf(
                'Task %s: filled %d actual(s), %d plan(s), created %d row(s) across %d region(s).',
                $taskNumber, $stat['filled'], $stat['plans'], $stat['created'], count($stat['regions']),
            ));
        }
        foreach ($skips as $note) {
            $this->warn($note);
        }
        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }

    /**
     * Fill one progress line from the file. Returns true when anything was written.
     * @param array{plan: ?float, actual: ?float} $vals
     */
    private function applyLine(
        Task $task, int $lineNo, array $vals, string $period, string $periodType,
        int $runId, string $taskNumber, int $code, array &$stat, array &$skips,
    ): bool {
        if ($vals['plan'] === null && $vals['actual'] === null) {
            return false;
        }

        $row = $task->progress()->where('report_period', $period)->where('line_no', $lineNo)->first();
        if ($row === null) {
            // Label/unit come from the latest earlier row of the same line (e.g. Q1).
            $template = $task->progress()->where('line_no', $lineNo)->orderByDesc('report_period')->first();
            $row = new TaskProgress([
                'task_id'       => $task->id,
                'line_no'       => $lineNo,
                'metric_label'  => $template?->metric_label,
                'unit'          => $template?->unit,
                'report_period' => $period,
                'period_type'   => $periodType,
            ]);
            $row->task_id = $task->id;
            $stat['created']++;
        }

        $changed = false;

        if ($vals['plan'] !== null) {
            if ($row->plan_value === null) {
                $row->plan_value = $vals['plan'];
                $stat['plans']++;
                $changed = true;
            } elseif (abs((float) $row->plan_value - $vals['plan']) > 0.01) {
                $skips[] = sprintf(
                    'Task %s region %d line %d: file plan %s differs from DB plan %s — kept DB value.',
                    $taskNumber, $code, $lineNo, $vals['plan'], (float) $row->plan_value,
                );
            }
        }

        if ($vals['actual'] !== null) {
            if ($row->actual_value === null) {
                $row->actual_value = $vals['actual'];
                $stat['filled']++;
                $changed = true;
            } elseif (abs((float) $row->actual_value - $vals['actual']) > 0.01) {
                $skips[] = sprintf(
                    'Task %s region %d line %d: file actual %s differs from DB actual %s — kept DB value.',
                    $taskNumber, $code, $lineNo, $vals['actual'], (float) $row->actual_value,
                );
            }
        }

        if ($changed) {
            if ($row->actual_value !== null && $row->plan_value !== null && (float) $row->plan_value != 0.0) {
                $row->pct_of_plan = round((float) $row->actual_value / (float) $row->plan_value * 100, 4);
            }
            $row->import_run_id = $runId;
            $row->save();
        }

        return $changed;
    }

    /** Recompute the task's headline snapshot from this period's line 0, respecting the stale-period guard. */
    private function refreshHeadline(Task $task, string $period): void
    {
        $shouldAdvance = $task->latest_period === null
            || TaskPeriod::sortKey($period) >= TaskPeriod::sortKey($task->latest_period);
        if (! $shouldAdvance) {
            return;
        }

        $lines = $task->progress()->where('report_period', $period)->orderBy('line_no')->get();
        $head = $lines->firstWhere('line_no', 0);
        if ($head === null) {
            return;
        }

        // Status is the weakest link over ALL planned lines, not just line 0.
        $agg = TaskStatus::aggregate($lines->map(
            fn ($l) => ['plan' => $l->plan_value, 'actual' => $l->actual_value, 'pct' => $l->pct_of_plan]
        ));

        $task->update([
            'latest_period'   => $period,
            'headline_unit'   => $head->unit,
            'headline_plan'   => $head->plan_value,
            'headline_actual' => $head->actual_value,
            'headline_pct'    => $head->pct_of_plan,
            'lines_total'     => $agg['total'],
            'lines_done'      => $agg['done'],
            'status'          => $agg['status'],
        ]);
    }
}
