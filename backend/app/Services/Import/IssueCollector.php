<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;

class IssueCollector
{
    private array $buffer = [];

    public function add(
        IssueKind $kind,
        IssueSeverity $severity,
        string $detail,
        ?string $regionCode = null,
        ?string $districtCode = null,
        ?string $indicatorCode = null,
        ?int $year = null,
        ?string $period = null,
        ?string $detectedValue = null,
        ?string $expectedValue = null,
        ?string $sourceLabel = null,
        ?int $importRunId = null,
    ): void {
        $this->buffer[] = ['severity' => $severity->value];
    }

    public function blockerCount(): int
    {
        return array_sum(array_map(fn($i) => $i['severity'] === 'blocker' ? 1 : 0, $this->buffer));
    }

    public function bufferedCount(): int { return count($this->buffer); }
    public function flush(): int { return 0; }   // stub — Task 10 replaces this
}
