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

test('roadmapsOnly limits the list to regions that have a road map; default lists all 14', function () {
    \App\Models\Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);

    Livewire::test(RegionSwitcher::class, ['roadmapsOnly' => true])
        ->assertViewHas('regions', fn ($r) => $r->count() === 1 && (int) $r->first()->code === 1733);
    Livewire::test(RegionSwitcher::class)
        ->assertViewHas('regions', fn ($r) => $r->count() === 14);
});

test('the trigger shows the session region even when it is not in the filtered list', function () {
    \App\Models\Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    Session::put('region_code', 1726);   // Тошкент шаҳри — no road map

    Livewire::test(RegionSwitcher::class, ['roadmapsOnly' => true])
        ->assertSee('Тошкент шаҳри');
});
