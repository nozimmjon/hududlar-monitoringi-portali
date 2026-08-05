<?php

namespace App\Livewire;

use App\Models\Sector;
use App\Support\SectorDisplay;
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
        return view('livewire.sector-detail', SectorDisplay::detailData($this->sector, $this->filter));
    }
}
