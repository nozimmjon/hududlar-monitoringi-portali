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
        self::assertMonth($period);
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

        $measures = RoadmapMeasure::whereIn('id', $owned->pluck('roadmap_measure_id')->unique())
            ->with('lines.progress')->orderBy('source_row')->get();

        $changes = 0;
        DB::transaction(function () use ($measures, $parsed, $period, $roadmap, $user, &$changes): void {
            foreach ($measures as $measure) {
                $rows = [];
                foreach ($measure->lines as $line) {
                    $r      = ProgressSheet::resolveLine($line, $period);
                    $edit   = $parsed[$line->id] ?? [];
                    $actual = array_key_exists('actual', $edit) ? $edit['actual'] : $r['actual'];
                    $note   = array_key_exists('note', $edit) ? $edit['note'] : $r['note'];
                    $rows[] = ['label' => $line->label, 'unit' => $line->unit, 'plan' => $r['plan'], 'actual' => $actual, 'note' => $note];

                    if (array_key_exists('actual', $edit) && MeasureLineSync::differs($r['saved_actual'], $actual)) {
                        self::log($user, $roadmap, $measure, $line, $period, 'actual', self::num($r['saved_actual']), self::num($actual));
                        $changes++;
                    }
                    if (array_key_exists('note', $edit) && (string) $r['saved_note'] !== (string) $note) {
                        self::log($user, $roadmap, $measure, $line, $period, 'note', $r['saved_note'], $note);
                        $changes++;
                    }
                }
                MeasureLineSync::sync($measure, $rows, $period, $roadmap->year);
            }
        });

        return ['measures' => $measures->count(), 'changes' => $changes];
    }

    /**
     * Every measure that has lines and is not yet saved for the period gets its
     * carried values written as the period's report (lines without any value stay
     * null). One `confirm` entry per measure. Returns how many were written.
     */
    public static function confirmRest(Roadmap $roadmap, string $period, User $user): int
    {
        self::assertMonth($period);
        $sheet = ProgressSheet::build($roadmap, $period);
        $ids   = [];
        foreach ($sheet['measures'] as $m) {
            if ($m['state'] !== 'saved' && $m['lines'] !== []) {
                $ids[] = $m['id'];
            }
        }
        if ($ids === []) {
            return 0;
        }

        $measures = RoadmapMeasure::whereIn('id', $ids)->with('lines.progress')->orderBy('source_row')->get();
        DB::transaction(function () use ($measures, $period, $roadmap, $user): void {
            foreach ($measures as $measure) {
                $rows = [];
                foreach ($measure->lines as $line) {
                    $r      = ProgressSheet::resolveLine($line, $period);
                    $rows[] = ['label' => $line->label, 'unit' => $line->unit, 'plan' => $r['plan'], 'actual' => $r['actual'], 'note' => $r['note']];
                }
                MeasureLineSync::sync($measure, $rows, $period, $roadmap->year);
                self::log($user, $roadmap, $measure, null, $period, 'confirm', null, count($rows) . ' та қатор');
            }
        });

        return $measures->count();
    }

    private static function assertMonth(string $period): void
    {
        if (! RoadmapPeriod::isValid($period) || RoadmapPeriod::type($period) !== 'month') {
            throw new InvalidArgumentException("Нотўғри давр «{$period}» — YYYY-MM кутилади.");
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
