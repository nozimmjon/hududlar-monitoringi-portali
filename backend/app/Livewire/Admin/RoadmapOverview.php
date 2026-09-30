<?php

namespace App\Livewire\Admin;

use App\Livewire\RoadmapsPage;
use App\Models\Region;
use App\Models\Roadmap;
use App\Models\RoadmapChangeLog;
use App\Services\Roadmaps\ProgressSheet;
use App\Support\Roadmaps\RoadmapPeriod;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** /admin/roadmaps — one card per region: how much of the chosen month is entered, status split, last change. */
class RoadmapOverview extends Component
{
    #[Url(as: 'davr', except: '')]
    public string $period = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        $this->period = self::month($this->period);
    }

    /** A month the panel offers (January of the road-map year … the current month, never past December), else the newest offered one. */
    public static function month(string $period): string
    {
        $options = ProgressSheet::periodOptions(RoadmapsPage::YEAR);

        return in_array($period, $options, true) ? $period : $options[0];
    }

    public function selectPeriod(string $period): void
    {
        $this->period = self::month($period);
    }

    #[On('progress-imported')]
    public function refresh(): void
    {
        // re-render only
    }

    public function render()
    {
        abort_unless(auth()->check(), 403);

        $roadmaps = Roadmap::where('domain', RoadmapsPage::DOMAIN)->where('year', RoadmapsPage::YEAR)->get()->keyBy('region_code');
        $cards    = [];
        $totals   = ['saved' => 0, 'carry' => 0, 'empty' => 0, 'measures' => 0, 'lines' => 0];
        foreach (Region::orderBy('sort_order')->get() as $region) {
            $roadmap = $roadmaps->get($region->code);
            if (! $roadmap) {
                $cards[] = ['region' => $region, 'roadmap' => null];
                continue;
            }
            $sheet    = ProgressSheet::build($roadmap, $this->period);
            $statuses = ['done' => 0, 'in_progress' => 0, 'open' => 0];
            $lines    = 0;
            foreach ($sheet['measures'] as $m) {
                $statuses[$m['status']]++;
                $lines += count($m['lines']);
            }
            $cards[] = [
                'region'   => $region,
                'roadmap'  => $roadmap,
                'measures' => count($sheet['measures']),
                'lines'    => $lines,
                'counts'   => $sheet['counts'],
                'statuses' => $statuses,
                'last'     => RoadmapChangeLog::where('roadmap_id', $roadmap->id)->with('user')->orderByDesc('created_at')->orderByDesc('id')->first(),
            ];
            foreach (['saved', 'carry', 'empty'] as $k) {
                $totals[$k] += $sheet['counts'][$k];
            }
            $totals['measures'] += count($sheet['measures']);
            $totals['lines']    += $lines;
        }

        return view('livewire.admin.roadmap-overview', [
            'cards'       => $cards,
            'totals'      => $totals,
            'periods'     => ProgressSheet::periodOptions(RoadmapsPage::YEAR),
            'periodLabel' => RoadmapPeriod::label($this->period),
        ]);
    }
}
