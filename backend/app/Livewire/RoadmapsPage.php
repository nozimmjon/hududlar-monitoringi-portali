<?php

namespace App\Livewire;

use App\Models\Region;
use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Support\CurrentRegion;
use App\Support\Roadmaps\RoadmapPeriod;
use App\Support\Roadmaps\Roman;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /roadmaps — the region's water-management road-map measures with their monitoring
 * state. Region comes from the session (RegionSwitcher). Filters: one section OR one
 * district (district wins), a status, plus a substring search. Rail counts, the hero
 * ring and the KPI tiles always describe the whole road map; only the list is filtered.
 * Status/pct come from the stored columns (MeasureRecomputer) — never computed here.
 */
class RoadmapsPage extends Component
{
    // Fixed for this phase; import:roadmap accepts --year/--domain but the page shows only the 2026 water map.
    public const DOMAIN = 'water';
    public const YEAR   = 2026;

    public const STATUSES = ['all', 'done', 'in_progress', 'open'];

    #[Url(except: 'all')]
    public string $section = 'all';

    #[Url(except: 'all')]
    public string $district = 'all';   // districts.code as string

    #[Url(as: 'holat', except: 'all')]
    public string $status = 'all';

    #[Url(except: '')]
    public string $q = '';

    public int $regionCode;

    public function mount(): void
    {
        $this->regionCode = CurrentRegion::code();
    }

    public function selectSection(string $no): void
    {
        $this->section  = $no;
        $this->district = 'all';
    }

    public function selectDistrict(string $code): void
    {
        $this->district = $code;
        $this->section  = 'all';   // the rail highlights the district section while a district is chosen
    }

    public function selectStatus(string $status): void
    {
        $this->status = in_array($status, self::STATUSES, true) ? $status : 'all';
    }

    public function clearFilters(): void
    {
        $this->section  = 'all';
        $this->district = 'all';
        $this->status   = 'all';
        $this->q        = '';
    }

    public function render()
    {
        $region  = Region::where('code', $this->regionCode)->firstOrFail();
        $roadmap = Roadmap::where('domain', self::DOMAIN)
            ->where('region_code', $this->regionCode)
            ->where('year', self::YEAR)
            ->first();

        if (! $roadmap) {
            return view('livewire.roadmaps-page', ['roadmap' => null, 'region' => $region]);
        }

        // source_row = position in the docx table → document order, independent of insert order.
        $all = $roadmap->measures()->with(['district', 'lines.progress'])->orderBy('source_row')->get();

        $sections = $all->groupBy('section_no')->sortKeys()->map(fn (Collection $g, int $no) => [
            'no'    => $no,
            'roman' => self::roman($no),
            'title' => $g->first()->section_title,
            'count' => $g->count(),
        ])->values();

        $districtSectionNo = $all->first(fn (RoadmapMeasure $m) => $m->district_id !== null)?->section_no;

        $districts = $all->whereNotNull('district_id')->groupBy('district_id')->map(fn (Collection $g) => [
            'code'  => $g->first()->district->code,
            'name'  => $g->first()->district->name_full,
            'head'  => $g->first()->district_head_text,
            'count' => $g->count(),
            'pct'   => self::meanPct($g),
        ])->values();

        // A filter carried in from the URL may name a district or section this road map does not
        // have (another region's link, a hand-edited query string) — fall back to the full list.
        if ($this->district !== 'all' && ! $districts->contains(fn (array $d) => (string) $d['code'] === $this->district)) {
            $this->district = 'all';
        }
        if ($this->section !== 'all' && ! $sections->contains(fn (array $s) => (string) $s['no'] === $this->section)) {
            $this->section = 'all';
        }
        if (! in_array($this->status, self::STATUSES, true)) {
            $this->status = 'all';
        }

        $rows = $all;
        if ($this->district !== 'all') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => $m->district && (string) $m->district->code === $this->district);
        } elseif ($this->section !== 'all') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => (string) $m->section_no === $this->section);
        }
        if ($this->status !== 'all') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => $m->status === $this->status);
        }
        $needle = mb_strtolower(trim($this->q));
        if ($needle !== '') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => str_contains(
                mb_strtolower(implode(' ', [$m->title, $m->details, $m->responsible_text, $m->funding_text])),
                $needle,
            ));
        }

        // Document-order groups: section → (district) → measures.
        $groups = [];
        foreach ($rows as $m) {
            $key = $m->section_no . ':' . ($m->district_id ?? 0);
            $groups[$key] ??= [
                'key'           => $key,
                'roman'         => self::roman($m->section_no),
                'section_title' => $m->section_title,
                'district'      => $m->district,
                'head'          => $m->district_head_text,
                'measures'      => [],
            ];
            $groups[$key]['measures'][] = $m;
        }

        $counts = ['all' => $all->count()];
        foreach (['done', 'in_progress', 'open'] as $s) {
            $counts[$s] = $all->where('status', $s)->count();
        }

        return view('livewire.roadmaps-page', [
            'roadmap'           => $roadmap,
            'region'            => $region,
            'sections'          => $sections,
            'districts'         => $districts,
            'districtSectionNo' => $districtSectionNo,
            'groups'            => array_values($groups),
            'counts'            => $counts,
            'hero'              => [
                'pct'         => self::meanPct($all),
                'lines_total' => (int) $all->sum('lines_total'),
                'lines_done'  => (int) $all->sum('lines_done'),
                'no_lines'    => $all->where('lines_total', 0)->count(),
                'period'      => RoadmapPeriod::label(RoadmapPeriod::latest($all->pluck('latest_period')->filter()->all())),
            ],
            'today'             => now()->format('Y-m'),
            'shown'             => $rows->count(),
            'filtered'          => $this->section !== 'all' || $this->district !== 'all' || $this->status !== 'all' || $needle !== '',
        ]);
    }

    /** Mean of pct (unreported = 0) over measures that have planned lines; null when none has. */
    private static function meanPct(Collection $measures): ?int
    {
        $withLines = $measures->filter(fn (RoadmapMeasure $m) => (int) $m->lines_total > 0);
        if ($withLines->isEmpty()) {
            return null;
        }

        return (int) round($withLines->avg(fn (RoadmapMeasure $m) => (float) ($m->pct ?? 0)));
    }

    public static function roman(int $n): string
    {
        return Roman::of($n);
    }
}
