<?php

use App\Support\TaskStatus;

test('status is done at or above 100 percent', function () {
    expect(TaskStatus::statusFor(100.0))->toBe('done');
    expect(TaskStatus::statusFor(150.0))->toBe('done');
});

test('status is open below 100 percent or when missing', function () {
    expect(TaskStatus::statusFor(99.99))->toBe('open');
    expect(TaskStatus::statusFor(0.0))->toBe('open');
    expect(TaskStatus::statusFor(null))->toBe('open');
});
