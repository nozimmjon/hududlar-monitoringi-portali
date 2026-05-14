<?php

use App\Support\CurrentRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('code returns default when session unset', function () {
    expect(CurrentRegion::code())->toBe(1703);
});

test('set writes session and code reflects it', function () {
    CurrentRegion::set(1726);
    expect(CurrentRegion::code())->toBe(1726);
});

test('set ignores unknown region codes', function () {
    Session::flush();
    CurrentRegion::set(99999);
    expect(CurrentRegion::code())->toBe(1703);
});

test('current returns Region model for current code', function () {
    CurrentRegion::set(1726);
    expect(CurrentRegion::current()->code)->toBe(1726);
});

test('regions returns 14 ordered by sort_order', function () {
    $regions = CurrentRegion::regions();
    expect($regions)->toHaveCount(14);
    expect($regions->first()->code)->toBe(1735);  // Karakalpakstan sort=1
});
