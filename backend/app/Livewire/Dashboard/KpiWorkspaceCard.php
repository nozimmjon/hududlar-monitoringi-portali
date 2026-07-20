<?php

namespace App\Livewire\Dashboard;

use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Models\Task;
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
            'budget-investment'   => ['periodTargets' => $this->budgetInvestmentPeriods()],
            default               => [],
        };
    }

    /**
     * Per-period ўзлаштириш промise and reported fact (млн сўм). The fact rows only
     * carry the annual limit and, for unfinished periods, the region's forecast —
     * the monitoring tasks are the source for both the period plan and the reported
     * actual. Needle-guarded like TaskFactBridge::MAP so a workbook renumbering
     * cannot point a card at a different indicator.
     *
     * @return array<string, array{plan: ?float, actual: ?float}>
     */
    protected function budgetInvestmentPeriods(): array
    {
        $map = [
            'h1'   => ['125', 'Бюджет маблағлари ҳисобидан инвестиция'],
            'year' => ['129', 'Бюджет маблағлари ҳисобидан инвестиция'],
        ];

        $tasks = Task::forRegion($this->regionCode)
            ->whereIn('task_number', array_column($map, 0))
            ->with('progress')
            ->get()
            ->keyBy('task_number');

        $out = [];
        foreach ($map as $period => [$number, $needle]) {
            $task = $tasks->get($number);
            $line = $task?->progress
                ->where('report_period', $task->latest_period)
                ->firstWhere('line_no', 0);

            if ($line === null || mb_stripos((string) $line->metric_label, $needle) === false) {
                continue;
            }

            $out[$period] = [
                'plan'   => $line->plan_value !== null ? (float) $line->plan_value * 1000 : null,
                'actual' => $line->actual_value !== null ? (float) $line->actual_value * 1000 : null,
            ];
        }

        return $out;
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
            'limits'         => $this->inflationLimits(),
        ];
    }

    /**
     * Ceiling cards enriched with the reported rate from the matching monitoring
     * task. Inflation is lower-is-better, so a card is on target while амалда
     * stays at or below the cap.
     *
     * @return list<array{period:string,cap:string,note:string,plan:?float,actual:?float,onTarget:?bool}>
     */
    protected function inflationLimits(): array
    {
        $numbers = array_column(DashboardCatalog::INFLATION_LIMITS, 'task');
        $tasks = Task::forRegion($this->regionCode)
            ->whereIn('task_number', $numbers)
            ->with('progress')
            ->get()
            ->keyBy('task_number');

        $out = [];
        foreach (DashboardCatalog::INFLATION_LIMITS as $limit) {
            $task = $tasks->get($limit['task']);
            $line = $task !== null && mb_stripos($task->title, $limit['needle']) !== false
                ? $task->progress
                    ->where('report_period', $task->latest_period)
                    ->firstWhere('line_no', 0)
                : null;

            $plan   = $line?->plan_value !== null ? (float) $line->plan_value : null;
            $actual = $line?->actual_value !== null ? (float) $line->actual_value : null;

            $out[] = [
                'period'   => $limit['period'],
                'cap'      => $limit['cap'],
                'note'     => $limit['note'],
                'plan'     => $plan,
                'actual'   => $actual,
                'onTarget' => $actual !== null && $plan !== null ? $actual <= $plan : null,
            ];
        }

        return $out;
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
