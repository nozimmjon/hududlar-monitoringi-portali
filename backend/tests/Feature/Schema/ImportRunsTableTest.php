<?php

namespace Tests\Feature\Schema;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportRunsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['region_code','year','triggered_by_user_id','trigger_kind','status',
                 'started_at','parsed_at','promoted_at','rejected_at','failed_at',
                 'files_processed','rows_staged','rows_promoted',
                 'issues_open_count','issues_blocker_count','notes'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('import_runs', $c), "missing column $c");
        }
    }

    public function test_creates_run(): void
    {
        $this->seed();
        $run = ImportRun::create([
            'region_code' => 'andijan', 'year' => 2026,
            'trigger_kind' => 'cli', 'status' => ImportRunStatus::Parsing,
            'started_at' => now(),
        ]);
        $this->assertNotNull($run->id);
        $this->assertSame(0, $run->files_processed);
    }
}
