<?php

namespace App\Services\Roadmaps;

use App\Models\Roadmap;
use App\Models\RoadmapChangeLog;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use App\Models\User;
use App\Support\Roadmaps\EntryValue;
use App\Support\Roadmaps\RoadmapPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Write side of the admin panel. save() and confirmRest() go through
 * MeasureLineSync::sync() — the same path the importer uses — so the page can never
 * disagree with the importer.
 *
 * correct() is the exception: it writes the line definitions itself and recomputes through
 * MeasureRecomputer. sync() keys lines by POSITION, so handing it a reordered (or shortened)
 * list would move ids — and the reported history hanging off them — onto the neighbouring
 * indicator; correct() exists precisely to reorder, rename and remove lines by id.
 *
 * Every write logs to roadmap_change_log.
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
            // Read inside the transaction: as late as possible, and committed atomically with the
            // writes below, so the logged old values and the rows they describe land together.
            // Not one snapshot, though — on READ COMMITTED another session's commit between this
            // read and the sync would still be seen; that would want lockForUpdate().
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
            // Read inside the transaction — same reason as save(): as late as possible, and
            // committed atomically with the writes. Not a snapshot either (READ COMMITTED); a
            // measure saved by someone else right after this read would be confirmed on top.
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

    /** Lines are renumbered through this offset first so the unique (measure, line_no) index never collides mid-way. */
    private const RENUMBER_OFFSET = 10000;

    /**
     * Replace the measure's indicator definitions with $lines (complete, in order):
     * existing lines are updated (label/unit/plan), new ones (id null) created, lines
     * missing from the list removed (their progress rows go with them), then line_no is
     * renumbered 1..n. A changed plan rewrites the line's pct_of_plan for every
     * reported period. Every change is logged; the measure is recomputed at the end.
     *
     * @param list<array{id?: int|string|null, label?: ?string, unit?: ?string, plan?: ?string}> $lines
     * @throws InvalidArgumentException naming the row («2-қатор: …») — nothing is written
     */
    public static function correct(RoadmapMeasure $measure, array $lines, User $user): void
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Камида битта индикатор қолсин.');
        }

        DB::transaction(function () use ($measure, $lines, $user): void {
            // Read inside the transaction: as late as possible, and committed atomically with the
            // writes below. Validation still runs before anything is written — it only throws, and
            // a throw out of the closure rolls back a transaction that has written nothing yet.
            $measure->load(['lines.progress', 'roadmap']);
            $roadmap  = $measure->roadmap;
            $existing = $measure->lines->keyBy('id');

            $clean = [];
            $seen  = [];
            foreach (array_values($lines) as $i => $l) {
                $no    = $i + 1;
                $label = trim((string) ($l['label'] ?? ''));
                if ($label === '') {
                    throw new InvalidArgumentException("{$no}-қатор: индикатор номи бўш.");
                }
                if (mb_strlen($label) > RoadmapMeasureLine::LABEL_MAX) {
                    throw new InvalidArgumentException("{$no}-қатор: индикатор номи жуда узун (" . mb_strlen($label) . ' белги).');
                }
                $unit = trim((string) ($l['unit'] ?? ''));
                if (mb_strlen($unit) > RoadmapMeasureLine::UNIT_MAX) {
                    throw new InvalidArgumentException("{$no}-қатор: ўлчов " . RoadmapMeasureLine::UNIT_MAX . ' белгидан ошмасин.');
                }
                try {
                    $plan = EntryValue::parse(isset($l['plan']) ? (string) $l['plan'] : null);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException("{$no}-қатор: режа — {$e->getMessage()}.");
                }
                $id = isset($l['id']) && $l['id'] !== '' ? (int) $l['id'] : null;
                if ($id !== null && ! $existing->has($id)) {
                    throw new InvalidArgumentException("{$no}-қатор: индикатор #{$id} бу чора-тадбирга тегишли эмас.");
                }
                if ($id !== null && isset($seen[$id])) {
                    throw new InvalidArgumentException("{$no}-қатор: индикатор #{$id} рўйхатда икки марта.");
                }
                if ($id !== null) {
                    $seen[$id] = true;
                }
                $clean[] = ['id' => $id, 'label' => $label, 'unit' => $unit === '' ? null : $unit, 'plan' => $plan];
            }

            foreach ($existing as $line) {
                if (isset($seen[$line->id])) {
                    continue;
                }
                $months = $line->progress->whereNotNull('actual_value')->count();
                self::log($user, $roadmap, $measure, $line, null, 'line_removed',
                    self::describe($line->label, $line->unit, $line->plan_value) . " · {$months} ой", null);
                $line->delete();                                   // progress rows cascade
            }

            $models = [];
            foreach ($clean as $i => $l) {
                $no = $i + 1;
                if ($l['id'] === null) {
                    $line = new RoadmapMeasureLine([
                        'roadmap_measure_id' => $measure->id, 'line_no' => self::RENUMBER_OFFSET + $no,
                        'label' => $l['label'], 'unit' => $l['unit'], 'plan_value' => $l['plan'],
                    ]);
                    $line->save();
                    self::log($user, $roadmap, $measure, $line, null, 'line_added', null, self::describe($l['label'], $l['unit'], $l['plan']));
                } else {
                    $line = $existing[$l['id']];
                    if ($line->label !== $l['label']) {
                        self::log($user, $roadmap, $measure, $line, null, 'label', $line->label, $l['label']);
                    }
                    if ((string) $line->unit !== (string) $l['unit']) {
                        self::log($user, $roadmap, $measure, $line, null, 'unit', $line->unit, $l['unit']);
                    }
                    $planChanged = MeasureLineSync::differs($line->plan_value, $l['plan']);
                    if ($planChanged) {
                        self::log($user, $roadmap, $measure, $line, null, 'plan', self::num($line->plan_value), self::num($l['plan']));
                    }
                    $line->fill(['line_no' => self::RENUMBER_OFFSET + $no, 'label' => $l['label'], 'unit' => $l['unit'], 'plan_value' => $l['plan']])->save();
                    if ($planChanged) {
                        foreach ($line->progress as $p) {
                            $pct = MeasureRecomputer::pctOfPlan($l['plan'], $p->actual_value);
                            if (MeasureLineSync::differs($p->pct_of_plan, $pct)) {
                                $p->fill(['pct_of_plan' => $pct])->save();
                            }
                        }
                    }
                }
                $models[$no] = $line;
            }
            foreach ($models as $no => $line) {
                $line->fill(['line_no' => $no])->save();
            }

            $measure->unsetRelation('lines');
            (new MeasureRecomputer())->recompute($measure, $roadmap->year);
        });
    }

    /**
     * The measure's latest changes, newest first, with user and line loaded.
     *
     * @return Collection<int, RoadmapChangeLog>
     */
    public static function history(RoadmapMeasure $measure, int $limit = 30): Collection
    {
        return RoadmapChangeLog::where('roadmap_measure_id', $measure->id)
            ->with(['user', 'line'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit($limit)->get();
    }

    /** «label · unit · plan» for the line_added / line_removed entries. */
    private static function describe(string $label, ?string $unit, null|float|int|string $plan): string
    {
        return $label . ' · ' . ($unit ?? '—') . ' · ' . ($plan === null ? '—' : EntryValue::format($plan));
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

    /**
     * One line, always: the grid's «Изоҳ» is a single-line input, so a note imported from an
     * xlsx cell with hard breaks is flattened here rather than losing its tail on the next save.
     */
    private static function note(mixed $raw): ?string
    {
        $s = (string) preg_replace('/\s+/u', ' ', trim((string) $raw));

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
