<?php

namespace App\Livewire;

use App\Models\Indicator;
use App\Models\IndicatorFact;
use Livewire\Component;

class ExecutionPage extends Component
{
    public string $period = 'year';

    public array $periodLabels = [
        'year' => 'Йиллик',
        'h1'   => 'I ярим йил',
        'q1'   => 'I чорак',
    ];

    private array $executionIndicators = [
        'budget', 'budget_investment', 'investment', 'export',
    ];

    public function render()
    {
        $facts = IndicatorFact::where('region_code', 1703)
            ->where('year', 2026)
            ->whereNull('district_code')
            ->where('period', $this->period)
            ->whereIn('indicator_code', $this->executionIndicators)
            ->get()
            ->keyBy('indicator_code');

        $indicators = Indicator::whereIn('code', $this->executionIndicators)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        return view('livewire.execution-page', [
            'facts'               => $facts,
            'indicators'          => $indicators,
            'executionIndicators' => $this->executionIndicators,
        ]);
    }
}
