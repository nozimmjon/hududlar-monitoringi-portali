<?php

namespace App\Console\Commands;

use App\Models\Roadmap;
use App\Services\Roadmaps\RoadmapTemplateWriter;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class RoadmapTemplate extends Command
{
    protected $signature = 'roadmap:template
        {--region= : SOATO region code, e.g. 1733 (Хоразм)}
        {--all : Every region with a loaded road map, one sheet each}
        {--period= : Report period the file is for: YYYY-MM or YYYY-Qn}
        {--out= : Output .xlsx (default: data/Сув хўжалиги бўйича йўл хариталар/мониторинг/<period>/<region>.xlsx)}
        {--year=2026 : Road-map year}
        {--domain=water : Road-map family}';

    protected $description = 'Write the xlsx a region fills with indicator actuals (stored lines, else suggested from the measure text).';

    public function handle(): int
    {
        if ($this->option('all') && (string) $this->option('region') !== '') {
            $this->error('Use either --region or --all, not both.');

            return self::FAILURE;
        }

        $period = strtoupper(trim((string) $this->option('period')));      // «2026-q3» → «2026-Q3»
        if (! RoadmapPeriod::isValid($period)) {
            $this->error('Provide --period as YYYY-MM or YYYY-Qn (e.g. 2026-09 or 2026-Q3).');

            return self::FAILURE;
        }

        $base = Roadmap::query()
            ->where('roadmaps.domain', (string) $this->option('domain'))
            ->where('roadmaps.year', (int) $this->option('year'))
            ->with([
                'region',
                'measures' => fn ($q) => $q->orderBy('source_row'),
                'measures.district',
                'measures.lines.progress' => fn ($q) => $q->where('report_period', $period),
            ]);

        if ($this->option('all')) {
            $roadmaps = $base->join('regions', 'regions.code', '=', 'roadmaps.region_code')
                ->orderBy('regions.sort_order')->select('roadmaps.*')->get();
            if ($roadmaps->isEmpty()) {
                $this->error('No road map is loaded yet — run import:roadmap first.');

                return self::FAILURE;
            }
            $name = 'Барча вилоятлар';
        } else {
            $code = (int) $this->option('region');
            if ($code <= 0) {
                $this->error('Provide --region=<SOATO code> or --all.');

                return self::FAILURE;
            }
            $roadmap = $base->where('roadmaps.region_code', $code)->first();
            if (! $roadmap) {
                $this->error("Region {$code} has no road map loaded — run import:roadmap --region={$code} first.");

                return self::FAILURE;
            }
            $roadmaps = collect([$roadmap]);
            $name     = $roadmap->region->name_short;
        }

        $out = (string) ($this->option('out') ?: base_path(ImportRoadmap::DEFAULT_DIR . "/мониторинг/{$period}/{$name}.xlsx"));
        $dir = dirname($out);
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
            $this->error("Cannot create {$dir}");

            return self::FAILURE;
        }

        $writer = new RoadmapTemplateWriter();
        try {
            $book = $writer->build($roadmaps, $period);
            IOFactory::createWriter($book, 'Xlsx')->save($out);
        } catch (Throwable $e) {                        // the usual cause: the file is still open in Excel
            $this->error("Cannot write {$out}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $rows = [];
        foreach ($roadmaps as $roadmap) {
            $s      = $writer->stats[$roadmap->region_code];
            $rows[] = [$roadmap->region->name_full, $s['measures'], $s['lines'], $s['suggested']];
        }
        $this->table(['Вилоят', 'Тадбирлар', 'Индикатор қаторлари', 'of which suggested'], $rows);
        $this->info("Written {$out}");

        return self::SUCCESS;
    }
}
