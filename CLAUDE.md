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
| `/dashboard` | `KpiDashboard` (+ `Dashboard/*` panels) | KPI overview per region |
| `/tasks` | `TasksBoard` | Task monitoring board (plan/actual/% per task) |
| `/districts` | `DistrictsPage` | District comparison (map + table) |
| `/profile` | `RegionProfile` | District drilldown (incl. "Туман топшириқлари" panel) |
| `/execution` | `ExecutionPage` | Execution monitoring |

The active region is session state (`App\Support\CurrentRegion`, default 1703 = Andijan, switchable via `RegionSwitcher`). Region/district reference data uses SOATO codes.

Styling: prebuilt `public/css/portal.css` (built via Vite/Tailwind from `resources/css/app.css`). When changing UI, prefer reusing existing classes; new CSS requires `npm run build`.

### Data import pipelines

Two separate pipelines, both Artisan commands using PhpSpreadsheet:

1. **Indicator facts (KPI dashboard data):** `import:region` / `import:promote` / `import:all-regions` — staging→promote pipeline reading the per-region workbooks under `data/<region>/`.
2. **Task monitoring (tasks board + district tasks):** `import:task-progress --period=2026-Q1` — reads the all-regions workbook `data/tasks/Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx` sent monthly by the partner organisation. Operator runbook: **`backend/docs/task-import.md`** (workflow, options, one-time legacy cleanup, known limitations). Key behaviors: idempotent per-period upserts, history kept in `task_progress`, binary done/open status (≥100% of plan), districts linked from the Ижрочи column, refuses files whose region columns shifted/reordered (all 14 verified).

### Conceptual model (drives UX decisions)

The portal answers: *"Кафолат хатидаги ваъдалар бажариляптими?"* — promise (plan) vs fact (actual), drilled from macro KPI → driver → district → task. Changes that hide the plan-vs-actual comparison or the driver chain regress the core concept.

## Legacy prototype (`index.html`)

Single ~900KB HTML file, all data inline (one giant `DATA` line — treat as data, not code), state-based routing, no build step. Open directly or `python -m http.server 8000`. Only touch when explicitly asked; the HTML/CSS pitfall to preserve: clickable cards use `<div role="button">`, never `<button>` with block children (breaks Edge rendering).

## Conventions

- **UI language:** Cyrillic Uzbek throughout. Do not translate labels to Latin script or English unless asked.
- **`data/` stays local and out of git** (raw workbooks from regions + the partner tasks file). Never commit them.
- The repo stays private until source documents are cleared for sharing — no CI that publishes artifacts.
- Commits: Conventional Commits style (`feat(tasks): …`, `fix(import): …`).
