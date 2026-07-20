# Task monitoring import — operator runbook

The partner organisation sends a refreshed copy of
**`Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx`** every month (some indicators update
monthly, some quarterly — see the *Ҳисобот шакилланадиган сана* column). This runbook
covers how to load it.

The same `import:task-progress` command also accepts the **"Иқтисодий кўрсаткичлар"**
file generation (e.g. the half-year snapshot). The layout is auto-detected from the
row-3 headers; see [Economic file generation](#economic-file-generation) below.

## Monthly workflow

1. Place the new file at `data/tasks/Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx`
   (repo root, gitignored), replacing the previous one.
2. From `backend/`, run **one command** with the period the file represents:

```bash
# a monthly file (e.g. April 2026):
php artisan import:task-progress --period=2026-04

# a quarterly file (e.g. Q1 2026):
php artisan import:task-progress --period=2026-Q1
```

3. Check the output:
   - `Parsed 97 task definitions.` — the parser found all numbered tasks
     (a much smaller number means the workbook layout changed; the command
     aborts loudly if the region columns shifted).
   - `Wrote N progress rows across 14 region(s).`
   - `Unmatched executor tokens: …` — district names in the *Ижрочи* column that
     could not be matched to seeded districts (see below). Non-fatal.

4. Open `/tasks` (any region via the region switcher) and `/districts` → district →
   profile. Cards show Режа / Амалда / Бажарилиши %, status, and expandable
   metric/district detail.

### Useful options

| Option | Meaning |
|---|---|
| `--file=…` | Use a different workbook path |
| `--region=andijan` or `--region=1703` | Import a single region only |
| `--dry-run` | Parse and report coverage without writing anything |

`import:all-regions --period=2026-Q1` also chains the task import per region after the
indicator import.

## Economic file generation

The half-year "Иқтисодий кўрсаткичлар" workbook carries the same 97 tasks (same
col-B numbering) but differs from the monitoring file:

- **No metadata columns** (KPI type, data source, schedule, mechanism, integration) —
  the import keeps whatever an earlier monitoring import recorded for those fields.
- **Region blocks start at column G** (col 7) instead of M (col 13). Auto-detected;
  the same shifted/reordered-columns guard applies.
- **The % column is a ratio** (`1.0` = 100%) — converted to percent on import. A `0`
  with an empty *Амалда ижроси* is treated as "no execution data yet" (stored as
  empty, shown as —), not as 0%.
- **Region applicability follows Режа кўрсаткичи**: a region whose plan is empty or
  «х» on every line of a task is *not listed* for that task — no rows are written
  (and the task stays hidden for that region via the plan filter on all pages).
  A task whose headline has no plan but whose sub-line does is still listed.
- **Lower-is-better indicators** (инфляция/ишсизлик/камбағаллик даражаси, нарх ва
  тариф caps — the task numbers in `TasksTaxonomy::LOWER_IS_BETTER_TASKS`): the
  file's % cell is actual/plan, which reads >100% exactly when a region did WORSE
  than the plan. The import recomputes these as **plan/actual** (both layouts), so
  exceeding an inflation forecast shows <100% and open, staying below it shows
  ≥100% and done. Extend that list if the partner adds new such indicators.
- **Dashboard bridge**: after writing task rows, the import pushes reported
  actuals into the region-level `indicator_facts` rows the KPI dashboard reads
  (`App\Support\TaskFactBridge::MAP` — budget 117/121, investment 155/157,
  export 165/167 ×1000, unemployment 181/200, poverty 213/214, jobs/legalization/
  microprojects 182/201 lines, mfy_clear 215). Module pages then show **Амалда**
  instead of the imported Кутилиш forecast; the forecast stays in
  `expected_value` for reference. Rows are matched by task number + line and
  guarded by a metric-label substring; a label mismatch skips the mapping with a
  warning instead of writing a wrong number. Note: re-running the *indicator*
  import (`import:region`/`import:promote`) rebuilds those fact rows, so re-run
  the task import afterwards to restore the actuals.

Import it with a half-year period:

```bash
php artisan import:task-progress --file="../data/+++Иқтисодий кўрсаткичлар.xlsx" --period=2026-H1
```

`--period` accepts `YYYY-H1`/`YYYY-H2` alongside quarters and months; half-year rows
are stored with `period_type = half` and sort between Q2 and Q3 (H1 → June,
H2 → December) for the headline-advance guard.

## Annex tables workbook (илова жадваллар)

The partner also sends a half-year annex workbook (**`data/илова жадваллар.xlsx`**,
25 sheets "N-илова", one indicator per sheet, one row per region). Most of it
duplicates the economic file, but a few sheets carry actuals the economic file
leaves empty. A separate command fills exactly those gaps **after** the regular
H1 import:

```bash
php artisan import:ilova --period=2026-H1            # add --dry-run first
```

Covered tasks: **4 line 1** (2-илова — the ўсиш суръати ratio columns D/F, scaled
×100 to percent), **10** (4-илова — actual = count of districts whose diff from the
region average is positive), **40** (7-илова), **46** (8-илова), **48** (9-илова),
**111 headline** (15б-илова), **133** (17-илова, cols У–Х).

Behavior:

- **Gap-filling only** — a non-null plan/actual already in the DB is never
  overwritten; a conflicting file value is reported and skipped. Idempotent.
- **Explicit zeros are written** — a closed half-year with no execution is a
  real 0% (shown as 0%, task open), unlike an empty cell which stays "—".
- **Regions matched by name, never by row position** — the annex sheets shuffle
  region order (17-илова swaps Сирдарё/Сурхондарё, 15б-илова omits Жиззах and
  uses its own order, several sheets omit Тошкент шаҳри). An unrecognised region
  name on a numbered row aborts the import.
- **Header guards** — each sheet's key columns are verified against expected
  header text; a moved/renamed column aborts instead of importing wrong numbers.
- Task **133** actuals arrive for four regions (Андижон, Бухоро, Навоий,
  Тошкент вилояти) that have **no plan** in either file — the actual is stored,
  but the task stays hidden for those regions by the no-plan filter and shows no
  percent.

## How updates behave

- **Idempotent**: re-running the same `--period` replaces that period's progress rows
  and re-syncs districts. No duplicates.
- **History**: each period's rows are kept in `task_progress`. The board shows the
  latest period; history stays queryable for future trend views.
- **Status**: three states, weakest-link — `in_progress` (Бажарилмоқда) when no
  line of the latest period shows real progress (every actual missing or an
  explicit 0 — nothing achieved yet), else `done` only when EVERY metric line
  that has a plan is at ≥100%, else `open` (Бажарилмаган). Multi-indicator tasks
  (`lines_total > 1`) show "M/N индикатор бажарилди" on the boards instead of
  line-0 Режа/Амалда, and list every line in Батафсил. Computed on import;
  `php artisan tasks:recompute` rebuilds status + line counters from
  `task_progress` at any time without re-importing.
- **Stale files**: importing an *older* period than the latest already imported will
  not regress the headline/status shown in the UI (guarded), but it **will** refresh
  task titles/executors/districts from that older file — import files in
  chronological order.
- **Deadline overrides**: `TasksTaxonomy::DEADLINE_OVERRIDES` corrects tasks whose
  Муддати column is wrong in the source file (currently task 217 → I ярим йиллик).
  Applied on every import so the partner file cannot revert the fix; extend the
  map for future corrections.

## One-time setup in an environment that ran the old DOCX importer

Environments that previously ran `import:tasks` (retired) carry legacy task rows whose
numbering does not match the XLSX. Before the **first** XLSX import, purge them once:

```bash
php artisan tinker --execute="App\Models\Task::query()->delete();"
```

(Then run the import as above. Never needed again — subsequent imports upsert.)

Also ensure the reporting year exists (`reporting_years` table); the command refuses
to import a period for an unseeded year:

```bash
php artisan db:seed --class=ReportingYearSeeder
```

## Known limitations

- **Unmatched executor tokens** (~12 with the current file): pathological source
  strings — missing spaces (*"Навбаҳортумани"*), two districts joined with *"ва"*,
  plural *"ҳокимликлари"*, locative suffixes. These tasks simply don't get a district
  link; everything else about them imports fine. Fix path: add the variant to the
  district's `alt_labels` in the seeder, or extend
  `App\Support\TaskExecutorResolver::normalize()`.
- **Per-district numbers do not exist in the source** — a district's task shows the
  *region-level* plan/actual with the district flagged as co-responsible.
- **Formula evaluation**: the parser evaluates workbook formulas
  (`getCalculatedValue`), so the file must come from the trusted partner channel.
- **Region column order is enforced**: the import refuses any file whose 14 region
  blocks are not in the expected columns (shifted or reordered), reporting exactly
  which columns mismatch.
