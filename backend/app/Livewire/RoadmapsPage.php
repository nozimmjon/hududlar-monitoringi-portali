<?php

namespace App\Livewire;

use App\Models\Region;
use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Support\CurrentRegion;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /roadmaps — registry of the region's water-management road-map measures.
 * Region comes from the session (RegionSwitcher). Filters: one section OR one
 * district (district wins), plus a substring search. Rail counts are never
 * filtered; KPI tiles describe the whole road map.
 */
class RoadmapsPage extends Component
{
    // Fixed for this phase; import:roadmap accepts --year/--domain but the page shows only the 2026 water map.
    public const DOMAIN = 'water';
    public const YEAR   = 2026;

    #[Url(except: 'all')]
    public string $section = 'all';

    #[Url(except: 'all')]
    public string $district = 'all';   // districts.code as string

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

    public function clearFilters(): void
    {
        $this->section  = 'all';
        $this->district = 'all';
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
        $all = $roadmap->measures()->with('district')->orderBy('source_row')->get();

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
        ])->values();

        // A filter carried in from the URL may name a district or section this road map does not
        // have (another region's link, a hand-edited query string) — fall back to the full list.
        if ($this->district !== 'all' && ! $districts->contains(fn (array $d) => (string) $d['code'] === $this->district)) {
            $this->district = 'all';
        }
        if ($this->section !== 'all' && ! $sections->contains(fn (array $s) => (string) $s['no'] === $this->section)) {
            $this->section = 'all';
        }

        $rows = $all;
        if ($this->district !== 'all') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => $m->district && (string) $m->district->code === $this->district);
        } elseif ($this->section !== 'all') {
            $rows = $rows->filter(fn (RoadmapMeasure $m) => (string) $m->section_no === $this->section);
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

        return view('livewire.roadmaps-page', [
            'roadmap'           => $roadmap,
            'region'            => $region,
            'sections'          => $sections,
            'districts'         => $districts,
            'districtSectionNo' => $districtSectionNo,
            'groups'            => array_values($groups),
            'kpi'               => [
                'total'          => $all->count(),
                'region_level'   => $all->whereNull('district_id')->count(),
                'district_level' => $all->whereNotNull('district_id')->count(),
                'districts'      => $districts->count(),
            ],
            'shown'             => $rows->count(),
            'filtered'          => $this->section !== 'all' || $this->district !== 'all' || $needle !== '',
        ]);
    }

    public static function roman(int $n): string
    {
        $out = '';
        foreach ([10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'] as $v => $r) {
            while ($n >= $v) {
                $out .= $r;
                $n   -= $v;
            }
        }

        return $out;
    }
}
