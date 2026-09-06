<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Region;
use App\Models\Roadmap;
use App\Services\Roadmaps\DocxTableReader;
use App\Services\Roadmaps\RoadmapParser;
use App\Support\Import\DistrictNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportRoadmap extends Command
{
    /** Relative to base_path(); the docx files are named "<N>. <Region> …docx" where N = region folder number. */
    public const DEFAULT_DIR = '../data/Сув хўжалиги бўйича йўл хариталар';

    public const DOMAINS = ['water'];

    protected $signature = 'import:roadmap
        {--region= : SOATO region code, e.g. 1733 (Хоразм)}
        {--file= : Path to the .docx (default: the one file under data/Сув хўжалиги бўйича йўл хариталар/ whose name starts with the region folder number)}
        {--year=2026 : Road-map year}
        {--domain=water : Road-map family}
        {--dry-run : Parse and print the summary without writing}';

    protected $description = 'Import a regional "ЙЎЛ ХАРИТАСИ" (water-management measures) .docx into roadmaps / roadmap_measures.';

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
            $this->error("--domain must be one of: " . implode(', ', self::DOMAINS) . " — got «{$domain}».");
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

        $this->info("Parsing {$file} — {$region->name_full}, {$year}, {$domain}…");
        try {
            $blocks = (new DocxTableReader())->read($file);
            $parsed = (new RoadmapParser($this->districtResolver($regionCode)))->parse($blocks);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($parsed['title_text'] === '') {
            $this->warn('Ҳужжат сарлавҳаси топилмади (жадваллар орасида матн йўқ) — бўш сарлавҳа билан импорт қилинади.');
        }

        $this->printSummary($parsed['measures']);

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($parsed, $domain, $regionCode, $year, $file): void {
            $roadmap = Roadmap::updateOrCreate(
                ['domain' => $domain, 'region_code' => $regionCode, 'year' => $year],
                [
                    'title_text'     => $parsed['title_text'],
                    'approvers_text' => $parsed['approvers_text'],
                    'source_file'    => basename($file),
                    'imported_at'    => now(),
                ],
            );
            $roadmap->measures()->delete();             // full replace — the docx is the source of truth
            foreach ($parsed['measures'] as $m) {
                $roadmap->measures()->create($m);
            }
        });

        $this->info('Imported ' . count($parsed['measures']) . " measures for {$region->name_full}.");

        return self::SUCCESS;
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
        $prefixed = fn (string $p) => preg_match('/^' . $m[1] . '[.\s]/u', basename($p)) === 1;
        $all      = array_values(array_filter(glob($dir . '/*.doc*') ?: [], $prefixed));
        $docx     = array_values(array_filter($all, fn (string $p) => str_ends_with(mb_strtolower($p), '.docx')));
        $doc      = array_values(array_diff($all, $docx));

        if (count($docx) === 1) {
            return $docx[0];
        }

        if ($docx === [] && $doc !== []) {
            $this->error('«' . basename($doc[0]) . '» — эски .doc формати. Word\'да .docx қилиб сақланг, сўнг --file билан кўрсатинг.');
            return null;
        }

        $this->error(count($docx) === 0
            ? "No .docx starting with «{$m[1]}» in {$dir} — pass --file."
            : "Several .docx start with «{$m[1]}» in {$dir} — pass --file.");

        return null;
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
}
