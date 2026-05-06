<?php

use App\Enums\Period;

test('Period enum includes Q2 case', function () {
    expect(Period::Q2->value)->toBe('q2');
});

test('Period enum has exactly 5 cases', function () {
    expect(Period::cases())->toHaveCount(5);
});

test('Period::from("q2") returns Period::Q2', function () {
    expect(Period::from('q2'))->toBe(Period::Q2);
});
