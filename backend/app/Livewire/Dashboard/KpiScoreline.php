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
        // hasPlan() mirrors the tasks board — unmeasurable tasks stay out of the count.
        $base = Task::forRegion($this->regionCode)->hasPlan()->forModule($this->module);

        if (in_array($this->module, DashboardCatalog::MODULES_WITH_INDICATOR_TASKS, true)) {
            $base->forIndicator($this->kpi);
        }

        $tasks = $base->get(['status', 'period_code', 'deadline_text']);

        // Same rule as the module tabs: the h1 bucket plus anything already
        // finished, so early delivery counts towards the half-year result.
        if ($this->period === 'h1') {
            $tasks = $tasks->filter(
                fn ($t) => $t->status === 'done'
                    || \App\Support\TaskPeriod::deadlineBucket($t->period_code, $t->deadline_text) === 'h1'
            );
        }

        // Same reading as the entry page: on track unless reported behind plan.
        $total = $tasks->count();
        $done  = $tasks->whereIn('status', ['done', 'in_progress'])->count();
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
