# Module Tabs Restyle — Design

**Date:** 2026-05-21
**Scope:** The dashboard module-tabs strip only — `backend/` Laravel app.

## Problem

The dashboard module tabs (`.dashboard-module-tabs` / `.module-tab`) currently
render each tab as a bordered card with a module icon, a label, a count, and a
thin progress bar. The user supplied a reference image and wants the tabs
restyled to match it: a white rounded "track" containing equal-width pill tabs,
each showing a bold label over a `(done/total)` count, with the active tab
filled by a blue gradient. No icon, no progress bar.

## Goal

Restyle the module tabs to match the reference image:

- A single white rounded container ("track") wraps all tabs, with a soft shadow.
- Each tab is a rounded pill: light grey when inactive, blue-gradient when active.
- Each tab shows two centered lines — label on top, `(0/32)` count below.
- The module icon and the per-tab progress bar are removed.

## Design

### Markup — `kpi-module-tabs.blade.php`

- Remove the `<span class="module-tab__icon">…</span>` block.
- Remove the `<span class="module-tab__bar">…</span>` block.
- Keep `<span class="module-tab__body">` containing `<strong>` (label) and
  `<span class="module-tab__count">`.
- The count text becomes parenthesised: `({{ $done }}/{{ $total }})`.
- Remove the now-unused `$pct` and `$iconName` PHP variables from the `@php`
  block. `$counts`, `$total`, `$done` stay (count still needs them).
- The `wire:click="selectModule(...)"` and `active` class logic are unchanged.

### CSS — `portal.css`

`.dashboard-module-tabs` (the track):
- White background, ~20px border-radius, 1px `--line` border, soft shadow.
- `display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px;`
- ~6px internal padding so the pills inset from the track edge.

`.module-tab` (the pill):
- `display: grid` (or flex column), content centered both axes.
- ~15px border-radius, no border, light grey fill (`#eef0f3`).
- ~11px vertical / ~12px horizontal padding.
- Transition on background.
- Remove the old grid-template-columns/rows, the icon column, and the bar row.

`.module-tab__body`:
- Column layout, centered, small gap between label and count.

`.module-tab__body strong` (label):
- ~13.5px, weight 700, ink color, centered. Allow wrapping (no forced ellipsis)
  so long labels stay readable inside the pill.

`.module-tab__count`:
- ~11.5px, weight 700, muted color, tabular numerals.

`.module-tab.active`:
- Background `var(--accent-grad)` (blue gradient).
- Label white; count translucent white.
- Soft blue-tinted shadow.

`.module-tab:hover` (inactive):
- Slightly darker grey fill.

Delete the `.module-tab__icon`, `.module-tab__icon svg`, `.module-tab__bar`,
`.module-tab__bar i`, `.module-tab.active .module-tab__icon`,
`.module-tab.active .module-tab__bar`, and `.module-tab__bar:has(...)` rules —
those selectors no longer exist in the markup.

### Responsive

Keep the existing breakpoints. The `@media (max-width: 1180px)` rule already
sets `.dashboard-module-tabs` to a 3-column grid; the `@media (max-width: 760px)`
rule sets it to 1 column. Both continue to work — the white track simply wraps
its rows. No media-query changes required.

## Out of scope

- Tab behaviour / routing (`selectModule`) — unchanged.
- Any other dashboard component.

## Verification

Render the app and capture an Edge headless screenshot of `/dashboard`; confirm
the tabs match the reference: white track, grey pills, blue-gradient active tab,
two-line label + count, no icons, no progress bar. Check the ≤1180px and ≤760px
widths collapse cleanly.
