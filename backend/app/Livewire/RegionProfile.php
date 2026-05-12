<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\Indicator;
use App\Models\IndicatorFact;
use Livewire\Attributes\Url;
use Livewire\Component;

class RegionProfile extends Component
{
    #[Url]
    public string $districtCode = '';

    public function render()
    {
        $district   = null;
        $facts      = collect();
        $indicators = collect();

        if ($this->districtCode !== '') {
            $district = District::where('code', $this->districtCode)
                ->where('region_code', 1703)
                ->first();

            $facts = IndicatorFact::where('region_code', 1703)
                ->where('year', 2026)
                ->where('district_code', $this->districtCode)
                ->where('period', 'year')
                ->get()
                ->keyBy('indicator_code');

            $indicatorCodes = $facts->keys()->toArray();
            $indicators = Indicator::whereIn('code', $indicatorCodes)
                ->orderBy('sort_order')
                ->get()
                ->keyBy('code');
        }

        return view('livewire.region-profile', [
            'district'   => $district,
            'facts'      => $facts,
            'indicators' => $indicators,
        ]);
    }
}
