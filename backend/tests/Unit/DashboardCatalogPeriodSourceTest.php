<?php

use App\Support\DashboardCatalog;

function factRow(array $attrs): object
{
    return (object) array_merge([
        'plan_value' => null, 'expected_value' => null, 'actual_hokimyat' => null,
        'actual_statkom' => null, 'growth_pct' => null, 'pct_of_plan' => null,
        'hokimyat_reported_at' => null,
    ], $attrs);
}

test('hokimyat actual makes any period actual', function () {
    $row = factRow(['actual_hokimyat' => 100.0, 'plan_value' => 90.0]);
    expect(DashboardCatalog::periodSourceKind('grp', 'h1', $row))->toBe('actual');
});

test('statkom actual makes any period actual', function () {
    $row = factRow(['actual_statkom' => 100.0, 'plan_value' => 90.0]);
    expect(DashboardCatalog::periodSourceKind('grp', 'h1', $row))->toBe('actual');
});

test('reported growth actual makes the period actual', function () {
    // TaskFactBridge stamps hokimyat_reported_at when it writes a growth actual.
    $row = factRow(['growth_pct' => 108.8, 'hokimyat_reported_at' => '2026-07-20 00:00:00']);
    expect(DashboardCatalog::periodSourceKind('grp', 'h1', $row))->toBe('actual');
    expect(DashboardCatalog::periodState('grp', 'h1', $row)['cls'])->toBe('actual');
});

test('planned growth without a report stays a plan', function () {
    // Imported forecast growth: no reported_at stamp -> still Режа.
    $row = factRow(['growth_pct' => 107.1591, 'plan_value' => 52100.8]);
    expect(DashboardCatalog::periodSourceKind('grp', 'h1', $row))->toBe('plan');
    expect(DashboardCatalog::periodState('grp', 'h1', $row)['label'])->toBe('Режа');
});
