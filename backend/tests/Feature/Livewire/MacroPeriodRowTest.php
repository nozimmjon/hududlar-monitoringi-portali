<?php

use App\Livewire\Dashboard\KpiWorkspaceCard;
use App\Models\IndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the period row on the grp KPI page', function () {
    Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'grp'])
        ->assertSeeHtml('class="macro-period-row"')
        ->assertDontSeeHtml('class="macro-hero-strip"');
});

it('marks II чорак as Амалда once a growth actual is reported', function () {
    $this->seed();
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'grp', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 52100.8, 'growth_pct' => 108.8, 'hokimyat_reported_at' => now(),
        'unit' => 'млрд сўм', 'source_label' => 'Прогноз',
    ]);

    $html = Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'grp'])->html();
    expect($html)->toMatch('/II чорак.*?\+8,8%.*?\(Амалда\)/su');
});

it('keeps II чорак as Режа while growth is only a forecast', function () {
    $this->seed();
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'grp', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 52100.8, 'growth_pct' => 107.2,
        'unit' => 'млрд сўм', 'source_label' => 'Прогноз',
    ]);

    $html = Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'grp'])->html();
    expect($html)->toMatch('/II чорак.*?\(Режа\)/su');
});
