<?php

namespace App\Livewire\Dashboard;

use App\Models\Task;
use App\Support\CurrentRegion;
use App\Support\DashboardCatalog;
use App\Support\TaskPeriod;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiModuleTabs extends Component
{
    #[Reactive]
    public string $module = 'macro';

    public string $period = 'year';

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

        $tasks = Task::forRegion($this->regionCode)
            ->whereNotNull('module_code')
            ->get(['module_code', 'status', 'period_code', 'deadline_text']);

        // Год якуни = full annual picture (all tasks); I ярим йиллик = h1-bucket only.
        if ($this->period === 'h1') {
            $tasks = $tasks->filter(
                fn ($t) => TaskPeriod::deadlineBucket($t->period_code, $t->deadline_text) === 'h1'
            );
        }

        foreach ($tasks->groupBy('module_code') as $code => $group) {
            $taskCounts[$code] = [
                'done'  => $group->where('status', 'done')->count(),
                'total' => $group->count(),
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
