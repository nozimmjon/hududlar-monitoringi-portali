<?php
// backend/tests/Feature/Tasks/TaskFactBridgeTest.php

use App\Models\IndicatorFact;
use App\Support\DashboardCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\TaskWorkbookFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->fixture = TaskWorkbookFixture::makeEconomic();

    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'budget', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 2407.886, 'expected_value' => 2598.643794,
        'unit' => 'млрд сўм', 'source_label' => 'Прогноз',
    ]);
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'export', 'period' => 'h1', 'year' => 2026,
        'expected_value' => 361620.883206, 'growth_pct' => 121.0,
        'unit' => 'минг доллар', 'source_label' => 'Прогноз',
    ]);
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'unemployment', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 3.841246,
        'unit' => '%', 'source_label' => 'Прогноз',
    ]);
    // Macro rows carry only the forecast growth until the tasks workbook reports.
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'grp', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 52100.811094, 'growth_pct' => 107.1591,
        'unit' => 'млрд сўм', 'source_label' => 'Прогноз',
    ]);
    IndicatorFact::create([
        'region_code' => 1703, 'indicator_code' => 'services', 'period' => 'h1', 'year' => 2026,
        'plan_value' => 27911.207377, 'growth_pct' => 114.5,
        'unit' => 'млрд сўм', 'source_label' => 'Прогноз',
    ]);
});

afterEach(function () {
    @unlink($this->fixture);
});

test('task actuals replace the dashboard Кутилиш values on import', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-H1'])
        ->assertSuccessful();

    // budget (task 117): actual lands as-is, execution % recomputed vs the FACT plan.
    $budget = IndicatorFact::where('indicator_code', 'budget')->where('period', 'h1')->first();
    expect((float) $budget->actual_hokimyat)->toBeNumericallyClose(2628.7145758079, 1e-3);
    expect((float) $budget->pct_of_plan)->toBeNumericallyClose(2628.7145758079 / 2407.886 * 100, 1e-3);
    // The imported forecast is preserved for reference.
    expect((float) $budget->expected_value)->toBeNumericallyClose(2598.643794, 1e-3);

    // export (task 165): млн долл -> минг доллар (×1000); no fact plan -> pct untouched.
    $export = IndicatorFact::where('indicator_code', 'export')->where('period', 'h1')->first();
    expect((float) $export->actual_hokimyat)->toBeNumericallyClose(512004.40906254, 1e-2);
    expect($export->pct_of_plan)->toBeNull();

    // unemployment (task 181): lower-is-better -> pct = fact plan / actual.
    $unemp = IndicatorFact::where('indicator_code', 'unemployment')->where('period', 'h1')->first();
    expect((float) $unemp->actual_hokimyat)->toBeNumericallyClose(4.4501433700843, 1e-4);
    expect((float) $unemp->pct_of_plan)->toBeNumericallyClose(3.841246 / 4.4501433700843 * 100, 1e-3);

    // The dashboard now reads these rows as real actuals, not Кутилиш.
    expect(DashboardCatalog::periodSourceKind('budget', 'h1', $budget))->toBe('actual');
    expect(DashboardCatalog::factLabel('budget', 'h1', $budget))->toBe('Амалда');
    expect(DashboardCatalog::periodState('budget', 'h1', $budget)['cls'])->toBe('actual');
    expect(DashboardCatalog::executionLabel('budget', 'h1', $budget))->toBe('Ижро');
});

test('macro growth and volume actuals land on dashboard facts', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-H1'])
        ->assertSuccessful();

    // grp (task 1): growth-only actual — 8.8% delta becomes ratio 108.8, marked reported.
    $grp = IndicatorFact::where('indicator_code', 'grp')->where('period', 'h1')->first();
    expect((float) $grp->growth_pct)->toBeNumericallyClose(108.8, 1e-4);
    expect($grp->hokimyat_reported_at)->not->toBeNull();
    expect($grp->actual_hokimyat)->toBeNull();
    expect(DashboardCatalog::periodSourceKind('grp', 'h1', $grp))->toBe('actual');
    expect(DashboardCatalog::periodState('grp', 'h1', $grp)['cls'])->toBe('actual');

    // services (task 36): volume трлн -> млрд (×1000) plus the growth line.
    $srv = IndicatorFact::where('indicator_code', 'services')->where('period', 'h1')->first();
    expect((float) $srv->actual_hokimyat)->toBeNumericallyClose(33988.9, 1e-2);
    expect((float) $srv->growth_pct)->toBeNumericallyClose(116.1, 1e-4);
    expect((float) $srv->pct_of_plan)->toBeNumericallyClose(33988.9 / 27911.207377 * 100, 1e-3);
    expect(DashboardCatalog::periodState('services', 'h1', $srv)['cls'])->toBe('actual');
});

test('a fact row without a task actual keeps its Кутилиш presentation', function () {
    // No import at all — rows still carry only the forecast.
    $budget = IndicatorFact::where('indicator_code', 'budget')->where('period', 'h1')->first();
    expect(DashboardCatalog::periodSourceKind('budget', 'h1', $budget))->toBe('expected');
    expect(DashboardCatalog::factLabel('budget', 'h1', $budget))->toBe('Кутилиш');
    expect(DashboardCatalog::periodState('budget', 'h1', $budget)['label'])->toBe('Кутилиш');
});

test('dry run does not touch indicator facts', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-H1', '--dry-run' => true])
        ->assertSuccessful();

    $budget = IndicatorFact::where('indicator_code', 'budget')->where('period', 'h1')->first();
    expect($budget->actual_hokimyat)->toBeNull();
});

test('the bridge keeps the planned growth beside the reported one', function () {
    $this->artisan('import:task-progress', ['--file' => $this->fixture, '--period' => '2026-H1'])
        ->assertSuccessful();

    // Task 1 line 0: plan 7.2 / actual 8.8 -> both stored as ratios.
    $grp = IndicatorFact::where('indicator_code', 'grp')->where('period', 'h1')->first();
    expect((float) $grp->growth_pct)->toBeNumericallyClose(108.8, 1e-4);
    expect((float) $grp->plan_growth_pct)->toBeNumericallyClose(107.2, 1e-4);

    // Task 36 line 1: plan 14.5 / actual 16.1.
    $srv = IndicatorFact::where('indicator_code', 'services')->where('period', 'h1')->first();
    expect((float) $srv->growth_pct)->toBeNumericallyClose(116.1, 1e-4);
    expect((float) $srv->plan_growth_pct)->toBeNumericallyClose(114.5, 1e-4);
});
