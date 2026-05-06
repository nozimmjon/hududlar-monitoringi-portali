<?php

test('toBeNumericallyClose accepts equal floats', function () {
    expect(1.0)->toBeNumericallyClose(1.0, 1e-9);
});

test('toBeNumericallyClose accepts within-tolerance floats', function () {
    expect(108.6)->toBeNumericallyClose(108.5999999, 1e-3);
});

test('toBeNumericallyClose rejects out-of-tolerance values', function () {
    expect(fn () => expect(108.6)->toBeNumericallyClose(108.0, 1e-3))
        ->toThrow(\PHPUnit\Framework\ExpectationFailedException::class);
});

test('toBeNumericallyClose accepts numeric strings (decimal column types)', function () {
    expect('108.600000')->toBeNumericallyClose(108.6, 1e-6);
});
