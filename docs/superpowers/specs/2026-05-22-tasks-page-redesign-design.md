# Tasks Page Redesign

**Date:** 2026-05-22
**Topic:** Redesign the tasks page main content (`tasks-board`) to match the reference image `tasks.png`.

## Goal

Rebuild the tasks-page main content so it matches the reference image
`tasks.png` — a full-width filter bar, then a two-column body: a main column
(big status-filter boxes + task list) and a right sidebar (progress donut +
stacked summary boxes).

## Current state

`backend/resources/views/livewire/tasks-board.blade.php`:

- `.task-filter.report-filter` — 4 fields (module / indicator / status / search).
- `<details class="task-advanced-filters">` — collapsible period + district filters.
- `.task-summary-strip.execution-overview` — copy block + 3 `.exec-status-pill`
  status buttons + `.exec-progress-box` donut.
- `.task-workspace` → `.task-groups` → `.task-group` → head + `.task-list` of
  `.task-card.compact`.

`backend/app/Livewire/TasksBoard.php` — Livewire component with `#[Url]` props
`module`, `indicator`, `status`, `period`, `district`, `search`; computed
`tasks`, `moduleOptions`, `indicatorOptions`, `districtOptions`, `totals`.

## Reference image — extracted values

`tasks.png` is 1014×611 (a compressed mockup screenshot; colors approximate).

Colors sampled:

| Element | Hex |
| --- | --- |
| Blue box / donut ring | `#1754d3` |
| Green box | `#01a358` |
| Red box | `#e6302f` |
| Page background | `#f6f8fb` |
| Box number / label text | `#ffffff` |

Layout: full-width filter bar; below it a two-column grid — main column
(~73%) and sidebar (~27%).

## Target layout

```
<div>                                  (TasksBoard root)
  .task-filter                         filter bar — 4 fields, full width
  .tasks-layout                        two-column grid
    .tasks-main
      .task-stat-row                   3 big status-filter boxes
      .task-group                      list header + task cards
    .tasks-side
      .task-donut-card                 progress donut
      .task-stat-stack                 3 stacked summary boxes
```

## Components

### 1. Filter bar — `.task-filter`

- 4 fields in a full-width grid: **Йўналиш / жадвал** (`module` select),
  **KPI / топшириқ йўналиши** (`indicator` select), **Ҳолат** (`status`
  select), **Қидириш** (`search` input).
- The `<details class="task-advanced-filters">` block is **removed** from the
  markup.
- Keep existing `wire:model.live` bindings unchanged.

### 2. Big status boxes — `.task-stat-row` / `.task-stat-box`

- A row of 3 equal boxes inside `.tasks-main`.
- Each box: large white number (top-left), white label below, a circular
  translucent-white icon badge (top-right).
- Boxes: **Жами** (blue `#1754d3`, users icon) · **Бажарилди**
  (green `#01a358`, check icon) · **Бажарилмади** (red `#e6302f`, x icon).
- These **replace** the current `.exec-status-pill` buttons as the status
  filter: each box is a `<button type="button" wire:click="selectStatus(...)">`
  — Жами → `'all'`, Бажарилди → `'done'`, Бажарилмади → `'open'`.
- The box matching the current `$status` gets an `is-active` class (a ring or
  raised treatment).
- Numbers come from `$totals` (`total` / `done` / `open`).
- Per the codebase HTML pitfall, the clickable box is a
  `<button>` whose direct children are inline/`<span>` — no block `<div>`
  children.

### 3. Task list — `.task-group` / `.task-card`

- Header row: `<h3>` "{scope} топшириқлар" (scope = current
  `$shownScope`) + a grey chip with `{{ $tasks->count() }} та`.
- Each task is a `.task-card`:
  - **Number badge** — sequential `{{ $loop->iteration }}` (1, 2, 3 …),
    rendered as a small rounded badge.
  - **Title** — `<strong>{{ $task->title }}</strong>`.
  - **Meta line** — `$task->deadline_text`, the district/region scope text,
    and `$task->module?->label ?? $task->section_label` (same data as today).
  - **Chips** (right side) — a chip for kind (`KPI` / `Чора-тадбир`) and, when
    present, a blue chip for `$task->indicator->label_short`.
- Empty state: keep the current "Бу филтр бўйича топшириқ топилмади." message.

### 4. Sidebar donut — `.task-donut-card`

- A white card containing a circular progress donut.
- Ring track is blue `#1754d3`; the progress arc is dark navy (`var(--blue-2)`),
  driven by `--pct:{{ $totals['pct'] }}`. At 0% the ring reads as a full blue
  ring.
- Center: `{{ $totals['pct'] }}%` (large, dark) above the label "бажарилиш".

### 5. Sidebar summary boxes — `.task-stat-stack`

- 3 boxes stacked vertically below the donut card, same colors and icons as the
  big row (Жами blue / Бажарилди green / Бажарилмади red).
- Read-only summary (not buttons) — plain elements showing the `$totals`
  counts. The big row in the main column is the interactive filter.

## CSS

- All new styles go in `backend/public/css/portal.css`.
- Add scoped color vars in `:root`: `--task-blue: #1754d3`,
  `--task-green: #01a358`, `--task-red: #e6302f`.
- `.tasks-layout` — two-column grid, `minmax(0, 1fr)` main +
  `minmax(280px, .36fr)` sidebar, gap 16px; collapses to one column at the
  existing narrow breakpoint.
- Remove CSS rules that become dead after the markup change:
  `.task-advanced-filters`, `.task-advanced-grid`, `.task-summary-strip`,
  `.task-summary-copy`, `.task-workspace`, `.task-groups` — **only if** grep
  confirms they are unused elsewhere; the implementation plan must verify each
  before deletion.
- `.exec-status-pill` base rules stay (used by the scoreline `.execution-strip`).

## PHP — `TasksBoard.php`

- No behavioural change to data loading.
- `$period` / `$district` `#[Url]` props, `selectPeriod` / `selectDistrict`,
  and the `districtOptions` computed property are **kept** — they still filter
  via URL parameters so existing deep links from other pages keep working;
  they simply lose their UI control.
- `clearFilters()` keeps resetting `period` / `district`.

## Out of scope

- Task data model, `Task` query scopes, the chips' underlying data.
- The execution page and the dashboard scoreline (which also use
  `.exec-status-pill` / `.exec-donut`) — untouched.

## Verification

- Open the tasks page — layout matches `tasks.png`: filter bar, 3 big status
  boxes, task list, sidebar donut + 3 stacked boxes.
- Click each big status box — the list filters (all / done / open) and the
  active box is highlighted.
- A deep link such as `/tasks?district=<id>` still filters the list even
  though no district control is visible.
- Narrow the viewport — the two columns collapse to one cleanly.
- Hard refresh (`Ctrl+F5`) — `portal.css` is served raw, no build.

## Open questions

None.
