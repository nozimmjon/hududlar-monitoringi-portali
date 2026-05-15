<?php

namespace App\Livewire\Dashboard;

use App\Models\Task;
use App\Support\CurrentRegion;
use App\Support\DashboardCatalog;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiModuleTabs extends Component
{
    #[Reactive]
    public string $module = 'macro';

    public int $regionCode;

    public function mount(): void
    {
        $this->regionCode = CurrentRegion::code();
    }

    public function selectModule(string $code): void
    {
        $this->dispatch('module-selected', module: $code);
    }

    public function render()
    {
        $taskCounts = [];
        foreach (DashboardCatalog::moduleCodes() as $code) {
            $taskCounts[$code] = ['done' => 0, 'total' => 0];
        }

        $rows = Task::forRegion($this->regionCode)
            ->selectRaw("module_code, COUNT(*) AS total, SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done")
            ->whereNotNull('module_code')
            ->groupBy('module_code')
            ->get();

        foreach ($rows as $row) {
            $taskCounts[$row->module_code] = [
                'done'  => (int) $row->done,
                'total' => (int) $row->total,
            ];
        }

        $icons = [];
        foreach (DashboardCatalog::moduleCodes() as $code) {
            $icons[$code] = DashboardCatalog::moduleIcon($code);
        }

        return view('livewire.dashboard.kpi-module-tabs', [
            'modules'       => DashboardCatalog::modules(),
            'currentModule' => $this->module,
            'taskCounts'    => $taskCounts,
            'icons'         => $icons,
        ]);
    }
}
