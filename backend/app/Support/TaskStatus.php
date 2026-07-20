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
     * A task is done only when EVERY line that has a plan is at ≥100% — a planned
     * line with no percent yet (no execution data) keeps the task open. Lines
     * without a plan are informational and never counted.
     *
     * @param iterable<array{plan: float|string|null, pct: float|string|null}> $lines
     * @return array{status: string, total: int, done: int}
     */
    public static function aggregate(iterable $lines): array
    {
        $total = 0;
        $done = 0;
        foreach ($lines as $line) {
            if ($line['plan'] === null) {
                continue;
            }
            $total++;
            if ($line['pct'] !== null && (float) $line['pct'] >= 100.0) {
                $done++;
            }
        }

        return [
            'status' => $total > 0 && $done === $total ? 'done' : 'open',
            'total'  => $total,
            'done'   => $done,
        ];
    }
}
