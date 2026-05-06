<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use Illuminate\Support\Facades\DB;

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
        $now = now();
        $this->buffer[] = [
            'import_run_id'   => $importRunId,
            'region_code'     => $regionCode ?? '',
            'district_code'   => $districtCode,
            'indicator_code'  => $indicatorCode,
            'year'            => $year,
            'period'          => $period,
            'issue_kind'      => $kind->value,
            'severity'        => $severity->value,
            'detail'          => $detail,
            'detected_value'  => $detectedValue,
            'expected_value'  => $expectedValue,
            'source_label'    => $sourceLabel,
            'detected_at'     => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];
    }

    public function blockerCount(): int
    {
        return array_sum(array_map(fn($i) => $i['severity'] === IssueSeverity::Blocker->value ? 1 : 0, $this->buffer));
    }

    public function bufferedCount(): int
    {
        return count($this->buffer);
    }

    public function flush(): int
    {
        if (empty($this->buffer)) {
            return 0;
        }
        $count = 0;
        foreach (array_chunk($this->buffer, 200) as $chunk) {
            DB::table('data_quality_issues')->insert($chunk);
            $count += count($chunk);
        }
        $this->buffer = [];
        return $count;
    }
}
