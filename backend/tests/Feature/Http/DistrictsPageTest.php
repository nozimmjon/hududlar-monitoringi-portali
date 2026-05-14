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
        'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'sort_order' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regionId = DB::table('regions')->where('code', 1703)->value('id');

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
            'region_id' => $regionId, 'region_code' => 1703,
            'code' => 1703401, 'name_short' => 'Андижон ш.', 'name_full' => 'Андижон шаҳри',
            'kind' => 'city', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'region_id' => $regionId, 'region_code' => 1703,
            'code' => 1703224, 'name_short' => 'Асака т.', 'name_full' => 'Асака тумани',
            'kind' => 'district', 'sort_order' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    // unit and source_label are NOT NULL in the indicator_facts migration
    IndicatorFact::create([
        'region_code' => 1703, 'district_code' => 1703401,
        'indicator_code' => 'industry', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 100, 'actual_hokimyat' => 95,
        'growth_pct' => 8.0, 'pct_of_plan' => 95.0,
        'unit' => 'trln', 'source_label' => 'Ҳокимлик ҳисоботи',
    ]);
    IndicatorFact::create([
        'region_code' => 1703, 'district_code' => 1703224,
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
        ->call('selectDistrict', '1703224')
        ->assertSet('district', '1703224');
});

test('detail table contains profile link for each district', function () {
    $response = $this->get('/districts');
    $response->assertSee('/profile?districtCode=1703224', false);
    $response->assertSee('/profile?districtCode=1703401', false);
});

test('status thresholds drive cell coloring', function () {
    $response = $this->get('/districts');
    // andijan_city has pct_of_plan=95.0 -> green; asaka_district has 75.0 -> red
    $html = $response->getContent();
    expect($html)->toContain('map-cell green');
    expect($html)->toContain('map-cell red');
});

test('detail table shows industry-specific column headers for industry KPI', function () {
    $response = $this->get('/districts?kpi=industry');
    $response->assertOk();
    $response->assertSee('I чорак амалда', false);
    $response->assertSee('I ярим йиллик прогноз', false);
    $response->assertSee('Йиллик прогноз', false);
});

test('detail table shows budget-specific column headers when budget KPI active', function () {
    DB::table('indicators')->insert([
        'code' => 'budget', 'label_full' => 'Бюджет', 'label_short' => 'Бюджет',
        'scope' => 'both', 'default_unit' => 'млрд', 'module_code' => 'macro',
        'lower_is_better' => false, 'has_growth_pct' => false, 'has_pct_of_plan' => true,
        'has_sentinel' => false, 'sort_order' => 3,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    \App\Models\IndicatorFact::create([
        'region_code' => 1703, 'district_code' => 1703401,
        'indicator_code' => 'budget', 'period' => 'h1', 'year' => 2026,
        'unit' => 'млрд', 'source_label' => 'test',
        'plan_value' => 200, 'actual_hokimyat' => 180,
        'pct_of_plan' => 90.0,
    ]);

    \Livewire\Livewire::test(\App\Livewire\DistrictsPage::class)
        ->set('kpi', 'budget')
        ->assertSee('II чорак ижро')
        ->assertSee('I ярим йиллик ижро')
        ->assertDontSee('I чорак амалда');
});

test('side aside renders T/D count chips, metric tiles, and leaderboard markup', function () {
    $response = $this->get('/districts');
    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('district-count-split');
    expect($html)->toContain('district-summary-metrics');
    expect($html)->toContain('district-summary-actions');
    expect($html)->toContain('districts-leaderboard');
    expect($html)->toContain('districts-lb-list');
    expect($html)->toContain('lb-row');
    expect($html)->toContain('lb-rank');
});

test('detail table renders T-topshiriq and D-maqsad cells', function () {
    $response = $this->get('/districts');
    $html = $response->getContent();
    expect($html)->toContain('T-топшириқ');
    expect($html)->toContain('D-мақсад');
});
