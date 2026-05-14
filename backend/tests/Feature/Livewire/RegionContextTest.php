<?php

use App\Livewire\DistrictsPage;
use App\Livewire\Dashboard\KpiFrontCards;
use App\Livewire\Dashboard\KpiScoreline;
use App\Livewire\Dashboard\KpiWorkspaceCard;
use App\Livewire\Dashboard\MacroComposition;
use App\Livewire\ExecutionPage;
use App\Livewire\RegionProfile;
use App\Livewire\TasksBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('region-aware components read session for region context', function () {
    Session::put('region_code', 1726);

    foreach ([
        DistrictsPage::class,
        KpiFrontCards::class,
        KpiScoreline::class,
        KpiWorkspaceCard::class,
        MacroComposition::class,
        ExecutionPage::class,
        RegionProfile::class,
        TasksBoard::class,
    ] as $component) {
        $rendered = Livewire::test($component);
        expect($rendered->get('regionCode'))->toBe(1726, "{$component} regionCode");
    }
});

test('default session falls back to Andijan 1703', function () {
    Session::flush();
    Livewire::test(DistrictsPage::class)
        ->assertSet('regionCode', 1703);
});
