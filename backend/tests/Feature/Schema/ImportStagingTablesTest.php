<?php

namespace Tests\Feature\Schema;

use App\Enums\StagingStatus;
use App\Models\ImportRun;
use App\Models\ImportStagingFoodBalance;
use App\Models\ImportStagingIndicatorFact;
use App\Models\ImportStagingWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportStagingTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('import_staging_indicator_facts'));
        $this->assertTrue(Schema::hasTable('import_staging_food_balance'));
        $this->assertTrue(Schema::hasTable('import_staging_warehouses'));
    }

    public function test_staging_indicator_facts_mirrors_production_columns(): void
    {
        $cols = ['region_code','district_code','year','indicator_code','period',
                 'plan_value','actual_hokimyat','growth_pct','unit','source_label',
                 'import_run_id','staging_status','validation_errors'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('import_staging_indicator_facts', $c),
                "missing column $c");
        }
    }

    public function test_staging_allows_duplicates_no_unique_key(): void
    {
        $this->seed();
        $run = ImportRun::create([
            'region_code' => 1703, 'year' => 2026, 'trigger_kind' => 'cli',
            'status' => 'parsing', 'started_at' => now(),
        ]);
        for ($i = 0; $i < 2; $i++) {
            ImportStagingIndicatorFact::create([
                'import_run_id' => $run->id,
                'region_code' => 1703, 'district_code' => null, 'year' => 2026,
                'indicator_code' => 'grp', 'period' => 'h1',
                'plan_value' => 52100.8, 'unit' => 'млрд сўм', 'source_label' => 'test',
                'staging_status' => StagingStatus::Pending,
            ]);
        }
        $this->assertSame(2, ImportStagingIndicatorFact::count(),
            'staging tables must allow multiple rows for the same logical key');
    }
}
