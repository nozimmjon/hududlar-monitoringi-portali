<?php

namespace Tests\Feature\Schema;

use App\Models\Indicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndicatorsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['id','code','label_full','label_short','sector','module_code','scope',
                 'default_unit','lower_is_better','supported_periods','has_growth_pct',
                 'has_pct_of_plan','has_sentinel','count_extra_label','count_extra_2_label',
                 'icon','sort_order','notes','created_at','updated_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('indicators', $c), "missing column $c");
        }
    }

    public function test_seed_inserts_twenty_indicators(): void
    {
        $this->seed();
        $this->assertSame(20, Indicator::count());
    }

    public function test_seed_includes_required_codes(): void
    {
        $this->seed();
        $required = ['grp','industry','agriculture','construction','services','inflation',
                     'budget','budget_investment','investment','export','unemployment',
                     'poverty','small_business_share','localization','energy_electricity',
                     'energy_gas','jobs','legalization','mfy_clear','microprojects'];
        foreach ($required as $code) {
            $this->assertNotNull(Indicator::where('code', $code)->first(),
                "missing indicator $code");
        }
    }

    public function test_poverty_has_sentinel_and_lower_is_better(): void
    {
        $this->seed();
        $poverty = Indicator::where('code', 'poverty')->firstOrFail();
        $this->assertTrue($poverty->lower_is_better);
        $this->assertTrue($poverty->has_sentinel);
    }

    public function test_construction_is_region_scope(): void
    {
        $this->seed();
        $this->assertSame('region', Indicator::where('code', 'construction')->value('scope'));
    }
}
