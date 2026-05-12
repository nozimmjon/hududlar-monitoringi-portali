# Districts page (Andijan) design

**Date:** 2026-05-12
**Status:** Approved (pending user spec review)
**Scope:** Implement the `/districts` page for Andijan region with full visual parity to `index.html#districtsPage`. One Livewire component, two pure helper classes, one Blade view, three test files. Andijan only; schema is already 14-region-ready.

---

## 1. Goal

Replace the current `DistrictsPage` Livewire stub (filterable table only) with the full layout from the prototype: module-tabs header, KPI selector, sort/search controls, Andijan SVG map (16 cells, real province shapes), selected-district summary card, leaderboard, detail table, and profile-jump links. All CSS classes from `portal.css` are reused; zero new CSS rules.

The page answers the chain: *given a KPI, which districts are doing well/poorly, and what does the selected district look like in detail?*

## 2. Non-goals

- No new CSS. All required classes already exist in `backend/public/css/portal.css`.
- No migrations or data ingestion. Existing `districts`, `indicator_facts`, `promise_targets`, `tasks`, `indicators`, `modules` tables are sufficient.
- No other regions. Geometry array and component pin to Andijan.
- No tasks-jump or evidence-injection buttons (prototype's `data-page-jump`).
- No profile page rendering. Profile links use `route('profile').'?districtCode='.$code` and the page renders elsewhere.

## 3. Strategy

One Livewire component owns state and queries. Two pure PHP helper classes own data and pure functions (geometry table + status thresholds) so unit tests can exercise them without booting Livewire. Blade view consumes computed properties only.

| File | Responsibility |
|---|---|
| `backend/app/Livewire/DistrictsPage.php` | URL-synced filters, computed properties, action methods. |
| `backend/resources/views/livewire/districts-page.blade.php` | Markup mirroring `index.html#districtsPage` 1:1. |
| `backend/app/Support/AndijanMapGeometry.php` | `VIEWBOX` constant + `CELLS` const array (16 entries) copied verbatim from `index.html`. |
| `backend/app/Support/DistrictStatus.php` | Static `statusFor(?float $pctOfPlan, ?float $growth, bool $lowerIsBetter): string` returning `'green' | 'amber' | 'red' | 'grey'`. |
| `backend/tests/Unit/AndijanMapGeometryTest.php` | Cell count and shape sanity. |
| `backend/tests/Unit/DistrictStatusTest.php` | Threshold boundaries for both directions. |
| `backend/tests/Feature/Http/DistrictsPageTest.php` | Route 200, markup classes present, Livewire interactions. |

## 4. Livewire component

### 4.1 URL-synced state

| Prop | Type | Default | Purpose |
|---|---|---|---|
| `module` | `string` | `'macro'` | Active dashboard module. |
| `kpi` | `string` | `'industry'` | Active indicator code. |
| `period` | `string` | `'h1'` | Reporting period (`q1` / `h1` / `m9` / `year`). |
| `district` | `string` | `''` | Selected district `code`. Empty means "first ranked". |
| `sort` | `string` | `'attention'` | One of `attention` / `execution` / `plan` / `tasks` / `name`. |
| `search` | `string` | `''` | District name filter. |

`regionCode` is a constant on the component: `protected string $regionCode = 'andijan';`.

### 4.2 Action methods

- `selectModule(string $code): void` — sets `$module`, resets `$kpi` to the first available indicator in that module, clears `$search`.
- `selectKpi(string $code): void` — sets `$kpi`, syncs `$module` via indicator → module mapping.
- `selectDistrict(string $code): void` — sets `$district`.
- `setSort(string $value): void` — sets `$sort`.

Search field uses `wire:model.live.debounce.300ms="search"`.

### 4.3 Computed properties (`#[Computed]`)

- `districts()` — `District::where('region_code', 'andijan')->orderBy('sort_order')->get()->keyBy('code')`. Always 16.
- `facts()` — `IndicatorFact::where('region_code', 'andijan')->where('indicator_code', $this->kpi)->where('period', $this->period)->whereNotNull('district_code')->get()->keyBy('district_code')`.
- `rollup()` — same query as `facts()` but `whereNull('district_code')->first()`. Province-wide row.
- `statusByDistrict()` — `Collection<string, string>` (district code → status) using `DistrictStatus::statusFor()` with the current indicator's `lower_is_better` flag. Districts without a fact get `'grey'`.
- `rankedDistricts()` — list of `['district' => District, 'fact' => ?IndicatorFact, 'status' => string]` sorted by `$sort` and filtered by `$search`. Sort order rules:
  - `attention`: red first, amber, grey, green; then alphabetical within group.
  - `execution`: descending `pct_of_plan` (nulls last).
  - `plan`: descending `plan_value` (nulls last).
  - `tasks`: descending count from `Task::forDistrict($district->id)->forIndicator($this->kpi)->count()` (one query batched via `Task::forIndicator($kpi)->withCount('districts')` — implementation detail).
  - `name`: localeCompare-equivalent ascending `name_full`.
- `selectedDistrict()` — `rankedDistricts->firstWhere('district.code', $this->district)` or `rankedDistricts->first()` if empty/unset.
- `moduleOptions()` — modules that have ≥1 indicator with ≥1 district-level fact for the region. Returns `Module` Eloquent collection ordered by `sort_order`.
- `kpiOptions()` — indicators in `$module` that have ≥1 district-level fact for `$period`. Returns `Indicator` Eloquent collection ordered by `label_short`.
- `coverage()` — `['count' => int, 'periods' => list<string>]`. Count of district-level facts for current KPI + period; periods is distinct list of `period` values from indicator's facts for any district.
- `targetCount()` — `PromiseTarget::where('region_code', 'andijan')->where('indicator_code', $this->kpi)->whereNotNull('target_districts')->count()`.
- `taskCount()` — `Task::forRegion('andijan')->forIndicator($this->kpi)->count()`.

## 5. Helper classes

### 5.1 `AndijanMapGeometry`

```php
namespace App\Support;

class AndijanMapGeometry
{
    public const VIEWBOX = '0 0 600 328';

    /**
     * 16 cells copied verbatim from index.html (lines 8837–8854).
     * Each entry: name (matches District.name_full), short (label),
     * cx, cy (label centroid), path (SVG `d` attribute).
     */
    public const CELLS = [
        // 14 districts + Андижон ш + Хонобод ш
        ['name' => 'Андижон тумани',   'short' => 'Андижон',   'cx' => 331.4, 'cy' => 129.2, 'path' => '...'],
        // ... 15 more
    ];
}
```

Names must equal `districts.name_full` for the region. Verified by unit test.

### 5.2 `DistrictStatus`

```php
namespace App\Support;

class DistrictStatus
{
    public const GREEN = 'green';
    public const AMBER = 'amber';
    public const RED   = 'red';
    public const GREY  = 'grey';

    /**
     * Direction-aware status thresholds.
     *
     * Higher-is-better: >=95 green, >=80 amber, else red.
     * Lower-is-better:  <=100 green, <=110 amber, else red.
     * Falls back to growth_pct if pct_of_plan is null. Both null -> grey.
     */
    public static function statusFor(?float $pctOfPlan, ?float $growth, bool $lowerIsBetter): string
    {
        $value = $pctOfPlan ?? $growth;
        if ($value === null) return self::GREY;

        if ($lowerIsBetter) {
            if ($value <= 100) return self::GREEN;
            if ($value <= 110) return self::AMBER;
            return self::RED;
        }

        if ($value >= 95) return self::GREEN;
        if ($value >= 80) return self::AMBER;
        return self::RED;
    }
}
```

## 6. Blade view (`districts-page.blade.php`)

Mirrors `index.html` lines 9226–9272. Three top-level blocks:

### 6.1 `<header class="districts-head">`

- `<div class="dashboard-module-tabs district-module-tabs">` — one `<button class="module-tab {{ $code === $module ? 'active' : '' }}" wire:click="selectModule('{{ $code }}')">` per entry in `moduleOptions()`. `<span class="module-dot">` + `<strong>` label.
- `<div class="module-heading">` — h2 = active module label, p = brief description (constant per module from a small inline map).
- `<div class="district-kpi-selector">` (only when `kpiOptions()->count() > 1`) — one `<button class="district-kpi-option {{ $i->code === $kpi ? 'active' : '' }}" wire:click="selectKpi('{{ $i->code }}')">` per indicator.
- `<div class="district-data-layers">` — 4 mini-stat blocks: coverage (count district facts), `D-мақсад` (`targetCount`), `T-топшириқ` (`taskCount`), logic note (static text from an inline map keyed by indicator code; falls back to generic copy if unknown).
- `<div class="districts-head-actions">` — sort `<select>` with 5 options + search `<input>`.

### 6.2 `<div class="districts-grid">`

- `<section class="districts-map">` — `<header class="districts-map-head">` (KPI label + caption), `<div class="districts-map-canvas">` containing an SVG with the four gradient `<defs>` (green/amber/red/grey) and a drop-shadow filter copied from prototype (lines 8881–8901). Loop `AndijanMapGeometry::CELLS`:
  - `<g class="map-cell {{ $statusByDistrict[$code] }} {{ $code === selectedDistrictCode ? 'selected' : '' }} {{ $isCity ? 'is-city' : '' }}" wire:click="selectDistrict('{{ $code }}')"><title>{{ name }} · {{ value }}</title><path class="map-fill" d="{{ path }}"/></g>`
  - `<text class="map-label" x="{{ cx }}" y="{{ cy + 1 }}" text-anchor="middle" dominant-baseline="central">{{ short }}</text>`
- `<div class="districts-map-legend">` — 4 chip spans (green/amber/red/grey).
- `<aside class="districts-side">` — `.district-summary-card` for selected district (KPI value, status chip, growth, fact/plan rows) + `.district-leaderboard` ordered list of all districts with mini progress bars.

### 6.3 `<section class="panel district-detail-table">`

- `.panel-head` with title + source chip.
- `<table>` columns: Туман / Режа / Амалда / Ўсиш % / Ижро % / Ҳолат / Профил (link).
- Per row: `<a class="mini-button" href="{{ route('profile') }}?districtCode={{ $code }}">Профил</a>`.

## 7. Routing

`/districts` route already exists at `routes/web.php` (`Route::view('/districts', 'pages.districts')->name('districts')`). `pages/districts.blade.php` already mounts `<livewire:districts-page />`. No route changes.

## 8. Tests

| Path | Cases |
|---|---|
| `tests/Unit/AndijanMapGeometryTest.php` | `VIEWBOX === '0 0 600 328'`; `count(CELLS) === 16`; every entry has non-empty string `name`, `short`, `path` and numeric `cx`, `cy`; cell names form a subset of the Andijan districts seeded via `database/seeders/AndijanDistrictsSeeder.php` (if one exists) or assert they match `District::where('region_code','andijan')->pluck('name_full')` after seeding. |
| `tests/Unit/DistrictStatusTest.php` | Higher-better thresholds: 95 green / 80 amber / 79 red / null grey. Lower-better thresholds: 100 green / 110 amber / 111 red / null grey. Fallback: null pct + non-null growth uses growth side. Both null = grey. |
| `tests/Feature/Http/DistrictsPageTest.php` | `GET /districts` returns 200 and HTML contains `districts-map`, `districts-side`, `district-detail-table`. Seeds Andijan + a few districts + a few `indicator_facts` rows. Livewire test: `Livewire::test(DistrictsPage::class)->call('selectModule', 'export')->assertSet('kpi', 'export')`. `Livewire::test()->call('selectDistrict', '<code>')->assertSet('district', '<code>')`. Detail-table row contains a `/profile?districtCode=<code>` href. |

## 9. Files touched

| File | Action |
|---|---|
| `backend/app/Livewire/DistrictsPage.php` | replace stub |
| `backend/resources/views/livewire/districts-page.blade.php` | replace stub |
| `backend/app/Support/AndijanMapGeometry.php` | new |
| `backend/app/Support/DistrictStatus.php` | new |
| `backend/tests/Unit/AndijanMapGeometryTest.php` | new |
| `backend/tests/Unit/DistrictStatusTest.php` | new |
| `backend/tests/Feature/Http/DistrictsPageTest.php` | new |

No CSS, no migrations, no other Blade files, no JS.

## 10. Risks and mitigations

- **Risk:** Map cell names drift from `District::name_full`. *Mitigation:* unit test cross-checks both sets.
- **Risk:** `IndicatorFact.pct_of_plan` null while `actual_hokimyat` is set. *Mitigation:* `DistrictStatus::statusFor` falls back to `growth_pct`; both null → grey.
- **Risk:** Some macro indicators (`grp`, `construction`) have no district-level facts. *Mitigation:* `kpiOptions()` filters to indicators with ≥1 district-level fact; they don't appear in the selector.
- **Risk:** SVG paths bloat the rendered HTML (~10KB). *Mitigation:* one-shot server render; gzipped wire payload ~3-4KB.
- **Risk:** Status thresholds (95/80, 100/110) are opinionated; differ from prototype's exact `rowStatus()` implementation. *Mitigation:* thresholds documented in `DistrictStatus` doc-comment so future tweaks are localized.
