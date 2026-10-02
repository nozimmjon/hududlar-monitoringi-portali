<?php

namespace App\Support\Roadmaps;

/**
 * How many measures of a sheet are late: the deadline month the document names has already
 * passed and the measure is not done. Same rule as the red «over» deadline chip in
 * MeasureDisplay::deadlineChip(), so the card count and the grid chips can never disagree.
 *
 * Deadlines are never stored — RoadmapDeadline parses «2026 йил декабрь» on the way past —
 * so this reads the measures ProgressSheet::build() already loaded and costs no query.
 */
final class RoadmapOverdue
{
    /**
     * @param  iterable<array{deadline_text?: ?string, status?: string}> $measures rows of ProgressSheet::build()['measures']
     * @param  int    $year  the road map's year, for deadlines written without one
     * @param  string $month 'YYYY-MM' to judge against, normally ProgressSheet::currentPeriod()
     */
    public static function count(iterable $measures, int $year, string $month): int
    {
        $n = 0;
        foreach ($measures as $m) {
            if (($m['status'] ?? 'in_progress') === 'done') {
                continue;
            }
            if (RoadmapDeadline::monthsLeft($m['deadline_text'] ?? null, $year, $month) < 0) {
                $n++;
            }
        }

        return $n;
    }
}
