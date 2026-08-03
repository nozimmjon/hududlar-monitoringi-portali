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

    /** Sortable key: quarters/halves map to their closing month (Q1->03, ..., H1->06, H2->12). */
    public static function sortKey(string $period): string
    {
        if (preg_match('/^(\d{4})-Q([1-4])$/', $period, $m)) {
            return $m[1] . '-' . str_pad((string) ((int) $m[2] * 3), 2, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^(\d{4})-H([12])$/', $period, $m)) {
            return $m[1] . '-' . ($m[2] === '1' ? '06' : '12');
        }
        return $period;
    }

    private const MONTHS = [
        'январ', 'феврал', 'март', 'апрел', 'май', 'июн',
        'июл', 'август', 'сентябр', 'октябр', 'ноябр', 'декабр',
    ];

    /**
     * Deadline filter bucket: 'h1' (incl. Jan–Jun months and I–II quarters),
     * 'q3'/'q4' (quarters or their months), 'h2', 'year', 'ongoing', 'none'.
     *
     * The deadline TEXT wins over the stored code for half/quarter phrasing —
     * rows imported before the H2 template existed carry 'h1' for
     * «II ярим йиллик» and null for «III чорак».
     */
    public static function deadlineBucket(?string $periodCode, ?string $deadlineText): string
    {
        $t = (string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', (string) $deadlineText));

        if (mb_strpos($t, 'ярим йиллик') !== false) {
            return preg_match('/\bII\b/u', $t) === 1 ? 'h2' : 'h1';
        }
        if (mb_strpos($t, 'чорак') !== false) {
            if (preg_match('/\b(IV|III|II|I)\b/u', $t, $m)) {
                return match ($m[1]) {
                    'III'   => 'q3',
                    'IV'    => 'q4',
                    default => 'h1', // I–II quarters close inside the first half
                };
            }
            return 'none';
        }

        if ($periodCode === 'h1' || $periodCode === 'q1' || $periodCode === 'q2') return 'h1';
        if ($periodCode === 'h2') return 'h2';
        if ($periodCode === 'q3') return 'q3';
        if ($periodCode === 'q4') return 'q4';

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
            'h2'      => 'II ярим йиллик',
            'year'    => 'Йил якуни',
            'ongoing' => 'Йил давомида',
        ];
    }

    /**
     * Board sort bucket by deadline: H1 (incl. Jan–Jun months) → Q3 → Q4 →
     * H2 → year-end → ongoing → unknown.
     */
    public static function deadlineSortRank(?string $periodCode, ?string $deadlineText): int
    {
        return match (self::deadlineBucket($periodCode, $deadlineText)) {
            'h1'      => 10,
            'q3'      => 20,
            'q4'      => 25,
            'h2'      => 27,
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

        if (mb_strpos($t, 'ярим йиллик') !== false) {
            return preg_match('/\bII\b/u', $t) === 1 ? 'h2' : 'h1';
        }
        if (mb_strpos($t, 'чорак') !== false && preg_match('/\b(IV|III|II|I)\b/u', $t, $m)) {
            return match ($m[1]) {
                'I'     => 'q1',
                'II'    => 'q2',
                'III'   => 'q3',
                default => 'q4',
            };
        }
        if (mb_strpos($t, 'якуни') !== false)       return 'year';
        if (mb_strpos($t, 'давомида') !== false)     return 'ongoing';
        if (preg_match('/(январ|феврал|март|апрел|май|июн|июл|август|сентябр|октябр|ноябр|декабр)/u', $t)) {
            return 'month';
        }
        return null;
    }
}
