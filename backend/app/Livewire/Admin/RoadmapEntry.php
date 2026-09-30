<?php

namespace App\Livewire\Admin;

use App\Livewire\RoadmapsPage;
use App\Models\Region;
use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Services\Roadmaps\ProgressEntry;
use App\Services\Roadmaps\ProgressSheet;
use App\Support\Roadmaps\EntryValue;
use App\Support\Roadmaps\Roman;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /admin/roadmaps/{code} — the entry grid for one region and one month. Typing lives
 * in Alpine (public/js/ad-entry.js); this side renders the sheet, saves the buffer,
 * confirms the rest, edits indicator definitions and shows history. $version re-keys
 * every value cell after a write so the morph replaces them with fresh values.
 */
class RoadmapEntry extends Component
{
    public int $code;

    #[Url(as: 'davr', except: '')]
    public string $period = '';

    public int $version = 0;
    public string $flash = '';
    public string $error = '';
    // not "openPanel"/"openHistory": a public property with the same name as an action shadows it in $wire
    public ?int $panelFor = null;
    /** @var list<array{id: ?int, label: string, unit: string, plan: string, months: int}> */
    public array $panelLines = [];
    public ?int $historyFor = null;

    public function mount(int $code): void
    {
        abort_unless(auth()->check(), 403);
        $this->code = $code;
        abort_if($this->roadmap() === null, 404, 'Бу вилоят учун йўл харитаси юкланмаган');
        $this->period = RoadmapOverview::month($this->period);
    }

    private function roadmap(): ?Roadmap
    {
        return Roadmap::where('domain', RoadmapsPage::DOMAIN)->where('region_code', $this->code)->where('year', RoadmapsPage::YEAR)->first();
    }

    /** A measure of this road map, or 404. */
    private function measure(int $id): RoadmapMeasure
    {
        return RoadmapMeasure::where('roadmap_id', $this->roadmap()?->id)->findOrFail($id);
    }

    public function switchRegion(string $code): void
    {
        $this->redirectRoute('admin.roadmaps.entry', ['code' => (int) $code, 'davr' => $this->period]);
    }

    public function selectPeriod(string $period): void
    {
        $this->period     = RoadmapOverview::month($period);
        $this->panelFor   = null;
        $this->panelLines = [];
        $this->historyFor = null;
        $this->flash      = '';
        $this->error      = '';
        $this->version++;
    }

    /**
     * @param  array<int|string, array{actual?: ?string, note?: ?string}> $edits the Alpine buffer
     * @return array{ok: bool, measures?: int, changes?: int, message?: string}
     */
    public function save(array $edits): array
    {
        abort_unless(auth()->check(), 403);
        $this->flash = '';
        $this->error = '';
        try {
            $r = ProgressEntry::save($this->roadmap(), $this->period, $edits, auth()->user());
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $this->flash = "{$r['measures']} та чора-тадбир сақланди";
        $this->version++;
        $this->dispatch('ad-refresh');

        return ['ok' => true, 'measures' => $r['measures'], 'changes' => $r['changes']];
    }

    public function confirmRest(): void
    {
        abort_unless(auth()->check(), 403);
        $n = ProgressEntry::confirmRest($this->roadmap(), $this->period, auth()->user());
        $this->flash = "{$n} та чора-тадбир ўзгаришсиз тасдиқланди";
        $this->error = '';
        $this->version++;
        $this->dispatch('ad-refresh');
    }

    public function openPanel(int $measureId): void
    {
        $m = $this->measure($measureId)->load('lines.progress');
        $this->panelLines = $m->lines->map(fn ($l) => [
            'id'     => $l->id,
            'label'  => $l->label,
            'unit'   => (string) $l->unit,
            'plan'   => EntryValue::format($l->plan_value),
            'months' => $l->progress->whereNotNull('actual_value')->count(),
        ])->values()->all();
        $this->panelFor   = $measureId;
        $this->historyFor = null;
        $this->error      = '';
    }

    public function panelAdd(): void
    {
        $this->panelLines[] = ['id' => null, 'label' => '', 'unit' => '', 'plan' => '', 'months' => 0];
    }

    public function panelRemove(int $i): void
    {
        if (isset($this->panelLines[$i])) {
            array_splice($this->panelLines, $i, 1);
        }
    }

    public function panelMove(int $i, int $dir): void
    {
        $j = $i + $dir;
        if (! isset($this->panelLines[$i], $this->panelLines[$j])) {
            return;
        }
        [$this->panelLines[$i], $this->panelLines[$j]] = [$this->panelLines[$j], $this->panelLines[$i]];
    }

    public function panelClose(): void
    {
        $this->panelFor   = null;
        $this->panelLines = [];
    }

    public function panelSave(): void
    {
        abort_unless(auth()->check(), 403);
        if ($this->panelFor === null) {
            return;
        }
        try {
            ProgressEntry::correct($this->measure($this->panelFor), $this->panelLines, auth()->user());
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->flash = 'Индикаторлар янгиланди';
        $this->error = '';
        $this->panelClose();
        $this->version++;
        $this->dispatch('ad-refresh');
    }

    public function openHistory(int $measureId): void
    {
        $this->measure($measureId);
        $this->historyFor = $measureId;
        $this->panelFor   = null;
        $this->panelLines = [];
    }

    public function closeHistory(): void
    {
        $this->historyFor = null;
    }

    #[On('progress-imported')]
    public function imported(): void
    {
        $this->flash = 'Excel файли юкланди';
        $this->error = '';
        $this->version++;
        $this->dispatch('ad-refresh');
    }

    public function render()
    {
        abort_unless(auth()->check(), 403);
        $roadmap      = $this->roadmap();
        $this->period = RoadmapOverview::month($this->period);
        $sheet        = ProgressSheet::build($roadmap, $this->period);

        $groups = [];
        foreach ($sheet['measures'] as $m) {
            $key = $m['section_no'] . ':' . ($m['district_id'] ?? 0);
            $groups[$key] ??= [
                'key'           => $key,
                'roman'         => Roman::of($m['section_no']),
                'section_title' => $m['section_title'],
                'district'      => $m['district_name'],
                'head'          => $m['district_head'],
                'measures'      => [],
            ];
            $groups[$key]['measures'][] = $m;
        }

        $history      = null;
        $historyTitle = '';
        if ($this->historyFor !== null) {
            $hm           = $this->measure($this->historyFor);
            $history      = ProgressEntry::history($hm);
            $historyTitle = $hm->title;
        }

        $loaded = Roadmap::where('domain', RoadmapsPage::DOMAIN)->where('year', RoadmapsPage::YEAR)->pluck('region_code');

        return view('livewire.admin.roadmap-entry', [
            'roadmap'      => $roadmap,
            'region'       => $roadmap->region,
            'regions'      => Region::whereIn('code', $loaded)->orderBy('sort_order')->get(),
            'periods'      => ProgressSheet::periodOptions($roadmap->year),
            'sheet'        => $sheet,
            'groups'       => array_values($groups),
            'today'        => ProgressSheet::currentPeriod(),
            'history'      => $history,
            'historyTitle' => $historyTitle,
        ]);
    }
}
