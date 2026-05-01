# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

A prototype of the Andijan regional monitoring portal ("Худудлар мониторинги портали"). The deliverable is a **single-file HTML prototype** generated from real source workbooks (Excel/Word) by Python builder scripts. There is no backend, no app framework, no test suite — the entire output is one static HTML page with embedded JSON.

The current generation is **v7**; older versions (v6, v5, v4, mockups, "claude/chatpro" early drafts) are kept under `platform prototypes/_archive/` and `platform prototypes/claude/`, `chatpro/`, etc. for reasoning audit. Do not modify archived versions.

## Common commands

Regenerate the latest prototype (run from repo root):

```powershell
python tools/generate_andijon_integrated_platform_v7.py
```

Or with the bundled Codex runtime:

```powershell
C:\Users\n.ortiqov\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe tools\generate_andijon_integrated_platform_v7.py
```

Then open `platform prototypes/andijon_integrated_platform_v7.html` in a browser. There are no install/build/lint/test steps — `openpyxl` is the only third-party Python dependency.

## Architecture

### Pipeline

```
data_source/ (xlsx + docx, gitignored)
        │
        │  extract_andijon_pilot.py            (one-time extraction)
        ▼
platform prototypes/andijon_full_pilot_assets/andijon_full_pilot_data.json
        │
        │  generate_andijon_integrated_platform_v7.py
        │   + hand-coded promise targets from the guarantee letter
        │   + synthesized workflow/executor data (real source lacks these fields)
        ▼
platform prototypes/andijon_integrated_platform_v7.html  (single self-contained file)
```

`data_source/` is **gitignored** — raw DOCX/XLSX inputs stay local. The extracted JSON (`andijon_full_pilot_data.json`) is committed and is what the v7 builder actually reads. If the JSON is missing, run `tools/extract_andijon_pilot.py` first; it requires the source workbooks to be present locally.

### Conceptual model (drives all UX decisions)

The portal answers one question: *"Кафолат хатидаги ваъдалар бажариляптими?"* (Are the promises in the guarantee letter being kept?). Every screen surfaces the chain:

```
Guarantee letter (macro KPI promise)
  → Driver KPI (industry, investment, localization, …)
  → District contribution (16 districts)
  → Task (executor, deadline, status)
  → Result feeds back to macro
```

When changing v7, preserve this **promise ↔ execution** linkage. Tile/page changes that break the promise vs fact comparison or hide the driver chain regress the core concept.

### v7 prototype = three pages, single HTML, hash-routed

1. **Ваъда vs Ижро** (`#promise`, default) — 10 macro KPI tiles, each with promise (H1 + year), fact, delta, sparkline, quality badge, status accent. Click → side-drawer with related tasks. Hex map of 16 districts + 14-region comparative strip below.
2. **Туманлар** (`#districts`) — metric switcher (Саноат/Бюджет/Инвестиция/Бандлик), recolorable hex map, district table. Row click → side-drawer with 6-cell district profile.
3. **Кафолат хати топшириқлари** (`#tasks`) — letter banner, summary cards, sector filter chips, search, 4-column kanban (`assigned → in progress → done` + `blocked`).

Page switching is JS class-toggle on `body`; the side-drawer is shared across pages.

### Builder script (`tools/generate_andijon_integrated_platform_v7.py`)

~750 lines, single-file generator. Key functions:

- `build_promise_kpis(data)` — produces the 10 KPI tiles (5 macro + budget + FI + export + unemployment + poverty) by combining JSON data with hand-coded promise targets.
- `kpi_status(promise_pct, fact_pct, lower_is_better)` — green if delta ≥ +0.1pp, red if ≤ −0.5pp, amber otherwise. The `lower_is_better` flag flips the sign convention for inflation/unemployment/poverty.
- `assign_workflow_state(idx, total)` — deterministically distributes 61 tasks across 4 stages (≈35/35/25/5%) with rotating placeholder executor names. The source xlsx has no executor column, so this is synthesized.
- `build_districts_payload(data)` — flattens nested district structure; **must filter the string `"холи ҳудуд"`** from `poverty_h1` (it is a sentinel, not a number).

`SECTOR_BY_KPI`, `MODULE_DEFS`, and `KPI_TO_MODULE` at the top of the file map KPI keys → guarantee-letter sectors / source xlsx modules. Adding a new KPI means updating all three.

### Data shape gotchas (learned from v7 build)

When working with `andijon_full_pilot_data.json`:

- `regional.foreign_investment` is a **dict**, not a list.
- `regional.export` values are in **минг $** (thousands), not млн $ — convert when displaying as million.
- Macro indicator keys are full Cyrillic strings (e.g. `"Қишлоқ хўжалиги маҳсулотлари"`, not `"Қишлоқ хўжалиги"`); look them up via the explicit `promise_key` parameter rather than substring matching.
- For the districts page, investment uses `h1_pct` directly — it is already an absorption percentage, **not** growth, so do not apply `pct - 100`.

### HTML/CSS rendering pitfall

A `<button>` element containing block-level `<div>` children is HTML-invalid and breaks Edge headless rendering (KPI grid collapses to 1 column). Use `<div role="button" tabindex="0">` for clickable card patterns.

## What the prototype intentionally does NOT have

These are documented compromises, not bugs:

- Real Andijon SVG geography (uses hex grid via `DIST_LAYOUT`).
- Real workflow status / executor names (synthesized; source data has neither).
- 13 other regions' real data (mocked GDP-growth values; only Andijon was extracted).
- Local Inter font (loaded from Google Fonts).
- PDF/Excel export (print CSS only).

When the user asks for these, treat them as net-new work, not regressions.

## Conventions

- Cyrillic Uzbek throughout the UI; do not translate labels to Latin script or English unless asked.
- v6 and earlier are **frozen**. New work goes into v7 unless the user explicitly asks to fork a new version.
- The repo expects to stay private until source documents are cleared for sharing — do not add CI that publishes artifacts.
- Single-file HTML is a constraint, not an accident. Do not split assets out (no separate CSS/JS files, no bundler, no npm) without explicit user direction.
