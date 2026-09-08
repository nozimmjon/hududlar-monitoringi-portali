<?php

namespace App\Console\Commands;

use App\Models\Roadmap;
use App\Models\RoadmapMeasureLine;
use App\Services\Roadmaps\MeasureRecomputer;
use App\Services\Roadmaps\RoadmapProgressReader;
use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

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
            $this->error("--year must be a four-digit year, got «{$this->option('year')}».");

            return self::FAILURE;
        }
        if (! in_array($domain, ImportRoadmap::DOMAINS, true)) {
            $this->error('--domain must be one of: ' . implode(', ', ImportRoadmap::DOMAINS) . " — got «{$domain}».");

            return self::FAILURE;
        }

        $book = null;
        try {
            $book   = IOFactory::load($file);
            $parsed = (new RoadmapProgressReader())->read($book);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $book?->disconnectWorksheets();          // a road-map workbook is small, but nothing below needs it
            unset($book);
        }

        $option = strtoupper(trim((string) $this->option('period')));
        $period = $option !== '' ? $option : (string) $parsed['period'];
        if (! RoadmapPeriod::isValid($period)) {
            $this->error('Provide --period as YYYY-MM or YYYY-Qn, or a sheet title carrying «Ҳисобот даври: YYYY-MM».');

            return self::FAILURE;
        }
        if ($option !== '' && $parsed['period'] !== null && $parsed['period'] !== $period) {
            $this->error("--period {$period} does not match the file's own period {$parsed['period']}.");

            return self::FAILURE;
        }
        if ($parsed['blocks'] === []) {
            $this->error('No indicator rows found (no keys in column A).');

            return self::FAILURE;
        }

        // Resolve every key before writing anything.
        $byRegion = [];
        foreach ($parsed['blocks'] as $key => $block) {
            $byRegion[RoadmapKey::parse($key)['region']][$key] = $block;
        }
        $work = [];
        foreach ($byRegion as $regionCode => $blocks) {
            $roadmap = Roadmap::where('domain', $domain)->where('region_code', $regionCode)->where('year', $year)
                ->with(['region', 'measures.district', 'measures.lines.progress'])->first();
            if (! $roadmap) {
                $this->error("Region {$regionCode} has no road map for {$domain}/{$year} (keys like " . array_key_first($blocks) . ') — run import:roadmap first.');

                return self::FAILURE;
            }
            $measures = [];
            foreach ($roadmap->measures as $m) {
                $measures[RoadmapKey::make($regionCode, $m->section_no, $m->district?->code, $m->seq_no)] = $m;
            }
            $items = [];
            foreach ($blocks as $key => $block) {
                $m = $measures[$key] ?? null;
                if (! $m) {
                    $this->error("{$block['sheet']}!A{$block['row']}: unknown key {$key} — the road map has no such measure.");

                    return self::FAILURE;
                }
                if ($block['lines'] === [] && $m->lines->isNotEmpty()) {
                    $this->error("{$block['sheet']}!A{$block['row']}: measure {$key} has {$m->lines->count()} stored lines but no rows in the file — truncated file?");

                    return self::FAILURE;
                }
                $items[] = [$m, $block];
            }
            $work[] = ['roadmap' => $roadmap, 'items' => $items];
        }

        $summary    = [];
        $notes      = [];
        $removedAll = 0;
        DB::beginTransaction();
        try {
            $recomputer = new MeasureRecomputer();
            foreach ($work as $entry) {
                $s = ['measures' => 0, 'total' => $entry['roadmap']->measures->count(), 'lines' => 0, 'removed' => 0,
                    'reported' => 0, 'relabeled' => 0, 'cleared' => 0, 'done' => 0, 'in_progress' => 0, 'open' => 0];
                foreach ($entry['items'] as [$measure, $block]) {
                    $s['measures']++;
                    $stored = $measure->lines->keyBy('line_no');
                    foreach ($block['lines'] as $i => $l) {
                        $no       = $i + 1;
                        $line     = $stored->get($no);
                        $progress = null;
                        if ($line !== null) {
                            // Line identity is the row position, so an inserted row moves every following
                            // line's reported history onto the next indicator — worth saying out loud.
                            if ($line->label !== $l['label']
                                && $line->progress->contains(fn ($p) => $p->report_period !== $period && $p->actual_value !== null)) {
                                $s['relabeled']++;
                            }
                            $progress = $line->progress->firstWhere('report_period', $period);
                            if ($progress !== null && $progress->actual_value !== null && $l['actual'] === null) {
                                $s['cleared']++;
                            }
                        }

                        $line ??= new RoadmapMeasureLine(['roadmap_measure_id' => $measure->id, 'line_no' => $no]);
                        $line->fill(['label' => $l['label'], 'unit' => $l['unit'], 'plan_value' => $l['plan']])->save();

                        $progress ??= $line->progress()->make(['report_period' => $period]);   // a fresh line has no progress to load
                        $progress->fill([
                            'period_type'  => RoadmapPeriod::type($period),
                            'actual_value' => $l['actual'],
                            'pct_of_plan'  => MeasureRecomputer::pctOfPlan($l['plan'], $l['actual']),
                            'note'         => $l['note'],
                            'reported_at'  => now()->toDateString(),
                        ])->save();

                        $s['lines']++;
                        if ($l['actual'] !== null) {
                            $s['reported']++;
                        }
                    }
                    if ($block['lines'] !== []) {
                        $s['removed'] += $measure->lines()->where('line_no', '>', count($block['lines']))->delete();
                    }
                    $measure->unsetRelation('lines');
                    $values = $recomputer->recompute($measure, $entry['roadmap']->year);
                    $s[$values['status']]++;
                }
                $region      = $entry['roadmap']->region->name_full;
                $removedAll += $s['removed'];
                $summary[]   = [$region, "{$s['measures']}/{$s['total']}", $s['lines'], $s['removed'], $s['reported'], $s['done'], $s['in_progress'], $s['open']];
                $notes[]     = $s + ['region' => $region, 'missing' => $s['total'] - $s['measures']];
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

        $this->table(['Вилоят', 'Файлда/жами', 'Қаторлар', 'Ўчирилди', 'Амалда', 'done', 'in_progress', 'open'], $summary);
        if ($removedAll > 0) {
            $this->warn("{$removedAll} line(s) removed — no longer in the file (their history went with them).");
        }
        foreach ($notes as $n) {
            if ($n['relabeled'] > 0) {
                $this->warn("{$n['relabeled']} line(s) with reported history changed their label — a row inserted mid-block shifts the numbering; check that the history still belongs to the right indicator.");
            }
            if ($n['cleared'] > 0) {
                $this->warn("{$n['cleared']} previously reported «Амалда» value(s) cleared by this file.");
            }
            if ($n['missing'] > 0) {
                $this->warn("{$n['missing']} of {$n['total']} measure(s) of {$n['region']} are not in the file — left untouched.");
            }
        }
        $this->info("Period {$period}: " . count($parsed['blocks']) . ' measure(s) processed from ' . basename($file) . '.');
        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }
}
