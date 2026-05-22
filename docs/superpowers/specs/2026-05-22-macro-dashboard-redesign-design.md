# Macro Dashboard Redesign — Design Spec

**Date:** 2026-05-22
**Branch:** v7-design-polish
**Reference:** `Screenshot 2026-05-22 100100.png` (user-supplied mockup of the target macro view)

## Goal

Restyle the **Макроиқтисодиёт** dashboard view to match the reference screenshot: a single white card holding the module heading, a dark-blue ЯҲМ hero, a 2×2 sector grid, and a period row — plus a restyled module-tab strip and a restyled execution scoreline.

## Scope

- **In scope:** the `macro` module view only. Module-tab strip restyle is global chrome (shared by all modules, intended by the screenshot).
- **Out of scope:** the other 6 modules' card bodies (`inflation`, `budget`, `budget_invest`, `foreign_invest`, `export`, `employment`). They keep their current layout. Roll-out to them is a later, separate effort.
- **No backend/data changes.** All values already bind from `IndicatorFact` / `DashboardCatalog`. No new queries, no fabricated numbers.
- Approach **A**: CSS restyle in `portal.css` plus thin Blade markup edits. No Livewire PHP component class changes.

## Known discrepancy

The catalog defines **7 modules**; the screenshot shows **6 tabs** (omits `Инфляция`, module 2). Removing a module is a data/structure change, not a redesign — **all 7 tabs are kept**. Only tab styling changes.

## Module / data facts (read from code)

- `macro` module: `kpis = [grp, industry, agriculture, construction, services]`, `has_front_cards = true`, `layout_class = macro-layout`, `label = "1. Макроиқтисодиёт"`.
- `kpi-dashboard.blade.php` stacks: `kpi-module-tabs` → `module-heading` → `kpi-front-cards` (if front cards) → `kpi-workspace-card` → `kpi-scoreline`.
- `kpi-front-cards` renders one `.front-kpi` button per code; `grp` already gets a `parent` class when macro.
- `kpi-workspace-card` renders panel `macro-growth` for any macro-growth KPI. `macro-growth.blade.php` has two branches: `solo` (`macro-hero-strip` — value + arrow + chips) for grp/agriculture/construction/services; `with-side` (`macro-main-panel` + `industry-driver-panel`) for industry.
- `kpi-scoreline` renders `.scoreline.execution-strip`: copy + 3 status pills + donut.
- `selectKpi` is wired through `kpi-front-cards` → `KpiDashboard`; clicking a front card swaps `$kpi` and re-renders the workspace panel reactively.

## Design

### 1. Module tabs (global)

Restyle `.dashboard-module-tabs` / `.module-tab` in `portal.css` to a row of solid blue buttons; active tab = darker navy; count stays as the small `(done/total)` text. **No markup change** to `kpi-module-tabs.blade.php`.

### 2. Module card wrapper (macro only)

In `kpi-dashboard.blade.php`, wrap `module-heading` + `kpi-front-cards` + `kpi-workspace-card` in a single `<div>` whose class is conditional on `$module`:

- `macro` → class `module-card`: white background, hairline border, radius, padding.
- any other module → class `module-flow` styled `display: contents` — the wrapper disappears from layout, so those 6 modules render byte-for-byte as today.

`kpi-scoreline` stays **outside** the wrapper (its own card below, per screenshot).

The workspace card's inner `.kpi-monitor-card` chrome is neutralized inside `.module-card` (`border:0; background:transparent; padding:0; box-shadow:none`) so there is no card-in-card.

### 3. Blue ЯҲМ hero

The `grp` / `parent` `.front-kpi` becomes a dark royal-blue panel: a "ЯҲМ" badge, the big white year-growth value (e.g. `+7.8%`), a "йиллик ўсиш" caption, and a large low-opacity decorative up-arrow on the right.

- `kpi-front-cards.blade.php`: inside the `@foreach`, when `$parent` is set, also render a decorative arrow `<svg>` (the existing up-trend polyline, scaled, `aria-hidden`). All other markup stays.
- The hero always shows `grp`. It stays clickable (`wire:click="selectKpi('grp')"`).
- Colors reference existing accent tokens in `portal.css` (royal-blue accent family) — no new hex literals where a token exists.

### 4. Sector grid

The 4 non-parent `.front-kpi` cards (industry, agriculture, construction, services) become white cards with hairline border, in a 2×2 grid to the right of the hero. Each: icon badge + short label + blue value + "йиллик ўсиш" caption — current markup already produces icon + `h3` + `.front-kpi-value` + `.front-kpi-note`, so **no markup change**, CSS only.

Grid: `.front-kpis.macro-layout` becomes a CSS grid — hero placed `grid-column: 1 / grid-row: span 2`; the 4 sectors auto-flow into a 2-column × 2-row block on the right.

### 5. Period row

Rework the `solo` branch of `macro-growth.blade.php`. Today it renders `macro-hero-strip` (value + arrow + `macro-hero-strip__chip` list). The value+arrow now live in the hero (section 3), so `solo` instead renders a **4-cell period row** `.macro-period-row`: one cell per entry of the existing `$macroPeriods` array — I чорак / II чорак / III чорак / Йиллик — each showing the period label, the growth value, and the state in parentheses (`Амалда` / `Режа` / `Режа` / `Мақсад`). Data comes from `$rows` exactly as the current chips do.

The `with-side` branch (industry) is unchanged structurally; it only inherits `.module-card` chrome and is restyled lightly so it sits cleanly in the card. It is **not** pixel-matched to a reference (the screenshot does not show it).

### 6. Scoreline restyle

`kpi-scoreline.blade.php` keeps base classes `scoreline execution-strip` and gains a module class (`is-macro` when `$module === 'macro'`). CSS targets `.scoreline.is-macro` for the new look so the other 6 modules' scoreline is untouched.

New macro look: a small uppercase label + bold scope title + intro on the left; the percentage shown as a plain large number; 3 tinted count blocks — Жами (blue), Бажарилди (green), Бажарилмади (red), each a label + large number. Markup may be lightly reordered/reclassed; counts/links (`$total`, `$done`, `$open`, `$pct`, task routes) are preserved.

### 7. Sub-KPI behavior

Clicking a sector card selects that KPI (`selectKpi`) and swaps the panel below — current behavior, preserved (per the user's choice "swap in-page panel"). The blue grp hero stays in place across all macro KPI selections. `industry` shows the existing drivers panel; the other sectors show their own period row.

### Copy fix

`DashboardCatalog::MODULES['macro']['intro']` changes from `ЯҲМ ва асосий таркибий кўрсаткичлар` to `ЯҲМ ва асосий тармоқлар кўрсаткичлари` to match the screenshot subtitle.

## Files touched

| File | Change |
| --- | --- |
| `backend/public/css/portal.css` | Bulk: module tabs, `.module-card`, blue hero, sector grid, `.macro-period-row`, `.scoreline.is-macro` |
| `backend/resources/views/livewire/kpi-dashboard.blade.php` | Conditional `module-card` / `module-flow` wrapper |
| `backend/resources/views/livewire/dashboard/kpi-front-cards.blade.php` | Decorative arrow SVG for the parent card |
| `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php` | `solo` branch → `.macro-period-row` |
| `backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php` | Add `is-macro` class; light markup reorder |
| `backend/app/Support/DashboardCatalog.php` | macro `intro` text |

No Livewire PHP component classes change.

## Test handling

The redesign removes the `macro-hero-strip` markup, so existing tests that assert it must be updated as part of the work:

- `tests/Feature/Livewire/MacroHeroStripTest.php` — both cases assert `macro-hero-strip` markup. Rewrite the file: rename to assert the new `.macro-period-row` is rendered for `grp`, and that it is absent / `macro-main-panel` present for `industry`. (Note: this file already has a failing assertion against current code — `macro-hero-strip__chip is-actual` — so it is also a baseline-failure fix.)
- `tests/Feature/Http/DashboardRoutesTest.php:59` — asserts `macro-hero-strip` present on the dashboard. Update to assert `macro-period-row`.
- `tests/Feature/Http/DashboardRoutesTest.php:73` — asserts `scoreline execution-strip`. The base classes are **kept**, so this passes unchanged.
- `tests/Feature/Livewire/KpiModuleTabsTest.php` — asserts `module-tab__icon` / `module-tab__bar`, which current tab markup does not render (pre-existing baseline failure). The tab restyle is CSS-only and does not touch tab markup, so this test is left as-is; it is not introduced or worsened by this work.

After implementation the full suite must run; the only changes vs. the pre-work baseline must be the intentionally-updated `MacroHeroStripTest` and `DashboardRoutesTest` tests turning green.

## Verification

After implementation, render the dashboard headless (Edge `--headless --screenshot`) at the macro view and compare against the reference screenshot for: tab row, single white card, blue hero + 2×2 sectors, period row, scoreline. Confirm clicking a sector still swaps the panel and clicking another module tab still works.
