# Industry Page Redesign

**Date:** 2026-05-22
**Topic:** Redesign the industry KPI panel to remove the hero card and mini-button, switch to a stacked full-width layout, unify driver-card coloring to blue, and bump fonts — matching the current dashboard visual language.

## Goal

When the macro module's KPI is **Саноат (industry)**, the dashboard renders
`backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php` with
`$showIndustryDrivers = true`. Redesign that branch to:

1. Remove the `.macro-hero-card` (big green year-growth hero).
2. Remove the `mini-button` ("Саноат деталларига ўтиш ›").
3. Switch from the 2-column `with-side` split to **stacked Layout A**.
4. Unify the 3 industry-driver cards to a single **blue** accent.
5. Increase font sizes to match the polished scoreline / period-row / module-card style.

## Current state

`macro-growth.blade.php`:

- `$showIndustryDrivers === true` → `<section class="macro-growth-panel with-side">`
  containing:
  - `.macro-main-panel` — `.macro-section-title` + `.macro-hero-card`
    (`.macro-hero-copy`) + `.macro-period-grid` of 4 `.macro-period-card`.
  - `.industry-driver-panel` aside — `.industry-driver-head` + 3
    `.industry-driver-card` + a `mini-button`.
- `$showIndustryDrivers === false` → `.macro-period-row` of 4
  `.macro-period-cell` (the already-polished component with blue separators).

Confirmed: `macro-section-title`, `macro-main-panel`, `macro-hero-card`,
`macro-hero-copy`, `macro-period-grid`, `macro-period-card`, `macro-period-head`,
`macro-mini-bar` are referenced **only** in `macro-growth.blade.php`. Once the
industry branch stops using them they are fully dead CSS.

## Target — Layout A (stacked, full width)

Industry branch markup:

```
<section class="macro-growth-panel">
  <div class="macro-period-row"> 4 × macro-period-cell </div>
  <aside class="industry-driver-panel">
    <div class="industry-driver-head"> strong + info-dot </div>
    <div class="industry-driver-list"> 3 × industry-driver-card </div>
  </aside>
</section>
```

- The period row uses the **exact same markup** as the non-industry branch
  (4 `$macroPeriods` → `.macro-period-cell` with `__label` / `__value` /
  `__state`). Therefore the `@if($showIndustryDrivers)` no longer forks the
  period markup — the `.macro-period-row` renders unconditionally, and only the
  `.industry-driver-panel` `<aside>` stays inside the `@if`.
- The driver cards remain `<a>` links to the districts page (unchanged hrefs)
  and keep the `.industry-driver-arrow`.
- Removed from markup: `.macro-main-panel` wrapper, `.macro-section-title`,
  `.macro-hero-card` / `.macro-hero-copy`, `.macro-period-grid` /
  `.macro-period-card` / `.macro-mini-bar`, the `mini-button`, and the
  `with-side` / `solo` modifier classes.

## CSS changes — `backend/public/css/portal.css`

### Layout

- Remove `.macro-layout-card .macro-growth-panel.with-side` rule. The base
  `.macro-layout-card .macro-growth-panel` (single column, `gap: 16px`) already
  produces the stacked layout.

### Driver panel

- `.industry-driver-panel` — `border-color: var(--blue)` (matches the
  blue-border card language used elsewhere).
- `.industry-driver-list` — `grid-template-columns: repeat(3, minmax(0, 1fr))`
  (was a vertical stack).
- `.industry-driver-head strong` — font-size `18px` → `22px`.

### Driver cards — blue-unified

- `.driver-icon` — single blue treatment: `color: var(--blue)`,
  `background: var(--blue-soft)`. Remove the `.driver-icon.green` /
  `.driver-icon.blue` / `.driver-icon.orange` variant rules.
- `.industry-driver-metric strong` — `color: var(--blue)`. Remove the
  `.industry-driver-card.green .industry-driver-metric strong` and
  `.industry-driver-card.orange .industry-driver-metric strong` overrides.
- The `cls` value (`green`/`blue`/`orange`) still comes from
  `DashboardCatalog::industryDrivers()`; it simply no longer has matching CSS.
  No PHP change needed.

### Font bumps (driver card)

| Selector | From | To |
| --- | --- | --- |
| `.industry-driver-title strong` | 16px | 19px |
| `.industry-driver-title span` | 12.5px | 14px |
| `.industry-driver-metric span` | 11.5px | 13px |
| `.industry-driver-metric strong` | 18px | 26px |
| `.industry-driver-metric small` | 10.8px | 12.5px |

### Dead CSS removal

Delete (all confirmed unreferenced after the markup change):

- `.macro-hero-card`, `.macro-hero-copy` (+ its `span` / `strong` / `small`).
- `.macro-section-title` (+ its `strong` / `span`).
- `.macro-layout-card .macro-period-grid`.
- `.macro-layout-card .macro-period-card` (+ `.actual`, `strong`,
  `.actual strong`).
- `.macro-layout-card .macro-period-head`.
- `.macro-layout-card .macro-mini-bar`.
- `.industry-driver-panel .mini-button`.
- The responsive `.macro-hero-card { … }` rule in the narrow-width media block.

Keep `.mini-button` base rules — still used by other pages (profile, districts,
tasks panels).

### Responsive

- Add to the existing narrow-width media block:
  `.industry-driver-list { grid-template-columns: 1fr; }` so the 3-up driver
  row collapses to a single column on narrow screens.

## Out of scope

- Non-industry macro KPI panels (ЯҲМ, agriculture, construction, services) —
  unchanged; they already render `.macro-period-row`.
- `.macro-period-row` / `.macro-period-cell` styling — already polished,
  untouched.
- `DashboardCatalog::industryDrivers()` PHP — unchanged.

## Verification

- Dashboard → macro module → select **Саноат** KPI: no hero card, no
  mini-button; full-width period row on top; 3 blue driver cards in one row
  below.
- Select another macro KPI (e.g. **ЯҲМ**): its period row is unchanged.
- Each driver card still links to the districts page.
- Narrow the viewport: driver row collapses to one column; period row collapses
  per its existing responsive rule.
- Hard refresh (`Ctrl+F5`) — `portal.css` served raw, no build.

## Open questions

None.
