<?php
// backend/tests/Feature/Livewire/EmploymentH1FactsTest.php

use App\Livewire\Dashboard\KpiWorkspaceCard;
use App\Models\IndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

function employmentFact(string $code, string $period, ?float $plan, ?float $actual, string $unit): IndicatorFact
{
    return IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => $code, 'period' => $period, 'year' => 2026,
        'plan_value' => $plan, 'actual_hokimyat' => $actual, 'unit' => $unit,
        'source_label' => 'Ҳокимлик',
        'hokimyat_reported_at' => $actual !== null ? now() : null,
    ]);
}

function employmentHtml(string $kpi): string
{
    return Livewire::test(KpiWorkspaceCard::class, [
        'module' => 'employment', 'kpi' => $kpi, 'period' => 'h1',
    ])->html();
}

test('the unemployment page shows the H1 plan and the reported rate', function () {
    employmentFact('unemployment', 'h1', 3.841246, 4.450143, '%');
    employmentFact('unemployment', 'year', 3.600512, null, '%');

    $html = employmentHtml('unemployment');

    expect($html)->toContain('II чорак (I ярим йиллик)')
        ->toContain('3,8%')       // режа
        ->toContain('4,5%')       // амалда
        ->toContain('is-over');   // above the ceiling: unemployment is lower-is-better
});

test('a rate at or below the H1 plan reads as on target', function () {
    employmentFact('poverty', 'h1', 4.5, 4.1, '%');

    expect(employmentHtml('poverty'))->toContain('is-ok')->not->toContain('is-over');
});

test('the yearly figures are left untouched', function () {
    employmentFact('unemployment', 'h1', 3.841246, 4.450143, '%');
    employmentFact('unemployment', 'year', 3.600512, null, '%');

    $html = employmentHtml('unemployment');

    // The H1 block never prints a year value, and the year row keeps no fact.
    expect($html)->not->toContain('3,6%');
    expect(IndicatorFact::where('indicator_code', 'unemployment')->where('period', 'year')
        ->value('actual_hokimyat'))->toBeNull();
});

test('driver stats separate the H1 plan from the H1 fact', function () {
    employmentFact('poverty', 'h1', 4.5, 4.7, '%');
    employmentFact('jobs', 'h1', 38.944, 44.153, 'минг нафар');
    employmentFact('jobs', 'year', 86.674, null, 'минг нафар');
    // No reported figure yet — must show a dash, never the plan dressed as a fact.
    employmentFact('mfy_clear', 'h1', 246, null, 'count');
    employmentFact('mfy_clear', 'year', 442, null, 'count');

    $html = employmentHtml('poverty');

    expect($html)->toContain('II чорак режа')
        ->toContain('амалда')
        ->toContain('38,9')     // jobs H1 plan
        ->toContain('44,2')     // jobs H1 fact
        ->toContain('246')      // mfy_clear H1 plan
        ->toContain('—');       // mfy_clear has no fact
});
