<?php

namespace App\Support;

class TaskPeriod
{
    /** Reporting cadence from the col J schedule text. "чорак" wins over "ой". */
    public static function cadenceFor(?string $scheduleText): string
    {
        $text = (string) $scheduleText;
        if (mb_strpos($text, 'чорак') !== false) return 'quarterly';
        if (mb_strpos($text, 'ой') !== false)    return 'monthly';
        return 'quarterly';
    }

    /** 'quarter' for "2026-Q1", 'half' for "2026-H1", else 'month'. */
    public static function periodType(string $reportPeriod): string
    {
        if (preg_match('/-Q[1-4]$/', $reportPeriod)) return 'quarter';
        if (preg_match('/-H[12]$/', $reportPeriod))  return 'half';
        return 'month';
    }

    public static function yearFromPeriod(string $reportPeriod): int
    {
        return (int) substr($reportPeriod, 0, 4);
    }

    private const MONTHS = [
        'январ', 'феврал', 'март', 'апрел', 'май', 'июн',
        'июл', 'август', 'сентябр', 'октябр', 'ноябр', 'декабр',
    ];

    /**
     * Deadline filter bucket: 'h1' (incl. Jan–Jun months), 'q3'/'q4' months,
     * 'year', 'ongoing', 'none' for unknown.
     */
    public static function deadlineBucket(?string $periodCode, ?string $deadlineText): string
    {
        if ($periodCode === 'h1') return 'h1';

        if ($periodCode === 'month') {
            $m = self::monthNumber($deadlineText);
            if ($m === null || $m <= 6) return 'h1';
            if ($m <= 9)                return 'q3';
            return 'q4';
        }

        if ($periodCode === 'year')    return 'year';
        if ($periodCode === 'ongoing') return 'ongoing';
        return 'none';
    }

    /** UI labels per bucket, in deadline order. */
    public static function deadlineBucketLabels(): array
    {
        return [
            'h1'      => 'I ярим йиллик',
            'q3'      => 'III чорак',
            'q4'      => 'IV чорак',
            'year'    => 'Йил якуни',
            'ongoing' => 'Йил давомида',
        ];
    }

    /**
     * Board sort bucket by deadline: H1 (incl. Jan–Jun months) → Q3 months →
     * Q4 months → year-end → ongoing → unknown.
     */
    public static function deadlineSortRank(?string $periodCode, ?string $deadlineText): int
    {
        return match (self::deadlineBucket($periodCode, $deadlineText)) {
            'h1'      => 10,
            'q3'      => 20,
            'q4'      => 25,
            'year'    => 30,
            'ongoing' => 40,
            default   => 50,
        };
    }

    /** 1–12 from a month name inside the deadline text, null if none found. */
    private static function monthNumber(?string $deadlineText): ?int
    {
        if ($deadlineText === null) return null;
        foreach (self::MONTHS as $i => $name) {
            if (mb_strpos($deadlineText, $name) !== false) return $i + 1;
        }
        return null;
    }

    /** Normalize deadline text (col F) to a coarse period_code. */
    public static function deadlineToPeriodCode(?string $deadline): ?string
    {
        if ($deadline === null) return null;
        $t = preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $deadline));
        $t = trim((string) $t);
        if ($t === '') return null;

        if (mb_strpos($t, 'ярим йиллик') !== false) return 'h1';
        if (mb_strpos($t, 'якуни') !== false)       return 'year';
        if (mb_strpos($t, 'давомида') !== false)     return 'ongoing';
        if (preg_match('/(январ|феврал|март|апрел|май|июн|июл|август|сентябр|октябр|ноябр|декабр)/u', $t)) {
            return 'month';
        }
        return null;
    }
}
