<?php

namespace Tests\Feature\Schema;

use App\Enums\AvailabilityStatus;
use App\Models\RegionIndicatorAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegionIndicatorAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_row_per_region_indicator_pair(): void
    {
        $this->seed();
        $regions = DB::table('regions')->count();
        $indicators = DB::table('indicators')->count();
        $this->assertSame($regions * $indicators, RegionIndicatorAvailability::count());
    }

    public function test_default_status_is_available(): void
    {
        $this->seed();
        $available = RegionIndicatorAvailability::where('status', AvailabilityStatus::Available)->count();
        $total = RegionIndicatorAvailability::count();
        $this->assertGreaterThan($total - 20, $available, 'most rows should default to available');
    }

    public function test_tashkent_city_agriculture_is_not_applicable(): void
    {
        $this->seed();
        $row = RegionIndicatorAvailability::where('region_code', 'tashkent_city')
            ->where('indicator_code', 'agriculture')->firstOrFail();
        $this->assertSame(AvailabilityStatus::NotApplicable, $row->status);
    }

    public function test_navoiy_macro_indicators_are_blocked(): void
    {
        $this->seed();
        $blockedCodes = ['grp','industry','agriculture','construction','services'];
        foreach ($blockedCodes as $code) {
            $row = RegionIndicatorAvailability::where('region_code', 'navoiy')
                ->where('indicator_code', $code)->firstOrFail();
            $this->assertSame(AvailabilityStatus::Blocked, $row->status,
                "navoiy × $code should be blocked");
            $this->assertNotNull($row->note);
        }
    }
}
