# Header / Sidebar Relayout — Design

**Date:** 2026-05-21
**Scope:** Application chrome (frame) only — `backend/` Laravel app.

## Problem

The current chrome puts a full-width dark gradient header across the very top,
with the sidebar starting *below* it. The user wants a modern dashboard frame:
a full-height sidebar and a smaller, white header that sits only over the
content area.

## Goal

Restructure the app frame so:

1. The sidebar runs full height (top of viewport → bottom), filling the
   top-left corner the header currently occupies.
2. The header no longer spans full width — it spans only the content column
   (everything right of the sidebar).
3. The header background becomes white with a clean border below
   (hairline rule + soft shadow).

## Final layout

```
┌──────────────┬─────────────────────────────────────┐
│ Бошқарув     │ CERR · Андижон вилояти              │ ← white header,
│ маркази      │        мониторинг платформаси        │   border below
│              ├─────────────────────────────────────┤
│ ▸ KPI        │                                     │
│   Топшириқлар│           main content              │
│   Туманлар   │                                     │
│ [▾ Андижон]  │                                     │
└──────────────┴─────────────────────────────────────┘
  sidebar full-height
```

## Design

### Shell (CSS Grid)

`<body>` becomes a 2-column grid: `grid-template-columns: var(--nav-w) minmax(0, 1fr)`,
`min-height: 100vh`. Two grid children:

- `.sidebar` — column 1. Stretches the full grid-row height.
- `.content-col` — column 2. A flex column holding `<header>` + `<main>`.

`app.blade.php` is restructured: the `<header>` moves *inside* `.content-col`,
no longer a direct child of `<body>`. The old `.shell` wrapper is removed.

### Header

- White background; spans only `.content-col` width.
- Height ~62px — thin bar; holds one line.
- Content: the CERR brand mark + the brand title
  `Андижон вилояти мониторинг платформаси` on one line.
- The old subtitle line (`KPI · туманлар · ижро мониторинги`) is dropped.
- Border below: 1px `--line` rule plus a soft low-opacity shadow.
- `position: sticky; top: 0` so it stays as content scrolls.

### Sidebar

- Full height, starting at the very top of the viewport.
- Dark styling **unchanged** — same gradient/colors, only repositioned.
- Top: `Бошқарув маркази` label. Then the 3 nav buttons. Region switcher
  pinned at the bottom (`margin-top: auto`, as today).
- `position: sticky; top: 0; height: 100vh`.

### Responsive

Below the existing mobile breakpoint (~760px) the grid collapses to a single
column: sidebar becomes a horizontal bar on top, header and main stack beneath.
Existing media-query rules for `.shell` / `.mast` / `.brand` are updated to the
new class names.

## Files affected

- `backend/resources/views/layouts/app.blade.php` — restructure markup
  (sidebar + `.content-col` > header + main).
- `backend/public/css/portal.css` — replace `.topbar` / `.mast` / `.brand` /
  `.shell` / `.sidebar` / `.main` rules and their responsive overrides.

## Out of scope

- Sidebar internals (nav buttons, region switcher styling).
- Page content, dashboard components, colors elsewhere.
- The other 4 pages' content (they inherit the new frame automatically).

## Verification

After implementation, run the app and capture Edge headless screenshots at
desktop and narrow widths; confirm: full-height sidebar, white content-width
header with border, no horizontal overflow, nav/region switcher intact.
