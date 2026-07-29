# Sector tasks import — operator runbook

Separate from the district-level task board (`task-import.md`), this pipeline covers
the **тармоқ корхоналари** (sector enterprises) guarantee-letter tasks: 17 large
state-owned/holding enterprises (Ўзбекнефтгаз, Ўзбекгидроэнерго, ИЭС, Кимё саноати,
НКМК, Навоийуран, Олмалиқ КМК, Ўзметкомбинат, ТМК, Ўзавтосаноат, Ўзэлтехсаноат,
Енгил саноат, Ўзтўқимачиликсаноат, Ўзчармсаноат, Қурилиш материаллари, Фармацевтика,
Ўзбекзаргарсаноати), each with its own H2-2026 guarantee-letter tasks.

## The file

**`Вазифалар_2026_тармоқлар_кесимида.xlsx`** — one workbook, **17 sheets, one per
enterprise**. Each sheet's title starts with a leading order number (e.g. `1.
Ўзбекнефтгаз...`) that must match the sector's `sort_order` in the `sectors` table,
and the title text must contain the sector's short name.

Columns (per sheet, data rows starting at row 5):

| Col | Meaning |
|---|---|
| A | № — task number (filled only on a task's first row; blank = continuation line) |
| B | № — metric/indicator line number within the task |
| C | Кўрсаткич номи — task title |
| D | Индикатор номи — metric label |
| E | Ўлчов бирлиги — unit |
| F | Муддати — deadline text |
| G | Режа — plan value |
| H | Амалда ижроси — actual value |
| I | фоиз — percent (**ignored**, see below) |

## Where it lives

Place the file at `data/sectors/Вазифалар_2026_тармоқлар_кесимида.xlsx` (repo root,
gitignored — `data/` stays local, never committed).

## Commands

From `backend/`, run one command with the period the file represents:

```bash
# baseline plans-only file — use the FIRST MONTH of the half, not the half itself
# (see "Period convention" below for why):
php artisan import:sector-tasks --period=2026-07

# a later update with actuals filled in:
php artisan import:sector-tasks --period=2026-08

# dry run first — parse and report without writing anything:
php artisan import:sector-tasks --period=2026-07 --dry-run

# a different file path:
php artisan import:sector-tasks --file=../data/sectors/some-other-copy.xlsx --period=2026-07
```

Check the output:
- `Parsed 17 sheet(s): 119 tasks, 521 metric lines.` — the parser found every sheet
  and every task (a smaller/larger count means the workbook layout or sheet set
  changed — see below).
- `Wrote N progress rows for 17 sector(s), period …` — the write confirmation.
- Any warning lines (missing task title, indicator row without a task number, or an
  unrecognised deadline spelling) — non-fatal, but worth checking; see the deadline
  table below.

Rebuild statuses/headline snapshots from stored progress without re-importing:

```bash
php artisan sector-tasks:recompute
```

## Period convention

**Use `--period=2026-07` for the plans-only baseline, NOT `2026-H2`.** Periods sort
by their *closing* month — a half sorts as its last month, so `2026-H2` sorts as
`2026-12` (December). If the baseline were imported as `2026-H2`, every monthly file
from August through November and even `2026-Q3` would sort *before* it, and the
task snapshot (status/headline/`latest_period`) would stay frozen on the baseline
until a December-or-later file finally arrived — silently hiding all progress
reported in between. Importing the baseline as `2026-07` (the first month of H2)
avoids this: it sorts before every later period in the half, so each subsequent
monthly or quarterly import correctly advances the snapshot. `2026-H2` remains a
perfectly valid `--period` value for a file that genuinely represents the whole
half — just don't use it for the baseline.

- **Initial plans-only baseline** (H/I columns empty, as sent for the guarantee
  letter itself): `--period=2026-07` (first month of the half, not the half code).
- **Monthly update files** (partner refreshes actuals through the half-year):
  `--period=2026-08`, `--period=2026-09`, …
- **Quarterly files**, if the partner switches cadence: `--period=2026-Q3`,
  `--period=2026-Q4`.

`--period` accepts `YYYY-Q1..Q4`, `YYYY-H1`/`H2`, or `YYYY-MM` (`01`–`12`).

## Idempotency

Re-running the same `--period` **replaces** that period's progress rows (deleted then
re-inserted inside one transaction) — no duplicates. Rows from other periods are
untouched, so history accumulates in `sector_task_progress` across periods; the task's
`latest_period`/headline fields only advance to a period that is not older than what
is already stored (a stale re-import of an older file won't regress the board).

## What aborts the import (nothing written)

The command validates everything before writing a single row — any of these abort
with no DB changes:

- **Layout drift in header rows** — the C3/D3/E3/F3/G4/H4/I4 header cells don't
  contain the expected needle text (Кўрсаткич номи / Индикатор номи / Ўлчов бирлиги /
  Муддати / Режа / Амалда / фоизда).
- **Sheet title without a leading order number** (e.g. missing the `N.` prefix).
- **Unknown sheet number** — the sheet's leading number has no matching `sort_order`
  in the `sectors` table.
- **Sheet name/sector mismatch** — the sheet title doesn't contain the matched
  sector's short name (guards against sheets being reordered/renamed).
- **Duplicate line numbers** (column B) within one task — a hand-edited file with a
  repeated indicator number would otherwise hit the DB unique constraint mid-write;
  caught up front instead with a clear message.
- **Invalid `--period`** — must match `YYYY-Q[1-4]`, `YYYY-H[12]`, or `YYYY-MM`.
- **Unconfigured reporting year** — the year in `--period` must exist in
  `reporting_years`; seed it first if missing (`ReportingYearSeeder`).

A sector present in `sectors` but **missing its sheet** in the file is not fatal — it
is reported as a warning and simply skipped for that period.

## Percent handling

**Column I is ignored.** The percent shown everywhere is recomputed on import as
honest `actual / plan × 100` from columns G/H — this avoids importing whatever
rounding or hand-entry the source workbook's own percent column carries.

## Status model

Same weakest-link model as the district task board:

- **Бажарилмоқда (`in_progress`)** — nothing reported yet: every line's actual is
  missing or an explicit 0.
- **`done`** — every metric line that has a plan is at ≥100% of plan.
- **`open`** — otherwise (at least one planned line below 100%, but something has
  been reported).

Lines without a plan value are informational only and never count toward the
total/done tally.

## Deadline normalization

Column F's free-text deadline is normalized to a `deadline_code` on import:

| Source text contains | `deadline_code` |
|---|---|
| `якун` (e.g. «2026 йил якуни») | `year` |
| `ярим йил` (e.g. «2026 йил 2-ярим йиллиги») | `h2` |
| `iv` (e.g. «IV чорак», «IV-чорак») | `q4` |
| `iii` (e.g. «III чорак», «III-чорак») | `q3` |
| anything else | `year` — plus a warning naming the unrecognised text |

Matching is case-insensitive and normalizes Cyrillic look-alike letters (і/ѵ) to
Latin before matching, so hand-edited future files with mixed scripts still resolve
correctly.

## First-time setup

```bash
php artisan migrate --force                        # sectors / sector_tasks / sector_task_progress tables
php artisan db:seed --class=SectorSeeder --force    # the 17 enterprises
```

Never needed again after that — subsequent imports upsert into the existing tables.
