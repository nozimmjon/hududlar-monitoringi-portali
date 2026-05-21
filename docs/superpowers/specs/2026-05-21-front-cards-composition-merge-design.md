# Front Macro Cards / Composition Merge — Design

**Date:** 2026-05-21
**Scope:** Dashboard macro view — `backend/` Laravel app.

## Problem

On the dashboard macro view two components show the same data:

- `kpi-front-cards` renders the 5 macro KPI selector cards (ЯҲМ + industry,
  agriculture, construction, services). It already fetches `period='year'`
  facts but renders only the label — no value.
- `macro-composition` renders the same 4 sub-goals again (industry,
  agriculture, construction, services) as value cards inside the growth panel
  (`macro-comp-inline`).

The two pull identical `period='year'` `IndicatorFact` rows. The composition
panel is redundant once the front cards show their values.

## Goal

1. Display the yearly growth value on each macro front-KPI card.
2. Make the front-KPI cards beautiful — a clean card with icon, name, and a
   prominent yearly value.
3. Remove the redundant `macro-comp-inline` render and the now-unused
   `MacroComposition` component.

## Design

### `kpi-front-cards.blade.php` — card redesign

Each `.front-kpi` button is restructured to show:

- The icon badge (existing `kpi-icon`).
- The KPI short name (`$ind->label_short`).
- The **yearly growth value** — `DashboardCatalog::growthValue($fact->growth_pct)`.
- A small note line: `йиллик ўсиш`.

`$fact` is `$facts->get($code)` — already provided by `KpiFrontCards::render()`
(the `period='year'` query). No PHP/query change.

The value renders only when `$fact` exists and `$fact->growth_pct !== null`.
For the macro module all 5 KPIs have `growth_pct`, so all show a value. Other
modules where `growth_pct` is null degrade gracefully — the value/note simply
do not render; the card still shows icon + name.

`wire:click="selectKpi('{{ $code }}')"`, the `active` class, and the `parent`
class for `grp` are all preserved — the cards remain the KPI selector.

### `portal.css` — `.front-kpi` restyle

Restyle `.front-kpi` and the `.front-kpis.module-kpis.macro-layout` variant so
the card cleanly presents icon + name + large value + note, with a clear
hover and active/selected state. No new design tokens — use existing
`--ink`, `--muted`, `--blue`, `--line`, `--accent-grad`, radius and shadow
tokens.

### `macro-growth.blade.php` — drop the inline composition

Remove this block from the `@else` (solo) branch:

```blade
@if($kpi === 'grp')
    <livewire:dashboard.macro-composition :key="'macro-comp-inline'" />
@endif
```

The `@php` block, the `macro-hero-strip` markup, and the `with-side` branch
are unchanged.

### Delete the unused `MacroComposition` component

`macro-comp-inline` is the only render of `macro-composition` (verified — no
other `<livewire:dashboard.macro-composition` reference exists). After step 3
the component is dead. Delete:

- `backend/app/Livewire/Dashboard/MacroComposition.php`
- `backend/resources/views/livewire/dashboard/macro-composition.blade.php`

The CSS rules for `.macro-composition-*` are left in place as harmless dead
rules. They must NOT be bulk-removed: the same file's `.composition-grid` and
`.component-card` classes are still used by other panels
(`inflation-details`, `poverty-details`), and a careless sweep risks them.

## Out of scope

- The `macro-hero-strip` (the +7,8% panel) — unchanged.
- The KPI scoreline, module tabs, sidebar, other panels.
- Removing `.macro-composition-*` CSS.

## Verification

Render the app and capture an Edge headless screenshot of `/dashboard`
(macro module, default `grp` KPI). Confirm:

- The 5 front-KPI cards each show a yearly growth value.
- The cards look clean; the selected card is clearly marked.
- No composition dropdown appears in the growth panel.
- The hero strip is unchanged.
- Clicking a card still switches the workspace KPI.

Check the ≤1180px and ≤760px widths still collapse cleanly.
