<?php

use App\Models\Indicator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('budget indicator has supported_periods = [h1, q2, year]', function () {
    $this->seed();
    $budget = Indicator::where('code', 'budget')->firstOrFail();
    expect($budget->supported_periods)->toBe(['h1', 'q2', 'year']);
});

test('macro indicators still use the default 4-period set', function () {
    $this->seed();
    $grp = Indicator::where('code', 'grp')->firstOrFail();
    expect($grp->supported_periods)->toBe(['q1', 'h1', 'm9', 'year']);
});
