<?php

use App\Livewire\DistrictsPage;
use App\Models\IndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // reporting_years is FK-referenced by indicator_facts.year
    DB::table('reporting_years')->insert([
        'year' => 2026, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('regions')->insert([
        'code' => 'andijan', 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'sort_order' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = DB::table('regions')->where('code', 'andijan')->value('id');

    DB::table('modules')->insert([
        ['code' => 'macro',  'label' => 'Макро',   'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'export', 'label' => 'Экспорт', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('indicators')->insert([
        [
            'code' => 'industry', 'label_full' => 'Саноат', 'label_short' => 'Саноат',
            'scope' => 'both', 'default_unit' => 'trln', 'module_code' => 'macro',
            'lower_is_better' => false, 'has_growth_pct' => true, 'has_pct_of_plan' => true,
            'has_sentinel' => false, 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'code' => 'export', 'label_full' => 'Экспорт', 'label_short' => 'Экспорт',
            'scope' => 'both', 'default_unit' => 'mln', 'module_code' => 'export',
            'lower_is_better' => false, 'has_growth_pct' => true, 'has_pct_of_plan' => true,
            'has_sentinel' => false, 'sort_order' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    DB::table('districts')->insert([
        [
            'region_id' => $regionId, 'region_code' => 'andijan',
            'code' => 'andijan_city', 'name_short' => 'Андижон ш.', 'name_full' => 'Андижон шаҳри',
            'kind' => 'city', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'region_id' => $regionId, 'region_code' => 'andijan',
            'code' => 'asaka_district', 'name_short' => 'Асака т.', 'name_full' => 'Асака тумани',
            'kind' => 'district', 'sort_order' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    // unit and source_label are NOT NULL in the indicator_facts migration
    IndicatorFact::create([
        'region_code' => 'andijan', 'district_code' => 'andijan_city',
        'indicator_code' => 'industry', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 100, 'actual_hokimyat' => 95,
        'growth_pct' => 8.0, 'pct_of_plan' => 95.0,
        'unit' => 'trln', 'source_label' => 'Ҳокимлик ҳисоботи',
    ]);
    IndicatorFact::create([
        'region_code' => 'andijan', 'district_code' => 'asaka_district',
        'indicator_code' => 'industry', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 80, 'actual_hokimyat' => 60,
        'growth_pct' => 3.0, 'pct_of_plan' => 75.0,
        'unit' => 'trln', 'source_label' => 'Ҳокимлик ҳисоботи',
    ]);
});

test('GET /districts returns 200 with map and table markup', function () {
    $response = $this->get('/districts');

    $response->assertOk();
    $response->assertSee('districts-map', false);
    $response->assertSee('districts-side', false);
    $response->assertSee('district-detail-table', false);
    $response->assertSee('Андижон шаҳри', false);
    $response->assertSee('Асака тумани', false);
});

test('selectModule sets module property', function () {
    Livewire::test(DistrictsPage::class)
        ->call('selectModule', 'export')
        ->assertSet('module', 'export');
});

test('selectKpi sets indicator and syncs module', function () {
    Livewire::test(DistrictsPage::class)
        ->call('selectKpi', 'export')
        ->assertSet('kpi', 'export')
        ->assertSet('module', 'export');
});

test('selectDistrict updates state', function () {
    Livewire::test(DistrictsPage::class)
        ->call('selectDistrict', 'asaka_district')
        ->assertSet('district', 'asaka_district');
});

test('detail table contains profile link for each district', function () {
    $response = $this->get('/districts');
    $response->assertSee('/profile?districtCode=asaka_district', false);
    $response->assertSee('/profile?districtCode=andijan_city', false);
});

test('status thresholds drive cell coloring', function () {
    $response = $this->get('/districts');
    // andijan_city has pct_of_plan=95.0 -> green; asaka_district has 75.0 -> red
    $html = $response->getContent();
    expect($html)->toContain('map-cell green');
    expect($html)->toContain('map-cell red');
});
