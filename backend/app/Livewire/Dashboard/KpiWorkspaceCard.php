<?php

namespace App\Livewire\Dashboard;

use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Support\DashboardCatalog;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiWorkspaceCard extends Component
{
    #[Reactive]
    public string $module = 'macro';

    #[Reactive]
    public string $kpi = 'grp';

    public string $period = 'h1';

    public int $regionCode;

    public function mount(): void
    {
        $this->regionCode = \App\Support\CurrentRegion::code();
    }

    public function render()
    {
        $indicator = Indicator::where('code', $this->kpi)->first();

        $rows = IndicatorFact::where('region_code', $this->regionCode)
            ->where('year', 2026)
            ->whereNull('district_code')
            ->where('indicator_code', $this->kpi)
            ->whereIn('period', DashboardCatalog::PERIODS)
            ->get()
            ->keyBy('period');

        $panel = match (true) {
            $this->kpi === 'inflation'                  => 'inflation-details',
            $this->kpi === 'unemployment'               => 'unemployment-details',
            $this->kpi === 'poverty'                    => 'poverty-details',
            $this->kpi === 'budget_investment'          => 'budget-investment',
            DashboardCatalog::isMacroGrowthKpi($this->kpi) => 'macro-growth',
            default                                     => 'quarter-matrix',
        };

        $extra = $this->loadPanelData($panel);

        return view('livewire.dashboard.kpi-workspace-card', array_merge([
            'indicator' => $indicator,
            'kpi'       => $this->kpi,
            'rows'      => $rows,
            'panel'     => $panel,
            'period'    => $this->period,
        ], $extra));
    }

    protected function loadPanelData(string $panel): array
    {
        return match ($panel) {
            'inflation-details'   => $this->inflationData(),
            'unemployment-details' => $this->employmentData(['jobs', 'legalization']),
            'poverty-details'     => $this->povertyData(),
            'macro-growth'        => $this->macroGrowthData(),
            default               => [],
        };
    }

    protected function inflationData(): array
    {
        $foods = DB::table('food_balance')
            ->where('region_code', $this->regionCode)
            ->where('year', 2026)
            ->whereNotNull('product')
            ->where('product', '!=', 'шундан:')
            ->whereNotNull('resource_total')
            ->get();

        $sensitiveFoods = $foods->filter(fn ($f) => $f->local_supply_ratio !== null)
            ->sortBy('local_supply_ratio')
            ->take(4)
            ->values();

        $warehouses = DB::table('warehouses')
            ->where('region_code', $this->regionCode)
            ->where('year', 2026)
            ->whereNull('district_code')
            ->get();

        return [
            'foods'          => $foods,
            'sensitiveFoods' => $sensitiveFoods,
            'warehouses'     => $warehouses,
        ];
    }

    protected function employmentData(array $codes): array
    {
        $facts = IndicatorFact::where('region_code', $this->regionCode)
            ->where('year', 2026)
            ->whereNull('district_code')
            ->whereIn('indicator_code', $codes)
            ->whereIn('period', ['h1', 'year'])
            ->get()
            ->groupBy('indicator_code');

        $indicators = Indicator::whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        return [
            'employmentFacts'      => $facts,
            'employmentIndicators' => $indicators,
        ];
    }

    protected function povertyData(): array
    {
        $base = $this->employmentData(['jobs', 'legalization', 'mfy_clear', 'microprojects']);

        $clearDistricts = DB::table('districts')
            ->where('region_code', $this->regionCode)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('indicator_facts')
                    ->whereColumn('indicator_facts.district_code', 'districts.code')
                    ->where('indicator_facts.indicator_code', 'poverty')
                    ->where('indicator_facts.is_sentinel', true)
                    ->where('indicator_facts.sentinel_label', 'like', '%холи%');
            })
            ->orderBy('sort_order')
            ->get();

        return array_merge($base, ['clearDistricts' => $clearDistricts]);
    }

    protected function macroGrowthData(): array
    {
        $facts = IndicatorFact::where('region_code', $this->regionCode)
            ->where('year', 2026)
            ->whereNull('district_code')
            ->whereIn('indicator_code', DashboardCatalog::MACRO_GROWTH_KPIS)
            ->whereIn('period', DashboardCatalog::PERIODS)
            ->get()
            ->groupBy('indicator_code');

        $indicators = Indicator::whereIn('code', DashboardCatalog::MACRO_GROWTH_KPIS)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        return [
            'macroFacts'      => $facts,
            'macroIndicators' => $indicators,
        ];
    }
}
