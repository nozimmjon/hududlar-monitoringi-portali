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

test('GET /districts renders header card, map, and rank list without pre-selection', function () {
    $response = $this->get('/districts');

    $response->assertOk();
    $response->assertSee('districts-header', false);
    $response->assertSee('module-seg', false);
    $response->assertSee('districts-map', false);
    $response->assertSee('districts-ranklist', false);
    $response->assertSee('Андижон шаҳри', false);
    $response->assertSee('Асака тумани', false);
    $response->assertDontSee('Танланган ҳудуд', false);
    $response->assertDontSee('district-peek open', false);
    $response->assertDontSee('districts-table', false);
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

test('clearDistrict resets the selection', function () {
    Livewire::test(DistrictsPage::class)
        ->set('district', '1703224')
        ->call('clearDistrict')
        ->assertSet('district', '');
});


test('clicking a district opens the slide-over peek with stats and profile link', function () {
    $response = $this->get('/districts?district=1703224');
    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('district-peek');
    expect($html)->toContain('Танланган ҳудуд');
    expect($html)->toContain('Режа');
    expect($html)->toContain('Факт');
    expect($html)->toContain('/profile?districtCode=1703224');
    expect($html)->not->toContain('districts-table');
    expect($html)->not->toContain('districts-leaderboard');
});

test('peek uses plain task and target labels, not D-/T- codes', function () {
    $response = $this->get('/districts?district=1703224');
    $html = $response->getContent();
    expect($html)->toContain('Топшириқлар');
    expect($html)->toContain('Кафолат мажбурияти');
    expect($html)->not->toContain('T-топшириқ');
    expect($html)->not->toContain('D-мақсад');
});
