<?php

namespace App\Services\Roadmaps;

use App\Console\Commands\ImportRoadmap;
use App\Models\Roadmap;
use App\Models\RoadmapChangeLog;
use App\Models\User;
use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
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
            return ImportReport::failed($name, "--year тўрт хонали йил бўлиши керак, «{$year}» берилди.");
        }
        if (! in_array($domain, ImportRoadmap::DOMAINS, true)) {
            return ImportReport::failed($name, '--domain қуйидагилардан бири бўлиши керак: ' . implode(', ', ImportRoadmap::DOMAINS) . " — «{$domain}» берилди.");
        }

        $book = null;
        try {
            $book   = IOFactory::load($file);
            $parsed = (new RoadmapProgressReader())->read($book);
        } catch (SpreadsheetException $e) {
            // PhpSpreadsheet refuses an unreadable or corrupt file in English, naming its own
            // internals («Unable to identify a reader for this file») — say it in the operator's
            // language and keep the original in brackets for the log.
            return ImportReport::failed($name, "Файлни ўқиб бўлмади — бу .xlsx шаблон эмасми? ({$e->getMessage()})");
        } catch (RuntimeException $e) {
            // The reader's own aborts (already Cyrillic, naming sheet and cell) land here, so a
            // structurally broken file comes back as a report the operator can read instead of a 500.
            return ImportReport::failed($name, $e->getMessage());
        } finally {
            $book?->disconnectWorksheets();          // a road-map workbook is small, but nothing below needs it
            unset($book);
        }

        // First: is this a template at all? A workbook with no keys in column A is the wrong file,
        // and saying so beats a complaint about its missing «Ҳисобот даври».
        if ($parsed['blocks'] === []) {
            return ImportReport::failed($name, 'Файлда индикатор қаторлари йўқ (A устунида калитлар йўқ) — бу roadmap:template файли эмасми?');
        }

        $option = strtoupper(trim((string) $periodOption));
        $period = $option !== '' ? $option : (string) $parsed['period'];
        if (! RoadmapPeriod::isValid($period)) {
            return ImportReport::failed($name, 'Ҳисобот даври аниқланмади — --period ни YYYY-MM ёки YYYY-Qn кўринишида беринг, ёки варақ сарлавҳасида «Ҳисобот даври: YYYY-MM» бўлсин.');
        }
        if ($option !== '' && $parsed['period'] !== null && $parsed['period'] !== $period) {
            return ImportReport::failed($name, "--period {$period} файлнинг ўз даврига ({$parsed['period']}) мос келмади.");
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
                return ImportReport::failed($name, "{$regionCode} вилояти учун {$year} йил {$domain} йўл харитаси йўқ (" . array_key_first($blocks) . ' каби калитлар) — аввал import:roadmap ишга туширинг.');
            }
            $measures = [];
            foreach ($roadmap->measures as $m) {
                $measures[RoadmapKey::make($regionCode, $m->section_no, $m->district?->code, $m->seq_no)] = $m;
            }
            $items = [];
            foreach ($blocks as $key => $block) {
                $m = $measures[$key] ?? null;
                if (! $m) {
                    return ImportReport::failed($name, "{$block['sheet']}!A{$block['row']}: номаълум калит {$key} (A устуни) — шаблон бошқа йўл харитага тегишлими?");
                }
                if ($block['lines'] === [] && $m->lines->isNotEmpty()) {
                    return ImportReport::failed($name, "{$block['sheet']}!A{$block['row']}: {$key} чора-тадбирида сақланган {$m->lines->count()} та индикатор қатори бор, файлда эса бирортаси ҳам йўқ — файл тўлиқ эмасми?");
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

            return ImportReport::failed($name, $e->getMessage() . ' — ҳеч нарса ёзилмади.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return new ImportReport($period, $name, count($parsed['blocks']), $regions, self::warnings($regions, $period), null, $write);
    }

    /** The operator warnings, one sentence each — the command prints them, the upload panel renders them verbatim. @return list<string> */
    public static function warnings(array $regions, string $period): array
    {
        $out = [];
        foreach ($regions as $n) {
            if ($n['removed'] > 0) {
                $out[] = "{$n['region']}: {$n['removed']} та индикатор қатори файлда йўқ — ўчирилади (тарихи билан бирга).";
            }
            if ($n['relabeled'] > 0) {
                $out[] = "{$n['region']}: ҳисоботи бор {$n['relabeled']} та индикатор қатори қайта номланди — блок ўртасига қўшилган қатор рақамлашни суради; тарих ўз индикаторида қолганини текширинг.";
            }
            if ($n['cleared'] > 0) {
                $out[] = "{$n['region']}: {$n['cleared']} та аввал киритилган «Амалда» қиймати бу файлда бўш — ўчирилади.";
            }
            if ($n['repct'] > 0) {
                $out[] = "{$n['region']}: режа ўзгаргани учун {$n['repct']} та ҳисобот фоизи қайта ҳисобланди.";
            }
            if ($n['blank_advance'] > 0) {
                $out[] = "{$n['region']}: {$n['blank_advance']} та чора-тадбир {$period} даврига «Амалда» қийматисиз ўтди — янги давр учун тўлдирилмаган шаблон юкланганми? Тўлдирилган файл юкланмагунча уларнинг ҳолати Бажарилмоқда бўлиб қолади.";
            }
            if ($n['missing'] > 0) {
                $out[] = "{$n['region']}: {$n['total']} та чора-тадбирдан {$n['missing']} таси файлда йўқ — ўзгаришсиз қолдирилди.";
            }
        }

        return $out;
    }
}
