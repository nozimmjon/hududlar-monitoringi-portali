<?php

use App\Support\DashboardCatalog;

test('moduleIcon returns trend for macro', function () {
    expect(DashboardCatalog::moduleIcon('macro'))->toBe('trend');
});

test('moduleIcon returns price for inflation', function () {
    expect(DashboardCatalog::moduleIcon('inflation'))->toBe('price');
});

test('moduleIcon returns bank for budget', function () {
    expect(DashboardCatalog::moduleIcon('budget'))->toBe('bank');
});

test('moduleIcon returns briefcase for budget_invest', function () {
    expect(DashboardCatalog::moduleIcon('budget_invest'))->toBe('briefcase');
});

test('moduleIcon returns globe for foreign_invest', function () {
    expect(DashboardCatalog::moduleIcon('foreign_invest'))->toBe('globe');
});

test('moduleIcon returns rocket for export', function () {
    expect(DashboardCatalog::moduleIcon('export'))->toBe('rocket');
});

test('moduleIcon returns users for employment', function () {
    expect(DashboardCatalog::moduleIcon('employment'))->toBe('users');
});

test('moduleIcon falls back to trend for unknown code', function () {
    expect(DashboardCatalog::moduleIcon('does_not_exist'))->toBe('trend');
});
