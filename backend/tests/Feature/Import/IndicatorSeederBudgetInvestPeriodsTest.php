<?php

use App\Models\Indicator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('budget_investment indicator has supported_periods = [q1, h1, year]', function () {
    $this->seed();
    $bi = Indicator::where('code', 'budget_investment')->firstOrFail();
    expect($bi->supported_periods)->toBe(['q1', 'h1', 'year']);
});

test('macro indicators still use the default 4-period set', function () {
    $this->seed();
    $grp = Indicator::where('code', 'grp')->firstOrFail();
    expect($grp->supported_periods)->toBe(['q1', 'h1', 'm9', 'year']);
});
