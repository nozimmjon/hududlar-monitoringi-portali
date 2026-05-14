<?php

namespace App\Livewire;

use App\Support\CurrentRegion;
use Livewire\Component;

class RegionSwitcher extends Component
{
    public int $regionCode;

    public function mount(): void
    {
        $this->regionCode = CurrentRegion::code();
    }

    public function select(int $code): void
    {
        CurrentRegion::set($code);
        $this->redirect(request()->path(), navigate: false);
    }

    public function render()
    {
        return view('livewire.region-switcher', [
            'regions' => CurrentRegion::regions(),
        ]);
    }
}
