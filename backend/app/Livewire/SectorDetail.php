<?php

namespace App\Livewire;

use App\Models\Sector;
use App\Models\SectorTask;
use Livewire\Attributes\Url;
use Livewire\Component;

class SectorDetail extends Component
{
    public Sector $sector;

    /** Status filter (deep-linkable: /sectors/nkmk?holat=open). */
    #[Url(as: 'holat', except: 'all')]
    public string $filter = 'all';

    /** @var array<int, bool> expanded multi-line task ids */
    public array $expanded = [];

    public function mount(string $code): void
    {
        $this->sector = Sector::where('code', $code)->firstOrFail();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'done', 'open', 'in_progress'], true) ? $filter : 'all';
    }

    public function toggleTask(int $taskId): void
    {
        $this->expanded[$taskId] = ! ($this->expanded[$taskId] ?? false);
    }

    public function render()
    {
        $tasks = SectorTask::where('sector_id', $this->sector->id)
            ->orderBy('task_no')
            ->with('progress')
            ->get();

        $counts = [
            'all'         => $tasks->count(),
            'done'        => $tasks->where('status', 'done')->count(),
            'open'        => $tasks->where('status', 'open')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
        ];

        $linesTotal = (int) $tasks->sum('lines_total');
        $linesDone  = (int) $tasks->sum('lines_done');
        $hasReport  = $tasks->contains(fn (SectorTask $t) => $t->status !== 'in_progress');
        $pct        = $hasReport && $linesTotal > 0 ? $linesDone / $linesTotal * 100 : null;

        return view('livewire.sector-detail', [
            'tasks'  => $this->filter === 'all' ? $tasks : $tasks->where('status', $this->filter)->values(),
            'counts' => $counts,
            'agg'    => [
                'lines_total' => $linesTotal,
                'lines_done'  => $linesDone,
                'pct'         => $pct,
            ],
        ]);
    }
}
