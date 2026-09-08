<?php

namespace App\Support\Roadmaps;

/**
 * «Муддати» cell → a deadline month. The documents write «2026 йил декабрь»,
 * «2026 йил апрель-октябрь» (a range: the last month is the deadline), a
 * quarter as «3-чорак» or «IV чорак» (Roman or Arabic numerals), rarely a
 * bare year; anything unreadable means year-end. A year only counts when
 * followed by «йил» — «ПҚ-2019 сонли қарор» is a decree number, not a year.
 * A year earlier than the road map's own year is ignored, so «2025 йил
 * декабрь» on a 2026 map resolves to 2026-12. Latin-script month names
 * (e.g. «aprel-oktyabr») are not recognised and also fall back to year-end.
 */
final class RoadmapDeadline
{
    /** Month stems (full words minus the soft sign) → month number. */
    private const MONTHS = [
        'январ' => 1, 'феврал' => 2, 'март' => 3, 'апрел' => 4, 'май' => 5, 'июн' => 6,
        'июл' => 7, 'август' => 8, 'сентябр' => 9, 'октябр' => 10, 'ноябр' => 11, 'декабр' => 12,
    ];

    /** A month word with optional Uzbek/Russian endings («декабрь», «декабрда», «майгача», «декабря»); a stem glued to a longer word («майдон», «марта») is not a month. */
    private const MONTH_RE = '/(?<!\p{L})(январ|феврал|март|апрел|май|июн|июл|август|сентябр|октябр|ноябр|декабр)ь?(?:я|и|ида|идан|игача|да|дан|га|гача|нинг)?(?!\p{L})/u';

    /** «3-чорак», «3 чорак», «IV чорак» (Roman numerals arrive lower-cased). */
    private const QUARTER_RE = '/(?<![\p{L}\d])(iv|iii|ii|i|[1-4])\s*-?\s*чорак/u';

    /** Only a year followed by «йил» counts, and only when it is not earlier than the road map's year — «2019-йил 17-июндаги ПФ-5742-сон қарор» is a citation, not a deadline. */
    private const YEAR_RE = '/(?<!\d)(20\d{2})(?=\s*-?\s*йил)/u';

    /** Dative forms for the countdown chip («декабргача»). */
    private const UNTIL = [
        1 => 'январгача', 2 => 'февралгача', 3 => 'мартгача', 4 => 'апрелгача', 5 => 'майгача', 6 => 'июнгача',
        7 => 'июлгача', 8 => 'августгача', 9 => 'сентябргача', 10 => 'октябргача', 11 => 'ноябргача', 12 => 'декабргача',
    ];

    /** @return string 'YYYY-MM' */
    public static function month(?string $deadlineText, int $year): string
    {
        $t = mb_strtolower(trim((string) $deadlineText));
        $roadmapYear = $year;
        if (preg_match_all(self::YEAR_RE, $t, $y) > 0) {
            foreach ($y[1] as $found) {
                if ((int) $found >= $roadmapYear) {                // «2019-йил … қарор» is a citation, not a deadline
                    $year = (int) $found;                          // last qualifying «NNNN йил» wins
                }
            }
        }

        $month = 12;
        if (preg_match_all(self::MONTH_RE, $t, $mm) > 0) {
            $stems = $mm[1];
            $month = self::MONTHS[end($stems)];               // last month wins: «апрель-октябрь» → October
        } elseif (preg_match(self::QUARTER_RE, $t, $q) === 1) {
            $month = (['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4][$q[1]] ?? (int) $q[1]) * 3;
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

    /** «декабргача» for a 'YYYY-MM'; anything else (a quarter string) reads as December. */
    public static function untilLabel(string $deadlineMonth): string
    {
        return self::UNTIL[(int) substr($deadlineMonth, 5, 2)] ?? self::UNTIL[12];
    }
}
