<?php

namespace App\Livewire;

use App\Support\DashboardCatalog;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class KpiDashboard extends Component
{
    #[Url]
    public string $module = 'macro';

    #[Url]
    public string $kpi = 'grp';

    public function mount(): void
    {
        if (! DashboardCatalog::module($this->module)) {
            $this->module = 'macro';
        }
        if (! in_array($this->kpi, DashboardCatalog::moduleKpis($this->module), true)) {
            $this->kpi = DashboardCatalog::firstKpiForModule($this->module);
        }
    }

    #[On('module-selected')]
    public function selectModule(string $module): void
    {
        if (! DashboardCatalog::module($module)) {
            return;
        }
        $this->module = $module;
        $this->kpi = DashboardCatalog::firstKpiForModule($module);
    }

    #[On('kpi-selected')]
    public function selectKpi(string $kpi): void
    {
        $this->kpi = $kpi;
        $this->module = DashboardCatalog::moduleForKpi($kpi);
    }

    public function render()
    {
        return view('livewire.kpi-dashboard', [
            'module'      => $this->module,
            'kpi'         => $this->kpi,
            'moduleLabel' => DashboardCatalog::moduleLabel($this->module),
            'moduleIntro' => DashboardCatalog::moduleIntro($this->module),
            'hasFrontCards' => DashboardCatalog::hasFrontCards($this->module),
        ]);
    }
}
