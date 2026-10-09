<?php

namespace App\Livewire\Admin;

use App\Livewire\RoadmapsPage;
use App\Models\Region;
use App\Models\Roadmap;
use App\Models\RoadmapChangeLog;
use App\Services\Roadmaps\ProgressSheet;
use App\Support\Roadmaps\RoadmapOverdue;
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
        $this->period = self::month($this->period);

        $roadmaps = Roadmap::where('domain', RoadmapsPage::DOMAIN)->where('year', RoadmapsPage::YEAR)->get()->keyBy('region_code');
        // max(id) agrees with the created_at desc, id desc order because record() stamps created_at = now().
        $lastChanges = RoadmapChangeLog::with('user')
            ->whereIn('id', RoadmapChangeLog::selectRaw('max(id)')->whereIn('roadmap_id', $roadmaps->pluck('id'))->groupBy('roadmap_id'))
            ->get()->keyBy('roadmap_id');
        $cards    = [];
        // «Кечиккан» is always judged against today, never against the browsed month: a month in
        // the past would otherwise report nothing late and the ranking would go quiet.
        $today    = ProgressSheet::currentPeriod();
        // Only regions that actually have a road map: a card with nothing to enter is noise
        // (Тошкент шаҳри has no file), and the entry page's region select is built the same way.
        foreach (Region::whereIn('code', $roadmaps->keys())->orderBy('sort_order')->get() as $region) {
            $roadmap = $roadmaps->get($region->code);
            $sheet   = ProgressSheet::build($roadmap, $this->period);
            $lines   = 0;
            foreach ($sheet['measures'] as $m) {
                $lines += count($m['lines']);
            }
            $last    = $lastChanges->get($roadmap->id);
            $overdue = RoadmapOverdue::count($sheet['measures'], $roadmap->year, $today);
            $cards[] = [
                'region'    => $region,
                'roadmap'   => $roadmap,
                'measures'  => count($sheet['measures']),
                'lines'     => $lines,
                'counts'    => $sheet['counts'],
                'last'      => $last,
                'overdue'   => $overdue,
                'idle_days' => $last ? (int) floor($last->created_at->diffInDays(now(ProgressSheet::TZ), absolute: true)) : null,
            ];
        }

        // a worklist, not the fixed region order: most overdue first, then the least filled, and
        // the portal's own order breaks what is still tied
        usort($cards, fn ($a, $b) => [$b['overdue'], $a['counts']['saved'] / max(1, $a['measures'])]
                                 <=> [$a['overdue'], $b['counts']['saved'] / max(1, $b['measures'])]);

        return view('livewire.admin.roadmap-overview', ['cards' => $cards]);
    }
}
