<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DistrictsRegionCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_districts_table_has_region_code_column(): void
    {
        $this->assertTrue(Schema::hasColumn('districts', 'region_code'));
    }

    public function test_region_code_is_not_null_after_seed(): void
    {
        $this->seed();
        $nullCount = DB::table('districts')->whereNull('region_code')->count();
        $this->assertSame(0, $nullCount, 'all districts must have a region_code');
    }

    public function test_region_code_matches_parent_region(): void
    {
        $this->seed();
        $mismatched = DB::table('districts as d')
            ->join('regions as r', 'd.region_id', '=', 'r.id')
            ->whereColumn('d.region_code', '!=', 'r.code')
            ->count();
        $this->assertSame(0, $mismatched);
    }

    public function test_unique_region_code_district_code(): void
    {
        $this->seed();
        $duplicates = DB::table('districts')
            ->select('region_code', 'code', DB::raw('COUNT(*) as c'))
            ->groupBy('region_code', 'code')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        $this->assertSame(0, $duplicates);
    }
}
