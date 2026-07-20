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

    public string $period = 'h1';

    public int $regionCode;

    public function mount(): void
    {
        $this->regionCode = \App\Support\CurrentRegion::code();
    }

    public function selectKpi(string $code): void
    {
        $this->dispatch('kpi-selected', kpi: $code);
    }

    public function render()
    {
        $codes = DashboardCatalog::moduleKpis($this->module);

        $facts = IndicatorFact::where('region_code', $this->regionCode)
            ->where('year', 2026)
            ->whereNull('district_code')
            ->where('period', $this->period)
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
            // The cards read facts for the selected period, so the caption must
            // name that period — not always "йиллик".
            'growthNote'   => $this->period === 'h1' ? 'ярим йиллик ўсиш' : 'йиллик ўсиш',
        ]);
    }
}
