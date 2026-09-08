<?php

namespace App\Support\Roadmaps;

/**
 * «Муддати» cell → a deadline month. The documents write «2026 йил декабрь»,
 * «2026 йил апрель-октябрь» (a range: the last month is the deadline), rarely a
 * quarter; anything unreadable means year-end.
 */
final class RoadmapDeadline
{
    /** Lower-cased stems that start every spelling seen (декабрь / декабр / дек). */
    private const STEMS = [
        'янв' => 1, 'фев' => 2, 'мар' => 3, 'апр' => 4, 'май' => 5, 'июн' => 6,
        'июл' => 7, 'авг' => 8, 'сен' => 9, 'окт' => 10, 'ноя' => 11, 'дек' => 12,
    ];

    /** Dative forms for the countdown chip («декабргача»). */
    private const UNTIL = [
        1 => 'январгача', 2 => 'февралгача', 3 => 'мартгача', 4 => 'апрелгача', 5 => 'майгача', 6 => 'июнгача',
        7 => 'июлгача', 8 => 'августгача', 9 => 'сентябргача', 10 => 'октябргача', 11 => 'ноябргача', 12 => 'декабргача',
    ];

    /** @return string 'YYYY-MM' */
    public static function month(?string $deadlineText, int $year): string
    {
        $t = mb_strtolower(trim((string) $deadlineText));
        if (preg_match('/(?<!\d)(20\d{2})(?!\d)/', $t, $y) === 1) {
            $year = (int) $y[1];
        }

        $month = 12;
        if (preg_match_all('/(?<!\p{L})(янв|фев|мар|апр|май|июн|июл|авг|сен|окт|ноя|дек)/u', $t, $mm) > 0) {
            $month = self::STEMS[end($mm[1])];
        } elseif (preg_match('/(?<!\d)([1-4])\s*-?\s*чорак/u', $t, $q) === 1) {
            $month = ((int) $q[1]) * 3;
        }

        return RoadmapPeriod::fromYearMonth($year, $month);
    }

    /** True once the report period is the deadline month or later. */
    public static function reached(string $reportPeriod, ?string $deadlineText, int $year): bool
    {
        return RoadmapPeriod::monthIndex($reportPeriod) >= RoadmapPeriod::monthIndex(self::month($deadlineText, $year));
    }

    /** Deadline month minus the given 'YYYY-MM' (negative = overdue). */
    public static function monthsLeft(?string $deadlineText, int $year, string $todayPeriod): int
    {
        return RoadmapPeriod::monthIndex(self::month($deadlineText, $year)) - RoadmapPeriod::monthIndex($todayPeriod);
    }

    public static function untilLabel(string $deadlineMonth): string
    {
        return self::UNTIL[(int) substr($deadlineMonth, 5, 2)];
    }
}
