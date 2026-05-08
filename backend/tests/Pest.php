<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit/TaskScopeTest.php');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeNumericallyClose', function (float|int|string $expected, float $tolerance = 1e-6) {
    $actual = is_numeric($this->value) ? (float) $this->value : null;
    $expectedFloat = is_numeric($expected) ? (float) $expected : null;

    if ($actual === null || $expectedFloat === null) {
        return $this->toBe($expected);   // fall through to standard equality if types are wrong
    }

    return expect(abs($actual - $expectedFloat))
        ->toBeLessThanOrEqual(
            $tolerance,
            sprintf('Expected %s ± %s, got %s', $expected, $tolerance, $this->value)
        );
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
