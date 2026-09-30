<?php

namespace App\Services\Roadmaps;

use App\Console\Commands\ImportRoadmap;
use App\Models\Roadmap;
use App\Models\RoadmapChangeLog;
use App\Models\User;
use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * import:roadmap-progress as a service: reads a filled template, resolves every key,
 * writes definitions + the period's actuals through MeasureLineSync, and reports.
 * write = false does everything inside a transaction and rolls it back (the dry run).
 * A successful write logs one `import` entry per region (user null = terminal).
 */
final class ProgressImport
{
    public function run(string $file, ?string $periodOption, int $year, string $domain, bool $write, ?User $user = null, ?string $displayName = null): ImportReport
    {
        $name = $displayName ?? basename($file);
        if (! is_file($file)) {
            return ImportReport::failed($name, "Файл топилмади: {$file}");
        }
        if ($year < 2000 || $year > 2100) {
            return ImportReport::failed($name, "--year must be a four-digit year, got «{$year}».");
        }
        if (! in_array($domain, ImportRoadmap::DOMAINS, true)) {
            return ImportReport::failed($name, '--domain must be one of: ' . implode(', ', ImportRoadmap::DOMAINS) . " — got «{$domain}».");
        }

        $book = null;
        try {
            $book   = IOFactory::load($file);
            $parsed = (new RoadmapProgressReader())->read($book);
        } catch (RuntimeException $e) {
            return ImportReport::failed($name, $e->getMessage());
        } finally {
            $book?->disconnectWorksheets();          // a road-map workbook is small, but nothing below needs it
            unset($book);
        }

        $option = strtoupper(trim((string) $periodOption));
        $period = $option !== '' ? $option : (string) $parsed['period'];
        if (! RoadmapPeriod::isValid($period)) {
            return ImportReport::failed($name, 'Provide --period as YYYY-MM or YYYY-Qn, or a sheet title carrying «Ҳисобот даври: YYYY-MM».');
        }
        if ($option !== '' && $parsed['period'] !== null && $parsed['period'] !== $period) {
            return ImportReport::failed($name, "--period {$period} does not match the file's own period {$parsed['period']}.");
        }
        if ($parsed['blocks'] === []) {
            return ImportReport::failed($name, 'No indicator rows found (no keys in column A).');
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
                return ImportReport::failed($name, "Region {$regionCode} has no road map for {$domain}/{$year} (keys like " . array_key_first($blocks) . ') — run import:roadmap first.');
            }
            $measures = [];
            foreach ($roadmap->measures as $m) {
                $measures[RoadmapKey::make($regionCode, $m->section_no, $m->district?->code, $m->seq_no)] = $m;
            }
            $items = [];
            foreach ($blocks as $key => $block) {
                $m = $measures[$key] ?? null;
                if (! $m) {
                    return ImportReport::failed($name, "{$block['sheet']}!A{$block['row']}: unknown key {$key} — the road map has no such measure.");
                }
                if ($block['lines'] === [] && $m->lines->isNotEmpty()) {
                    return ImportReport::failed($name, "{$block['sheet']}!A{$block['row']}: measure {$key} has {$m->lines->count()} stored lines but no rows in the file — truncated file?");
                }
                $items[] = [$m, $block];
            }
            $work[] = ['roadmap' => $roadmap, 'items' => $items];
        }

        $regions = [];
        DB::beginTransaction();
        try {
            foreach ($work as $entry) {
                $s = ['region' => $entry['roadmap']->region->name_full, 'in_file' => 0, 'total' => $entry['roadmap']->measures->count(),
                    'lines' => 0, 'removed' => 0, 'reported' => 0, 'relabeled' => 0, 'cleared' => 0, 'repct' => 0, 'blank_advance' => 0,
                    'done' => 0, 'in_progress' => 0, 'open' => 0, 'missing' => 0];
                foreach ($entry['items'] as [$measure, $block]) {
                    $s['in_file']++;
                    $r = MeasureLineSync::sync($measure, $block['lines'], $period, $entry['roadmap']->year);
                    foreach (['lines', 'removed', 'reported', 'relabeled', 'cleared', 'repct'] as $k) {
                        $s[$k] += $r[$k];
                    }
                    $s[$r['status']]++;
                    $s['blank_advance'] += $r['blank_advance'] ? 1 : 0;
                }
                $s['missing'] = $s['total'] - $s['in_file'];
                if ($write) {
                    RoadmapChangeLog::record([
                        'user_id' => $user?->id, 'roadmap_id' => $entry['roadmap']->id, 'report_period' => $period, 'action' => 'import',
                        'new_value' => "{$name} · {$s['in_file']} та чора-тадбир · {$s['reported']} та амалда",
                    ]);
                }
                $regions[] = $s;
            }
            $write ? DB::commit() : DB::rollBack();
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return ImportReport::failed($name, $e->getMessage() . ' — nothing written.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return new ImportReport($period, $name, count($parsed['blocks']), $regions, self::warnings($regions, $period), null, $write);
    }

    /** The operator warnings, one sentence each (identical to what the command printed before the refactor). @return list<string> */
    public static function warnings(array $regions, string $period): array
    {
        $out = [];
        foreach ($regions as $n) {
            if ($n['removed'] > 0) {
                $out[] = "{$n['region']}: {$n['removed']} line(s) removed — no longer in the file (their history went with them).";
            }
            if ($n['relabeled'] > 0) {
                $out[] = "{$n['region']}: {$n['relabeled']} line(s) with reported history changed their label — a row inserted mid-block shifts the numbering; check that the history still belongs to the right indicator.";
            }
            if ($n['cleared'] > 0) {
                $out[] = "{$n['region']}: {$n['cleared']} previously reported «Амалда» value(s) cleared by this file.";
            }
            if ($n['repct'] > 0) {
                $out[] = "{$n['region']}: {$n['repct']} reported percentage(s) recomputed after a plan change.";
            }
            if ($n['blank_advance'] > 0) {
                $out[] = "{$n['region']}: {$n['blank_advance']} measure(s) advanced to {$period} with no «Амалда» values — an unfilled template imported for a new period? Their status fell back to Бажарилмоқда until the filled file is imported.";
            }
            if ($n['missing'] > 0) {
                $out[] = "{$n['missing']} of {$n['total']} measure(s) of {$n['region']} are not in the file — left untouched.";
            }
        }

        return $out;
    }
}
