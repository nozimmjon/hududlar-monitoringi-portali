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

function districtPlanFact(string $kpi, int $districtCode, ?float $plan, array $extra = []): void
{
    IndicatorFact::create(array_merge([
        'region_code' => 1703, 'district_code' => $districtCode, 'indicator_code' => $kpi,
        'period' => 'h1', 'year' => 2026, 'plan_value' => $plan, 'unit' => '%',
        'source_label' => 'Прогноз',
    ], $extra));
}

test('a KPI with only district plans enters plan mode and fills the map', function () {
    $codes = District::where('region_code', 1703)->orderBy('sort_order')->pluck('code')->take(3);
    foreach ($codes as $i => $code) {
        districtPlanFact('unemployment', (int) $code, 3.5 + $i);
    }

    $c = Livewire::test(DistrictsPage::class)->set('kpi', 'unemployment')->set('period', 'h1');

    expect($c->get('planMode'))->toBeTrue();
    $colors = $c->get('mapColors');
    foreach ($codes as $code) {
        expect($colors[(int) $code])->toBe('plan');   // filled, not 'nd'
    }
});

test('plan-mode pills show the district plan, percent KPIs keep the % suffix', function () {
    $code = (int) District::where('region_code', 1703)->orderBy('sort_order')->value('code');
    districtPlanFact('unemployment', $code, 3.8);

    $layout = Livewire::test(DistrictsPage::class)->set('kpi', 'unemployment')->set('period', 'h1')
        ->get('mapLayout');

    $pill = collect($layout['pills'])->firstWhere('code', $code);
    expect($pill['value'])->toBe('3,8%')->and($pill['color'])->toBe('plan');
});

test('a count KPI shows a bare plan number, no percent', function () {
    $code = (int) District::where('region_code', 1703)->orderBy('sort_order')->value('code');
    districtPlanFact('jobs', $code, 12.0, ['unit' => 'минг нафар']);

    $layout = Livewire::test(DistrictsPage::class)->set('kpi', 'jobs')->set('period', 'h1')
        ->get('mapLayout');

    $pill = collect($layout['pills'])->firstWhere('code', $code);
    expect($pill['value'])->toBe('12,0')->not->toContain('%');
});

test('a KPI with execution data stays in normal mode', function () {
    $code = (int) District::where('region_code', 1703)->orderBy('sort_order')->value('code');
    districtPlanFact('unemployment', $code, 3.8, ['actual_hokimyat' => 4.4, 'pct_of_plan' => 86.4]);

    $c = Livewire::test(DistrictsPage::class)->set('kpi', 'unemployment')->set('period', 'h1');

    expect($c->get('planMode'))->toBeFalse();
    expect($c->get('mapColors')[$code])->not->toBe('plan');
});
