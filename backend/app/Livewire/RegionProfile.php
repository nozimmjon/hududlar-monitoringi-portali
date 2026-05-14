<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Models\Task;
use App\Support\DashboardCatalog;
use App\Support\DistrictMetricResolver;
use App\Support\DistrictStatus;
use App\Support\DistrictTableConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class RegionProfile extends Component
{
    private const REGION_CODE = 1703;
    private const YEAR        = 2026;

    #[Url]
    public string $districtCode = '';

    #[Url]
    public string $kpi = 'industry';

    public function selectKpi(string $code): void
    {
        $this->kpi = $code;
    }

    public function mount(): void
    {
        $kpis = $this->availableKpis();
        if ($kpis->isNotEmpty() && ! $kpis->firstWhere('code', $this->kpi)) {
            $this->kpi = $kpis->first()->code;
        }
    }

    #[Computed]
    public function district(): ?District
    {
        if ($this->districtCode === '') return null;
        return District::where('region_code', self::REGION_CODE)
            ->where('code', (int) $this->districtCode)
            ->first();
    }

    #[Computed]
    public function facts(): Collection
    {
        if (! $this->district) return collect();
        return IndicatorFact::where('region_code', self::REGION_CODE)
            ->where('year', self::YEAR)
            ->where('district_code', $this->district->code)
            ->where('period', 'year')
            ->get()
            ->keyBy('indicator_code');
    }

    #[Computed]
    public function availableKpis(): Collection
    {
        $codes = DB::table('region_indicator_availability')
            ->where('region_code', self::REGION_CODE)
            ->where('status', 'available')
            ->pluck('indicator_code');

        return Indicator::whereIn('code', $codes)
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function selectedIndicator(): ?Indicator
    {
        return $this->availableKpis->firstWhere('code', $this->kpi);
    }

    #[Computed]
    public function tableConfig(): array
    {
        return DistrictTableConfig::for($this->kpi);
    }

    #[Computed]
    public function selectedFact(): ?IndicatorFact
    {
        return $this->facts->get($this->kpi);
    }

    #[Computed]
    public function status(): string
    {
        $fact = $this->selectedFact;
        if (! $fact) return 'grey';
        $lowerIsBetter = DashboardCatalog::isLowerBetter($this->kpi);
        return DistrictStatus::statusFor(
            $fact->pct_of_plan !== null ? (float) $fact->pct_of_plan : null,
            $fact->growth_pct  !== null ? (float) $fact->growth_pct  : null,
            $lowerIsBetter,
        );
    }

    #[Computed]
    public function tasksForKpi(): Collection
    {
        if (! $this->district) return collect();
        return Task::forRegion(self::REGION_CODE)
            ->forIndicator($this->kpi)
            ->forDistrict($this->district->id)
            ->limit(4)
            ->get();
    }

    #[Computed]
    public function taskCounts(): array
    {
        if (! $this->district) return ['total' => 0, 'unfinished' => 0];
        $base = Task::forRegion(self::REGION_CODE)
            ->forIndicator($this->kpi)
            ->forDistrict($this->district->id);
        $total = (clone $base)->count();
        $done  = (clone $base)->where('status', 'done')->count();
        return ['total' => $total, 'unfinished' => $total - $done];
    }

    public function render()
    {
        return view('livewire.region-profile', [
            'district'             => $this->district,
            'facts'                => $this->facts,
            'availableKpis'        => $this->availableKpis,
            'selectedIndicator'    => $this->selectedIndicator,
            'tableConfig'          => $this->tableConfig,
            'selectedFact'         => $this->selectedFact,
            'status'               => $this->status,
            'tasks'                => $this->tasksForKpi,
            'taskCounts'           => $this->taskCounts,
            'districtTargetsCount' => 0,
        ]);
    }
}
