<?php

namespace App\Livewire;

use App\Models\Sector;
use App\Models\SectorTask;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class SectorsDashboard extends Component
{
    /** Selected sector code (deep-linkable: /sectors?sector=nkmk). */
    #[Url(as: 'sector')]
    public ?string $sector = null;

    /** @var array<int, bool> expanded multi-line task ids in the drilldown */
    public array $expanded = [];

    public function selectSector(string $code): void
    {
        $this->sector = $this->sector === $code ? null : $code;
        $this->expanded = [];
    }

    public function toggleTask(int $taskId): void
    {
        $this->expanded[$taskId] = ! ($this->expanded[$taskId] ?? false);
    }

    public function render()
    {
        $sectors = Sector::orderBy('sort_order')->with('tasks')->get();

        $cards = $sectors->map(function (Sector $s): array {
            $tasks = $s->tasks;
            $linesTotal = (int) $tasks->sum('lines_total');
            $linesDone  = (int) $tasks->sum('lines_done');
            $hasReport  = $tasks->contains(fn (SectorTask $t) => $t->status !== 'in_progress');

            return [
                'sector'      => $s,
                'logo'        => $s->logoPath(),
                'tasks_total' => $tasks->count(),
                'lines_total' => $linesTotal,
                'done'        => $tasks->where('status', 'done')->count(),
                'open'        => $tasks->where('status', 'open')->count(),
                'waiting'     => $tasks->where('status', 'in_progress')->count(),
                // Indicator-level completion; null = nothing reported yet.
                'pct'         => $hasReport && $linesTotal > 0 ? $linesDone / $linesTotal * 100 : null,
            ];
        });

        $selected = $this->sector !== null
            ? $sectors->firstWhere('code', $this->sector)
            : null;

        $selectedTasks = $selected
            ? SectorTask::where('sector_id', $selected->id)
                ->orderBy('task_no')
                ->with('progress')
                ->get()
            : new Collection();

        return view('livewire.sectors-dashboard', [
            'cards'         => $cards,
            'selected'      => $selected,
            'selectedTasks' => $selectedTasks,
            'summary'       => [
                'sectors' => $cards->count(),
                'tasks'   => $cards->sum('tasks_total'),
                'done'    => $cards->sum('done'),
                'open'    => $cards->sum('open'),
                'waiting' => $cards->sum('waiting'),
            ],
        ]);
    }
}
