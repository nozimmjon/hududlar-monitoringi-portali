<?php

namespace App\Livewire\Dashboard;

use App\Support\DashboardCatalog;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiModuleTabs extends Component
{
    #[Reactive]
    public string $module = 'macro';

    public function selectModule(string $code): void
    {
        $this->dispatch('module-selected', module: $code);
    }

    public function render()
    {
        return view('livewire.dashboard.kpi-module-tabs', [
            'modules'      => DashboardCatalog::modules(),
            'currentModule' => $this->module,
        ]);
    }
}
