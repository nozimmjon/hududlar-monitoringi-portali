# Sector (тармоқ) guarantee-letter tasks — DB import design

**Date:** 2026-07-29
**Status:** implemented (2026-07-29)
**Phase:** 1 of 2 (DB + import; visualization/dashboard is a separate later phase)

> **As-built deviations** (accepted during implementation): the file is passed as
> `--file=` option with a default under `data/sectors/` (not a positional argument);
> `report_period`/`period_type` additionally accept `2026-H2`/`half`, though the
> recommended plans-only baseline is `2026-07` — an H-period baseline sorts by its
> closing month (December) and would freeze snapshot advancement until year end;
> header verification checks columns C–I (the A/B `№` shape is enforced by the row
> parsing rules instead); the import command also rejects duplicate line numbers
> within a task, and the parser transliterates Cyrillic lookalikes in
> Roman-numeral deadlines.

## Background

17 industrial sector enterprises (тармоқ корхоналари) submitted H2-2026 guarantee
letters. Their tasks arrive in one workbook — `Вазифалар_2026_тармоқлар_кесимида.xlsx`
— one sheet per enterprise. The portal must store these tasks and later show them on a
dedicated dashboard. An updated copy of the same workbook will arrive monthly/quarterly
with the fact columns filled in, so the model needs per-period history exactly like the
existing region task pipeline (`tasks` + `task_progress`).

Workbook facts (verified 2026-07-29):

- 17 sheets, identical layout. Total 119 tasks / 521 indicator lines.
- Row 1 title; row 2 organisation + signer ("«Ўзбекнефтгаз» АЖ — Бошқарув раиси
  А. Сангинов имзолаган кафолат хати (II бўлим)"); rows 3–4 headers; data from row 5;
  an "Изоҳлар:" row terminates each sheet.
- Columns: A task № (filled only on the first line of a task), B line № (sheet-global,
  continuous), C task title (only on first line), D indicator label, E unit, F deadline,
  G plan, H actual, I % — H and I are currently empty everywhere.
- Deadline (F) has 5 spelling variants that normalize to 4 codes:
  `2026 йил якуни` → `year` (449), `2026 йил 2-ярим йиллиги` → `h2` (32),
  `2026 йил III-чорак`/`III чорак` → `q3` (33), `IV чорак`/`IV-чорак` → `q4` (7).

## Decision: separate parallel tables (approach A)

Mirror the proven `tasks`/`task_progress` design in independent tables. Do not touch
the region task pipeline (no nullable `region_code`, no polymorphism). Cost is a small
amount of duplicated status-recompute logic; benefit is zero regression risk across the
existing suite and a schema that stays honest about being a different domain.

## Schema

### `sectors` — reference, seeded

| column | type | notes |
| --- | --- | --- |
| id | smallIncrements | |
| code | string(48) unique | stable slug: `uzbekneftgaz`, `nkmk`, `tmk`, … |
| name_short | string(96) | "Ўзбекнефтгаз" (sheet-derived display name) |
| org_full | string(255) | "«Ўзбекнефтгаз» АЖ" |
| signer_text | string(255) nullable | "Бошқарув раиси А. Сангинов" |
| sort_order | smallInteger | sheet order 1–17 |
| timestamps | | |

`SectorSeeder` holds the 17 fixed rows (codes are hand-assigned, never derived at
import time). Import matches sheets to sectors by normalized sheet/org name and
refuses unknown sheets.

### `sector_tasks` — one row per task (column A level)

| column | type | notes |
| --- | --- | --- |
| id | id | |
| sector_id | FK sectors cascade | |
| task_no | smallInteger | column A |
| title | text | column C |
| status | string(16) | `done` \| `open` \| `in_progress` |
| lines_total / lines_done | integer | recomputed on import |
| latest_period | string(16) nullable | denormalized snapshot, same style as `tasks` |
| headline_unit | string(48) nullable | |
| headline_plan / headline_actual | decimal(20,6) nullable | |
| headline_pct | decimal(10,4) nullable | |
| timestamps | | |

Unique `(sector_id, task_no)`. The headline is the task's first indicator line
(lowest line_no).

### `sector_task_progress` — line × report-period history

| column | type | notes |
| --- | --- | --- |
| id | id | |
| sector_task_id | FK cascade | |
| line_no | smallInteger | column B — sheet-global, the stable upsert key |
| metric_label | string(255) | column D |
| unit | string(48) nullable | column E |
| deadline_text | string(64) nullable | column F raw |
| deadline_code | string(8) | normalized `year` \| `h2` \| `q3` \| `q4` |
| report_period | string(16) | `2026-Q3` or `2026-08` |
| period_type | string(8) | `quarter` \| `month` |
| plan_value / actual_value | decimal(20,6) nullable | columns G / H |
| pct_of_plan | decimal(10,4) nullable | recomputed from plan+actual, never trusted from file (column I ignored) |
| reported_at | date nullable | |
| timestamps | | |

Unique `(sector_task_id, line_no, report_period)`.

## Import command

`import:sector-tasks {file} --period=2026-Q3 [--dry-run]`

1. Open workbook, iterate sheets. Match each sheet to a `sectors` row by the numeric
   prefix of the sheet title ("5. НКМК" → sort_order 5), then verify the normalized
   sheet name against the sector's `name_short`; number/name mismatch or unknown
   sheet → abort with a clear error (same philosophy as the region-column
   verification in `import:task-progress`). `--period` is required.
2. Verify header rows 3–4 contain the expected labels (№ / Кўрсаткич номи /
   Индикатор номи / Ўлчов бирлиги / Муддати / Режа / Амалда ижроси / фоизда).
   Layout drift → abort.
3. Parse data rows: column A filled starts a new task (upsert `sector_tasks` by
   `(sector_id, task_no)`, title updated on change); blank A continues the current
   task. Stop at "Изоҳлар:".
4. Upsert `sector_task_progress` by `(sector_task_id, line_no, report_period)`.
   `pct_of_plan = actual / plan × 100` when both present, else NULL.
5. Recompute per task: `lines_total`/`lines_done` (over lines with a plan),
   status, and the latest-period headline snapshot.
6. `--dry-run` prints counts and warnings without writing.

Idempotent: re-running the same file+period updates in place, no duplicates.
Also ships `sector-tasks:recompute` (rebuild statuses/snapshots without re-import,
same as `tasks:recompute`).

Status rules copied from the region model: nothing reported → `in_progress`
(Бажарилмоқда); reported and every planned line ≥ 100% of plan → `done`;
otherwise `open` (weakest-link).

## Testing

Pest feature tests with a small generated fixture workbook (PhpSpreadsheet, built in
the test, mirroring the real layout):

- parser maps tasks/lines/units/deadlines correctly (incl. all 5 deadline spellings)
- idempotent re-import (same period twice → no dupes, values updated)
- second period appends history, snapshot moves to latest period
- pct recomputed, file's column I ignored
- layout drift and unknown sheet name → abort, nothing written
- seeder seeds 17 sectors with stable codes
- status transitions: in_progress → open → done

## Out of scope (phase 2)

Dashboard/UI, navigation, Livewire components, routes. Nothing in phase 1 renders
anything.

## Operational notes

- Workbook lives under `data/sectors/` — stays local, never committed (existing rule).
- Runbook `backend/docs/sector-task-import.md` written as part of implementation.
