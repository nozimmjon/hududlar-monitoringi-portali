<?php

use App\Livewire\Dashboard\KpiModuleTabs;
use Livewire\Livewire;

it('renders module tabs with icon, count, and progress bar classes', function () {
    Livewire::test(KpiModuleTabs::class)
        ->assertSeeHtml('class="module-tab__icon"')
        ->assertSeeHtml('class="module-tab__count"')
        ->assertSeeHtml('class="module-tab__bar"');
});

it('renders 0/0 count when region has no tasks for a module', function () {
    Livewire::test(KpiModuleTabs::class)
        ->assertSeeHtml('0/0');
});
