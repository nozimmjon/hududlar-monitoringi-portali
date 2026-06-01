# Design — XLSX-driven task monitoring (all 14 regions)

Date: 2026-06-01
Status: Approved (brainstorm), pending implementation plan
Build target: **Laravel backend (`backend/`)** — the legacy static `index.html` prototype is untouched.

## 1. Problem / context

A partner organisation now supplies a single structured workbook covering **all 14 regions of Uzbekistan** with task plans and current execution:

`data/tasks/Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx`

It must **replace** the current task system. The backend already has a task subsystem built from per-region **DOCX** guarantee-letter files (`import:tasks`, `tasks` + `task_districts` tables, `TasksBoard` Livewire page, a district-tasks panel on the region profile). That DOCX source has **no plan/actual/% numbers** and the `tasks.status` column is `"Reserved for future status-tracking spec"` and never set.

This spec makes the monthly XLSX the **sole task source**, stores plan + actual + % with **history**, derives a binary **done/open** status (import-only), and surfaces it on the tasks board and the district drilldown.

### Decisions (from brainstorm)

| Decision | Choice |
|---|---|
| Region scope | Tasks feature covers all 14 regions (existing region switcher). Dashboard/districts/profile stay as-is. |
| Build target | Laravel backend only. `index.html` untouched. |
| Source of truth | XLSX **fully replaces** the DOCX importer (DOCX importer retired, kept in git history). |
| History | Keep monthly snapshots (per `report_period`). Board shows latest; history preserved for future trends. |
| Cadence | Per-task, from col J: monthly vs quarterly. |
| Status model | **Binary** `done` (≥100%) / `open`. Raw % shown, no risk tiers. |
| Status source | **Import-only.** Partner file is the single source of truth; no manual editing. |
| Tooling | Native **Artisan command** using `phpoffice/phpspreadsheet ^5.7` (already installed). No separate Python. |

## 2. Source workbook structure

One sheet, `A3:BP264` (≈264 rows × 68 cols), heavily merged (1225 merge ranges).

**Descriptor columns (A–L), merged across the header rows 3–4:**

| Col | Header | Meaning |
|---|---|---|
| A | № | Task number (integer). Also holds section markers. |
| B | № | duplicate № |
| C | Кўрсаткич номи | Task / goal title (used as `tasks.title`) |
| D | Индикатор номи | Measured indicator line (→ `task_progress.metric_label`) |
| E | Ўлчов бирлиги | Unit (дона, фоиз, млрд сўм, …) |
| F | Муддати | Deadline text → period_code |
| G | Ижрочи | Generic executor (ignore; use per-region Ижрочи) |
| H | Топшириқ тури | Kind: `KPI` or `Чора-тадбирлар` |
| I | Маълумот манбаи | Data source |
| J | Ҳисобот шакилланадиган сана | Reporting cadence text |
| K | Амалга ошириш механизми | Mechanism |
| L | Интеграция ҳолати | Integration status |

**14 region blocks, 4 columns each (M..BP), in fixed order.** Each block = `Ижрочи`, `Режа кўрсаткичи` (plan), `Амалда ижроси` (actual), `Бажарилиши фоизда` (% of plan). The block's first (Ижрочи) column index:

| Block start col | Index | XLSX header | Region slug | SOATO code |
|---|---|---|---|---|
| M | 13 | Қорақалпоғистон Респубилкаси | karakalpak | 1735 |
| Q | 17 | Андижон вилояти | andijan | 1703 |
| U | 21 | Бухоро вилояти | bukhara | 1706 |
| Y | 25 | Жиззах вилояти | jizzakh | 1708 |
| AC | 29 | Қашқадарё вилояти | kashkadarya | 1710 |
| AG | 33 | Навоий вилояти | navoi | 1712 |
| AK | 37 | Наманган вилояти | namangan | 1714 |
| AO | 41 | Самарқанд вилояти | samarkand | 1718 |
| AS | 45 | Сирдарё вилояти | sirdarya | 1724 |
| AW | 49 | Сурхондарё вилояти | surkhandarya | 1722 |
| BA | 53 | Тошкент филояти *(sic)* | tashkent | 1727 |
| BE | 57 | Фарғона вилояти | fergana | 1730 |
| BI | 61 | Хоразм вилояти | khorezm | 1733 |
| BM | 65 | Тошкент шаҳри | tashkent_city | 1726 |

Importer maps regions **by fixed column position**, with a header-text sanity check that aborts if a block header no longer matches (guards against column shifts in future files).

**Row taxonomy:**
- **Section header**: col A holds a roman numeral (`I.`…`VII.`) → module; or `N.N.` (e.g. `1.2.`) → indicator; or plain text (e.g. *"Саноат йўналишида, шу жумладан маҳаллийлаштириш"*) → sub-label under current indicator. Reuse/extend `App\Support\TasksTaxonomy` (`ROMAN_TO_MODULE`, `NUMERIC_TO_INDICATOR`).
- **Task row**: col A = integer AND col C non-empty → a new task. Captures task-level metadata + each region's headline metric line.
- **Continuation row**: col A empty, col D non-empty → an additional metric line (`line_no` 1+) of the current task (multi-metric tasks, e.g. task #8 = "йирик корхона сони" дона + "қайта тикланадиган ишлаб чиқариш" млрд сўм). Rows like *"шундан:"* are sub-labels, not metrics.

Counts: 97 numbered tasks, 243 indicator (D) lines → ≈2.5 metric lines per task. Kind split is roughly one-third KPI / two-thirds Чора-тадбирлар (exact task-level split confirmed at import time). In the **initial baseline file the actual & % columns are empty** — it is the plan baseline; monthly files fill actuals.

### Cadence vocabulary (col J)

| Text | Cadence |
|---|---|
| Ҳар чорак якуни билан кейинги ойнинг 25 санаси | `quarterly` |
| Ҳар ой якуни билан кейинги ойнинг 25 санаси | `monthly` |
| Ҳар ой / Ҳар ойда | `monthly` |

Default `quarterly` if unmatched.

### Deadline normalization (col F)

`period_code`: contains `I ярим йиллик` → `h1`; contains `якуни` → `year`; contains `давомида` → `ongoing`; explicit month (e.g. `май ойи`) → `month`. Whitespace/newline variants normalized (collapse `\s+`).

## 3. Data model (new migrations)

### 3a. Alter `tasks`
Add:
- `cadence` string(16) nullable — `monthly` | `quarterly`
- `data_source` text nullable (col I)
- `report_schedule_text` text nullable (col J, raw)
- `integration_status` string(64) nullable (col L)
- `mechanism_text` text nullable (col K)
- Denormalized headline snapshot (latest period, for fast board/profile rendering, recomputed on every import):
  - `latest_period` string(16) nullable
  - `headline_unit` string(48) nullable
  - `headline_plan` decimal(20,6) nullable
  - `headline_actual` decimal(20,6) nullable
  - `headline_pct` decimal(10,4) nullable

Keep: existing `status` (now actively set, binary `done`/`open`), `period_code`, unique `(region_code, task_number)`, FKs.
The DOCX-era columns `section_path`, `section_label`, `source_paragraph_index` are reused by the XLSX importer (source_paragraph_index = source row index).

### 3b. New `task_progress` (metric-line × report-period history)
```
id
task_id            FK tasks cascadeOnDelete
line_no            smallint    -- 0 = headline metric, 1+ = sub-metrics
metric_label       string(255) -- col D
unit               string(48)  -- col E
report_period      string(16)  -- '2026-03' (month) | '2026-Q1' (quarter)
period_type        string(8)   -- 'month' | 'quarter'
plan_value         decimal(20,6) nullable
actual_value       decimal(20,6) nullable
pct_of_plan        decimal(10,4) nullable
reported_at        date nullable
import_run_id      FK import_runs nullOnDelete (nullable)
timestamps
unique (task_id, line_no, report_period)
index (task_id, report_period)
```
Re-importing the same `report_period` updates those rows (idempotent); a new period appends history. `plan_value` is repeated per snapshot (acceptable; the partner re-sends plan each file and plan can be revised).

### 3c. Models
- `Task`: add fillables for the new columns; add `progress(): hasMany(TaskProgress)`; helper `latestProgress()` (rows where `report_period == latest_period`), `headlineProgress()` (line_no 0 of latest).
- New `TaskProgress` model: `belongsTo(Task)`, `belongsTo(ImportRun)`, casts for decimals/date.

## 4. Importer — `php artisan import:task-progress`

Signature: `import:task-progress {--file=} {--period=} {--region=all} {--dry-run}`
- `--file` default `base_path('../data/tasks/Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx')`.
- `--period` the report period this file represents, e.g. `2026-Q1` or `2026-03`. Required (no safe default). `period_type` inferred from format (`-Qn` → quarter, `-MM` → month).
- `--region` `all` (default) or a slug/code to import a single region's columns.
- `--dry-run` parse + report, write nothing.

Algorithm:
1. Open workbook (PhpSpreadsheet, `setReadDataOnly(true)`), read the single sheet.
2. Create an `ImportRun` record (existing model) for traceability; wrap writes in a DB transaction.
3. Walk rows 7..max. Maintain `currentModule` / `currentIndicator` / `currentSectionPath` / `currentSectionLabel` from section-header rows.
4. On a **task row** (col A int + col C text): build task attrs (title, deadline→period_code, kind, data_source, schedule→cadence, mechanism, integration, section, source row index). Then for each of the 14 region blocks:
   - executor text = block col 0; if blank/`х`, skip that region for this task.
   - headline metric (line 0): label = col D, unit = col E, plan = block col 1, actual = block col 2, pct = block col 3.
   - upsert `tasks` on `(region_code, task_number)`; sync `task_districts` from executor (reuse `resolveDistricts`); write `task_progress` (line 0) for `--period`.
5. On a **continuation row**, append `task_progress` line_no (1+) for the in-progress task, per region.
6. Value parsing: `'х'`, `''`, non-numeric → null. `pct_of_plan` = partner col (Бажарилиши фоизда) when numeric; else `actual/plan·100` when both present; else null.
7. After all rows: per task recompute headline snapshot (`latest_period`, `headline_*` from line_no 0 of the imported period) and `status` = `done` if `headline_pct >= 100` else `open`.
8. Output summary: tasks upserted, progress rows written, unmatched executor tokens (warn).

**Idempotency (decided):** the importer **always upserts** task definitions on `(region_code, task_number)` and **never bulk-deletes** tasks — unlike the legacy DOCX importer's `Task::where('region_code',…)->delete()`, which would wipe `task_districts` and cascade-delete progress history. It re-syncs `task_districts` from the current file and **replaces only the given period's** `task_progress` rows. Therefore a monthly actuals-only file never destroys districts or prior-period history; re-running the same `(region, period)` is safe and repeatable.

**Monthly/quarterly workflow:** partner sends a refreshed file → `php artisan import:task-progress --file=… --period=2026-04` (monthly tasks update; quarterly tasks carry forward unchanged until their quarter). History accrues per period.

Retire `import:tasks` (DOCX) — leave the file but mark deprecated in its description, or remove; decided in the plan.

## 5. Status / period logic

New `App\Support\TaskStatus`:
- `statusFor(?float $pct): string` → `done` iff `$pct !== null && $pct >= 100`, else `open`.
New period helper (in importer or `TasksTaxonomy`):
- `cadenceFor(string $scheduleText): string`
- `periodType(string $reportPeriod): string`

## 6. UI changes

### 6a. Tasks board — `App\Livewire\TasksBoard` + `resources/views/livewire/tasks-board.blade.php`
- Eager-load headline progress; each task card shows: title, kind chip, module/indicator chip, deadline, **cadence chip**, district-count, and a **plan → actual → %** line + done/open badge + "last updated: {latest_period}".
- Expandable detail: all `task_progress` metric lines (label · unit · plan · actual · %) for the latest period + responsible districts list.
- Donut + totals already exist → now fed by real `status`.
- Empty / baseline state: when no actuals exist yet (`headline_actual` all null), show "Режа киритилган, амалдаги натижа кутилмоқда" instead of 0% red everywhere.
- Filters: keep module/indicator/status/period/district/search. Optionally add a `cadence` filter (monthly/quarterly) — nice-to-have.

### 6b. District drilldown — `App\Livewire\RegionProfile` + `resources/views/livewire/profile/bottom.blade.php`
- The existing **"Туман топшириқлари"** panel (already lists a district's tasks via `task_districts`) is enriched: per task show plan/actual/% + done/open badge + deadline. This is the "select a district → see its tasks" surface the request asks for.
- **Limitation surfaced honestly:** the file has no per-district numbers, only per-region. A district's task shows the **region** plan/actual with the district flagged as co-responsible (derived from the per-region Ижрочи list). No fabricated per-district values.

### 6c. Districts comparison — `App\Livewire\DistrictsPage` + `pages/districts`
- Add a light `done/total` task badge per district (counts via `task_districts`), linking into the drilldown. Lighter touch than the drilldown panel.

## 7. Non-goals
- No manual status editing / approval workflow (import-only).
- No invented per-district numeric targets (source has none).
- `index.html` prototype is not modified.
- No auth/roles changes.
- History is stored now; trend charts/visualizations are future work.
- `CLAUDE.md` still says "no backend"; updating it is an optional follow-up, not part of this work.

## 8. Risks / edge cases
- Future partner files may shift columns → header sanity check aborts loudly.
- Region header typo "Тошкент **филояти**" must be tolerated (match by position, not exact spelling).
- Tasks with all-`х` regions (e.g. auction rows) → skipped per region, task may have zero progress rows for some regions.
- Multi-metric tasks: `done` is judged on the **headline** (line_no 0) metric only; sub-metrics are shown but do not flip status. (Acceptable under binary model.)
- Districts must be seeded for a region before its Ижрочи tokens resolve; unmatched tokens are warned, not fatal.
