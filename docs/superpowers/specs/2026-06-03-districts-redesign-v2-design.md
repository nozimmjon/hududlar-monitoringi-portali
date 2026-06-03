# Districts page redesign v2 — design spec

**Date:** 2026-06-03
**Branch:** v7-design-polish
**Scope:** `backend/` — `/districts` (`DistrictsPage` Livewire component + view + `portal.css`)
**Status:** Approved (mockup `.superpowers/mock-districts-v3.html`); ready for implementation plan.
**Supersedes the UI of:** `2026-06-03-districts-page-redesign-design.md` (v1). v1 merged the lists into one table but kept the same overall shape; the user rejected it as "no improvement."

## Problem (with v1 live)

Viewing the rendered page showed v1 did not change the *feel*: filters on top → map + side panel → a long table beneath. Specifically:
1. A district is **pre-selected** on entry (`selectedDistrict` falls back to `rows[0]`) — presumptuous.
2. The **merged table is still long** (14 tall rows past the fold) — the dominant element.
3. The **toolbar is too plain** (flat pills).

## Goals

- One screen, no scrolling to a giant table.
- Nothing selected on entry.
- A richer, "designed" toolbar that also orients the user (what KPI, what region value).
- Preserve the core concept: plan vs fact + drill macro KPI → district → task.

## Non-goals

- No data-model / route / import changes. Map geometry + choropleth unchanged.
- No `npm run build` — `public/css/portal.css` is hand-maintained, edited directly.
- Full per-KPI metric breakdowns are **not** shown on this page anymore; they live on the district **profile** page (the drilldown target).

## Confirmed decisions

1. **Page model:** Map + **compact rank list** side by side. The heavy detail table is **removed** from this page.
2. **No pre-selection.** Entry shows the map + rank list with nothing highlighted.
3. **Detail reveal:** clicking a district (map cell or rank row) opens a **slide-over peek panel** over the right edge with quick stats + Профил/Журнал; closing it clears the selection. Page height does not grow.
4. **Toolbar:** a unified **hero header card** (C+A hybrid) — module segmented control + grouped search/sort, a hero line (KPI title + region value + period, absorbing the old blue rollup banner), and a **KPI stat-cards** row (each KPI shows its region value + trend; active highlighted).

## New page structure

Top to bottom: **Header card → [Map | Rank list] → (Slide-over peek, overlay).**

### 1. Header card (`.districts-header`)

One white card containing three stacked zones:

- **Top row:** module **segmented control** (`.module-seg`, buttons from `moduleOptions`, active filled blue) on the left; a grouped **search + sort** control (`.districts-tools`) on the right.
- **Hero row:** large KPI icon + `indicator->label_short` title + subtitle (`{регион} · туманлар кесими`); on the right, the **region value** (the active KPI's region-level rollup, formatted `pct_of_plan` or `growth_pct` with sign) + a period chip. This replaces the standalone rollup banner that was inside the map.
- **KPI stat-cards row** (`.kpi-stats`): one card per KPI in the current module (`kpiOptions`), each showing `label_short` + that KPI's region value + a trend marker; the active KPI card is highlighted. Clicking selects the KPI (`selectKpi`). Hidden when the module has only one KPI.

### 2. Main grid (`.districts-grid`: map | rank list)

- **Map** (`.districts-map`): the existing choropleth SVG, hover tooltip, and legend — unchanged, **except** the rollup banner is removed from here (moved into the header hero). Clicking a cell calls `selectDistrict(code)`.
- **Rank list** (`.districts-ranklist`): replaces both the old leaderboard and the merged table. A scrollable list (fixed max-height ≈ map height) of `rankedDistricts`, each a short row: rank · status dot · `name_full` · mini progress bar · primary value (`pct_of_plan`, else `growth_pct`, else `—`). Row click calls `selectDistrict(code)`; keyboard accessible (`tabindex="0"`, Enter/Space). Header shows count. Sort + search (from the header controls) reorder/filter this list via the existing `rankedDistricts` logic.

### 3. Slide-over peek panel (`.district-peek`)

Always in the DOM; an `.open` class (server-rendered when a district is selected) toggles its visibility via a CSS transform transition, with a dimming `.district-peek-backdrop`. Contents when open:

- `Танланган ҳудуд` label + `name_full` + status chip.
- Big execution value (`pct_of_plan`) + `Ижро бажарилиши · {kpiShort}`.
- `Режа` / `Факт` tiles (`plan_value` / `actual_hokimyat ?? actual_statkom`).
- Plain chips: `Топшириқлар {done}/{total}` and `Кафолат мажбурияти {n}`.
- Actions: `Профил` (route `profile?districtCode=`), `Журнал` (route `execution?...`).
- Close affordance (× and backdrop click) → `clearDistrict()`.

## Component changes (`app/Livewire/DistrictsPage.php`)

**Behavior:**
- `selectedDistrict()` — **remove the `rows[0]` fallback.** Return the matching row only when `$this->district !== ''`; otherwise `null`. (This is what removes pre-selection.)
- Add `clearDistrict(): void` — sets `$this->district = ''` (closes the peek).

**New computed:**
- `moduleKpiStats(): array` — for each indicator in `kpiOptions`, the region-level (`district_code` null) fact at that KPI's `DistrictTableConfig::for($code)['primary_period']`; returns `[code => ['indicator'=>Indicator, 'value'=>?float, 'kind'=>'execution'|'growth']]`. Feeds the KPI stat-cards. (`value` = `pct_of_plan ?? growth_pct`; `kind` = `execution` when `pct_of_plan` is present, else `growth`.) Trend marker (view-side): `up` when `kind=growth && value≥0` or `kind=execution && value≥100`, else `down`; `—`/no marker when `value` is null.

**Keep:** `facts` (district-level facts for the active KPI — still used by `colorScale`, `statusByDistrict`, `rankedDistricts`), `districts`, `indicator`, `rollup` (now feeds the hero value), `statusByDistrict`, `rankedDistricts`, `taskCountByDistrict`, `targetCountByDistrict`, `colorScale`, `colorRange`, `mapGeometry`, `moduleOptions`, `kpiOptions`, and actions `selectModule`, `selectKpi`, `selectDistrict`, `setSort`. URL state unchanged (`module`, `kpi`, `period`, `district`, `sort`, `search`).

**Remove (no longer used — the per-KPI metric table is gone):** the `tableConfig()` and `factMatrix()` computed properties. Keep the `DistrictTableConfig` import — it is still used by `selectKpi()` (primary period) and `moduleKpiStats()`. The `DistrictMetricResolver` usage and `resolveCell` helper leave the view entirely.

## View (`resources/views/livewire/districts-page.blade.php`) — full rewrite

Replace the current toolbar + grid + table with: header card (segmented modules + tools + hero + stat-cards) → grid (map + rank list) → slide-over peek. The map `<section>` keeps its SVG/legend but drops the rollup banner block. No `.districts-table`, no `.district-summary-card` grid child, no `resolveCell`/`DistrictMetricResolver`.

## CSS (`public/css/portal.css`)

Hand-maintained, no build.

- **Add:** `.districts-header` (card), `.module-seg`/`.module-seg button(.on)`, `.districts-tools`, `.districts-hero` (icon, title, value, period chip), `.kpi-stats`/`.kpi-stat-card(.on)` (icon, value, trend), `.districts-ranklist` (+ rows: `.rank-row`, status dot, mini bar, value, `.selected`), `.district-peek` (+ `.open` transition), `.district-peek-backdrop`.
- **Remove:** the v1 merged-table additions (`.districts-table .dt-rank`, `.dt-status`, `.dt-exec`, `.dt-bar` and their `tr.green/amber/red` variants, and the `.districts-table .row-title strong` override); the v1 toolbar bits being replaced (`.districts-toolbar`, `.district-kpi-pills`, `.district-kpi-pill*`); and the old detail-panel rules now unused (`.district-summary-*`, `.district-count-split`) and the rollup-banner rule if it is districts-only. Remove the standalone `.district-detail-table` / `.district-table` rules if no longer referenced (grep first; they were districts-only).
- Update responsive (`max-width`) rules that reference any removed selector.

## Interaction model

- Selection is Livewire state (`district`). Map cell click and rank-row click both call `selectDistrict(code)`. The peek renders `.open` when `selectedDistrict !== null`. Close (× / backdrop) calls `clearDistrict()`.
- No pre-selection: bare `/districts` (no `?district=`) renders no `.open` peek and no highlighted row/cell.
- The slide-over uses a CSS class toggle + transform transition (no new JS framework needed; Alpine already present for the map tooltip if a click-out is wanted, but server-rendered `.open` is sufficient).

## Labels

- Keep plain Cyrillic: `Топшириқлар {done}/{total}` (done/total), `Кафолат мажбурияти {n}`. No `D-`/`T-` codes. Stat-cards/hero values formatted with sign for growth, `%` for execution.

## Testing (TDD — rewrite `tests/Feature/Http/DistrictsPageTest.php` first, to red)

The page markup changes substantially. Update:

- **Bare `GET /districts`** — assert the new shell: `districts-header`, `module-seg`, `districts-ranklist`, `districts-map`; assert district names render in the rank list. Assert **no** `district-peek` in the `open` state (no pre-selection) — e.g. the rendered peek does **not** carry the `open` class and no row carries `selected`.
- **`GET /districts?district=<code>`** — assert the peek renders open for that district (status chip, `Режа`/`Факт`, `Топшириқлар`, `Кафолат мажбурияти`, Профил link).
- **Remove** the v1 assertions for `districts-table`, `dt-rank`, `dt-exec` and the two per-KPI column-header tests (`industry-specific column headers`, `budget-specific column headers`) — those metric labels are intentionally no longer on this page (they live on the profile page).
- **Keep** `selectModule` / `selectKpi` / `selectDistrict` state tests and the profile-link test (the peek + rank rows still emit profile links).
- Add a test that `clearDistrict` resets `district` to `''`.
- `DistrictsPageSelectionTest`: update the `selectDistrict updates selectedDistrict` test (still valid — selection by explicit code returns the row); add/confirm that with `district = ''` `selectedDistrict()` is `null` (no fallback).

Run targeted, alone (shared test DB): `php artisan test --filter=Districts`.

## Conceptual integrity check

- **Plan vs fact** preserved: region value in the hero, execution %/bar per district in the rank list, `Режа`/`Факт` in the peek.
- **Drill chain** preserved: region (hero) → district (map/rank → peek) → task (Топшириқлар chip, Журнал) and full detail via Профил.
- The page no longer shows the full per-KPI metric grid by design — that depth moves to the profile page, which is the intended district drilldown.
