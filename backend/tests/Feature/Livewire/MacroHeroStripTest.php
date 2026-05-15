<?php

use App\Livewire\Dashboard\KpiWorkspaceCard;
use Livewire\Livewire;

it('renders dark hero strip on grp KPI page', function () {
    Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'grp'])
        ->assertSeeHtml('class="macro-hero-strip"')
        ->assertSeeHtml('macro-hero-strip__chip is-actual')
        ->assertSeeHtml('macro-hero-strip__chip is-target');
});

it('does not render hero strip on industry KPI page', function () {
    Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'industry'])
        ->assertDontSeeHtml('class="macro-hero-strip"')
        ->assertSeeHtml('class="macro-hero-card"');
});
