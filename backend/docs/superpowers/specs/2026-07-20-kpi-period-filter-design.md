# KPI page period filter (I ярим йиллик / Йил якуни)

2026-07-20 · approved by user in-session

## What

Topbar segmented control with two options — «I ярим йиллик» (`h1`) and «Йил якуни»
(`year`) — visible only on the KPI dashboard route. Selecting a period switches the
headline plan/амалда/% values across the KPI page. Default: `h1` (latest reported).

## Why

H1 actuals now flow into `indicator_facts` (TaskFactBridge). Users need to flip
between half-year execution and year-end targets without losing the page context.

## How

Livewire event + key remount (matches existing `module-selected`/`kpi-selected` pattern):

1. **`App\Livewire\PeriodSwitcher`** (new) — topbar component, rendered in
   `layouts/app.blade.php` inside `@if(request()->routeIs('dashboard'))`. Holds
   `public string $period` (initialized from `?period=` query), renders two buttons,
   dispatches `period-selected(period)` on click.
2. **`KpiDashboard`** — `#[Url] public string $period = 'h1'`; `#[On('period-selected')]`
   handler validating against `['h1', 'year']`; passes `:period` to children and adds
   `-{$period}` to their `:key` (remount on switch).
3. **`KpiFrontCards`** — `public string $period` prop replaces the hardcoded
   `where('period', 'year')`; card sublabel shows the period label so numbers are
   unambiguous.
4. **`KpiWorkspaceCard`** — `public string $period` prop passed through to panel blades;
   detail panels that slice `h1`/`year` (unemployment/poverty drivers) prefer the chosen
   period for their headline stat. Quarter matrix and macro growth strip keep showing
   all periods (timeline stays visible — core plan-vs-fact concept).
5. **CSS** — `.topbar-period` segmented control, hand-edited into `public/css/portal.css`.

## Out of scope

Other pages (tasks/districts/profile), m9/q1 options, persistence beyond URL param.

## Tests (TDD)

- `PeriodSwitcher` renders both options, dispatches event.
- `KpiDashboard` defaults `h1`, accepts `year`, rejects junk, syncs URL.
- `KpiFrontCards` shows `h1` facts when period=h1, `year` facts when period=year.
- Topbar shows the switcher on `/dashboard`, not on `/tasks`.
