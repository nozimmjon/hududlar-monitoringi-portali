<?php

namespace App\Livewire;

use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\SectorTaskProgress;
use App\Support\SectorDisplay;
use Livewire\Component;

class SectorsDashboard extends Component
{
    public string $search = '';

    /** Code of the sector whose drawer is open, or null. */
    public ?string $open = null;

    /** Drawer status-tab filter. */
    public string $filter = 'all';

    /** @var array<int, bool> expanded multi-line task ids inside the drawer */
    public array $expanded = [];

    public function openSector(string $code): void
    {
        if (! Sector::where('code', $code)->exists()) {
            return;
        }
        $this->open     = $code;
        $this->filter   = 'all';
        $this->expanded = [];
    }

    public function closeSector(): void
    {
        $this->open = null;
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
        $sectors = Sector::orderBy('sort_order')
            ->with(['tasks' => fn ($q) => $q->orderBy('task_no')])
            ->get();

        // Earliest deadline bucket per sector, one aggregate query. deadline_code
        // is normalized on import (q3|q4|h2|year); the CASE ranks come from the
        // shared constants so order/labels live in one place.
        $case = collect(SectorDisplay::DEADLINE_ORDER)
            ->map(fn (int $rank, string $code) => "when '{$code}' then {$rank}")
            ->implode(' ');
        $fallback  = SectorDisplay::DEADLINE_ORDER['year'];
        $deadlines = SectorTaskProgress::query()
            ->join('sector_tasks', 'sector_tasks.id', '=', 'sector_task_progress.sector_task_id')
            ->groupBy('sector_tasks.sector_id')
            ->selectRaw("sector_tasks.sector_id, min(case deadline_code {$case} else {$fallback} end) as bucket")
            ->pluck('bucket', 'sector_id');

        $bucketLabels = array_combine(
            array_values(SectorDisplay::DEADLINE_ORDER),
            array_values(SectorDisplay::DEADLINE_LABELS),
        );

        $cards = $sectors->values()->map(function (Sector $s, int $idx) use ($deadlines, $bucketLabels, $fallback): array {
            $tasks      = $s->tasks;
            $linesTotal = (int) $tasks->sum('lines_total');
            $linesDone  = (int) $tasks->sum('lines_done');
            $hasReport  = $tasks->contains(fn (SectorTask $t) => $t->status !== 'in_progress');

            return [
                'idx'         => $idx + 1,
                'sector'      => $s,
                'logo'        => $s->logoPath(),
                'tasks_total' => $tasks->count(),
                'lines_total' => $linesTotal,
                'lines_done'  => $linesDone,
                'done'        => $tasks->where('status', 'done')->count(),
                'open'        => $tasks->where('status', 'open')->count(),
                'waiting'     => $tasks->where('status', 'in_progress')->count(),
                // Indicator-level completion; null = nothing reported yet.
                'pct'         => $hasReport && $linesTotal > 0 ? $linesDone / $linesTotal * 100 : null,
                'strip'       => $tasks->map(fn (SectorTask $t) => [
                    'no'     => $t->task_no,
                    'status' => $t->status,
                    'pct'    => SectorDisplay::taskPct($t),
                ])->all(),
                'deadline'    => $bucketLabels[$deadlines[$s->id] ?? $fallback],
            ];
        });

        $needle  = mb_strtolower(trim($this->search));
        $visible = $needle === '' ? $cards : $cards->filter(function (array $c) use ($needle): bool {
            return str_contains(mb_strtolower($c['sector']->cardName().' '.$c['sector']->org_full), $needle);
        })->values();

        $summary = [
            'sectors'     => $cards->count(),
            'tasks'       => (int) $cards->sum('tasks_total'),
            'done'        => (int) $cards->sum('done'),
            'open'        => (int) $cards->sum('open'),
            'waiting'     => (int) $cards->sum('waiting'),
            'lines_total' => (int) $cards->sum('lines_total'),
            'lines_done'  => (int) $cards->sum('lines_done'),
        ];
        $summary['pct'] = $summary['lines_total'] > 0
            ? $summary['lines_done'] / $summary['lines_total'] * 100
            : 0.0;

        $ranked = $cards->filter(fn (array $c) => $c['pct'] !== null)->sortByDesc('pct')->values();

        $drawer = null;
        if ($this->open !== null) {
            $sector = Sector::where('code', $this->open)->first();
            if ($sector) {
                $drawer = SectorDisplay::detailData($sector, $this->filter);
            }
        }

        return view('livewire.sectors-dashboard', [
            'cards'   => $cards,
            'visible' => $visible,
            'summary' => $summary,
            'ranked'  => $ranked,
            'drawer'  => $drawer,
        ]);
    }
}
