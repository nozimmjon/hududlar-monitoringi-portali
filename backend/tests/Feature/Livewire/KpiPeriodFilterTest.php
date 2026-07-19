<?php
// backend/tests/Feature/Livewire/KpiPeriodFilterTest.php

use App\Livewire\Dashboard\KpiFrontCards;
use App\Livewire\Dashboard\KpiModuleTabs;
use App\Livewire\Dashboard\KpiScoreline;
use App\Livewire\KpiDashboard;
use App\Livewire\PeriodSwitcher;
use App\Models\IndicatorFact;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('dashboard defaults to h1 and accepts year via event', function () {
    Livewire::test(KpiDashboard::class)
        ->assertSet('period', 'h1')
        ->dispatch('period-selected', period: 'year')
        ->assertSet('period', 'year');
});

test('dashboard rejects junk periods', function () {
    Livewire::test(KpiDashboard::class)
        ->dispatch('period-selected', period: 'q9')
        ->assertSet('period', 'h1');
});

test('switcher renders both options and dispatches selection', function () {
    Livewire::test(PeriodSwitcher::class)
        ->assertSee('I ярим йиллик')
        ->assertSee('Йил якуни')
        ->call('select', 'year')
        ->assertSet('period', 'year')
        ->assertDispatched('period-selected', period: 'year');
});

test('front cards show facts of the chosen period', function () {
    $this->seed();
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'grp', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 52100.8, 'growth_pct' => 108.8, 'hokimyat_reported_at' => now(),
        'unit' => 'млрд сўм', 'source_label' => 'Ҳокимлик',
    ]);
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'grp', 'period' => 'year', 'year' => 2026,
        'plan_value' => 124800.0, 'growth_pct' => 107.8,
        'unit' => 'млрд сўм', 'source_label' => 'Прогноз',
    ]);

    $h1 = Livewire::test(KpiFrontCards::class, ['module' => 'macro', 'kpi' => 'grp', 'period' => 'h1'])->html();
    expect($h1)->toContain('+8,8%')->not->toContain('+7,8%');

    $year = Livewire::test(KpiFrontCards::class, ['module' => 'macro', 'kpi' => 'grp', 'period' => 'year'])->html();
    expect($year)->toContain('+7,8%')->not->toContain('+8,8%');
});

test('module tabs count only half-year tasks when period is h1, all when year', function () {
    $this->seed();
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик', 'status' => 'done']);
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => null,
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан']);
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => null,
        'period_code' => 'ongoing', 'deadline_text' => '2026 йил давомида']);

    $h1 = Livewire::test(KpiModuleTabs::class, ['module' => 'macro', 'period' => 'h1'])
        ->viewData('taskCounts')['macro'];
    expect($h1)->toBe(['done' => 1, 'total' => 1]);

    $year = Livewire::test(KpiModuleTabs::class, ['module' => 'macro', 'period' => 'year'])
        ->viewData('taskCounts')['macro'];
    expect($year)->toBe(['done' => 1, 'total' => 3]);
});

test('scoreline counts follow the chosen period', function () {
    $this->seed();
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => 'grp',
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик', 'status' => 'done']);
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => 'grp',
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан']);

    $h1 = Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp', 'period' => 'h1']);
    expect($h1->viewData('total'))->toBe(1)->and($h1->viewData('done'))->toBe(1);

    $year = Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp', 'period' => 'year']);
    expect($year->viewData('total'))->toBe(2)->and($year->viewData('done'))->toBe(1);
});

test('topbar shows the switcher only on the dashboard page', function () {
    $this->seed();
    $this->get('/dashboard')->assertSee('topbar-period', false);
    $this->get('/tasks')->assertDontSee('topbar-period', false);
});
