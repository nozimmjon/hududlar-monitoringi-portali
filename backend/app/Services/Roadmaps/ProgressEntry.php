<?php

namespace App\Services\Roadmaps;

use App\Models\Roadmap;
use App\Models\RoadmapChangeLog;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use App\Models\User;
use App\Support\Roadmaps\EntryValue;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Write side of the admin panel. Every write goes through MeasureLineSync::sync() —
 * the same path the importer uses — so the page can never disagree with the importer,
 * and every write logs to roadmap_change_log.
 *
 * The edit buffer comes from the panel's own Alpine code and is trusted to be well-shaped
 * (line ids as keys, `actual`/`note` strings); odd shapes are refused or ignored, not sanitised.
 */
final class ProgressEntry
{
    /**
     * Writes every measure that owns an edited line, as a whole: edited lines get the
     * typed value, its other lines their saved-or-carried value. Untouched measures are
     * left alone.
     *
     * @param  array<int|string, array{actual?: ?string, note?: ?string}> $edits keyed by line id; a missing key = unchanged
     * @return array{measures: int, changes: int}
     * @throws InvalidArgumentException naming the offending line — nothing is written
     */
    public static function save(Roadmap $roadmap, string $period, array $edits, User $user): array
    {
        self::assertMonth($roadmap, $period);
        if ($edits === []) {
            return ['measures' => 0, 'changes' => 0];
        }

        $lineIds = array_map('intval', array_keys($edits));
        $owned   = RoadmapMeasureLine::whereIn('id', $lineIds)
            ->whereHas('measure', fn ($q) => $q->where('roadmap_id', $roadmap->id))
            ->get()->keyBy('id');
        foreach ($lineIds as $id) {
            if (! $owned->has($id)) {
                throw new InvalidArgumentException("Индикатор #{$id} бу йўл харитага тегишли эмас.");
            }
        }

        // Parse everything first: a bad number aborts before anything is written.
        $parsed = [];
        foreach ($edits as $id => $edit) {
            $edit  = (array) $edit;
            $entry = [];
            if (array_key_exists('actual', $edit)) {
                try {
                    $entry['actual'] = EntryValue::parse($edit['actual'] === null ? null : (string) $edit['actual']);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException("«{$owned[(int) $id]->label}»: {$e->getMessage()}");
                }
            }
            if (array_key_exists('note', $edit)) {
                $entry['note'] = self::note($edit['note']);
            }
            $parsed[(int) $id] = $entry;
        }
        $measureIds = $owned->pluck('roadmap_measure_id')->unique();

        $measuresCount = 0;
        $changes       = 0;
        DB::transaction(function () use ($measureIds, $parsed, $period, $roadmap, $user, &$measuresCount, &$changes): void {
            // Read inside the transaction: the old values logged below must come from the
            // same snapshot MeasureLineSync::sync() is about to overwrite.
            $measures = RoadmapMeasure::whereIn('id', $measureIds)->with('lines.progress')->orderBy('source_row')->get();
            foreach ($measures as $measure) {
                foreach ($measure->lines as $line) {
                    $edit = $parsed[$line->id] ?? [];
                    if ($edit === []) {
                        continue;                          // untouched line: rowsFor() below carries it forward, nothing to log
                    }
                    $r = ProgressSheet::resolveLine($line, $period);
                    if (array_key_exists('actual', $edit) && MeasureLineSync::differs($r['saved_actual'], $edit['actual'])) {
                        self::log($user, $roadmap, $measure, $line, $period, 'actual', self::num($r['saved_actual']), self::num($edit['actual']));
                        $changes++;
                    }
                    if (array_key_exists('note', $edit) && (string) $r['saved_note'] !== (string) $edit['note']) {
                        self::log($user, $roadmap, $measure, $line, $period, 'note', $r['saved_note'], $edit['note']);
                        $changes++;
                    }
                }
                MeasureLineSync::sync($measure, self::rowsFor($measure, $period, $parsed), $period, $roadmap->year);
            }
            $measuresCount = $measures->count();
        });

        return ['measures' => $measuresCount, 'changes' => $changes];
    }

    /**
     * Every measure that has lines and is not yet saved for the period gets its
     * carried values written as the period's report (lines without any value stay
     * null). One `confirm` entry per measure. Returns how many were written.
     */
    public static function confirmRest(Roadmap $roadmap, string $period, User $user): int
    {
        self::assertMonth($roadmap, $period);

        $count = 0;
        DB::transaction(function () use ($period, $roadmap, $user, &$count): void {
            // Read inside the transaction: same reason as save() — the sheet that decides
            // who is confirmable must be the sheet MeasureLineSync::sync() is about to act on.
            $ids = ProgressSheet::confirmableIds(ProgressSheet::build($roadmap, $period));
            if ($ids === []) {
                return;
            }

            $measures = RoadmapMeasure::whereIn('id', $ids)->with('lines.progress')->orderBy('source_row')->get();
            foreach ($measures as $measure) {
                $rows = self::rowsFor($measure, $period);
                MeasureLineSync::sync($measure, $rows, $period, $roadmap->year);
                self::log($user, $roadmap, $measure, null, $period, 'confirm', null, count($rows) . ' та қатор');
            }
            $count = $measures->count();
        });

        return $count;
    }

    /**
     * The measure's complete line set, shaped as MeasureLineSync::sync() expects it: a line
     * present in $parsed (keyed by line id) gets its edited value, every other line its
     * current saved-or-carried value. `plan` is the line's stored decimal string, not
     * resolveLine()'s rounded float — so a save never nudges plan_value or fires a spurious
     * repct recompute on a measure nobody touched the plan of.
     *
     * @param  array<int, array{actual?: ?float, note?: ?string}> $parsed keyed by line id; a missing key = unchanged
     * @return list<array{label: string, unit: ?string, plan: ?string, actual: ?float, note: ?string}>
     */
    private static function rowsFor(RoadmapMeasure $measure, string $period, array $parsed = []): array
    {
        $rows = [];
        foreach ($measure->lines as $line) {
            $r      = ProgressSheet::resolveLine($line, $period);
            $edit   = $parsed[$line->id] ?? [];
            $actual = array_key_exists('actual', $edit) ? $edit['actual'] : $r['actual'];
            $note   = array_key_exists('note', $edit) ? $edit['note'] : $r['note'];
            $rows[] = ['label' => $line->label, 'unit' => $line->unit, 'plan' => $line->plan_value, 'actual' => $actual, 'note' => $note];
        }

        return $rows;
    }

    /** A month within the road map's own year — a save can never land on another year's period. */
    private static function assertMonth(Roadmap $roadmap, string $period): void
    {
        if (! RoadmapPeriod::isValid($period) || RoadmapPeriod::type($period) !== 'month') {
            throw new InvalidArgumentException("Нотўғри давр «{$period}» — YYYY-MM кутилади.");
        }
        if ((int) substr($period, 0, 4) !== (int) $roadmap->year) {
            throw new InvalidArgumentException("Нотўғри давр «{$period}» — {$roadmap->year} йилнинг YYYY-MM ойи кутилади.");
        }
    }

    private static function note(mixed $raw): ?string
    {
        $s = trim((string) $raw);

        return $s === '' ? null : mb_substr($s, 0, 500);
    }

    /** '7,8' for the log, null stays null ('0' must survive — never use `?:` here). */
    private static function num(null|float|int|string $v): ?string
    {
        return $v === null ? null : EntryValue::format($v);
    }

    private static function log(User $user, Roadmap $roadmap, RoadmapMeasure $measure, ?RoadmapMeasureLine $line, ?string $period, string $action, ?string $old, ?string $new): void
    {
        RoadmapChangeLog::record([
            'user_id'                 => $user->id,
            'roadmap_id'              => $roadmap->id,
            'roadmap_measure_id'      => $measure->id,
            'roadmap_measure_line_id' => $line?->id,
            'line_label'              => $line?->label,      // snapshot: history stays readable after the line is removed
            'report_period'           => $period,
            'action'                  => $action,
            'old_value'               => $old,
            'new_value'               => $new,
        ]);
    }
}
