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

    /** 'quarter' for "2026-Q1", else 'month'. */
    public static function periodType(string $reportPeriod): string
    {
        return preg_match('/-Q[1-4]$/', $reportPeriod) ? 'quarter' : 'month';
    }

    public static function yearFromPeriod(string $reportPeriod): int
    {
        return (int) substr($reportPeriod, 0, 4);
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
