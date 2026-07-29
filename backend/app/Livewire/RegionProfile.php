<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Models\Task;
use App\Support\DashboardCatalog;
use App\Support\TaskPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class RegionProfile extends Component
{
    public int $regionCode;
    private const YEAR = 2026;

    /** Period filter options shown on the profile. */
    public const PERIOD_OPTIONS = ['h1' => 'I ярим йиллик', 'year' => 'Йил якуни'];

    #[Url]
    public string $districtCode = '';

    #[Url]
    public string $period = 'h1';

    public function mount(): void
    {
        $this->regionCode = \App\Support\CurrentRegion::code();
        if (! array_key_exists($this->period, self::PERIOD_OPTIONS)) {
            $this->period = 'h1';
        }
    }

    public function selectPeriod(string $period): void
    {
        if (array_key_exists($period, self::PERIOD_OPTIONS)) {
            $this->period = $period;
        }
    }

    #[Computed]
    public function district(): ?District
    {
        if ($this->districtCode === '') return null;
        return District::where('region_code', $this->regionCode)
            ->where('code', (int) $this->districtCode)
            ->first();
    }

    #[Computed]
    public function facts(): Collection
    {
        if (! $this->district) return collect();
        return IndicatorFact::where('region_code', $this->regionCode)
            ->where('year', self::YEAR)
            ->where('district_code', $this->district->code)
            ->where('period', $this->period)
            ->get()
            ->keyBy('indicator_code');
    }

    #[Computed]
    public function availableKpis(): Collection
    {
        $codes = DB::table('region_indicator_availability')
            ->where('region_code', $this->regionCode)
            ->where('status', 'available')
            ->pluck('indicator_code');

        return Indicator::whereIn('code', $codes)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * One card per indicator that carries data for the selected district+period.
     * Value/tag/note follow what the fact actually holds — a forecast is labelled
     * Кутилиш, a bare plan Режа, a reported actual Амалда; empty KPIs are hidden.
     *
     * @return list<array{label:string,icon:string,value:string,color:string,chip:string,tag:string,note:string}>
     */
    #[Computed]
    public function kpiCards(): array
    {
        $facts = $this->facts;
        $isH1 = $this->period === 'h1';
        $cards = [];

        foreach ($this->availableKpis as $ind) {
            $fact = $facts->get($ind->code);
            $mode = DashboardCatalog::factMode($fact);
            if ($mode === 'empty') {
                continue;
            }

            $unit = $fact->unit ?? $ind->default_unit ?? '';
            $card = [
                'label' => $ind->label_short,
                'icon'  => $ind->icon ?? 'trend',
                'color' => '',
                'chip'  => 'grey',
                'tag'   => 'Режа',
                'note'  => $isH1 ? 'ярим йиллик мақсад' : 'йиллик мақсад',
                'value' => DashboardCatalog::displayValue($fact->plan_value, $unit),
            ];

            if ($mode === 'execution' || $mode === 'forecast') {
                $card['chip'] = $mode === 'execution' ? 'green' : 'blue';
                $card['tag']  = $mode === 'execution' ? 'Амалда' : 'Кутилиш';
                if ($fact->growth_pct !== null) {
                    $card['value'] = DashboardCatalog::growthValue($fact->growth_pct);
                    $card['color'] = (float) $fact->growth_pct >= 100 ? 'green' : 'red';
                    $card['note']  = $isH1 ? 'ярим йилга ўсиш' : 'йил якунига ўсиш';
                } elseif ($fact->pct_of_plan !== null) {
                    $card['value'] = DashboardCatalog::fmt((float) $fact->pct_of_plan, 1) . '%';
                    $card['color'] = 'blue';
                    $card['note']  = 'режага нисбатан';
                } else {
                    $actual = $fact->actual_hokimyat ?? $fact->actual_statkom ?? $fact->expected_value;
                    $card['value'] = DashboardCatalog::displayValue($actual, $unit);
                    $card['note']  = $mode === 'execution' ? 'ҳисобот қиймати' : 'тезкор прогноз';
                }
            }

            $cards[] = $card;
        }

        return $cards;
    }

    /** All planned tasks of the district (any deadline) — for the hero counters. */
    #[Computed]
    public function allTasks(): Collection
    {
        if (! $this->district) return collect();
        return Task::forRegion($this->regionCode)
            ->forDistrict($this->district->id)
            ->hasPlan()
            ->with('progress')
            ->orderBy('section_path')
            ->orderBy('task_number')
            ->get();
    }

    /** Tasks whose deadline falls into the selected period (h1 bucket vs the rest). */
    #[Computed]
    public function tasks(): Collection
    {
        return $this->allTasks->filter(function (Task $t) {
            $isH1 = TaskPeriod::deadlineBucket($t->period_code, $t->deadline_text) === 'h1';
            return $this->period === 'h1' ? $isH1 : ! $isH1;
        })->values();
    }

    /** @return array{total:int,done:int,open:int,in_progress:int} */
    #[Computed]
    public function taskCounts(): array
    {
        $all = $this->allTasks;
        return [
            'total'       => $all->count(),
            'done'        => $all->where('status', 'done')->count(),
            'open'        => $all->where('status', 'open')->count(),
            'in_progress' => $all->where('status', 'in_progress')->count(),
        ];
    }

    public function render()
    {
        return view('livewire.region-profile', [
            'district'      => $this->district,
            'kpiCards'      => $this->kpiCards,
            'tasks'         => $this->tasks,
            'taskCounts'    => $this->taskCounts,
            'periodOptions' => self::PERIOD_OPTIONS,
        ]);
    }
}
