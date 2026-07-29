# Sector (тармоқлар) dashboard — UI design

**Date:** 2026-07-29
**Status:** approved
**Phase:** 2 of 2 (visualization for the sector guarantee-letter tasks imported in phase 1 — see `2026-07-29-sector-tasks-import-design.md`)

## Background

Phase 1 stores 17 sector enterprises' guarantee-letter tasks (119 tasks / 521 indicator
lines) in `sectors` / `sector_tasks` / `sector_task_progress` with per-period history
and denormalized snapshots (`status`, `headline_*`, `lines_total`, `lines_done`,
`latest_period`). This phase renders them on a dedicated dashboard. Design choices
below were validated interactively with mockups (card grid → compact card →
profile-style drilldown).

## Decisions (user-validated)

1. **Single page, overview + inline drilldown** — no separate enterprise profile page.
2. **Entry only from the home page** (country-map landing, `pages/home.blade.php`).
   The dashboard is national: NOT added to the region sidebar, no region scoping,
   `RegionSwitcher`/region topbar absent.
3. **Overview = compact card grid** (17 cards): enterprise name, task/indicator
   counts, progress bar, large percent, done/open/in-progress counters.
4. **Drilldown mirrors the district profile's task cards** (`dp-task` visual
   language) — the user explicitly asked for "туманларнинг топшириқлар саҳифаси каби
   дизайн".
5. **Multi-indicator tasks expand on click** (option B) — compact card first, inner
   indicator lines revealed on demand.

## Page structure

### Route & layout

- Route: `Route::view('/sectors', 'pages.sectors')->name('sectors');`
- Standalone national page like the home page: no region sidebar/topbar/switcher.
  Header carries the page title («Тармоқ корхоналари топшириқлари» or similar
  Cyrillic Uzbek label) and a «← Бош саҳифа» link back to `/`.
- One Livewire component: `App\Livewire\SectorsDashboard`, mounted from
  `resources/views/pages/sectors.blade.php`.

### Home-page entry

A block/button near the country map on `pages/home.blade.php` linking to
`route('sectors')` — visually consistent with the landing page (its existing card /
button styles), labeled «Тармоқлар» (final wording at implementation).

### Summary strip (top)

`dp-fact`-style cells: 17 корхона · 119 топшириқ · counts by status
(бажарилди / бажарилмаган / кутилмоқда). Computed from `sector_tasks` aggregates.

### Card grid (overview)

- One card per sector, ordered by `sectors.sort_order` (sheet order). No sorting or
  filter controls in this phase (YAGNI).
- Card content (compact variant): `name_short`, «N топшириқ · M индикатор», progress
  bar, large percent, counters «X бажарилди · Y бажарилмаган · Z кутилмоқда».
- Card percent AND progress bar both show the same ratio:
  `sum(lines_done) / sum(lines_total)` across the sector's tasks (indicator-level
  completion), matching how multi-line task percent works on the profile page.
  A sector with no reported actuals anywhere shows no percent: dimmed card with
  «Маълумот кутилмоқда» (current state of all 17 until the first monthly file).
- Progress bar tier colors follow the profile convention: green when all done,
  amber ≥50%, red <50%, grey when nothing reported.
- Clicking a card selects the sector (Livewire state), opens the drilldown below the
  grid, and highlights the active card.

### Drilldown (selected sector)

- Full-width block below the grid: heading «{org_full} — N топшириқ» plus
  `signer_text` as a muted subline.
- Task cards copy the district profile's `dp-task` structure exactly:
  - title + status chip: Бажарилди (green) / Бажарилмаган (amber) / Бажарилмоқда
    (violet);
  - strip cells: single-line tasks → РЕЖА / АМАЛДА (+ unit via
    `DashboardCatalog::unitLabel`) / БАЖАРИЛИШ; multi-line tasks → ИНДИКАТОРЛАР /
    БАЖАРИЛДИ / БАЖАРИЛИШ (lines_done/lines_total %);
  - tier-colored progress bar (same `--task-green/amber/red` variables, done caps
    at 100, undone capped display at 99 — reuse the profile's percent rules);
  - meta line: «Муддат: {deadline}» + «ҳолат: {latest_period}» or «маълумот
    кутилмоқда» for in_progress.
  - Task deadline shown = the task's headline line `deadline_text` (from the latest
    period's line with the lowest `line_no`).
- Multi-line tasks are clickable: expanding reveals inner indicator rows from
  `sector_task_progress` (latest period): `metric_label` — actual / plan unit — pct,
  rows without actuals dimmed with «—». Collapse on second click. Single-line tasks
  don't expand.

### Data access

- Overview: `Sector::orderBy('sort_order')` + per-sector aggregates over
  `sector_tasks` (counts by status, sum lines_total/lines_done). One query with
  `withCount`/aggregate joins — no N+1 across 17 sectors.
- Drilldown: selected sector's tasks ordered by `task_no` with their latest-period
  progress lines (`latest_period` per task; lines ordered by `line_no`).
- Percent values come from stored (recomputed-at-import) fields; the UI never
  recalculates plan/actual math beyond the lines_done/lines_total ratio.

## Styling

Reuse existing `public/css/portal.css` classes wherever they fit (`dp-task`,
`dp-task-top`, `task-strip`, `task-foot`, `chip`, `progress`, `dp-fact`, `muted`).
New CSS only for: the sector card grid, active-card highlight, expandable indicator
rows, and the standalone page header. Added by hand to `public/css/portal.css`
(hand-maintained file — no build step). Cyrillic Uzbek labels throughout.

## Testing

Pest feature tests (Livewire + HTTP):
- `/sectors` renders: 17 cards in sort order, summary strip totals match seeded/
  imported data.
- All-in_progress state (current reality): cards dimmed, «Маълумот кутилмоқда», no
  percent shown.
- Selecting a sector loads its tasks with correct chips/strip values (fixture import
  via the phase-1 `SectorWorkbookBuilder` + `import:sector-tasks`).
- Multi-line task expansion returns indicator rows of the latest period only.
- Home page contains the entry link to `/sectors`.

## Out of scope

Period switcher / history charts (single period exists today — revisit when monthly
files accumulate), filters and sorting controls, region sidebar integration, export.
