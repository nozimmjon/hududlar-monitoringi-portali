<?php

use App\Livewire\Dashboard\KpiWorkspaceCard;
use Livewire\Livewire;

it('renders the period row on the grp KPI page', function () {
    Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'grp'])
        ->assertSeeHtml('class="macro-period-row"')
        ->assertDontSeeHtml('class="macro-hero-strip"');
});

it('renders the industry main panel, not the period row, on the industry KPI page', function () {
    Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'industry'])
        ->assertSeeHtml('class="macro-main-panel"')
        ->assertDontSeeHtml('class="macro-period-row"');
});
