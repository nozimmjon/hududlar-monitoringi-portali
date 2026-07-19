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

    public string $period = 'year';

    public int $regionCode;

    public function mount(): void
    {
        $this->regionCode = \App\Support\CurrentRegion::code();
    }

    public function render()
    {
        $base = Task::forRegion($this->regionCode)->forModule($this->module);

        if (in_array($this->module, DashboardCatalog::MODULES_WITH_INDICATOR_TASKS, true)) {
            $base->forIndicator($this->kpi);
        }

        $tasks = $base->get(['status', 'period_code', 'deadline_text']);

        // Год якуни = full annual picture (all tasks); I ярим йиллик = h1-bucket only.
        if ($this->period === 'h1') {
            $tasks = $tasks->filter(
                fn ($t) => \App\Support\TaskPeriod::deadlineBucket($t->period_code, $t->deadline_text) === 'h1'
            );
        }

        $total = $tasks->count();
        $done  = $tasks->where('status', 'done')->count();
        $open  = $total - $done;
        $pct   = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        $indicator = Indicator::where('code', $this->kpi)->first();
        $kpiShort  = $indicator?->label_short ?? $this->kpi;
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
