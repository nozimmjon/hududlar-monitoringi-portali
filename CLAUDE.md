# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

A prototype of the Andijan regional monitoring portal ("Худудлар мониторинги портали"). The deliverable is a **single static HTML page** (`index.html` at the repo root) with all data embedded inline. There is no backend, no build step, no bundler, no test suite, and no third-party JS dependencies — only Inter loaded from Google Fonts.

The current generation is **v7** (`<title>… · v7</title>`). Older versions live in branches/history; the repo intentionally keeps only the current prototype on disk.

## How to run / develop

There is no build. Open `index.html` in a browser, or serve the directory statically:

```powershell
python -m http.server 8000
```

Then open `http://localhost:8000/`. There are no install, lint, or test commands.

A static server (rather than `file://`) is needed only if/when `districts.json` is wired in via `fetch` — currently it is not, so opening the file directly also works.

## Architecture

### Single-file structure

`index.html` (~6900 lines) contains:

- CSS in one `<style>` block at the top (design tokens via CSS custom properties on `:root`).
- Markup for the topbar, sidebar nav (4 buttons), shared `page-head` toolbar, and 5 empty `<section id="…Page">` containers that JS fills in.
- One JS block at the bottom. **Line 3749 is a single ~394KB line containing the entire embedded `DATA` object** (regional macro/budget/foreign_investment/export/employment/food_balance + per-district rows). Treat that line as data, not code — do not try to read or edit it inline; modify via the source workbooks under `data/` and re-derive, or use targeted Edits with unique surrounding context.

### Pages and routing

State-based, **not hash-routed**. `state.page` drives a `render()` function (~line 6840) that toggles `.hidden` on five sections:

| `state.page`  | Section id        | Renderer                       | Purpose                                                          |
| ------------- | ----------------- | ------------------------------ | ---------------------------------------------------------------- |
| `dashboard`   | `#dashboardPage`  | `renderDashboard` (~4632)      | KPI overview — entry screen                                      |
| `tasks`       | `#tasksPage`      | `renderTasksPage` (~5656)      | Guarantee-letter task board                                      |
| `districts`   | `#districtsPage`  | `renderDistrictsPage` (~6445)  | 14-region / district comparison views                            |
| `profile`     | `#profilePage`    | `renderProfilePage` (~6539)    | District drilldown profile (entered via `data-page-jump` jumps)  |
| `execution`   | `#executionPage`  | `renderExecutionPage` (~6694)  | Ижро мониторинги — execution monitoring                          |

Navigation: `.nav-btn[data-page="…"]` clicks set `state.page` and call `render()`. Cross-page jumps inside content use `[data-page-jump]` buttons. Every `render()` re-runs all five page renderers but only the active section is visible.

The shared toolbar (`#periodTabs`, `#sectorFilter`, `#searchBox`) is shown/hidden per-page inside `render()` — see the `.toggle("hidden", […].includes(state.page))` lines around 6845-6847 when adding a new page or repurposing controls.

### Conceptual model (drives all UX decisions)

The portal answers one question: *"Кафолат хатидаги ваъдалар бажариляптими?"* (Are the promises in the guarantee letter being kept?). Every screen surfaces the chain:

```
Guarantee letter (macro KPI promise)
  → Driver KPI (industry, investment, localization, …)
  → District contribution (16 districts)
  → Task (executor, deadline, status)
  → Result feeds back to macro
```

Tile/page changes that break the promise vs fact comparison or hide the driver chain regress the core concept.

### Data sources

- `data/` (gitignored) — raw `.xlsx` and `.docx` source workbooks for **all 14 regions of Uzbekistan**, organized by region folder (`1. Қорақалпоғистон Республикаси/` … `14. Тошкент ш/`). The Andijan folder (`2. Андижон/`) is what the current prototype is derived from.
- `districts.json` — real GeoJSON (FeatureCollection of MultiPolygons) for all 14 regions, at the repo root. **Currently unreferenced by `index.html`** — staged for future map work to replace any synthesized hex layout. When wiring it in, prefer `fetch("./districts.json")` and serve via a local HTTP server.
- The DATA blob on line 3749 of `index.html` is hand-derived from the Andijan workbooks. There is no automated pipeline anymore; updating data means editing that line directly (or a small helper you write ad hoc).

## HTML/CSS rendering pitfall

A `<button>` containing block-level `<div>` children is HTML-invalid and breaks Edge headless rendering (KPI grid collapses to 1 column). Use `<div role="button" tabindex="0">` for clickable card patterns. The codebase already follows this — preserve it when adding new clickable cards.

## What the prototype intentionally does NOT have

These are documented compromises, not bugs:

- A real interactive map (the GeoJSON is present but not wired up; current views use grids/tables).
- Real workflow status / executor names (synthesized; source data has neither).
- Real per-region macro data for the 13 non-Andijan regions in `index.html` (mocked; only Andijan is fully derived from `data/`).
- Local Inter font (loaded from Google Fonts).
- PDF/Excel export (print CSS only).

When the user asks for these, treat them as net-new work, not regressions.

## Conventions

- Cyrillic Uzbek throughout the UI; do not translate labels to Latin script or English unless asked.
- Single-file HTML is a constraint, not an accident. Do not split assets out (no separate CSS/JS files, no bundler, no npm) without explicit user direction.
- The repo is expected to stay private until source documents are cleared for sharing — do not add CI that publishes artifacts.
- `data/` content stays local and out of git. Don't commit raw workbooks.
