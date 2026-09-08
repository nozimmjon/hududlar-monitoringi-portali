<?php

namespace App\Support\Roadmaps;

use App\Support\TaskPeriod;
use InvalidArgumentException;

/** Report periods of the road-map monitoring: months ('2026-09') and quarters ('2026-Q3'). */
final class RoadmapPeriod
{
    public const REGEX = '/^(\d{4})-(0[1-9]|1[0-2]|Q[1-4])$/';

    public static function isValid(?string $period): bool
    {
        return $period !== null && preg_match(self::REGEX, $period) === 1;
    }

    /** 'month' | 'quarter' */
    public static function type(string $period): string
    {
        return str_contains($period, 'Q') ? 'quarter' : 'month';
    }

    /** One axis for months and quarters: YYYY*12 + MM; a quarter counts as its last month. */
    public static function monthIndex(string $period): int
    {
        if (preg_match(self::REGEX, $period, $m) !== 1) {
            throw new InvalidArgumentException("Bad period «{$period}» — expected YYYY-MM or YYYY-Qn.");
        }
        $mm = $m[2][0] === 'Q' ? ((int) $m[2][1]) * 3 : (int) $m[2];

        return (int) $m[1] * 12 + $mm;
    }

    public static function fromYearMonth(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    /** «2026 йил сентябрь» / «2026 йил III чорак» (same wording as the tasks board). */
    public static function label(?string $period): string
    {
        return $period === null ? 'ҳисобот йўқ' : TaskPeriod::reportPeriodLabel($period);
    }

    /** @param iterable<string> $periods */
    public static function latest(iterable $periods): ?string
    {
        $best = null;
        $bestIdx = -1;
        foreach ($periods as $p) {
            $idx = self::monthIndex($p);
            if ($idx > $bestIdx || ($idx === $bestIdx && strcmp($p, (string) $best) > 0)) {
                $best = $p;
                $bestIdx = $idx;
            }
        }

        return $best;
    }
}
