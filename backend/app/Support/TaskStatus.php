<?php

namespace App\Support;

class TaskStatus
{
    /**
     * Continuous tasks: worked on all year, so a mid-year percentage is not a
     * done/not-done verdict — they always report as «Бажарилмоқда».
     * task_number => title needle, so a workbook renumbering cannot move the
     * override onto a different task (same guard idea as TaskFactBridge::MAP).
     */
    public const CONTINUOUS = [
        '10' => 'Паст ўсиш кузатилган',
    ];

    /**
     * Ongoing-until-done tasks: long-running collection work where falling short
     * of plan mid-year means «still being worked on», not «failed» — so `open`
     * reports as «Бажарилмоқда» while a fully met task still closes as `done`.
     * Same task_number => title needle guard.
     */
    public const ONGOING_UNTIL_DONE = [
        '111' => 'Яширин иқтисодиётга қарши курашиш',
        '182' => 'Биринчи ярим йилда ишсизлик даражаси камайтириш чоралари',
    ];

    public static function isContinuous(?string $taskNumber, ?string $title): bool
    {
        return self::matches(self::CONTINUOUS, $taskNumber, $title);
    }

    public static function isOngoingUntilDone(?string $taskNumber, ?string $title): bool
    {
        return self::matches(self::ONGOING_UNTIL_DONE, $taskNumber, $title);
    }

    private static function matches(array $map, ?string $taskNumber, ?string $title): bool
    {
        $needle = $map[(string) $taskNumber] ?? null;

        return $needle !== null && mb_stripos((string) $title, $needle) !== false;
    }

    /**
     * Aggregate for one task: the normal weakest-link rule, with two overrides —
     * continuous tasks never close, ongoing-until-done tasks report unfinished
     * work as in_progress. Line counts stay truthful either way.
     *
     * @param iterable<array{plan: float|string|null, actual?: float|string|null, pct: float|string|null}> $lines
     * @return array{status: string, total: int, done: int}
     */
    public static function forTask(?string $taskNumber, ?string $title, iterable $lines): array
    {
        $agg = self::aggregate($lines);

        if (self::isContinuous($taskNumber, $title)) {
            $agg['status'] = 'in_progress';
        } elseif ($agg['status'] === 'open' && self::isOngoingUntilDone($taskNumber, $title)) {
            $agg['status'] = 'in_progress';
        }

        return $agg;
    }

    /** Binary done/open from a percent-of-plan value. */
    public static function statusFor(?float $pct): string
    {
        return $pct !== null && $pct >= 100.0 ? 'done' : 'open';
    }

    /**
     * Weakest-link aggregate over one period's metric lines.
     *
     * Three states: `in_progress` when no line shows any real progress — every
     * actual is missing or an explicit 0 (nothing achieved yet), otherwise `done`
     * only when EVERY line that has a plan is at ≥100%, else `open`. Lines
     * without a plan are informational and never counted in total/done.
     *
     * @param iterable<array{plan: float|string|null, actual?: float|string|null, pct: float|string|null}> $lines
     * @return array{status: string, total: int, done: int}
     */
    public static function aggregate(iterable $lines): array
    {
        $total = 0;
        $done = 0;
        $hasActual = false;
        foreach ($lines as $line) {
            if (($line['actual'] ?? null) !== null && (float) $line['actual'] != 0.0) {
                $hasActual = true;
            }
            if ($line['plan'] === null) {
                continue;
            }
            $total++;
            if ($line['pct'] !== null && (float) $line['pct'] >= 100.0) {
                $done++;
            }
        }

        return [
            'status' => ! $hasActual ? 'in_progress'
                : ($total > 0 && $done === $total ? 'done' : 'open'),
            'total'  => $total,
            'done'   => $done,
        ];
    }
}
