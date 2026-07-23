<?php
// backend/tests/Feature/Livewire/DistrictsPlanModeTest.php

use App\Livewire\DistrictsPage;
use App\Models\District;
use App\Models\IndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

function districtFact(string $kpi, int $districtCode, string $period, array $vals): void
{
    IndicatorFact::create(array_merge([
        'region_code' => 1703, 'district_code' => $districtCode, 'indicator_code' => $kpi,
        'period' => $period, 'year' => 2026, 'unit' => '%', 'source_label' => 'тест',
    ], $vals));
}

test('a plan-only KPI (employment) fills the map in plan mode', function () {
    $codes = District::where('region_code', 1703)->orderBy('sort_order')->pluck('code')->take(3);
    foreach ($codes as $i => $code) {
        districtFact('unemployment', (int) $code, 'h1', ['plan_value' => 3.5 + $i]);
    }

    $c = Livewire::test(DistrictsPage::class)->set('kpi', 'unemployment')->set('period', 'h1');

    expect($c->get('dataMode'))->toBe('plan');
    $colors = $c->get('mapColors');
    foreach ($codes as $code) {
        expect($colors[(int) $code])->toBe('plan');
    }
});

test('a forecast KPI (expected_value, no actual) fills neutral, not green/red', function () {
    $code = (int) District::where('region_code', 1703)->orderBy('sort_order')->value('code');
    // Кутилиш: expected + pct, but NO actual_hokimyat.
    districtFact('budget_investment', $code, 'h1', [
        'plan_value' => 58251, 'expected_value' => 30394, 'pct_of_plan' => 52.2, 'unit' => 'млн сўм',
    ]);

    $c = Livewire::test(DistrictsPage::class)->set('kpi', 'budget_investment')->set('period', 'h1');

    expect($c->get('dataMode'))->toBe('forecast');
    expect($c->get('mapColors')[$code])->toBe('plan');   // neutral fill, never 'ok'/'bad'
    $pill = collect($c->get('mapLayout')['pills'])->firstWhere('code', $code);
    expect($pill['value'])->toBe('52,2%');   // shows the кутилиш % of plan
});

test('a KPI with a real reported actual colours by execution', function () {
    $code = (int) District::where('region_code', 1703)->orderBy('sort_order')->value('code');
    districtFact('budget_investment', $code, 'h1', [
        'plan_value' => 100, 'actual_hokimyat' => 96, 'pct_of_plan' => 96, 'unit' => 'млн сўм',
    ]);

    $c = Livewire::test(DistrictsPage::class)->set('kpi', 'budget_investment')->set('period', 'h1');

    expect($c->get('dataMode'))->toBe('execution');
    expect($c->get('mapColors')[$code])->toBe('ok');   // >=95 -> green
});

test('the period filter switches between H1 and year', function () {
    $code = (int) District::where('region_code', 1703)->orderBy('sort_order')->value('code');
    districtFact('unemployment', $code, 'h1', ['plan_value' => 3.8]);
    districtFact('unemployment', $code, 'year', ['plan_value' => 3.6]);

    $c = Livewire::test(DistrictsPage::class)->set('kpi', 'unemployment')->set('period', 'h1');
    expect($c->get('rollup'))->toBeNull(); // no region rollup seeded, fine
    $h1 = collect($c->get('mapLayout')['pills'])->firstWhere('code', $code);
    expect($h1['value'])->toBe('3,8%');

    $c->call('selectPeriod', 'year');
    expect($c->get('period'))->toBe('year');
    $year = collect($c->get('mapLayout')['pills'])->firstWhere('code', $code);
    expect($year['value'])->toBe('3,6%');
});

test('selectPeriod rejects a period outside the offered options', function () {
    $c = Livewire::test(DistrictsPage::class)->set('period', 'h1');
    $c->call('selectPeriod', 'q1');
    expect($c->get('period'))->toBe('h1');
});
