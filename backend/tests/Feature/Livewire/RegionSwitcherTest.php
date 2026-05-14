<?php

use App\Livewire\RegionSwitcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('renders all 14 regions in select view data', function () {
    Livewire::test(RegionSwitcher::class)
        ->assertViewHas('regions', fn ($r) => $r->count() === 14);
});

test('select mutates session', function () {
    Livewire::test(RegionSwitcher::class)
        ->call('select', 1726);
    expect(Session::get('region_code'))->toBe(1726);
});

test('select with invalid code does not write session', function () {
    Livewire::test(RegionSwitcher::class)
        ->call('select', 99999);
    expect(Session::has('region_code'))->toBeFalse();
});
