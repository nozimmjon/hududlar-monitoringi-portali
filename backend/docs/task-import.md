# Task monitoring import — operator runbook

The partner organisation sends a refreshed copy of
**`Ҳудудий_кўрсаткичлар_назорати_бўйича.xlsx`** every month (some indicators update
monthly, some quarterly — see the *Ҳисобот шакилланадиган сана* column). This runbook
covers how to load it.

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

## How updates behave

- **Idempotent**: re-running the same `--period` replaces that period's progress rows
  and re-syncs districts. No duplicates.
- **History**: each period's rows are kept in `task_progress`. The board shows the
  latest period; history stays queryable for future trend views.
- **Status**: binary — `done` when the headline metric's *Бажарилиши фоизда* ≥ 100,
  otherwise `open`. Computed on import only (no manual editing).
- **Stale files**: importing an *older* period than the latest already imported will
  not regress the headline/status shown in the UI (guarded), but it **will** refresh
  task titles/executors/districts from that older file — import files in
  chronological order.

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
