# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

The Andijan/regional monitoring portal ("Худудлар мониторинги портали") in two generations:

1. **`backend/` — the real application (work here).** Laravel 12 + Livewire 3, PostgreSQL. All new features go here.
2. **`index.html` — the legacy static prototype (v7).** A single self-contained HTML file with inline data. Kept for reference; do not modify unless explicitly asked.

## Backend (`backend/`)

### How to run / develop

```powershell
cd backend
composer setup        # first time: install, .env, key, migrate, npm build
composer dev          # serve + queue + logs + vite concurrently
# or just:
php artisan serve
```

Database: PostgreSQL. Dev DB per `.env`; test DB `hududlar_monitoringi_test` (configured in `phpunit.xml`).
Seed reference data (regions, districts, modules, indicators, reporting year):

```powershell
php artisan db:seed
```

### Tests

Pest 3 + PHPUnit 11 on PostgreSQL (a running local Postgres is required).

```powershell
php artisan test                       # full suite (~10 min; needs memory_limit=2G, set in phpunit.xml)
php artisan test --filter=SomeTest     # single test/class
```

Conventions: Feature tests start with `uses(RefreshDatabase::class);` (not bound globally); seeders run explicitly via `$this->seed()`; Unit tests are pure `expect()` closures. Custom expectation `toBeNumericallyClose()` exists in `tests/Pest.php`. The full suite is expected to be green.

### Architecture

Pages are Livewire components, one per nav item, mounted from `resources/views/pages/*.blade.php`:

| Route | Livewire component | Purpose |
| --- | --- | --- |
| `/` | `StarterController` → `pages/start` | Front door: module chooser (Вилоятлар · Тармоқлар) with cached aggregates |
| `/regions` | `HomeController@index` → `pages/home` | Country map with per-region task execution; region click → `/region/{code}` → dashboard |
| `/dashboard` | `KpiDashboard` (+ `Dashboard/*` panels) | KPI overview per region |
| `/tasks` | `TasksBoard` | Task monitoring board (plan/actual/% per task) |
| `/districts` | `DistrictsPage` | District comparison (map + table) |
| `/profile` | `RegionProfile` | District drilldown (incl. "Туман топшириқлари" panel) |
| `/execution` | `ExecutionPage` | Execution monitoring |
| `/sectors` | `SectorsDashboard` | Sector enterprises (тармоқлар) guarantee-letter tasks — standalone national page, no region scoping; entered from the starter page only. Card click navigates to `/sectors/{code}` (`SectorDetail`) — full detail page with a sticky left rail (identity, ring, status filters) and wide task list; no drawer |
| `/roadmaps` | `RoadmapsPage` | Water-management road-map measures (сув хўжалиги йўл харитаси) for the session region (direct entry with no region chosen activates the first loaded region), with monitoring: per-measure indicator lines (plan/actual/%), derived status (Бажарилди · Бажарилмоқда · Бажарилмаган), ring gauges and deadline chips; rail filters by status/section/district, search; URL-access only (no sidebar link yet) |

The active region is session state (`App\Support\CurrentRegion`, default 1703 = Andijan, switchable via `RegionSwitcher`). Region/district reference data uses SOATO codes.

Styling: `public/css/portal.css` is hand-maintained (not generated from `resources/css/app.css`; no `npm run build`). When changing UI, prefer reusing existing classes; new page styles get their own namespaced block appended to the file (e.g. `sec-`, `wr-`).

### Data import pipelines

Four separate pipelines, all Artisan commands (the first three use PhpSpreadsheet; the fourth reads docx XML directly for the registry and PhpSpreadsheet for its monitoring xlsx — the repo's only xlsx *writer*):

1. **Indicator facts (KPI dashboard data):** `import:region` / `import:promote` / `import:all-regions` — staging→promote pipeline reading the per-region workbooks under `data/<region>/`.
2. **Task monitoring (tasks board + district tasks):** `import:task-progress --period=2026-Q1` — reads the all-regions workbook `data/tasks/Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx` sent monthly by the partner organisation. Operator runbook: **`backend/docs/task-import.md`** (workflow, options, one-time legacy cleanup, known limitations). Key behaviors: idempotent per-period upserts, history kept in `task_progress`, binary done/open status (≥100% of plan), districts linked from the Ижрочи column, refuses files whose region columns shifted/reordered (all 14 verified).
3. **Sector tasks (тармоқ корхоналари guarantee letters):** `import:sector-tasks --period=2026-07` — reads the all-sectors workbook `data/sectors/Вазифалар_2026_тармоқлар_кесимида.xlsx` (17 sheets, one per enterprise). Runbook: `backend/docs/sector-task-import.md`. `sector-tasks:recompute` rebuilds statuses without re-import.
4. **Water road maps (сув хўжалиги йўл хариталари):** two stages. *Registry:* `import:roadmap --region=1733` — reads the per-region `.docx` under `data/Сув хўжалиги бўйича йўл хариталар/` (ZipArchive + DOM, header-text keyed; only Хоразм imported so far); upserts measures **by position**, so ids and the monitoring rows survive a re-import (dry run previews removals/retitles). Runbook: `backend/docs/roadmap-import.md`. *Monitoring:* `roadmap:template --region=1733 --period=2026-09` writes the xlsx the region fills (indicator lines: label/unit/plan, yellow «Амалда»/«Изоҳ», hidden key column) → `import:roadmap-progress --file=… [--period=]` reads it back (line definitions + the period's actuals) → `roadmaps:recompute` rebuilds statuses without re-import. Runbook: `backend/docs/roadmap-monitoring.md`.

### Conceptual model (drives UX decisions)

The portal answers: *"Кафолат хатидаги ваъдалар бажариляптими?"* — promise (plan) vs fact (actual), drilled from macro KPI → driver → district → task. Changes that hide the plan-vs-actual comparison or the driver chain regress the core concept.

## Legacy prototype (`index.html`)

Single ~900KB HTML file, all data inline (one giant `DATA` line — treat as data, not code), state-based routing, no build step. Open directly or `python -m http.server 8000`. Only touch when explicitly asked; the HTML/CSS pitfall to preserve: clickable cards use `<div role="button">`, never `<button>` with block children (breaks Edge rendering).

## Conventions

- **UI language:** Cyrillic Uzbek throughout. Do not translate labels to Latin script or English unless asked.
- **`data/` stays local and out of git** (raw workbooks from regions + the partner tasks file). Never commit them.
- The repo stays private until source documents are cleared for sharing — no CI that publishes artifacts.
- Commits: Conventional Commits style (`feat(tasks): …`, `fix(import): …`).
