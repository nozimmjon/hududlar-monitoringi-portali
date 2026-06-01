<?php

use App\Livewire\Dashboard\KpiWorkspaceCard;
use Livewire\Livewire;

it('renders the period row on the grp KPI page', function () {
    Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'grp'])
        ->assertSeeHtml('class="macro-period-row"')
        ->assertDontSeeHtml('class="macro-hero-strip"');
});
