<?php

namespace App\Livewire\Dashboard;

use App\Models\IndicatorFact;
use App\Support\CurrentRegion;
use App\Support\DashboardCatalog;
use Livewire\Component;

class IndustryDriverPanel extends Component
{
    public function render()
    {
        $facts = IndicatorFact::where('region_code', CurrentRegion::code())
            ->where('year', 2026)
            ->whereNull('district_code')
            ->whereIn('indicator_code', ['localization', 'energy_electricity', 'energy_gas'])
            ->whereIn('period', ['h1', 'year'])
            ->get()
            ->groupBy('indicator_code');

        return view('livewire.dashboard.industry-driver-panel', [
            'industryDrivers' => DashboardCatalog::industryDrivers($facts),
        ]);
    }
}
