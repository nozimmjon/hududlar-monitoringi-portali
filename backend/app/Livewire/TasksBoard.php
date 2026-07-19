<?php

namespace App\Livewire;

use App\Models\Task;
use App\Models\Module;
use App\Models\District;
use App\Support\TaskPeriod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class TasksBoard extends Component
{
    #[Url]
    public string $module = 'all';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $period = 'all';

    #[Url]
    public string $district = 'all';

    #[Url]
    public string $deadline = 'h1';

    #[Url]
    public string $search = '';

    public int $regionCode;

    public function mount(): void
    {
        $this->regionCode = \App\Support\CurrentRegion::code();
    }

    public function selectModule(string $code): void
    {
        $this->module = $code;
    }

    public function selectStatus(string $code): void
    {
        $this->status = $code;
    }

    public function selectPeriod(string $code): void
    {
        $this->period = $code;
    }

    public function selectDistrict(string $code): void
    {
        $this->district = $code;
    }

    public function selectDeadline(string $code): void
    {
        $this->deadline = $code;
    }

    public function clearFilters(): void
    {
        $this->module = 'all';
        $this->status = 'all';
        $this->period = 'all';
        $this->district = 'all';
        $this->deadline = 'h1';
        $this->search = '';
    }

    #[Computed]
    public function tasks()
    {
        $q = Task::with(['module', 'indicator', 'districts'])
            ->with(['progress' => function ($p) {
                $p->orderBy('line_no');
            }])
            ->forRegion($this->regionCode)
            ->hasPlan();

        if ($this->module !== 'all')   $q->forModule($this->module);
        if ($this->period !== 'all')   $q->forPeriod($this->period);
        if ($this->district !== 'all') $q->forDistrict((int) $this->district);
        if ($this->search !== '')      $q->search($this->search);

        // Status: 'open' shows non-done; 'done' shows done; 'all' shows all.
        if ($this->status === 'open') $q->where('status', '!=', 'done');
        if ($this->status === 'done') $q->where('status', 'done');

        $tasks = $q->get();

        if ($this->deadline !== 'all') {
            $tasks = $tasks->filter(
                fn ($t) => TaskPeriod::deadlineBucket($t->period_code, $t->deadline_text) === $this->deadline
            );
        }

        // Tasks with a reported actual first; then by deadline bucket
        // (H1 → Q3 → year-end → ongoing); then source order.
        return $tasks
            ->sortBy([
                fn ($a, $b) => ($a->headline_actual === null) <=> ($b->headline_actual === null),
                fn ($a, $b) => TaskPeriod::deadlineSortRank($a->period_code, $a->deadline_text)
                           <=> TaskPeriod::deadlineSortRank($b->period_code, $b->deadline_text),
                fn ($a, $b) => $a->source_paragraph_index <=> $b->source_paragraph_index,
            ])
            ->values();
    }

    #[Computed]
    public function moduleOptions()
    {
        $codes = Task::forRegion($this->regionCode)
            ->hasPlan()
            ->whereNotNull('module_code')
            ->distinct()
            ->pluck('module_code');

        return Module::whereIn('code', $codes)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function districtOptions()
    {
        $taskIds = Task::forRegion($this->regionCode)->hasPlan()->pluck('id');

        return District::whereHas('tasks', fn ($q) => $q->whereIn('tasks.id', $taskIds))
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function deadlineOptions(): array
    {
        $present = Task::forRegion($this->regionCode)
            ->hasPlan()
            ->get(['period_code', 'deadline_text'])
            ->map(fn ($t) => TaskPeriod::deadlineBucket($t->period_code, $t->deadline_text))
            ->unique();

        return array_filter(
            TaskPeriod::deadlineBucketLabels(),
            fn ($code) => $present->contains($code),
            ARRAY_FILTER_USE_KEY
        );
    }

    #[Computed]
    public function totals(): array
    {
        $base = Task::forRegion($this->regionCode)->hasPlan();
        if ($this->module !== 'all')    $base->forModule($this->module);
        if ($this->period !== 'all')    $base->forPeriod($this->period);
        if ($this->district !== 'all')  $base->forDistrict((int) $this->district);
        if ($this->search !== '')       $base->search($this->search);

        $all = $base->get(['id', 'status', 'period_code', 'deadline_text']);

        if ($this->deadline !== 'all') {
            $all = $all->filter(
                fn ($t) => TaskPeriod::deadlineBucket($t->period_code, $t->deadline_text) === $this->deadline
            );
        }

        $total = $all->count();
        $done  = $all->where('status', 'done')->count();

        return [
            'total' => $total,
            'done'  => $done,
            'open'  => $total - $done,
            'pct'   => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    public function render()
    {
        return view('livewire.tasks-board');
    }
}
