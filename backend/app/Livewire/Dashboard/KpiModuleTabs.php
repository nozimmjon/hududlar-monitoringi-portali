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

        // hasPlan() mirrors the tasks board: a task with nothing planned has no
        // execution to report, so it must not inflate the completion counts.
        $tasks = Task::forRegion($this->regionCode)
            ->hasPlan()
            ->whereNotNull('module_code')
            ->get(['id', 'module_code', 'status', 'period_code', 'deadline_text']);

        // Йил якуни = full annual picture (all tasks); I ярим йиллик = the h1 bucket
        // plus anything already finished, so work delivered ahead of a later
        // deadline still counts towards the half-year result.
        if ($this->period === 'h1') {
            $tasks = $tasks->filter(
                fn ($t) => $t->status === 'done'
                    || TaskPeriod::deadlineBucket($t->period_code, $t->deadline_text) === 'h1'
            );
        }

        foreach ($tasks->groupBy('module_code') as $code => $group) {
            $taskCounts[$code] = [
                // Same reading as the entry page: a task is on track unless it is
                // reported behind plan, so Бажарилмоқда counts with Бажарилди.
                'done'  => $group->whereIn('status', ['done', 'in_progress'])->count(),
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
