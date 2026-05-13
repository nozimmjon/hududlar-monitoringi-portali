<?php

namespace App\Livewire\Dashboard;

use App\Models\Indicator;
use App\Models\Task;
use App\Support\DashboardCatalog;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiScoreline extends Component
{
    #[Reactive]
    public string $module = 'macro';

    #[Reactive]
    public string $kpi = 'grp';

    public function render()
    {
        $base = Task::forRegion(1703)->forModule($this->module);

        if (in_array($this->module, DashboardCatalog::MODULES_WITH_INDICATOR_TASKS, true)) {
            $base->forIndicator($this->kpi);
        }

        $total = (clone $base)->count();
        $done  = (clone $base)->where('status', 'done')->count();
        $open  = $total - $done;
        $pct   = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        $indicator = Indicator::where('code', $this->kpi)->first();
        $kpiShort  = $indicator->label_short ?? $this->kpi;
        $scope     = $kpiShort . 'га оид чора-тадбирлар';

        return view('livewire.dashboard.kpi-scoreline', [
            'total'  => $total,
            'done'   => $done,
            'open'   => $open,
            'pct'    => $pct,
            'scope'  => $scope,
            'module' => $this->module,
        ]);
    }
}
