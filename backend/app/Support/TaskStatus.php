<?php

namespace App\Support;

class TaskStatus
{
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
