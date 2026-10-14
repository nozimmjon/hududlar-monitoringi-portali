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
        $all     = CurrentRegion::regions();
        $regions = $all;
        if ($this->roadmapsOnly) {
            $withMaps = Roadmap::query()
                ->where('domain', RoadmapsPage::DOMAIN)
                ->where('year', RoadmapsPage::YEAR)
                ->distinct()
                ->pluck('region_code');
            $regions  = $all->whereIn('code', $withMaps)->values();
        }

        return view('livewire.region-switcher', [
            'regions' => $regions,
            // from the already-fetched list: the session region may be filtered out of $regions
            'current' => $all->firstWhere('code', CurrentRegion::code()) ?? CurrentRegion::current(),
        ]);
    }
}
