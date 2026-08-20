<?php

namespace App\Console\Commands;

use App\Support\TaskReviewFixes;
use Illuminate\Console\Command;

/**
 * Applies the region-staff review fixes (spelling + verified value corrections)
 * to the imported task data. Runs automatically at the end of
 * import:task-progress; safe to run manually any time (idempotent).
 */
class ApplyTaskReviewFixes extends Command
{
    protected $signature = 'tasks:apply-review-fixes';

    protected $description = 'Apply the region review fixes (data/edits) to imported task texts and values.';

    public function handle(): int
    {
        $counts = TaskReviewFixes::apply();

        $this->info(sprintf(
            'Review fixes: %d text cell(s), %d unit cell(s), %d value fix(es).',
            $counts['text'], $counts['units'], $counts['values']
        ));

        if (array_sum($counts) > 0) {
            $this->callSilently('tasks:recompute');
            $this->info('Statuses recomputed.');
        }

        return self::SUCCESS;
    }
}
