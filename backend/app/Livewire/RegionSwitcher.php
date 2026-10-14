<?php

namespace App\Livewire;

use App\Models\Roadmap;
use App\Support\CurrentRegion;
use Livewire\Component;

class RegionSwitcher extends Component
{
    public int $regionCode;

    /** true on /roadmaps: list only regions that have a road map (Тошкент шаҳри never will). */
    public bool $roadmapsOnly = false;

    public function mount(): void
    {
        $this->regionCode = CurrentRegion::code();
    }

    public function select(int $code): void
    {
        CurrentRegion::set($code);
        $this->js('window.location.reload()');
    }

    public function render()
    {
        $regions = CurrentRegion::regions();
        if ($this->roadmapsOnly) {
            $withMaps = Roadmap::query()->distinct()->pluck('region_code');
            $regions  = $regions->whereIn('code', $withMaps)->values();
        }

        return view('livewire.region-switcher', [
            'regions' => $regions,
            'current' => CurrentRegion::current(),
        ]);
    }
}
