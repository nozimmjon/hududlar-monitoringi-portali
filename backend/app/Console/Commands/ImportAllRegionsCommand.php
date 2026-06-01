<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\IndicatorFact;
use App\Models\Task;
use Database\Seeders\SoatoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ImportAllRegionsCommand extends Command
{
    protected $signature = 'import:all-regions
        {year=2026 : Reporting year, passed through to import:region}
        {--only= : Comma-separated region slugs or SOATO codes to limit the batch}
        {--no-tasks : Skip import:task-progress calls}
        {--period= : Report period (e.g. 2026-Q1) for import:task-progress; required to import tasks}
        {--no-promote : Stop at staging; do not auto-promote}';

    protected $description = 'Run import:region + import:promote + import:task-progress for every region.';

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $slugs = $this->resolveSlugs();

        if (empty($slugs)) {
            $this->error('No regions selected. Check --only filter.');
            return self::SUCCESS;
        }

        $this->info("Importing " . count($slugs) . " region(s) for year {$year}.");

        $summary = [];
        foreach ($slugs as $slug) {
            $summary[] = $this->processRegion($slug, $year);
        }

        $this->printSummary($summary);
        return self::SUCCESS;
    }

    /** @return list<string> Region slugs in SoatoSeeder order, filtered by --only. */
    private function resolveSlugs(): array
    {
        $allSlugs = array_values(SoatoSeeder::REGION_LATIN);

        $only = (string) ($this->option('only') ?? '');
        if ($only === '') {
            return $allSlugs;
        }

        $tokens = array_map('trim', explode(',', $only));
        $selected = [];
        foreach ($tokens as $tok) {
            if ($tok === '') continue;
            if (ctype_digit($tok)) {
                $code = (int) $tok;
                if (isset(SoatoSeeder::REGION_LATIN[$code])) {
                    $selected[] = SoatoSeeder::REGION_LATIN[$code];
                }
            } elseif (in_array($tok, $allSlugs, true)) {
                $selected[] = $tok;
            }
        }
        return array_values(array_unique($selected));
    }

    /** @return array{slug:string, xlsx:string, rows_staged:int, rows_promoted:int, tasks:string, tasks_count:int, note:string} */
    private function processRegion(string $slug, int $year): array
    {
        $row = [
            'slug'          => $slug,
            'xlsx'          => 'pending',
            'rows_staged'   => 0,
            'rows_promoted' => 0,
            'tasks'         => 'pending',
            'tasks_count'   => 0,
            'note'          => '',
        ];

        $regionCode = array_search($slug, SoatoSeeder::REGION_LATIN, true) ?: null;
        if ($regionCode === null) {
            $row['xlsx'] = 'fail';
            $row['note'] = 'unknown slug';
            return $row;
        }
        $regionCode = (int) $regionCode;

        $this->line(" → {$slug} ({$regionCode}): staging…");

        try {
            $exit = Artisan::call('import:region', [
                'region_code' => $slug,
                'year' => $year,
            ]);

            if ($exit !== 0) {
                $row['xlsx'] = 'fail';
                $row['note'] = "import:region exit={$exit}";
            } else {
                $run = ImportRun::where('region_code', $regionCode)
                    ->where('year', $year)
                    ->latest('id')
                    ->first();

                if ($run === null) {
                    $row['xlsx'] = 'fail';
                    $row['note'] = 'no ImportRun found';
                } else {
                    $row['rows_staged'] = (int) $run->rows_staged;
                    $row['xlsx'] = $run->status === ImportRunStatus::AwaitingReview
                        ? 'staged'
                        : 'fail';

                    if ($row['xlsx'] === 'staged'
                        && ! $this->option('no-promote')
                        && (int) $run->issues_blocker_count === 0
                    ) {
                        $promoteExit = Artisan::call('import:promote', ['run_id' => $run->id]);
                        if ($promoteExit === 0) {
                            $row['xlsx'] = 'promoted';
                            $row['rows_promoted'] = IndicatorFact::where('region_code', $regionCode)->count();
                        } else {
                            $row['note'] = "import:promote exit={$promoteExit}";
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $row['xlsx'] = 'error';
            $row['note'] = $this->truncate($e->getMessage(), 120);
        }

        $period = (string) $this->option('period');
        if ($this->option('no-tasks') || $period === '') {
            $row['tasks'] = 'skipped';
        } else {
            try {
                $tasksExit = Artisan::call('import:task-progress', [
                    '--region' => $slug,
                    '--period' => $period,
                ]);
                if ($tasksExit === 0) {
                    $row['tasks'] = 'ok';
                    $row['tasks_count'] = Task::where('region_code', $regionCode)->count();
                } else {
                    $row['tasks'] = 'fail';
                    $row['note'] = trim($row['note'] . " import:task-progress exit={$tasksExit}");
                }
            } catch (\Throwable $e) {
                $row['tasks'] = 'error';
                $row['note'] = trim($row['note'] . ' tasks: ' . $this->truncate($e->getMessage(), 80));
            }
        }

        return $row;
    }

    /** @param list<array<string, mixed>> $summary */
    private function printSummary(array $summary): void
    {
        $headers = ['region', 'xlsx', 'rows_staged', 'rows_promoted', 'tasks', 'tasks_count', 'note'];
        $rows = array_map(fn ($r) => [
            $r['slug'], $r['xlsx'], $r['rows_staged'], $r['rows_promoted'],
            $r['tasks'], $r['tasks_count'], $r['note'],
        ], $summary);

        $this->newLine();
        $this->table($headers, $rows);

        $total = count($summary);
        $xlsxOk = count(array_filter($summary, fn ($r) => $r['xlsx'] === 'promoted' || ($this->option('no-promote') && $r['xlsx'] === 'staged')));
        $tasksOk = count(array_filter($summary, fn ($r) => $r['tasks'] === 'ok'));

        $this->info("Run complete. {$xlsxOk}/{$total} xlsx ok, {$tasksOk}/{$total} tasks ok.");
    }

    private function truncate(string $s, int $n): string
    {
        return mb_strlen($s) <= $n ? $s : mb_substr($s, 0, $n) . '…';
    }
}
