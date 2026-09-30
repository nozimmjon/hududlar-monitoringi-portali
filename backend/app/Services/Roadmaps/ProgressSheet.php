<?php

namespace App\Services\Roadmaps;

use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use App\Support\Roadmaps\RoadmapDeadline;
use App\Support\Roadmaps\RoadmapPeriod;

/**
 * Read side of the admin entry grid: one region + one month → every measure with every
 * line resolved to what the cell shows and where it came from.
 *
 * Per line, for period P:
 *   1. a progress row for P exists            → 'saved'  (its actual/note; a null actual is an empty saved cell, not a carry)
 *   2. else the latest earlier row with a value → 'carry'  (actual/note from it, source_period = its period)
 *   3. else                                     → 'empty'
 * Measure state: 'saved' when any line is saved (saves are whole-measure), else 'carry'
 * when any line carries, else 'empty'.
 */
final class ProgressSheet
{
    public const TZ = 'Asia/Tashkent';

    /** «YYYY-MM» of the current month where the people typing live. */
    public static function currentPeriod(): string
    {
        return now(self::TZ)->format('Y-m');
    }

    /**
     * Months the panel offers: from January of the road-map year up to the current
     * month, never past December of the year, newest first.
     *
     * @return list<string>
     */
    public static function periodOptions(int $year, ?string $current = null): array
    {
        $first = RoadmapPeriod::monthIndex(RoadmapPeriod::fromYearMonth($year, 1));
        $last  = max($first, min(
            RoadmapPeriod::monthIndex($current ?? self::currentPeriod()),
            RoadmapPeriod::monthIndex(RoadmapPeriod::fromYearMonth($year, 12)),
        ));

        $out = [];
        for ($idx = $last; $idx >= $first; $idx--) {
            $y = intdiv($idx - 1, 12);
            $out[] = RoadmapPeriod::fromYearMonth($y, $idx - $y * 12);
        }

        return $out;
    }

    /**
     * @return array{period: string, year: int, measures: list<array<string, mixed>>, counts: array{saved: int, carry: int, empty: int, confirmable: int}}
     */
    public static function build(Roadmap $roadmap, string $period): array
    {
        $pIdx = RoadmapPeriod::monthIndex($period);
        // Later-year rows can never be a 'saved' match or an earlier-period carry source, so the
        // eager load skips them; bounded by year (not by period) because a lexical period bound
        // would wrongly drop a quarter like '2026-Q2', which sorts after '2026-06'.
        $measures = $roadmap->measures()
            ->with(['district', 'lines.progress' => fn ($q) => $q->where('report_period', '<', ((int) substr($period, 0, 4) + 1) . '-')])
            ->orderBy('source_row')
            ->get();

        $out    = [];
        $counts = ['saved' => 0, 'carry' => 0, 'empty' => 0, 'confirmable' => 0];
        foreach ($measures as $m) {
            $lines    = [];
            $anySaved = false;
            $anyCarry = false;
            foreach ($m->lines as $l) {
                $r = self::resolveLine($l, $period, $pIdx);
                $lines[]   = $r;
                $anySaved  = $anySaved || $r['state'] === 'saved';
                $anyCarry  = $anyCarry || $r['state'] === 'carry';
            }
            $state = $anySaved ? 'saved' : ($anyCarry ? 'carry' : 'empty');
            $counts[$state]++;
            if ($state !== 'saved' && $lines !== []) {
                $counts['confirmable']++;
            }
            $out[] = self::measureRow($m, $roadmap->year, $period, $state, $lines);
        }

        return ['period' => $period, 'year' => $roadmap->year, 'measures' => $out, 'counts' => $counts];
    }

    /**
     * @return array{id: int, line_no: int, label: string, unit: ?string, plan: ?float, actual: ?float, note: ?string,
     *               state: string, source_period: ?string, saved_actual: ?float, saved_note: ?string}
     */
    public static function resolveLine(RoadmapMeasureLine $line, string $period, ?int $pIdx = null): array
    {
        $pIdx ??= RoadmapPeriod::monthIndex($period);
        $saved  = $line->progress->firstWhere('report_period', $period);
        $base   = [
            'id'           => $line->id,
            'line_no'      => $line->line_no,
            'label'        => $line->label,
            'unit'         => $line->unit,
            'plan'         => $line->plan_value === null ? null : (float) $line->plan_value,
            'saved_actual' => $saved?->actual_value === null ? null : (float) $saved->actual_value,
            'saved_note'   => $saved?->note,
        ];

        if ($saved !== null) {
            return $base + ['actual' => $base['saved_actual'], 'note' => $saved->note, 'state' => 'saved', 'source_period' => null];
        }

        // Candidate periods strictly before P with a value; the earlier-vs-quarter tiebreak
        // on an equal month index is RoadmapPeriod::latest()'s own rule, not re-implemented here.
        $candidates = [];
        foreach ($line->progress as $p) {
            if ($p->actual_value !== null && RoadmapPeriod::monthIndex($p->report_period) < $pIdx) {
                $candidates[] = $p->report_period;
            }
        }
        $bestPeriod = RoadmapPeriod::latest($candidates);
        $best       = $bestPeriod === null ? null : $line->progress->firstWhere('report_period', $bestPeriod);
        if ($best !== null) {
            return $base + ['actual' => (float) $best->actual_value, 'note' => $best->note, 'state' => 'carry', 'source_period' => $best->report_period];
        }

        return $base + ['actual' => null, 'note' => null, 'state' => 'empty', 'source_period' => null];
    }

    /** @param list<array<string, mixed>> $lines */
    private static function measureRow(RoadmapMeasure $m, int $year, string $period, string $state, array $lines): array
    {
        return [
            'id'                => $m->id,
            'section_no'        => (int) $m->section_no,
            'section_title'     => $m->section_title,
            'district_id'       => $m->district_id,
            'district_name'     => $m->district?->name_full,
            'district_code'     => $m->district?->code,
            'district_head'     => $m->district_head_text,
            'seq_no'            => (int) $m->seq_no,
            'title'             => $m->title,
            'details'           => $m->detailLines(),
            'deadline_text'     => $m->deadline_text,
            'responsible_text'  => $m->responsible_text,
            'status'            => $m->status,
            'pct'               => $m->pct === null ? null : (float) $m->pct,
            'latest_period'     => $m->latest_period,
            'status_is_current' => $m->latest_period === $period,
            'deadline_reached'  => RoadmapDeadline::reached($period, $m->deadline_text, $year),
            'state'             => $state,
            'lines'             => $lines,
        ];
    }
}
