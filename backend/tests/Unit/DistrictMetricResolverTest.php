<?php

use App\Models\IndicatorFact;
use App\Support\DistrictMetricResolver;

test('value returns dash on null row', function () {
    expect(DistrictMetricResolver::value(null, 'growth'))->toBe('—');
    expect(DistrictMetricResolver::value(null, 'plan'))->toBe('—');
});

test('value formats growth with sign + comma decimal + percent', function () {
    $row = new IndicatorFact();
    $row->growth_pct = 8.0;
    $row->unit = 'trln';
    expect(DistrictMetricResolver::value($row, 'growth'))->toBe('+8,0%');

    $row->growth_pct = -2.1;
    expect(DistrictMetricResolver::value($row, 'growth'))->toBe('-2,1%');
});

test('value formats execution with sign + percent', function () {
    $row = new IndicatorFact();
    $row->pct_of_plan = 95.0;
    expect(DistrictMetricResolver::value($row, 'execution'))->toBe('+95,0%');
});

test('value formats plan with comma decimal and unit', function () {
    $row = new IndicatorFact();
    $row->plan_value = 100.5;
    $row->unit = 'trln';
    expect(DistrictMetricResolver::value($row, 'plan'))->toBe('100,5 trln');
});

test('value falls back to statkom when hokimyat null', function () {
    $row = new IndicatorFact();
    $row->actual_hokimyat = null;
    $row->actual_statkom = 12.3;
    $row->unit = 'млн';
    expect(DistrictMetricResolver::value($row, 'fact'))->toBe('12,3 млн');
});

test('value returns dash when the chosen metric column is null', function () {
    $row = new IndicatorFact();
    $row->growth_pct = null;
    expect(DistrictMetricResolver::value($row, 'growth'))->toBe('—');
});

test('note prefixes plan/fact/volume strings', function () {
    $row = new IndicatorFact();
    $row->plan_value = 50.0;
    $row->actual_hokimyat = 47.0;
    $row->unit = 'trln';

    expect(DistrictMetricResolver::note($row, 'plan'))->toBe('режа 50,0 trln');
    expect(DistrictMetricResolver::note($row, 'fact'))->toBe('факт 47,0 trln');
    expect(DistrictMetricResolver::note($row, 'volume'))->toBe('ҳажм 50,0 trln');
});

test('note returns empty string when row or kind null', function () {
    expect(DistrictMetricResolver::note(null, 'plan'))->toBe('');
    $row = new IndicatorFact();
    expect(DistrictMetricResolver::note($row, null))->toBe('');
});
