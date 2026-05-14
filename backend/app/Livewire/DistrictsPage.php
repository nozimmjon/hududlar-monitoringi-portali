<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Models\Module;
use App\Models\PromiseTarget;
use App\Models\Task;
use App\Support\DistrictStatus;
use App\Support\DistrictTableConfig;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class DistrictsPage extends Component
{
    public int $regionCode;

    #[Url]
    public string $module = 'macro';

    #[Url]
    public string $kpi = 'industry';

    #[Url]
    public string $period = 'h1';

    #[Url]
    public string $district = '';

    #[Url]
    public string $sort = 'attention';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->regionCode = \App\Support\CurrentRegion::code();
    }

    public function selectModule(string $code): void
    {
        $this->module = $code;
        $first = $this->kpiOptions()->first();
        $this->kpi = $first?->code ?? $this->kpi;
        $this->search = '';
    }

    public function selectKpi(string $code): void
    {
        $this->kpi = $code;
        $indicator = Indicator::where('code', $code)->first();
        if ($indicator?->module_code) {
            $this->module = $indicator->module_code;
        }
        $this->period = DistrictTableConfig::for($code)['primary_period'];
    }

    public function selectDistrict(string $code): void
    {
        $this->district = $code;
    }

    public function setSort(string $value): void
    {
        $this->sort = $value;
    }

    #[Computed]
    public function districts(): Collection
    {
        return District::where('region_code', $this->regionCode)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');
    }

    #[Computed]
    public function indicator(): ?Indicator
    {
        return Indicator::where('code', $this->kpi)->first();
    }

    #[Computed]
    public function facts(): Collection
    {
        return IndicatorFact::where('region_code', $this->regionCode)
            ->where('indicator_code', $this->kpi)
            ->where('period', $this->period)
            ->whereNotNull('district_code')
            ->get()
            ->keyBy('district_code');
    }

    #[Computed]
    public function rollup(): ?IndicatorFact
    {
        return IndicatorFact::where('region_code', $this->regionCode)
            ->where('indicator_code', $this->kpi)
            ->where('period', $this->period)
            ->whereNull('district_code')
            ->first();
    }

    #[Computed]
    public function statusByDistrict(): array
    {
        $lower = (bool) ($this->indicator?->lower_is_better);
        $out = [];
        foreach ($this->districts as $code => $_district) {
            $fact = $this->facts->get($code);
            $out[$code] = DistrictStatus::statusFor(
                $fact?->pct_of_plan !== null ? (float) $fact->pct_of_plan : null,
                $fact?->growth_pct !== null ? (float) $fact->growth_pct : null,
                $lower,
            );
        }
        return $out;
    }

    #[Computed]
    public function rankedDistricts(): array
    {
        $rows = [];
        foreach ($this->districts as $code => $district) {
            $fact = $this->facts->get($code);
            $status = $this->statusByDistrict[$code] ?? 'grey';
            $rows[] = [
                'district' => $district,
                'fact'     => $fact,
                'status'   => $status,
            ];
        }

        $query = mb_strtolower(trim($this->search));
        if ($query !== '') {
            $rows = array_values(array_filter($rows, function ($r) use ($query) {
                return mb_strpos(mb_strtolower($r['district']->name_full), $query) !== false
                    || mb_strpos(mb_strtolower($r['district']->name_short), $query) !== false;
            }));
        }

        $rank = ['red' => 0, 'amber' => 1, 'grey' => 2, 'green' => 3];

        usort($rows, function ($a, $b) use ($rank) {
            return match ($this->sort) {
                'execution' => ($b['fact']?->pct_of_plan ?? -INF) <=> ($a['fact']?->pct_of_plan ?? -INF),
                'plan'      => ($b['fact']?->plan_value ?? -INF)  <=> ($a['fact']?->plan_value ?? -INF),
                'name'      => strcmp($a['district']->name_full, $b['district']->name_full),
                'attention' => [$rank[$a['status']] ?? 99, $a['district']->name_full]
                                 <=> [$rank[$b['status']] ?? 99, $b['district']->name_full],
                default     => 0,
            };
        });

        return $rows;
    }

    #[Computed]
    public function selectedDistrict(): ?array
    {
        $rows = $this->rankedDistricts;
        if ($this->district !== '') {
            foreach ($rows as $row) {
                if ((string) $row['district']->code === $this->district) {
                    return $row;
                }
            }
        }
        return $rows[0] ?? null;
    }

    #[Computed]
    public function moduleOptions(): Collection
    {
        $codes = IndicatorFact::where('region_code', $this->regionCode)
            ->whereNotNull('district_code')
            ->join('indicators', 'indicator_facts.indicator_code', '=', 'indicators.code')
            ->whereNotNull('indicators.module_code')
            ->distinct()
            ->pluck('indicators.module_code');

        return Module::whereIn('code', $codes)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function kpiOptions(): Collection
    {
        $codes = IndicatorFact::where('region_code', $this->regionCode)
            ->where('period', $this->period)
            ->whereNotNull('district_code')
            ->join('indicators', 'indicator_facts.indicator_code', '=', 'indicators.code')
            ->where('indicators.module_code', $this->module)
            ->distinct()
            ->pluck('indicator_facts.indicator_code');

        return Indicator::whereIn('code', $codes)->orderBy('label_short')->get();
    }

    #[Computed]
    public function coverage(): array
    {
        $count = $this->facts->count();
        $periods = IndicatorFact::where('region_code', $this->regionCode)
            ->where('indicator_code', $this->kpi)
            ->whereNotNull('district_code')
            ->distinct()
            ->pluck('period')
            ->all();
        return ['count' => $count, 'periods' => $periods];
    }

    #[Computed]
    public function targetCount(): int
    {
        return PromiseTarget::where('region_code', $this->regionCode)
            ->where('indicator_code', $this->kpi)
            ->whereNotNull('target_districts')
            ->count();
    }

    #[Computed]
    public function taskCount(): int
    {
        return Task::forRegion($this->regionCode)
            ->forIndicator($this->kpi)
            ->count();
    }

    #[Computed]
    public function tableConfig(): array
    {
        return DistrictTableConfig::for($this->kpi);
    }

    /**
     * Build [$kpi][$district_code][$period] => IndicatorFact|null lookup
     * for every (kpi, period) pair referenced by the current tableConfig.
     */
    #[Computed]
    public function factMatrix(): array
    {
        $cfg = $this->tableConfig;
        $pairs = [];
        foreach ($cfg['columns'] as $col) {
            if ($col['metric'] === null) continue;
            $pairs[$col['metric']['kpi'] . '|' . $col['metric']['period']] = [
                $col['metric']['kpi'],
                $col['metric']['period'],
            ];
        }
        if (empty($pairs)) return [];

        $query = IndicatorFact::where('region_code', $this->regionCode)
            ->whereNotNull('district_code');

        $query->where(function ($q) use ($pairs) {
            foreach ($pairs as [$k, $p]) {
                $q->orWhere(function ($w) use ($k, $p) {
                    $w->where('indicator_code', $k)->where('period', $p);
                });
            }
        });

        $out = [];
        foreach ($query->get() as $row) {
            $periodKey = $row->period instanceof \BackedEnum ? $row->period->value : (string) $row->period;
            $out[$row->indicator_code][$row->district_code][$periodKey] = $row;
        }
        return $out;
    }

    /**
     * @return array<string, array{unfinished:int,total:int}>
     */
    #[Computed]
    public function taskCountByDistrict(): array
    {
        $out = [];
        $tasks = Task::forRegion($this->regionCode)
            ->forIndicator($this->kpi)
            ->with('districts:id,code')
            ->get();

        foreach ($tasks as $task) {
            foreach ($task->districts as $d) {
                $out[$d->code] ??= ['unfinished' => 0, 'total' => 0];
                $out[$d->code]['total']++;
                if ($task->status !== 'done') {
                    $out[$d->code]['unfinished']++;
                }
            }
        }
        return $out;
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function targetCountByDistrict(): array
    {
        $out = [];
        $targets = PromiseTarget::where('region_code', $this->regionCode)
            ->where('indicator_code', $this->kpi)
            ->whereNotNull('target_districts')
            ->get();

        foreach ($targets as $target) {
            $codes = is_array($target->target_districts) ? $target->target_districts : [];
            foreach ($codes as $code) {
                $out[$code] = ($out[$code] ?? 0) + 1;
            }
        }
        return $out;
    }

    #[Computed]
    public function mapGeometry(): array
    {
        return \App\Support\RegionMapGeometry::forRegion($this->regionCode);
    }

    public function render()
    {
        return view('livewire.districts-page', [
            'mapGeometry' => $this->mapGeometry,
        ]);
    }
}
