<?php

namespace App\Livewire;

use App\Models\Task;
use App\Models\Module;
use App\Models\Indicator;
use App\Models\District;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class TasksBoard extends Component
{
    #[Url]
    public string $module = 'all';

    #[Url]
    public string $indicator = 'all';

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $period = 'all';

    #[Url]
    public string $district = 'all';

    #[Url]
    public string $search = '';

    public int $regionCode = 1703;

    public function selectModule(string $code): void
    {
        $this->module = $code;
        $this->indicator = 'all';
    }

    public function selectIndicator(string $code): void
    {
        $this->indicator = $code;
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

    public function clearFilters(): void
    {
        $this->module = 'all';
        $this->indicator = 'all';
        $this->status = 'open';
        $this->period = 'all';
        $this->district = 'all';
        $this->search = '';
    }

    #[Computed]
    public function tasks()
    {
        $q = Task::with(['module', 'indicator', 'districts'])
            ->forRegion($this->regionCode);

        if ($this->module !== 'all')   $q->forModule($this->module);
        if ($this->indicator !== 'all') $q->forIndicator($this->indicator);
        if ($this->period !== 'all')   $q->forPeriod($this->period);
        if ($this->district !== 'all') $q->forDistrict((int) $this->district);
        if ($this->search !== '')      $q->search($this->search);

        // Status: 'open' shows non-done; 'done' shows done; 'all' shows all.
        if ($this->status === 'open') $q->where('status', '!=', 'done');
        if ($this->status === 'done') $q->where('status', 'done');

        return $q->orderBy('source_paragraph_index')->get();
    }

    #[Computed]
    public function moduleOptions()
    {
        $codes = Task::forRegion($this->regionCode)
            ->whereNotNull('module_code')
            ->distinct()
            ->pluck('module_code');

        return Module::whereIn('code', $codes)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function indicatorOptions()
    {
        $q = Task::forRegion($this->regionCode)->whereNotNull('indicator_code');
        if ($this->module !== 'all') $q->forModule($this->module);
        $codes = $q->distinct()->pluck('indicator_code');

        return Indicator::whereIn('code', $codes)->orderBy('label_short')->get();
    }

    #[Computed]
    public function districtOptions()
    {
        $taskIds = Task::forRegion($this->regionCode)->pluck('id');

        return District::whereHas('tasks', fn ($q) => $q->whereIn('tasks.id', $taskIds))
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function totals(): array
    {
        $base = Task::forRegion($this->regionCode);
        if ($this->module !== 'all')    $base->forModule($this->module);
        if ($this->indicator !== 'all') $base->forIndicator($this->indicator);
        if ($this->period !== 'all')    $base->forPeriod($this->period);
        if ($this->district !== 'all')  $base->forDistrict((int) $this->district);
        if ($this->search !== '')       $base->search($this->search);

        $total = $base->count();
        $done  = (clone $base)->where('status', 'done')->count();

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
