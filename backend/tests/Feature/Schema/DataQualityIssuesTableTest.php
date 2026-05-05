<?php

namespace Tests\Feature\Schema;

use App\Enums\IssueSeverity;
use App\Models\DataQualityIssue;
use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataQualityIssuesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['import_run_id','region_code','district_code','indicator_code','year','period',
                 'issue_kind','severity','detail','detected_value','expected_value',
                 'source_label','detected_at','resolved_at','resolved_by_user_id',
                 'resolution_kind','resolution_note'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('data_quality_issues', $c), "missing column $c");
        }
    }

    public function test_blocker_issue_creation(): void
    {
        $this->seed();
        $run = ImportRun::create([
            'region_code' => 'navoiy', 'year' => 2026, 'trigger_kind' => 'cli',
            'status' => 'parsing', 'started_at' => now(),
        ]);
        $issue = DataQualityIssue::create([
            'import_run_id' => $run->id,
            'region_code' => 'navoiy', 'indicator_code' => 'industry',
            'issue_kind' => 'cross_region_data', 'severity' => IssueSeverity::Blocker,
            'detail' => 'Sheet contains Surxondaryo districts under Navoi',
            'detected_value' => 'Termiz shahri',
            'detected_at' => now(),
        ]);
        $this->assertNotNull($issue->id);
        $this->assertSame(IssueSeverity::Blocker, $issue->severity);
    }
}
