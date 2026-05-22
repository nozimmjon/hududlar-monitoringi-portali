<?php

use App\Livewire\Dashboard\KpiModuleTabs;
use Livewire\Livewire;

it('renders module tabs with body and count classes', function () {
    Livewire::test(KpiModuleTabs::class)
        ->assertSeeHtml('class="module-tab__body"')
        ->assertSeeHtml('class="module-tab__count"');
});

it('renders 0/0 count when region has no tasks for a module', function () {
    Livewire::test(KpiModuleTabs::class)
        ->assertSeeHtml('0/0');
});
