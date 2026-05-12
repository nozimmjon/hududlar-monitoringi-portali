<?php

use App\Support\DistrictStatus;

test('null pct + null growth returns grey', function () {
    expect(DistrictStatus::statusFor(null, null, false))->toBe('grey');
    expect(DistrictStatus::statusFor(null, null, true))->toBe('grey');
});

test('higher-is-better thresholds use 95/80 split', function () {
    expect(DistrictStatus::statusFor(95.0, null, false))->toBe('green');
    expect(DistrictStatus::statusFor(94.9, null, false))->toBe('amber');
    expect(DistrictStatus::statusFor(80.0, null, false))->toBe('amber');
    expect(DistrictStatus::statusFor(79.9, null, false))->toBe('red');
});

test('lower-is-better thresholds use 100/110 split', function () {
    expect(DistrictStatus::statusFor(100.0, null, true))->toBe('green');
    expect(DistrictStatus::statusFor(100.1, null, true))->toBe('amber');
    expect(DistrictStatus::statusFor(110.0, null, true))->toBe('amber');
    expect(DistrictStatus::statusFor(110.1, null, true))->toBe('red');
});

test('falls back to growth when pct_of_plan is null', function () {
    expect(DistrictStatus::statusFor(null, 96.0, false))->toBe('green');
    expect(DistrictStatus::statusFor(null, 70.0, false))->toBe('red');
});

test('uses pct over growth when both present', function () {
    expect(DistrictStatus::statusFor(60.0, 99.0, false))->toBe('red');
    expect(DistrictStatus::statusFor(99.0, 50.0, false))->toBe('green');
});
