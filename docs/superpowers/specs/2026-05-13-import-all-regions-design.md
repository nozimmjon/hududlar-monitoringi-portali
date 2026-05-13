# All-regions batch import design

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Add `import:all-regions {year}` artisan orchestrator that loops every region in `SoatoSeeder::REGION_LATIN`, runs `import:region` → `import:promote` → `import:tasks` per region, captures per-region status, prints a summary table, and continues past failures. Remove the Navoiy skip in `ImportRegionCommand`.

---

## 1. Goal

The repo has 14 region folders under `data/` (e.g. `2. Андижон`, `6. Навоий`) each with 7 xlsx files and a guarantee-letter docx. `data/tasks/` has 14 task docx files. Existing commands `import:region`, `import:promote`, `import:tasks` work per region. Currently the operator must run 14 × 3 = 42 invocations to load everything.

This spec adds one batch driver that does all of that with a single command, continues past per-region failures, and prints a summary so the operator can see at a glance which regions succeeded and which need attention. The Navoiy upstream-data block is lifted (user confirmed fixed).

## 2. Non-goals

- No changes to per-region parser behaviour. `import:region` still emits an `ImportRun` per call, still respects blocker counts, still writes to staging tables.
- No new staging schema, no migration changes.
- No new UI exposure.
- No GUI; CLI only.
- No re-architecture of `import:promote`.
- No translation of region/district fixes that the existing per-region commands don't already handle.

## 3. Strategy

One new artisan command, `ImportAllRegionsCommand`, that:

- Reads the slug list from `Database\Seeders\SoatoSeeder::REGION_LATIN`.
- Filters by `--only=<csv>` if present (accepts slugs and SOATO ints).
- For each slug: calls `Artisan::call('import:region', ...)`, looks up the resulting `ImportRun`, optionally calls `Artisan::call('import:promote', ['run_id' => …])`, optionally calls `Artisan::call('import:tasks', ...)`, captures status + counts.
- Wraps every step in `try/catch` so one region's failure can't break the rest.
- Prints a Laravel-styled summary table at the end + a one-line tally.

`ImportRegionCommand` loses the four-line Navoiy guard.

## 4. Command signature

```
import:all-regions
    {year=2026 : Reporting year, passed through to import:region}
    {--only=    : Comma-separated region slugs or SOATO codes to limit the batch}
    {--no-tasks : Skip import:tasks calls}
    {--no-promote : Stop at staging; do not auto-promote}
```

## 5. Iteration logic

Pseudocode flow per region:

1. `Artisan::call('import:region', ['region_code' => $slug, 'year' => $year])` — captures non-zero exit as `xlsx => 'fail'`.
2. Reload latest `ImportRun` for `(region_code = SOATO int, year)`. If `null` → `xlsx => 'fail'`.
3. If `--no-promote` not set AND `issues_blocker_count === 0`: `Artisan::call('import:promote', ['run_id' => $run->id])`. On success `xlsx => 'promoted'`; on non-zero exit annotate `note`.
4. If `--no-tasks` not set: `Artisan::call('import:tasks', ['region' => $slug])`. On zero exit `tasks => 'ok'`, count = `Task::where('region_code', $regionCode)->count()`. On exception → `tasks => 'error'`.
5. Append the per-region row to the summary array.

Any `\Throwable` thrown by the underlying command is caught, the row is marked `error`, and the loop continues.

## 6. Summary output

End-of-run table (Laravel `$this->table($headers, $rows)`):

| Header | Value |
|---|---|
| region | slug |
| xlsx | `pending` / `staged` / `promoted` / `fail` / `error` |
| rows_staged | int (from `ImportRun.rows_staged`) |
| rows_promoted | int (count of `IndicatorFact.region_code = SOATO` after promote) |
| tasks | `ok` / `fail` / `error` / `skipped` |
| tasks_count | int (count of `Task.region_code = SOATO` after import) |
| note | truncated error message or empty |

Trailing summary line: `Run complete. <N>/<TOTAL> xlsx promoted, <M>/<TOTAL> tasks ok.`

Exit code: always `0` (continue-on-failure policy per Q3).

## 7. Navoiy skip removal

In `backend/app/Console/Commands/ImportRegionCommand.php`, lines 46-49 currently are:

```php
        if ($arg === 'navoiy') {
            $this->warn("Skipped 'navoiy' — see data_quality_issues for upstream macro 1.2 contamination.");
            return 0;
        }
```

Delete those four lines. No replacement. Navoiy is now treated identically to the other 13 regions.

## 8. Test

`backend/tests/Feature/Console/ImportAllRegionsCommandTest.php` — light smoke:

- Asserts the command is registered (`php artisan list` contains `import:all-regions`).
- Asserts `--only=andijan` limits the iteration to one region by checking the rendered output mentions `andijan` and NOT `bukhara`.
- Asserts the command exits `0` even when a region's `import:region` returns non-zero (continue-on-failure).
- Does NOT run the full per-region pipeline against real fixtures — that's covered by existing `ImportRegionCommandTest` per-module variants.

The implementation can lean on `Artisan::output()` and check the rendered table for region slugs.

## 9. Files touched

| File | Action |
|---|---|
| `backend/app/Console/Commands/ImportAllRegionsCommand.php` | new |
| `backend/app/Console/Commands/ImportRegionCommand.php` | remove 4-line Navoiy skip |
| `backend/tests/Feature/Console/ImportAllRegionsCommandTest.php` | new |

No CSS, no migrations, no models, no views, no seeders.

## 10. Operator usage

```bash
cd backend
php artisan import:all-regions 2026
# default: 14 regions, auto-promote, tasks included

php artisan import:all-regions 2026 --only=andijan,navoiy
# subset

php artisan import:all-regions 2026 --no-promote
# stage only; manual import:promote afterwards

php artisan import:all-regions 2026 --no-tasks
# skip task docx import
```

## 11. Risks

- **Risk:** Navoiy may still have upstream data issues now that the skip is gone. *Mitigation:* per-region exception handling — if it fails, summary marks it `error`/`fail` and the other 13 keep importing.
- **Risk:** `Artisan::call` inside one PHP process accumulates memory across 14 regions × big xlsx files. *Mitigation:* Garbage collection happens between calls. If memory is a real issue, operator can run `--only=...` in smaller batches.
- **Risk:** Per-region `IndicatorFact::count()` after promote could include rows from prior years. *Mitigation:* note the column header as "rows_promoted" meaning "total facts now in DB for this region" — not "rows added by this promote". Acceptable for an at-a-glance summary.
- **Risk:** Two regions share `name_latin` slug (the SoatoSeeder has `tashkent_city → 1726` and `tashkent → 1727`). *Mitigation:* slug list comes from `array_values(SoatoSeeder::REGION_LATIN)` so each slug appears once; the SOATO map already enforces uniqueness.
