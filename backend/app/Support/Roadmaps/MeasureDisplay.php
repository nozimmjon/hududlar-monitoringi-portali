<?php

namespace App\Support\Roadmaps;

use App\Models\RoadmapMeasure;
use App\Support\SectorDisplay;
use Illuminate\Support\Collection;

/** View helpers for the /roadmaps cards — formatting only, no status logic (that is MeasureRecomputer). */
final class MeasureDisplay
{
    private const STATUS = [
        'done'        => ['cls' => 'ok',   'label' => 'Бажарилди'],
        'in_progress' => ['cls' => 'wait', 'label' => 'Бажарилмоқда'],
        'open'        => ['cls' => 'bad',  'label' => 'Бажарилмаган'],
    ];

    /** 2π × r18 — the card ring; the hero ring (r45) passes its own 282.7. */
    private const RING_CIRC = 113.1;

    /** A measure that is not done never shows 100 % (cap 99). */
    public static function pshow(?float $pct, bool $done): ?int
    {
        return SectorDisplay::pshow($pct, $done);
    }

    /** none | red (<50) | amber (50–99) | green (≥100) — for indicator lines. */
    public static function tier(?float $pct): string
    {
        if ($pct === null) {
            return 'none';
        }

        return $pct >= 100 ? 'green' : ($pct >= 50 ? 'amber' : 'red');
    }

    /** Fill width in % of a bar whose full length is 120 % of plan (the tick at 83.33 % marks 100 %). Never negative. */
    public static function barWidth(?float $pct): float
    {
        return $pct === null ? 0.0 : round(max(0.0, min(100.0, $pct / 120 * 100)), 2);
    }

    /**
     * Mean of pct (unreported = 0) over measures that have planned lines; null when
     * none has. Used for both the hero ring and the rail's district bars, so the two
     * can never drift apart.
     *
     * @param Collection<int, RoadmapMeasure> $measures
     */
    public static function meanPct(Collection $measures): ?int
    {
        $withLines = $measures->filter(fn (RoadmapMeasure $m) => (int) $m->lines_total > 0);
        if ($withLines->isEmpty()) {
            return null;
        }

        return (int) round($withLines->avg(fn (RoadmapMeasure $m) => (float) ($m->pct ?? 0)));
    }

    public static function fmt(null|float|int|string $v): string
    {
        return SectorDisplay::fmt($v);
    }

    /** @return array{cls: string, label: string} */
    public static function statusChip(string $status): array
    {
        return self::STATUS[$status] ?? self::STATUS['in_progress'];
    }

    /**
     * The deadline exactly as the document writes it («2026 йил декабрь») — no countdown
     * (user decision 2026-09-09). Only the colour carries state: green once done, red when
     * the deadline month has passed (judged against $today), neutral otherwise.
     *
     * @param  string $today 'YYYY-MM' of the current month
     * @return array{cls: string, label: string}
     */
    public static function deadlineChip(?string $deadlineText, int $year, string $status, string $today): array
    {
        $text = $deadlineText ?? '—';
        if ($status === 'done') {
            return ['cls' => 'done', 'label' => $text];        // the chip's calendar icon + green carry the state
        }

        return [
            'cls'   => RoadmapDeadline::monthsLeft($deadlineText, $year, $today) < 0 ? 'over' : 'due',
            'label' => $text,
        ];
    }

    /**
     * One point per period (canonical order): mean over planned lines of max(0, min(100, pct ?? 0)) —
     * the same share rule as MeasureRecomputer::aggregate(); a line with no row in that period counts as 0.
     *
     * @return list<array{period: string, pct: float}>
     */
    public static function history(RoadmapMeasure $m): array
    {
        $planned = $m->lines->filter(fn ($l) => $l->plan_value !== null)->values();
        if ($planned->isEmpty()) {
            return [];
        }
        $periods = $planned->flatMap(fn ($l) => $l->progress->pluck('report_period'))->unique()->values()->all();
        usort($periods, fn (string $a, string $b) => RoadmapPeriod::monthIndex($a) <=> RoadmapPeriod::monthIndex($b));

        $out = [];
        foreach ($periods as $p) {
            $sum = 0.0;
            foreach ($planned as $l) {
                $row  = $l->progress->firstWhere('report_period', $p);
                $sum += max(0.0, min(100.0, $row?->pct_of_plan !== null ? (float) $row->pct_of_plan : 0.0));
            }
            $out[] = ['period' => $p, 'pct' => round($sum / $planned->count(), 1)];
        }

        return $out;
    }

    /** Polyline points for a 120 × 28 sparkline; empty below two points. */
    public static function sparkPoints(array $history): string
    {
        $n = count($history);
        if ($n < 2) {
            return '';
        }
        $pts = [];
        foreach (array_values($history) as $i => $h) {
            $x     = 3 + $i * (114 / ($n - 1));
            $y     = 25 - $h['pct'] / 100 * 22;
            $pts[] = self::trimNum($x) . ',' . self::trimNum($y);
        }

        return implode(' ', $pts);
    }

    /** stroke-dashoffset for a ring of the given circumference (default: the r=18 card ring); null = empty ring. */
    public static function ringOffset(?int $shownPct, float $circumference = self::RING_CIRC): string
    {
        $p = $shownPct === null ? 0 : min(100, max(0, $shownPct));

        return number_format($circumference * (1 - $p / 100), 1, '.', '');
    }

    private static function trimNum(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }
}
