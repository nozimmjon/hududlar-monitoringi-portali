<?php

namespace App\Livewire;

use App\Models\IndicatorFact;
use App\Models\Indicator;
use Illuminate\Support\Collection;
use Livewire\Component;

class KpiDashboard extends Component
{
    public string $period = 'year';
    public string $module = 'macro';

    public array $moduleMap = [
        'macro'         => ['grp', 'industry', 'agriculture', 'construction', 'services'],
        'inflation'     => ['inflation'],
        'budget'        => ['budget'],
        'budget_invest' => ['budget_investment'],
        'investment'    => ['investment'],
        'export'        => ['export'],
        'employment'    => ['unemployment', 'poverty', 'jobs', 'legalization', 'mfy_clear', 'microprojects'],
    ];

    public array $moduleLabels = [
        'macro'         => '1. Макроиқтисодиёт',
        'inflation'     => '2. Инфляция',
        'budget'        => '3. Бюджет',
        'budget_invest' => '4. Бюджет инвестициялари',
        'investment'    => '5. Хорижий инвестициялар',
        'export'        => '6. Экспорт',
        'employment'    => '7. Бандлик ва камбағаллик',
    ];

    public array $periodLabels = [
        'year' => 'Йиллик',
        'h1'   => 'I ярим йил',
        'q1'   => 'I чорак',
    ];

    public function render()
    {
        $indicatorCodes = $this->moduleMap[$this->module] ?? [];

        $facts = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNull('district_code')
            ->where('period', $this->period)
            ->whereIn('indicator_code', $indicatorCodes)
            ->get()
            ->keyBy('indicator_code');

        $indicators = Indicator::whereIn('code', $indicatorCodes)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        return view('livewire.kpi-dashboard', [
            'facts'      => $facts,
            'indicators' => $indicators,
        ]);
    }
}
