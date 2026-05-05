<?php

namespace Tests\Feature\Schema;

use App\Models\IndicatorFact;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndicatorFactsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['region_code','district_code','year','indicator_code','period',
                 'plan_value','expected_value','actual_hokimyat','actual_statkom',
                 'growth_pct','pct_of_plan','count_extra','count_extra_2',
                 'is_sentinel','sentinel_label','unit','source_label',
                 'hokimyat_reported_at','statkom_published_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('indicator_facts', $c), "missing column $c");
        }
    }

    public function test_unique_constraint_blocks_duplicates(): void
    {
        $this->seed();
        IndicatorFact::create([
            'region_code' => 'andijan', 'district_code' => null, 'year' => 2026,
            'indicator_code' => 'grp', 'period' => 'h1',
            'plan_value' => 52100.8, 'unit' => 'млрд сўм', 'source_label' => 'test',
        ]);
        $this->expectException(QueryException::class);
        IndicatorFact::create([
            'region_code' => 'andijan', 'district_code' => null, 'year' => 2026,
            'indicator_code' => 'grp', 'period' => 'h1',
            'plan_value' => 99.0, 'unit' => 'млрд сўм', 'source_label' => 'dup',
        ]);
    }

    public function test_district_rollup_is_allowed_via_null_district_code(): void
    {
        $this->seed();
        $row = IndicatorFact::create([
            'region_code' => 'andijan', 'district_code' => null, 'year' => 2026,
            'indicator_code' => 'industry', 'period' => 'q1',
            'plan_value' => 25945.4, 'growth_pct' => 108.4,
            'unit' => 'млрд сўм', 'source_label' => 'test',
        ]);
        $this->assertNotNull($row->id);
    }
}
