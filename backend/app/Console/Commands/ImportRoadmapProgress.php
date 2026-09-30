<?php

namespace App\Console\Commands;

use App\Services\Roadmaps\ProgressImport;
use Illuminate\Console\Command;

/**
 * Reads a road-map template back: the rows define the indicator lines (label / unit /
 * plan, by order inside the measure block) AND carry the period's actuals. The same
 * command serves both steps of the loop — the reviewed empty file (definitions only)
 * and the file the region returns (actuals). Idempotent per (file, period).
 */
class ImportRoadmapProgress extends Command
{
    protected $signature = 'import:roadmap-progress
        {--file= : The (filled) template .xlsx}
        {--period= : YYYY-MM or YYYY-Qn (default: the «Ҳисобот даври» in the sheet title)}
        {--year=2026 : Road-map year}
        {--domain=water : Road-map family}
        {--dry-run : Validate and report without writing}';

    protected $description = 'Import indicator line definitions and period actuals from a road-map template.';

    public function handle(): int
    {
        $file = (string) $this->option('file');
        if ($file === '' || ! is_file($file)) {
            $this->error("Файл топилмади: {$file}");

            return self::FAILURE;
        }

        $domain = (string) $this->option('domain');
        $year   = (int) $this->option('year');
        if ($year < 2000 || $year > 2100) {
            $this->error("--year тўрт хонали йил бўлиши керак, «{$this->option('year')}» берилди.");

            return self::FAILURE;
        }
        if (! in_array($domain, ImportRoadmap::DOMAINS, true)) {
            $this->error('--domain қуйидагилардан бири бўлиши керак: ' . implode(', ', ImportRoadmap::DOMAINS) . " — «{$domain}» берилди.");

            return self::FAILURE;
        }

        $report = (new ProgressImport())->run($file, $this->option('period'), $year, $domain, ! $this->option('dry-run'));
        if (! $report->ok()) {
            $this->error((string) $report->error);

            return self::FAILURE;
        }

        $this->table(['Вилоят', 'Файлда/жами', 'Қаторлар', 'Ўчирилди', 'Амалда', 'Бажарилди', 'Бажарилмоқда', 'Бажарилмаган'], $report->rows());
        foreach ($report->warnings as $warning) {
            $this->warn($warning);
        }
        $this->info("{$report->period} даври: {$report->file} файлидан {$report->blocks} та чора-тадбир ўқилди.");
        if ($this->option('dry-run')) {
            $this->warn('Синов ишга тушириш — ҳеч нарса ёзилмади.');
        }

        return self::SUCCESS;
    }
}
