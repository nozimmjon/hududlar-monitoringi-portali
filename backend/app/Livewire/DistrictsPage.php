<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\IndicatorFact;
use Livewire\Component;

class DistrictsPage extends Component
{
    public string $period = 'year';
    public string $indicatorCode = 'export';

    public array $availableIndicators = [
        'export'           => 'Экспорт',
        'investment'       => 'Инвестициялар',
        'budget'           => 'Бюджет',
        'budget_investment'=> 'Бюджет инвест',
        'industry'         => 'Саноат',
        'unemployment'     => 'Ишсизлик',
        'poverty'          => 'Камбағаллик',
    ];

    public array $periodLabels = [
        'year' => 'Йиллик',
        'h1'   => 'I ярим йил',
        'q1'   => 'I чорак',
    ];

    public function render()
    {
        $districts = District::where('region_code', 'andijan')
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        $facts = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->where('period', $this->period)
            ->where('indicator_code', $this->indicatorCode)
            ->whereNotNull('district_code')
            ->get()
            ->keyBy('district_code');

        $rollup = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->where('period', $this->period)
            ->where('indicator_code', $this->indicatorCode)
            ->whereNull('district_code')
            ->first();

        return view('livewire.districts-page', [
            'districts' => $districts,
            'facts'     => $facts,
            'rollup'    => $rollup,
        ]);
    }
}
