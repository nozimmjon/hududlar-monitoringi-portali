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
    // The caption must name the period the value came from.
    expect($h1)->toContain('ярим йиллик ўсиш');

    $year = Livewire::test(KpiFrontCards::class, ['module' => 'macro', 'kpi' => 'grp', 'period' => 'year'])->html();
    expect($year)->toContain('+7,8%')->not->toContain('+8,8%');
    expect($year)->toContain('йиллик ўсиш')->not->toContain('ярим йиллик ўсиш');
});

test('module tabs count only half-year tasks when period is h1, all when year', function () {
    $this->seed();
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик', 'status' => 'done',
        'headline_plan' => 10]);
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => null,
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан', 'headline_plan' => 10]);
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => null,
        'period_code' => 'ongoing', 'deadline_text' => '2026 йил давомида', 'headline_plan' => 10]);

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
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик', 'status' => 'done',
        'headline_plan' => 10]);
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => 'grp',
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан', 'headline_plan' => 10]);

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

test('growth cards show the planned growth percent, not the money plan', function () {
    $this->seed();
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'grp', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 52100.811094, 'growth_pct' => 108.8, 'plan_growth_pct' => 107.2,
        'hokimyat_reported_at' => now(), 'unit' => 'млрд сўм', 'source_label' => 'Ҳокимлик',
    ]);

    $html = Livewire::test(KpiFrontCards::class, ['module' => 'macro', 'kpi' => 'grp', 'period' => 'h1'])->html();

    expect($html)->toContain('+8,8%')      // reported growth stays the headline
        ->toContain('Режа')
        ->toContain('+7,2%')                // the promise, as a percent
        ->not->toContain('трлн сўм');       // never the monetary plan
});

test('employment cards show the plan even without a growth value', function () {
    $this->seed();
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'unemployment', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 3.841246, 'unit' => '%', 'source_label' => 'Прогноз',
    ]);

    $html = Livewire::test(KpiFrontCards::class, ['module' => 'employment', 'kpi' => 'unemployment', 'period' => 'h1'])->html();

    expect($html)->toContain('Режа')->toContain('3,8%');
});

test('a card without a plan renders no plan line', function () {
    $this->seed();
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'grp', 'period' => 'h1', 'year' => 2026,
        'growth_pct' => 108.8, 'unit' => 'млрд сўм', 'source_label' => 'Ҳокимлик',
    ]);

    $html = Livewire::test(KpiFrontCards::class, ['module' => 'macro', 'kpi' => 'grp', 'period' => 'h1'])->html();

    expect($html)->not->toContain('front-kpi-plan');
});

test('the plan line never leaks the internal count unit', function () {
    $this->seed();
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'mfy_clear', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 246, 'unit' => 'count', 'source_label' => 'Прогноз',
    ]);

    $html = Livewire::test(KpiFrontCards::class, ['module' => 'employment', 'kpi' => 'mfy_clear', 'period' => 'h1'])->html();

    expect($html)->toContain('246 та')->not->toContain('count');
});

test('tab and scoreline counts ignore tasks with nothing planned, like the board', function () {
    $this->seed();
    // Measurable h1 task.
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => 'grp',
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
        'headline_plan' => 10, 'status' => 'done']);
    // Same period, but nothing planned anywhere -> the board hides it, so must not
    // inflate the completion statistics either.
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => 'grp',
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
        'headline_plan' => null]);

    $counts = Livewire::test(KpiModuleTabs::class, ['module' => 'macro', 'period' => 'h1'])
        ->viewData('taskCounts')['macro'];
    expect($counts)->toBe(['done' => 1, 'total' => 1]);

    $score = Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp', 'period' => 'h1']);
    expect($score->viewData('total'))->toBe(1)->and($score->viewData('done'))->toBe(1);
});

test('the half-year view also credits tasks finished ahead of their deadline', function () {
    $this->seed();
    // Due in H1, still running.
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => 'grp',
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
        'headline_plan' => 10, 'status' => 'open']);
    // Due at year-end but already finished — counts as finished in the H1 view too.
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => 'grp',
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
        'headline_plan' => 10, 'status' => 'done']);
    // Due at year-end and still running — stays out of the H1 view.
    Task::factory()->create(['module_code' => 'macro', 'indicator_code' => 'grp',
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
        'headline_plan' => 10, 'status' => 'in_progress']);

    $counts = Livewire::test(KpiModuleTabs::class, ['module' => 'macro', 'period' => 'h1'])
        ->viewData('taskCounts')['macro'];
    expect($counts)->toBe(['done' => 1, 'total' => 2]);

    $score = Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp', 'period' => 'h1']);
    expect($score->viewData('total'))->toBe(2)->and($score->viewData('done'))->toBe(1);
});

test('tab and scoreline counts match the entry page: Бажарилмоқда counts as on track', function () {
    $this->seed();
    $base = ['module_code' => 'macro', 'indicator_code' => 'grp', 'period_code' => 'h1',
             'deadline_text' => '2026 йил I ярим йиллик', 'headline_plan' => 10];

    Task::factory()->create($base + ['status' => 'done']);
    Task::factory()->create($base + ['status' => 'in_progress']);
    Task::factory()->create($base + ['status' => 'open']);

    // Entry page rule: a task is on track unless it is reported behind plan.
    $counts = Livewire::test(KpiModuleTabs::class, ['module' => 'macro', 'period' => 'h1'])
        ->viewData('taskCounts')['macro'];
    expect($counts)->toBe(['done' => 2, 'total' => 3]);

    $score = Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp', 'period' => 'h1']);
    expect($score->viewData('total'))->toBe(3)
        ->and($score->viewData('done'))->toBe(2)
        ->and($score->viewData('open'))->toBe(1);
});
