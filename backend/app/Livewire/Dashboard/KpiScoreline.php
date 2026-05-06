<?php

namespace App\Livewire\Dashboard;

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
        // Mock counts per Plan 10 Q1=C decision (real tasks data lands in Plan 12)
        $total = 12;
        $done = 7;
        $open = $total - $done;
        $pct = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        $kpiShort = $this->kpi === 'grp' ? 'ЯҲМ' : ucfirst($this->kpi);
        $scope = $kpiShort . 'га оид чора-тадбирлар';

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
