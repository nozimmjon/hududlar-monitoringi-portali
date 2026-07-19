<?php

namespace App\Livewire;

use Livewire\Attributes\Url;
use Livewire\Component;

class PeriodSwitcher extends Component
{
    public const PERIODS = [
        'h1'   => 'I ярим йиллик',
        'year' => 'Йил якуни',
    ];

    #[Url]
    public string $period = 'h1';

    public function mount(): void
    {
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = 'h1';
        }
    }

    public function select(string $period): void
    {
        if (! array_key_exists($period, self::PERIODS)) {
            return;
        }
        $this->period = $period;
        $this->dispatch('period-selected', period: $period);
    }

    public function render()
    {
        return view('livewire.period-switcher', ['options' => self::PERIODS]);
    }
}
