<?php

use App\Livewire\RegionProfile;
use App\Models\District;
use App\Models\IndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

function profileFact(string $kpi, string $period, array $vals): IndicatorFact
{
    return IndicatorFact::create(array_merge([
        'region_code' => 1703, 'district_code' => 1703401, 'indicator_code' => $kpi,
        'period' => $period, 'year' => 2026, 'unit' => '%', 'source_label' => 'тест',
    ], $vals));
}

function makeAvailable(string ...$kpis): void
{
    foreach ($kpis as $kpi) {
        DB::table('region_indicator_availability')->updateOrInsert(
            ['region_code' => 1703, 'indicator_code' => $kpi],
            ['status' => 'available']
        );
    }
}

test('renders empty state when districtCode is missing', function () {
    Livewire::test(RegionProfile::class)
        ->assertSee('Туман танланмаган');
});

test('renders empty state when districtCode does not match any district', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '999999'])
        ->assertSee('Туман топилмади');
});

test('mounts on a valid district with the h1 period by default', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '1703401'])
        ->assertSet('period', 'h1')
        ->assertSee('I ярим йиллик')
        ->assertSee('Йил якуни')
        ->assertViewHas('district', fn ($d) => $d !== null && $d->code === 1703401);
});

test('an invalid period from the URL falls back to h1', function () {
    Livewire::test(RegionProfile::class, ['districtCode' => '1703401', 'period' => 'q9'])
        ->assertSet('period', 'h1');
});

test('selectPeriod switches the period and rejects unknown values', function () {
    $c = Livewire::test(RegionProfile::class, ['districtCode' => '1703401']);
    $c->call('selectPeriod', 'year')->assertSet('period', 'year');
    $c->call('selectPeriod', 'q1')->assertSet('period', 'year');
});

test('a forecast growth KPI renders as Кутилиш with a signed growth value', function () {
    makeAvailable('industry');
    profileFact('industry', 'h1', ['growth_pct' => 109.3, 'plan_value' => 698.8, 'unit' => 'млрд сўм']);

    Livewire::test(RegionProfile::class, ['districtCode' => '1703401'])
        ->assertSee('Кутилиш')
        ->assertSee('+9,3%');
});

test('a plan-only KPI renders as Режа with the plan value', function () {
    makeAvailable('unemployment');
    profileFact('unemployment', 'h1', ['plan_value' => 3.6]);

    Livewire::test(RegionProfile::class, ['districtCode' => '1703401'])
        ->assertSee('Режа')
        ->assertSee('3,6%')
        ->assertSee('ярим йиллик мақсад');
});

test('a KPI with no data for the district is hidden', function () {
    makeAvailable('industry', 'construction');
    profileFact('industry', 'h1', ['growth_pct' => 109.3]);

    $cards = Livewire::test(RegionProfile::class, ['districtCode' => '1703401'])
        ->get('kpiCards');

    $labels = collect($cards)->pluck('label');
    expect($labels)->toContain('Саноат')->not->toContain('Қурилиш');
});

test('switching the period swaps the KPI values', function () {
    makeAvailable('industry');
    profileFact('industry', 'h1', ['growth_pct' => 109.3]);
    profileFact('industry', 'year', ['growth_pct' => 109.8]);

    $c = Livewire::test(RegionProfile::class, ['districtCode' => '1703401']);
    $c->assertSee('+9,3%')->assertDontSee('+9,8%');

    $c->call('selectPeriod', 'year');
    $c->assertSee('+9,8%')->assertDontSee('+9,3%');
});

test('a pct-of-plan forecast KPI renders the percent against the plan', function () {
    makeAvailable('budget_investment');
    profileFact('budget_investment', 'h1', [
        'plan_value' => 16341.8, 'expected_value' => 11455.7, 'pct_of_plan' => 70.1, 'unit' => 'млн сўм',
    ]);

    Livewire::test(RegionProfile::class, ['districtCode' => '1703401'])
        ->assertSee('70,1%')
        ->assertSee('режага нисбатан');
});
