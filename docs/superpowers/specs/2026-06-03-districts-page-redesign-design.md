# Districts page redesign — design spec

**Date:** 2026-06-03
**Branch:** v7-design-polish
**Scope:** `backend/` — `/districts` page (`DistrictsPage` Livewire component + view + `portal.css`)
**Status:** Approved structure; ready for implementation plan.

## Problem

The current `/districts` page is long (2+ screens), repetitive, and hard to scan:

1. **Five stacked blocks before any content** — module tabs, an in-component heading (`Туманлар` + description), a grid of tall KPI cards, three "data-layer" cards (`D-маълумот` / `D-мақсад` / `T-топшириқ`), and a separate bordered sort/search card.
2. **The district list is rendered three times** — the choropleth map, the right-side leaderboard, and the bottom detail table all enumerate every district.
3. **The selected-district summary card duplicates** a slice of the detail-table metrics.
4. **Coded labels** (`D-маълумот`, `D-мақсад`, `T-топшириқ`) are cryptic; each needs a caption to be understood.

## Goals

- Compact the page to roughly 1.5 screens with a single, scannable district list.
- Make every element self-explanatory (plain Cyrillic labels, no `D-`/`T-` codes).
- Preserve the core concept: **plan vs fact** (`Кафолат хатидаги ваъдалар бажариляптими?`) and the drill chain **macro KPI → district → task**.

## Non-goals

- No changes to data models, migrations, import pipelines, routes, or other pages.
- Map geometry and the choropleth color logic stay as-is.
- No `npm run build`: `public/css/portal.css` is hand-maintained and edited directly.

## Decisions (confirmed with user)

1. **Layout direction A** — map + one merged table. The leaderboard and the bottom detail table collapse into a single ranked table; the map stays as the spatial anchor beside a selected-district detail panel.
2. **Remove all three `D-`/`T-` header cards entirely.** Targets and tasks remain visible *per district* in the table and detail panel, with plain labels.
3. **Curated table + detail panel.** The merged table shows only core columns and stays narrow; clicking a row fills the detail panel with that district's *full* metric set.

## New page structure

Top to bottom: **Toolbar → [Map | Detail panel] → Merged table.**

### 1. Compact toolbar

Replaces the five stacked header blocks with one compact control area.

- **Remove** the in-component `<h2>Туманлар</h2>` + description block — the page shell (`layouts/app.blade.php` → `@section('page-title')` / `page-subtitle`) already prints title and subtitle.
- **Remove** the `.district-data-layers` block (the three `D-`/`T-` cards) and its backing computed props.
- **KPI selector**: convert the tall 74px `.district-kpi-option` cards into a compact horizontal **pill row** (icon + `label_short`; the `source` text moves to a `title` tooltip).
- **Layout**: module tabs on one row with sort + search controls aligned right; KPI pills on the row beneath. No separate bordered sort/search card.

### 2. Hero row — Map (left) + Detail panel (right)

Two-column grid, same proportions family as today (`~1.4fr / ~0.7fr`).

- **Map** (`.districts-map`): unchanged in spirit — choropleth SVG, rollup banner (`регион · KPI · қиймат · давр`), legend. Remains the spatial anchor and a selection source.
- **Detail panel** (today's `.district-summary-card`, repurposed as the *depth* target):
  - District `name_full` + status chip (`Яхши` / `Ўртача` / `Эътибор` / `Маълумот йўқ`).
  - Large execution % — the plan-vs-fact headline.
  - Plain-label chips: **`Топшириқлар {done}/{total}`** and **`Кафолат мажбурияти {n}`** (no `T:` / `D:`).
  - **Full metric list** — *all* of the active KPI's `DistrictTableConfig` columns for the selected district (not just the first four), rendered via the existing `resolveCell()` helper over `factMatrix`.
  - Actions: `Профил` (route `profile?districtCode=`) · `Журнал` (route `execution?...`). Keep existing routes/params.
  - Empty state when nothing selected: `Харита ёки жадвалдан туман танланг`.

### 3. Merged table (full width)

The single source of the district list — replaces **both** the leaderboard aside **and** the old detail table. Root class: **`.districts-table`** (stable hook for tests).

- Columns: **`#`** (rank) · **`Туман/шаҳар`** · status · **`Ижро %`** (with mini progress bar) · **`Режа`** · **`Факт`** · **`Топшириқлар`** (chip) · **`Кафолат мажбурияти`** (chip) · **`Амал`** (Профил · Журнал).
- The first columns absorb the leaderboard's job (rank + status color + % bar); the metric columns stay curated (core only) — full per-KPI breakdown lives in the detail panel.
- **Row click** → `selectDistrict(code)` → map highlights + detail panel fills (replaces both `lb-row` and the old `tr.clickable` behaviors).
- **Sort** control reorders this table (`attention` / `execution` / `plan` / `name` — unchanged options, driven by `rankedDistricts`).
- **Search** filters this table (unchanged `rankedDistricts` filter).

## What is removed

- In-component heading block (`.module-heading`).
- `.district-data-layers` + `.district-data-layer*` markup and CSS.
- `.districts-leaderboard`, `.districts-lb-*`, `.lb-*` markup and CSS.
- The standalone `.district-detail-table` section (folded into the merged table).
- Cryptic strings `T-топшириқ`, `D-мақсад` everywhere.

## Backend changes (`app/Livewire/DistrictsPage.php`)

- **Remove** now-unused computed props that fed the deleted header cards: `coverage()`, `targetCount()`, `taskCount()`.
- **Keep** everything else: `rankedDistricts`, `factMatrix`, `tableConfig`, `selectedDistrict`, `statusByDistrict`, `taskCountByDistrict`, `targetCountByDistrict`, `colorScale`, `colorRange`, `mapGeometry`, `moduleOptions`, `kpiOptions`, and all actions (`selectModule`, `selectKpi`, `selectDistrict`, `setSort`).
- URL state (`#[Url]`: `module`, `kpi`, `period`, `district`, `sort`, `search`) is unchanged.
- No new computed properties are required — the detail panel reuses `tableConfig` + `factMatrix`.

## Labels

- Task chip flips from *unfinished*/total to **done/total** (`{done}/{total}`) so the ratio reads as progress; label `Топшириқлар`.
- Target chip label `Кафолат мажбурияти` (or short `Мажбурият` in the table header if space requires).
- All `D-`/`T-` prefixes removed.

## CSS (`public/css/portal.css`)

Hand-maintained — edit directly, no build step.

- **Add/adjust**: compact toolbar, slim KPI pills, detail-panel full-metric list, merged-table styles (rank, status color, `Ижро %` bar, chips).
- **Remove**: `.district-data-layers`, `.district-data-layer*`, `.districts-leaderboard`, `.districts-lb-*`, `.lb-*`, and the old standalone `.district-detail-table` rules that no longer apply (reuse class names where convenient to minimize churn).
- Update the responsive (`max-width`) rules that reference removed classes.

## Testing (TDD — update tests first, to red)

Existing tests in `backend/tests/Feature/Http/DistrictsPageTest.php` assert the old markup and must be rewritten:

- `GET /districts ... map and table markup` — drop `districts-side` / `district-detail-table` asserts; assert `districts-table` (and keep `districts-map`).
- `side aside renders ... leaderboard markup` — **rewrite** for the merged table (no `districts-leaderboard` / `lb-row` / `lb-rank`); assert `districts-table` row markup instead.
- `detail table renders T-topshiriq and D-maqsad cells` — **rewrite** to assert the plain labels (`Топшириқлар`, `Кафолат мажбурияти`) and absence of `T-топшириқ` / `D-мақсад`.
- `detail table shows industry-specific column headers` and `... budget-specific column headers` — the full per-KPI metric labels now live in the **detail panel**, not as table headers; update these to assert the labels render in the panel for the selected district.
- `detail table contains profile link for each district` — keep (merged table still emits profile links).

Tests that stay valid:

- `backend/tests/Feature/Livewire/DistrictsPageSelectionTest.php` — `selectedDistrict` computed and map-geometry coverage are unchanged.

Run targeted, alone (shared test DB — never run two suites at once):

```powershell
php artisan test --filter=Districts
```

## Conceptual integrity check

- **Plan vs fact** preserved: rollup banner (region headline), execution % + `Режа`/`Факт` columns, per-district detail metrics.
- **Drill chain** preserved: map → table row → detail panel → `Профил` / `Журнал` / `Топшириқлар` links.
- Nothing hides the plan-vs-fact comparison or the driver→district→task path.
