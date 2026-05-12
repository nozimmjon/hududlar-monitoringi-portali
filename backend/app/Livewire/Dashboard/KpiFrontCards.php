<?php

namespace App\Livewire\Dashboard;

use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Support\DashboardCatalog;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiFrontCards extends Component
{
    #[Reactive]
    public string $module = 'macro';

    #[Reactive]
    public string $kpi = 'grp';

    public function selectKpi(string $code): void
    {
        $this->dispatch('kpi-selected', kpi: $code);
    }

    public function render()
    {
        $codes = DashboardCatalog::moduleKpis($this->module);

        $facts = IndicatorFact::where('region_code', 1703)
            ->where('year', 2026)
            ->whereNull('district_code')
            ->where('period', 'year')
            ->whereIn('indicator_code', $codes)
            ->get()
            ->keyBy('indicator_code');

        $indicators = Indicator::whereIn('code', $codes)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        return view('livewire.dashboard.kpi-front-cards', [
            'codes'        => $codes,
            'facts'        => $facts,
            'indicators'   => $indicators,
            'layoutClass'  => DashboardCatalog::layoutClass($this->module),
            'selectedKpi'  => $this->kpi,
            'isMacro'      => $this->module === 'macro',
        ]);
    }
}
