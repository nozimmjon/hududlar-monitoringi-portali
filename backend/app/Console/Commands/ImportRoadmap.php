<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Region;
use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Services\Roadmaps\DocxTableReader;
use App\Services\Roadmaps\MeasureLineSync;
use App\Services\Roadmaps\RoadmapParser;
use App\Services\Roadmaps\XlsxRoadmapParser;
use App\Support\Import\DistrictNameNormalizer;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportRoadmap extends Command
{
    /** Relative to base_path(); the docx files are named "<N>. <Region> …docx" where N = region folder number. */
    public const DEFAULT_DIR = '../data/Сув хўжалиги бўйича йўл хариталар';

    /** The regions returned their 2026 road maps as xlsx in our template layout; same "<N>. …" naming. */
    public const XLSX_SUBDIR = 'Вилоятлар Йўл хариталари';

    public const DOMAINS = ['water'];

    protected $signature = 'import:roadmap
        {--region= : SOATO region code, e.g. 1733 (Хоразм)}
        {--file= : Path to the .docx or .xlsx (default: the one file under data/Сув хўжалиги бўйича йўл хариталар/ — the subfolder included — whose name starts with the region folder number)}
        {--year=2026 : Road-map year}
        {--domain=water : Road-map family}
        {--period= : YYYY-MM or YYYY-Qn — import the xlsx «Амалда»/«Изоҳ» columns as this period}
        {--range=null : What a plan written as a range («18-25») becomes: null | lower | upper}
        {--dry-run : Parse and print the summary without writing}';

    protected $description = 'Import a regional "ЙЎЛ ХАРИТАСИ" (water-management measures) .docx or .xlsx into roadmaps / roadmap_measures (+ indicator lines from the xlsx).';

    public function handle(): int
    {
        $regionCode = (int) $this->option('region');
        $region     = $regionCode > 0 ? Region::where('code', $regionCode)->first() : null;
        if (! $region) {
            $this->error('Provide --region=<SOATO code>, e.g. --region=1733 (Хоразм).');

            return self::FAILURE;
        }

        $year   = (int) $this->option('year');
        $domain = (string) $this->option('domain');
        if ($year < 2000 || $year > 2100) {
            $this->error("--year must be a four-digit year, got «{$this->option('year')}».");

            return self::FAILURE;
        }
        if (! in_array($domain, self::DOMAINS, true)) {
            $this->error('--domain must be one of: ' . implode(', ', self::DOMAINS) . " — got «{$domain}».");

            return self::FAILURE;
        }

        $period = strtoupper(trim((string) $this->option('period')));
        if ($period === '') {
            $period = null;
        } elseif (! RoadmapPeriod::isValid($period)) {
            $this->error("--period must be YYYY-MM or YYYY-Qn, got «{$this->option('period')}».");

            return self::FAILURE;
        }

        $range = (string) $this->option('range');
        if (! in_array($range, XlsxRoadmapParser::RANGE_MODES, true)) {
            $this->error('--range must be one of: ' . implode(' | ', XlsxRoadmapParser::RANGE_MODES) . " — got «{$range}».");

            return self::FAILURE;
        }

        $file = $this->option('file') ?: $this->defaultFile($region);
        if ($file === null) {
            return self::FAILURE;                       // defaultFile() already explained why
        }
        if (! is_file($file)) {
            $this->error("Файл топилмади: {$file}");

            return self::FAILURE;
        }

        $isXlsx = str_ends_with(mb_strtolower($file), '.xlsx');
        if (! $isXlsx && $period !== null) {
            $this->warn('--period is ignored for a .docx — the March documents carry no «Амалда» column.');
        }

        $this->info("Parsing {$file} — {$region->name_full}, {$year}, {$domain}…");
        try {
            if ($isXlsx) {
                $parsed = (new XlsxRoadmapParser($this->districtResolver($regionCode), $range))->parseFile($file);
            } else {
                $blocks = (new DocxTableReader())->read($file);
                $parsed = (new RoadmapParser($this->districtResolver($regionCode)))->parse($blocks) + ['warnings' => []];
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($parsed['title_text'] === '') {
            $this->warn('Ҳужжат сарлавҳаси топилмади — бўш сарлавҳа билан импорт қилинади.');
        }

        $this->printSummary($parsed['measures']);
        $file0 = self::lineTotals($parsed['measures']);

        if ($this->option('dry-run')) {
            $roadmap = Roadmap::where('domain', $domain)->where('region_code', $regionCode)->where('year', $year)->first();
            if ($roadmap) {
                $stats = $this->syncMeasures($roadmap, $parsed['measures'], false);
                $this->warn("Dry run: {$stats['removed']} measure(s) would be removed ({$stats['removed_with_lines']} with indicator lines); {$stats['retitled']} measure(s) with indicator lines would change title.");
            }
            if ($file0['lines'] > 0) {
                $this->info("lines: {$file0['lines']} ({$file0['planned']} with a plan), actuals: {$file0['actuals']}, notes: {$file0['notes']}");
            }
            $this->reportWarnings($parsed['warnings'], $period, $file0);
            $this->warn('Dry run — no changes written.');

            return self::SUCCESS;
        }

        $stats = DB::transaction(function () use ($parsed, $domain, $regionCode, $year, $file, $period): array {
            $roadmap = Roadmap::updateOrCreate(
                ['domain' => $domain, 'region_code' => $regionCode, 'year' => $year],
                [
                    'title_text'     => $parsed['title_text'],
                    'approvers_text' => $parsed['approvers_text'],
                    'source_file'    => basename($file),
                    'imported_at'    => now(),
                ],
            );

            $stats = $this->syncMeasures($roadmap, $parsed['measures'], true) + ['lines' => 0, 'lines_removed' => 0, 'actuals' => 0];

            // Reload after the position upsert: the rows the lines hang off may have just been created.
            $rows = $roadmap->measures()->with('lines.progress')->get()
                ->keyBy(fn (RoadmapMeasure $m) => self::position($m->section_no, $m->district_id, $m->seq_no));
            foreach ($parsed['measures'] as $data) {
                if (! array_key_exists('lines', $data)) {
                    continue;                           // the docx path defines no indicator lines
                }
                $pos = self::position($data['section_no'], $data['district_id'], $data['seq_no']);
                $row = $rows->get($pos);
                if (! $row) {
                    throw new RuntimeException("Position {$pos} vanished between the upsert and the line sync.");
                }
                $synced = MeasureLineSync::sync($row, $data['lines'], $period, $roadmap->year);
                $stats['lines']         += $synced['lines'];
                $stats['lines_removed'] += $synced['removed'];
                $stats['actuals']       += $synced['reported'];
            }

            return $stats;
        });

        $this->info('Imported ' . count($parsed['measures']) . " measures for {$region->name_full}.");
        if ($stats['removed'] > 0) {
            $this->warn("{$stats['removed']} measure(s) removed — their indicator lines and progress with them.");
        }
        if ($stats['retitled'] > 0) {
            $this->warn("{$stats['retitled']} measure(s) with indicator lines changed their title — a measure inserted or dropped mid-section shifts the numbering; check that the monitoring rows still belong to the right measures.");
        }
        if ($file0['lines'] > 0 || $stats['lines_removed'] > 0) {
            $actuals = $period !== null ? "actuals: {$stats['actuals']} (period {$period})" : "actuals: {$file0['actuals']} ignored";
            $this->info("Indicator lines: {$stats['lines']} written, {$stats['lines_removed']} removed; {$actuals}");
        }
        $this->reportWarnings($parsed['warnings'], $period, $file0);

        return self::SUCCESS;
    }

    /**
     * The one candidate to import, or the reason there is no single one. Pure so the
     * selection can be reasoned about without a data folder; defaultFile() does the globbing.
     *
     * @param  list<string> $candidates paths whose basename already matches the region number
     * @return array{file: ?string, error: ?string}
     */
    public static function pickSourceFile(array $candidates): array
    {
        $usable = array_values(array_filter($candidates, fn (string $p) => preg_match('/\.(docx|xlsx)$/i', $p) === 1));
        $legacy = array_values(array_diff($candidates, $usable));

        if (count($usable) === 1) {
            return ['file' => $usable[0], 'error' => null];
        }

        if ($usable === []) {
            return ['file' => null, 'error' => $legacy === []
                ? 'Вилоят рақами билан бошланадиган .docx/.xlsx файл топилмади — --file билан кўрсатинг.'
                : '«' . basename($legacy[0]) . '» — эски .doc формати. Word\'да .docx қилиб сақланг, сўнг --file билан кўрсатинг.'];
        }

        return ['file' => null, 'error' => 'Бир нечта мос файл топилди, --file билан танланг: '
            . implode(', ', array_map('basename', $usable))];
    }

    /**
     * Upserts $measures onto $roadmap by position (see position()): a row already at a kept
     * position is updated in place, so its id — and the indicator lines / progress hanging off
     * it — survive; a row whose position the new parse no longer uses is reported as removed
     * and, when $write, deleted. Creates and vanished positions are always disjoint sets — a
     * create only fires for a position absent from $existing, and a "gone" row is by definition
     * one still in $existing — so the delete below can run before or after the fill/create loop
     * without ever needing to race it. $write = false (dry run) computes the same stats without
     * writing anything, so the operator sees the damage before it happens.
     *
     * The xlsx parse carries a 'lines' key per measure; it is not a measure column and is
     * stripped here — MeasureLineSync writes those afterwards, against the saved rows.
     *
     * @param list<array<string,mixed>> $measures
     * @return array{removed:int, removed_with_lines:int, retitled:int}
     */
    private function syncMeasures(Roadmap $roadmap, array $measures, bool $write): array
    {
        $existing = $roadmap->measures()->withCount('lines')->get()
            ->keyBy(fn (RoadmapMeasure $m) => self::position($m->section_no, $m->district_id, $m->seq_no));

        $kept = [];
        foreach ($measures as $data) {
            $kept[self::position($data['section_no'], $data['district_id'], $data['seq_no'])] = true;
        }
        $gone = $existing->filter(fn (RoadmapMeasure $m, string $pos) => ! isset($kept[$pos]));

        $retitled = 0;
        foreach ($measures as $data) {
            $pos = self::position($data['section_no'], $data['district_id'], $data['seq_no']);
            unset($data['lines']);
            if ($row = $existing->get($pos)) {
                $row->fill($data);
                if ($row->isDirty('title') && $row->lines_count > 0) {
                    $retitled++;
                }
                if ($write) {
                    $row->save();
                }
            } elseif ($write) {
                $roadmap->measures()->create($data);
            }
        }

        if ($write) {
            $roadmap->measures()->whereIn('id', $gone->pluck('id'))->delete();
        }

        return [
            'removed'            => $gone->count(),
            'removed_with_lines' => $gone->filter(fn (RoadmapMeasure $m) => $m->lines_count > 0)->count(),
            'retitled'           => $retitled,
        ];
    }

    /**
     * Region-scoped district lookup tolerant to spelling variants (name_full,
     * name_short, alt_labels, all normalised the same way the KPI importer does).
     *
     * @return callable(string):?int
     */
    private function districtResolver(int $regionCode): callable
    {
        $map = [];
        District::where('region_code', $regionCode)->orderBy('sort_order')->get()->each(function (District $d) use (&$map): void {
            $aliases = array_merge([$d->name_full, $d->name_short], is_array($d->alt_labels) ? $d->alt_labels : []);
            foreach ($aliases as $alias) {
                $key = DistrictNameNormalizer::normalize((string) $alias);
                if ($key !== '' && ! isset($map[$key])) {
                    $map[$key] = $d->id;
                }
            }
        });

        return fn (string $name): ?int => $map[DistrictNameNormalizer::normalize($name)] ?? null;
    }

    private function defaultFile(Region $region): ?string
    {
        $dir = base_path(self::DEFAULT_DIR);
        if (preg_match('/^(\d+)/', (string) $region->folder_name, $m) !== 1) {
            $this->error("Region {$region->code} has no numeric folder_name prefix — pass --file explicitly.");

            return null;
        }

        $candidates = array_merge(
            glob($dir . '/*.doc*') ?: [],
            glob($dir . '/*.xlsx') ?: [],
            glob($dir . '/' . self::XLSX_SUBDIR . '/*.xlsx') ?: [],
        );
        // «~$8. Самарканд вилояти.xlsx» (an open-workbook lock file) never matches: it starts with «~».
        $candidates = array_values(array_filter($candidates, fn (string $p) => preg_match('/^' . $m[1] . '[.\s]/u', basename($p)) === 1));
        sort($candidates);

        $picked = self::pickSourceFile($candidates);
        if ($picked['error'] !== null) {
            $this->error($picked['error'] . " ({$dir}, «{$m[1]}»)");
        }

        return $picked['file'];
    }

    /**
     * What the file itself says about indicator lines — printed before anything is written,
     * so the dry run can talk about the actuals the import is about to ignore.
     *
     * @param  list<array<string,mixed>> $measures
     * @return array{lines:int, planned:int, actuals:int, notes:int}
     */
    private static function lineTotals(array $measures): array
    {
        $totals = ['lines' => 0, 'planned' => 0, 'actuals' => 0, 'notes' => 0];
        foreach ($measures as $m) {
            foreach ($m['lines'] ?? [] as $line) {
                $totals['lines']++;
                $totals['planned'] += $line['plan'] !== null ? 1 : 0;
                $totals['actuals'] += $line['actual'] !== null ? 1 : 0;
                $totals['notes']   += $line['note'] !== null ? 1 : 0;
            }
        }

        return $totals;
    }

    /**
     * @param list<string> $warnings
     * @param array{lines:int, planned:int, actuals:int, notes:int} $totals
     */
    private function reportWarnings(array $warnings, ?string $period, array $totals): void
    {
        foreach ($warnings as $warning) {
            $this->warn($warning);
        }
        $ignored = $totals['actuals'] + $totals['notes'];
        if ($period === null && $ignored > 0) {
            $this->warn("{$ignored} «Амалда»/«Изоҳ» value(s) ignored — pass --period=YYYY-MM to import them");
        }
    }

    /** @param list<array<string,mixed>> $measures */
    private function printSummary(array $measures): void
    {
        $bySection  = [];
        $byDistrict = [];
        foreach ($measures as $m) {
            $k = $m['section_no'] . '. ' . $m['section_title'];
            $bySection[$k] = ($bySection[$k] ?? 0) + 1;
            if ($m['district_id'] !== null) {
                $byDistrict[$m['district_id']] = ($byDistrict[$m['district_id']] ?? 0) + 1;
            }
        }

        $rows = [];
        foreach ($bySection as $k => $n) {
            $rows[] = [$k, $n];
        }
        $this->table(['Бўлим', 'Тадбирлар'], $rows);

        if ($byDistrict !== []) {
            $names = District::whereIn('id', array_keys($byDistrict))->pluck('name_full', 'id');
            $rows  = [];
            foreach ($byDistrict as $id => $n) {
                $rows[] = [$names[$id] ?? $id, $n];
            }
            $this->table(['Туман', 'Тадбирлар'], $rows);
        }

        $this->info('Total: ' . count($measures) . ' measures, ' . count($byDistrict) . ' districts.');
    }

    /**
     * Position inside one road map: section:districtId:seq (district 0 = region-level).
     * Uses district ids, not SOATO codes — the xlsx-facing RoadmapKey is a different string.
     */
    private static function position(int $sectionNo, ?int $districtId, int $seqNo): string
    {
        return $sectionNo . ':' . ($districtId ?? 0) . ':' . $seqNo;
    }
}
