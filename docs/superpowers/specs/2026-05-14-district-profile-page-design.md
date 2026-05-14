# District Profile page

**Date:** 2026-05-14
**Status:** Approved (pending user spec review)
**Scope:** Replace bare `RegionProfile` Livewire component with full 4-section profile matching the `index.html` prototype's `renderProfilePage()` output. Andijan-only, district-scoped. Reports panel + D-targets count render empty placeholders (no backing data yet).

---

## 1. Goal

The portal's `/profile` route currently renders a flat grid of indicator cards for a selected district (`RegionProfile.php` ≈ 47 lines, view ≈ 53 lines). The `index.html` prototype renders a much richer page for the same `state.page === "profile"`: filter row, hero focus card, quick-status side panel, all-KPIs button grid, reports panel, tasks panel. The brief is to port that prototype 1:1 to the Laravel side, reusing the existing `RegionProfile` Livewire component and route.

After this work, opening `/profile?districtCode=1703401&kpi=industry` produces the same 4-section layout as the prototype for Andijan city, all sections wired to real DB data (or documented empty states where no data exists yet).

## 2. Non-goals

- **Reports system.** No `reports` table yet. Reports panel shows an empty state. The "Ҳисобот киритиш" action button is a stub (visible but not wired).
- **D-targets backing data.** `promise_targets` table exists but is empty (no importer landed). The "Туман мақсадлари" chip and side-stat render `0`. No new importer.
- **Region switcher.** Region stays hard-coded to Andijan (`1703`) mirroring `KpiFrontCards`, `KpiScoreline`, `RegionProfile` itself.
- **Task status mutation UI.** Tasks currently land with `status='open'`. No edit form. Status chips reflect what's in DB.
- **`renderExecutionPage` jump.** "Ижро журнали" button is a stub (visible but no navigation).
- **Index.html stays untouched.** This work is Laravel-only.

## 3. Strategy

Single Livewire component `RegionProfile` (existing). Two URL-synced properties (`$districtCode`, `$kpi`). Computed properties compute district/facts/indicators/tasks/availability. One main blade view that pulls four partials (filter, hero, kpis-grid, bottom). Status logic reuses `App\Support\DistrictStatus` and `App\Support\DistrictTableConfig` (both already exist for districts page).

The component is the only consumer of the four new partials, so no shared component split — mirrors how `kpi-dashboard.blade.php` pulls partials inline.

## 4. Component structure

### 4.1 `RegionProfile` Livewire component

`backend/app/Livewire/RegionProfile.php`:

```php
namespace App\Livewire;

use App\Models\District;
use App\Models\Indicator;
use App\Models\IndicatorFact;
use App\Models\Region;
use App\Models\RegionIndicatorAvailability;
use App\Models\Task;
use App\Support\DistrictStatus;
use App\Support\DistrictTableConfig;
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

    #[Computed]
    public function district(): ?District
    {
        if ($this->districtCode === '') return null;
        return District::where('region_code', self::REGION_CODE)
            ->where('code', (int) $this->districtCode)
            ->first();
    }

    #[Computed]
    public function facts()
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
    public function availableKpis()
    {
        return Indicator::whereIn('code',
            RegionIndicatorAvailability::where('region_code', self::REGION_CODE)
                ->where('status', 'available')
                ->pluck('indicator_code'))
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
        return DistrictStatus::for($this->selectedFact);
    }

    #[Computed]
    public function tasksForKpi()
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

    public function mount(): void
    {
        // If kpi is missing or not available for this region, fall back to first available.
        if ($this->kpi === '' || ! $this->availableKpis->firstWhere('code', $this->kpi)) {
            $this->kpi = $this->availableKpis->first()->code ?? 'industry';
        }
    }

    public function render()
    {
        return view('livewire.region-profile', [
            'district'          => $this->district,
            'facts'             => $this->facts,
            'availableKpis'     => $this->availableKpis,
            'selectedIndicator' => $this->selectedIndicator,
            'tableConfig'       => $this->tableConfig,
            'selectedFact'      => $this->selectedFact,
            'status'            => $this->status,
            'tasks'             => $this->tasksForKpi,
            'taskCounts'        => $this->taskCounts,
            'districtTargetsCount' => 0, // promise_targets table empty (Bug J spec scope)
        ]);
    }
}
```

### 4.2 Blade orchestrator

`backend/resources/views/livewire/region-profile.blade.php` rewrites to:

```blade
<div>
    @if(! $districtCode || ! $district)
        @include('livewire.profile.empty', ['districtCode' => $districtCode])
    @else
        @include('livewire.profile.filter',     ['district' => $district, 'kpi' => $kpi, 'availableKpis' => $availableKpis])
        @include('livewire.profile.hero',       ['district' => $district, 'indicator' => $selectedIndicator, 'fact' => $selectedFact, 'status' => $status, 'tableConfig' => $tableConfig, 'facts' => $facts, 'taskCounts' => $taskCounts, 'districtTargetsCount' => $districtTargetsCount])
        @include('livewire.profile.kpis-grid',  ['district' => $district, 'availableKpis' => $availableKpis, 'kpi' => $kpi, 'facts' => $facts])
        @include('livewire.profile.bottom',     ['district' => $district, 'kpi' => $kpi, 'tasks' => $tasks, 'taskCounts' => $taskCounts, 'indicator' => $selectedIndicator])
    @endif
</div>
```

### 4.3 Partials

Each partial is a focused unit. Concrete blade code is omitted from the spec — the implementer writes them following the section-2 data contract above. CSS classes mirror the existing `profile-*` classes in `public/css/portal.css`.

| Partial | Lines (estimate) | Renders |
|---|---|---|
| `livewire/profile/empty.blade.php` | ~15 | "Туман танланмаган" / "Туман топилмади" empty states |
| `livewire/profile/filter.blade.php` | ~35 | district select + kpi select + 3 action buttons |
| `livewire/profile/hero.blade.php` | ~80 | 2-col grid: focus card + quick-status side panel |
| `livewire/profile/kpis-grid.blade.php` | ~25 | All-KPIs button grid |
| `livewire/profile/bottom.blade.php` | ~55 | 2-col grid: reports empty state + tasks list |

## 5. Tests

`backend/tests/Feature/Livewire/RegionProfileTest.php`:

1. **mounts with valid params**: districtCode=1703401, kpi=industry → `assertViewHas('district', not null)`, `assertViewHas('selectedIndicator', not null)`.
2. **missing districtCode**: empty state shown, no SQL crash.
3. **kpi falls back**: districtCode=1703401, kpi='nonexistent' → mount picks first available kpi.
4. **kpi not_applicable for region**: tashkent_city + agriculture (when seeder includes that case) — availableKpis excludes it, mount falls back.
5. **selectKpi action**: call `selectKpi('grp')` → `$component->kpi === 'grp'`.
6. **task count matches DB**: seed 3 tasks for indicator=industry,district=1703401 → taskCounts.total === 3.
7. **tasks panel empty**: district with 0 tasks → tasks collection empty.
8. **reports always empty (placeholder)**: until reports system lands. Optional placeholder test.

## 6. Files

| File | Action |
|---|---|
| `backend/app/Livewire/RegionProfile.php` | rewrite (47 → ~150 lines) |
| `backend/resources/views/livewire/region-profile.blade.php` | rewrite (53 → ~10 lines, includes partials) |
| `backend/resources/views/livewire/profile/empty.blade.php` | new |
| `backend/resources/views/livewire/profile/filter.blade.php` | new |
| `backend/resources/views/livewire/profile/hero.blade.php` | new |
| `backend/resources/views/livewire/profile/kpis-grid.blade.php` | new |
| `backend/resources/views/livewire/profile/bottom.blade.php` | new |
| `backend/tests/Feature/Livewire/RegionProfileTest.php` | new |

No new model, no migration, no service.

## 7. Operator smoke

```bash
cd backend && php artisan migrate:fresh --seed
php artisan import:all-regions 2026
php artisan serve --port=8000
```

Visit `http://localhost:8000/profile?districtCode=1703401&kpi=industry`. Verify:
1. Filter row: both selects populated, district = "Андижон шаҳри", kpi = "Саноат".
2. Hero focus card: title shows district name + KPI short, primary value matches `IndicatorFact::where('district_code',1703401)->where('indicator_code','industry')->where('period','year')->first()->pct_of_plan` (or `growth_pct`).
3. Hero status chip color matches `DistrictStatus::for(fact)` output (green/amber/red).
4. T-task chip shows correct count (use SQL: `SELECT COUNT(*) FROM tasks WHERE region_code=1703 AND indicator_code='industry' AND id IN (SELECT task_id FROM task_districts WHERE district_id = ?)`).
5. KPIs grid shows 5 macro indicators (grp, industry, agriculture, construction, services) + others; clicking one updates the hero.
6. Reports panel shows "Ҳисобот йўқ" empty state.
7. Tasks panel shows up to 4 task cards.
8. Click "Туманлар жадвали" button → navigates to `/districts?kpi=industry`.

## 8. Risks

- **Risk:** `DistrictTableConfig::for($kpi)` may return null for KPIs without config (energy_*, localization, etc.). *Mitigation:* fall back to a generic single-column shape (`label='Қиймат'`, `value=fact.pct_of_plan`). Verify all `availableKpis` codes have configs OR add defaults in the support class.
- **Risk:** Tasks `forDistrict` scope expects `District` model id (not SOATO code). Currently the scope already does `$d->where('districts.id', $districtId)`. *Mitigation:* pass `$this->district->id`, not `$this->district->code`. Test #6 covers it.
- **Risk:** URL serialization of `$districtCode` as `string` vs DB `int`. Already handled in districts-page click fix (`(string) $district->code === $this->district`). Apply same cast in `district()` computed: `where('code', (int) $this->districtCode)`.
- **Risk:** Reports panel placeholder grows stale if reports system lands later. *Mitigation:* extract empty-state into its own partial so the same parent shell can host real reports when they exist.
- **Risk:** Action button stubs (`Ҳисобот киритиш`, `Ижро журнали`) confuse operators by appearing clickable. *Mitigation:* add `disabled` attribute + `title="Тез орада"` tooltip. Visually distinct (grayed).
- **Risk:** `availableKpis` query joins region_indicator_availability + indicators. Slow for 14 regions but fine for one (Andijan).
- **Risk:** Tashkent city's `agriculture` won't appear in availableKpis because of `not_applicable` status — correct behavior, no regression for Andijan.
