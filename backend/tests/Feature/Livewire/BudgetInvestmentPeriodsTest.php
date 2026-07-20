<?php
// backend/tests/Feature/Livewire/BudgetInvestmentPeriodsTest.php

use App\Livewire\Dashboard\KpiWorkspaceCard;
use App\Models\IndicatorFact;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

/**
 * A fact row. `reported` marks a value the tasks bridge wrote (a real Амалда);
 * without it the number is the region workbook's forecast, which mid-year must
 * still read as Кутилиш for non-q1 periods.
 */
function budgetFact(string $period, ?float $actual, ?float $pct, ?float $expected = null, bool $reported = false): IndicatorFact
{
    return IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'budget_investment',
        'period' => $period, 'year' => 2026,
        'plan_value' => 950279.86, 'actual_hokimyat' => $actual, 'pct_of_plan' => $pct,
        'expected_value' => $expected,
        'unit' => 'млн сўм', 'source_label' => 'Ҳокимлик',
        'hokimyat_reported_at' => $reported ? now() : null,
    ]);
}

function budgetTask(string $num, ?float $plan, ?float $actual): Task
{
    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => $num,
        'title' => 'Бюджет маблағлари ҳисобидан инвестицияларни ўзлаштириш',
        'module_code' => 'budget', 'indicator_code' => 'budget_investment',
        'latest_period' => '2026-H1', 'headline_plan' => $plan,
    ]);
    $task->progress()->create([
        'line_no' => 0, 'report_period' => '2026-H1', 'period_type' => 'half',
        'metric_label' => 'Бюджет маблағлари ҳисобидан инвестицияларни ўзлаштириш',
        'unit' => 'млрд сўм', 'plan_value' => $plan, 'actual_value' => $actual,
    ]);

    return $task;
}

function budgetPanelHtml(): string
{
    return Livewire::test(KpiWorkspaceCard::class, [
        'module' => 'budget', 'kpi' => 'budget_investment', 'period' => 'h1',
    ])->html();
}

test('a reported half-year reads Амалда, not Кутилиш', function () {
    budgetFact('q1', 177548.80, 18.68);
    budgetFact('h1', 487684.78, 51.32, reported: true);
    budgetFact('year', null, null, 1024641.46);   // annual still a forecast

    $html = budgetPanelHtml();

    // The II чорак card must not still claim a forecast.
    expect(substr_count($html, 'Кутилиш'))->toBe(1);   // only the annual card remains
    expect(substr_count($html, 'Амалда'))->toBe(2);    // I чорак + II чорак
});

test('a half-year without reported data still reads Кутилиш', function () {
    budgetFact('q1', 177548.80, 18.68);
    budgetFact('h1', null, null, 444137.69);      // forecast only

    expect(budgetPanelHtml())->toContain('Кутилиш');
});

test('the period card shows the task plan next to the reported value', function () {
    budgetFact('h1', 487684.78, 51.32, reported: true);
    budgetTask('125', 444.0, 487.68);

    $html = budgetPanelHtml();

    expect($html)->toContain('давр режаси')
        ->toContain('444 млрд сўм');   // 444 млрд plan, scaled from the task
});

test('a renumbered task cannot supply the period plan', function () {
    budgetFact('h1', 487684.78, 51.32, reported: true);
    $task = budgetTask('125', 444.0, 487.68);
    $task->progress()->update(['metric_label' => 'Мутлақо бошқа кўрсаткич']);

    expect(budgetPanelHtml())->not->toContain('давр режаси');
});

test('an unfinished year keeps its forecast as Кутилиш, never Амалда', function () {
    // The region workbook stores the year-end FORECAST in actual_hokimyat with no
    // reported-at stamp; mid-year that must not read as achieved.
    budgetFact('q1', 177548.80, 18.68);
    budgetFact('h1', 444137.69, 46.74);
    budgetFact('year', 1024641.46, 107.83);

    $html = budgetPanelHtml();

    expect(substr_count($html, 'Кутилиш'))->toBe(2);   // II чорак + Йиллик
    expect(substr_count($html, 'Амалда'))->toBe(1);    // only the closed I чорак
});

test('a forecast card does not print a rival plan line', function () {
    // Year card: headline is the workbook forecast (≈ the plan). Showing "давр
    // режаси" next to it read as two competing annual plans.
    budgetFact('year', 1024641.46, 107.83);
    budgetTask('129', 1025.0, null);

    expect(budgetPanelHtml())->not->toContain('давр режаси');
});

test('task data drives the cards even before the fact bridge runs', function () {
    // Facts still hold the region workbook forecast with no reported stamp.
    budgetFact('h1', 444137.69, 46.74);
    budgetFact('year', 1024641.46, 107.83);
    budgetTask('125', 444.0, 487.68);   // H1 reported
    budgetTask('129', 1025.0, null);    // year: promise only

    $html = budgetPanelHtml();

    // Period cards only — the summary strip keeps its own clearly-labelled
    // «Йиллик кутилиш» tile, which is allowed to show the forecast.
    $cards = substr($html, strpos($html, 'budget-periods-grid'));

    expect($cards)->toContain('487,7 млрд сўм')   // II чорак leads with the fact
        ->toContain('давр режаси')
        ->toContain('444 млрд сўм')                // its plan beside it
        ->toContain('1 025 млрд сўм')              // Йиллик shows the plan itself
        ->not->toContain('1 024,6 млрд сўм');      // never the forecast
    expect(substr_count($cards, 'Амалда'))->toBe(1);   // only II чорак (q1 row absent)
    expect(substr_count($cards, 'Режа'))->toBeGreaterThan(0);
});
