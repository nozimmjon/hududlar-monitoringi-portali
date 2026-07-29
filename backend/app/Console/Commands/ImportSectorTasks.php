<?php

namespace App\Console\Commands;

use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\SectorTaskProgress;
use App\Services\Tasks\SectorWorkbookParser;
use App\Support\TaskPeriod;
use App\Support\TaskStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportSectorTasks extends Command
{
    protected $signature = 'import:sector-tasks
        {--file= : Path to the XLSX (defaults to data/sectors/Вазифалар_2026_тармоқлар_кесимида.xlsx)}
        {--period= : Report period this file represents, e.g. 2026-H2, 2026-Q3 or 2026-08}
        {--dry-run : Parse and report without writing}';

    protected $description = 'Import sector (тармоқ) guarantee-letter tasks plan+actual from the all-sectors XLSX.';

    public function handle(): int
    {
        $period = (string) $this->option('period');
        if ($period === '' || ! preg_match('/^\d{4}-(Q[1-4]|H[12]|\d{2})$/', $period)) {
            $this->error('Provide --period as YYYY-Q1..Q4, YYYY-H1/H2 or YYYY-MM (e.g. 2026-H2, 2026-Q3 or 2026-08).');
            return self::FAILURE;
        }
        $periodType = TaskPeriod::periodType($period);
        $year       = TaskPeriod::yearFromPeriod($period);

        if (! DB::table('reporting_years')->where('year', $year)->exists()) {
            $this->error("Reporting year {$year} is not configured (reporting_years table). Seed it before importing.");
            return self::FAILURE;
        }

        $file = $this->option('file')
            ?: base_path('../data/sectors/Вазифалар_2026_тармоқлар_кесимида.xlsx');
        if (! is_file($file)) {
            $this->error("Source workbook not found: {$file}");
            return self::FAILURE;
        }

        $this->info("Parsing {$file} for period {$period}…");
        try {
            $parsed = (new SectorWorkbookParser())->parse($file);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        foreach ($parsed['warnings'] as $w) {
            $this->warn($w);
        }

        // Match every sheet to a sector BEFORE writing anything: leading number →
        // sort_order, then the sheet title must contain the sector's short name.
        $sectorsByOrder = Sector::all()->keyBy('sort_order');
        $matched = []; // [Sector, tasks[]]
        foreach ($parsed['sheets'] as $sheet) {
            $sector = $sectorsByOrder->get($sheet['sort_order']);
            if (! $sector) {
                $this->error("Лист '{$sheet['sheet_title']}': {$sheet['sort_order']}-тартибли тармоқ справочникда йўқ. Импорт тўхтатилди.");
                return self::FAILURE;
            }
            if (mb_stripos($sheet['sheet_title'], $sector->name_short) === false) {
                $this->error(
                    "Лист '{$sheet['sheet_title']}' номи {$sheet['sort_order']}-тармоқ '{$sector->name_short}' га мос эмас — "
                    . 'лист тартиби ўзгарган бўлиши мумкин. Импорт тўхтатилди.'
                );
                return self::FAILURE;
            }
            $matched[] = [$sector, $sheet['tasks']];
        }

        $missing = $sectorsByOrder->keys()->diff(array_column($parsed['sheets'], 'sort_order'));
        foreach ($missing as $order) {
            $this->warn("Тармоқ '{$sectorsByOrder[$order]->name_short}' учун лист файлда йўқ — ташлаб кетилди.");
        }

        // A hand-edited workbook can repeat column B within one task, which would
        // hit the sector_task_progress unique constraint mid-transaction. Catch it
        // here, before any write, so the operator gets a clear message instead of
        // a raw SQL exception.
        foreach ($matched as [$sector, $tasks]) {
            foreach ($tasks as $t) {
                $lineNos = array_column($t['lines'], 'line_no');
                if (count($lineNos) !== count(array_unique($lineNos))) {
                    $this->error(
                        "Лист '{$sector->name_short}', вазифа №{$t['task_no']}: такрорланган индикатор рақами (B устун) — файл нотўғри тўлдирилган. Импорт тўхтатилди."
                    );
                    return self::FAILURE;
                }
            }
        }

        $taskCount = $lineCount = 0;
        foreach ($matched as [, $tasks]) {
            $taskCount += count($tasks);
            foreach ($tasks as $t) $lineCount += count($t['lines']);
        }
        $this->info('Parsed ' . count($matched) . " sheet(s): {$taskCount} tasks, {$lineCount} metric lines.");

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($matched, $period, $periodType) {
            foreach ($matched as [$sector, $tasks]) {
                foreach ($tasks as $t) {
                    $task = SectorTask::firstOrNew(['sector_id' => $sector->id, 'task_no' => $t['task_no']]);
                    $task->title = $t['title'];
                    if (! $task->exists) {
                        $task->status = 'in_progress';
                    }
                    $task->save();

                    // Replace this period's lines (idempotent re-import), then insert.
                    $task->progress()->where('report_period', $period)->delete();
                    $stored = [];
                    foreach ($t['lines'] as $line) {
                        $pct = ($line['plan'] !== null && $line['actual'] !== null && (float) $line['plan'] != 0.0)
                            ? round($line['actual'] / $line['plan'] * 100, 4)
                            : null;
                        SectorTaskProgress::create([
                            'sector_task_id' => $task->id,
                            'line_no'        => $line['line_no'],
                            'metric_label'   => $line['metric_label'],
                            'unit'           => $line['unit'],
                            'deadline_text'  => $line['deadline_text'],
                            'deadline_code'  => $line['deadline_code'],
                            'report_period'  => $period,
                            'period_type'    => $periodType,
                            'plan_value'     => $line['plan'],
                            'actual_value'   => $line['actual'],
                            'pct_of_plan'    => $pct,
                            'reported_at'    => $line['actual'] !== null ? now()->toDateString() : null,
                        ]);
                        $stored[] = ['line_no' => $line['line_no'], 'unit' => $line['unit'],
                                     'plan' => $line['plan'], 'actual' => $line['actual'], 'pct' => $pct];
                    }

                    // Weakest-link status over planned lines + headline snapshot from
                    // the task's first line. Only advance if this period is not older
                    // than what the task already shows.
                    $agg = TaskStatus::aggregate($stored);
                    $head = $stored[0] ?? null;
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
        });

        $this->info("Wrote {$lineCount} progress rows for " . count($matched) . ' sector(s), period ' . $period . '.');
        return self::SUCCESS;
    }
}
