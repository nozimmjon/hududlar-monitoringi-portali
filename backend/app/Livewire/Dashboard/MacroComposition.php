<?php

namespace App\Livewire\Dashboard;

use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Support\DashboardCatalog;
use Livewire\Component;

class MacroComposition extends Component
{
    public function selectKpi(string $code): void
    {
        $this->dispatch('kpi-selected', kpi: $code);
    }

    public function render()
    {
        $components = ['industry', 'agriculture', 'construction', 'services'];

        $facts = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNull('district_code')
            ->whereIn('indicator_code', $components)
            ->where('period', 'year')
            ->get()
            ->keyBy('indicator_code');

        $indicators = Indicator::whereIn('code', $components)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        return view('livewire.dashboard.macro-composition', [
            'components' => $components,
            'facts'      => $facts,
            'indicators' => $indicators,
        ]);
    }
}
