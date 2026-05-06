# Foreign Investment Module Importer — Design

**Date:** 2026-05-06
**Status:** Approved through Sections 1-3 in brainstorming. Awaiting user spec review before implementation plan.
**Branch:** `v7-design-polish`
**Scope:** Add the `foreign_invest` module to the importer. Sheet name varies across 4 patterns covering all 14 regions; resolved via `SheetResolver` content-pattern matching. Per-period plan/expected/actual mapping from the workbook's nested column header. Andijan parity assertions for 51 staging rows under `indicator_code='investment'`.

**Predecessors:**
- Schema: `docs/superpowers/specs/2026-05-05-fact-tables-and-import-schema-design.md` (Plan 1)
- Importer infrastructure: `docs/superpowers/specs/2026-05-06-importer-tracer-bullet-design.md` (Plan 2)
- Module precedents: Plans 3 (inflation), 4 (budget), 5 (budget_invest)

---

## 1. Context

Foreign investment is the fifth module to onboard. Like budget and budget_invest, it writes to the `indicator_facts` cube — `indicator_code='investment'` (the indicator catalog uses the short word; the module_code is `foreign_invest`).

**Per-period mapping is cleaner than budget_invest's because each period carries its own plan**, no replication needed. Different cube facets are populated per period based on what the workbook actually carries: q1 has `actual` but no `expected`; h1 has `expected` but no actual; year has only `forecast` and `expected`. Counts split similarly: `count_extra` is per-period project count for q1/h1 (NULL on year); `count_extra_2` carries jobs only on h1 (NULL on q1 and year).

Sheet variance is the highest of any module so far — 4 distinct sheet-name patterns across 14 regions, including `2-жадвал (туманка)` shared by 10 regions. Resolved via plain-string content-pattern signatures.

## 2. Constraints

- **Andijan only.** Plan 9 rolls out to other regions.
- **Module:** `foreign_invest` only. Plans 7-8 add `export`, `employment`.
- **No sentinel handling.** Foreign investment data has no `холи ҳудуд`-equivalent.
- **Sheet-name variance** (from cross-region survey):
  - `4.2. Хорижий инвестициялар` (Karakalpakstan)
  - `4,2-хорижий инв` (Andijan)
  - `2-жадвал (туманка)` (Bukhara, Jizzakh, Navoi, Namangan, Samarkand, Sirdarya, Tashkent oblast, Ferghana, Khorezm, Tashkent city — 10 regions)
  - `4.2-жадвал (туманка)` (Qashqadaryo, Surkhandarya)
  All four resolved by plain-string content-pattern signatures matching column-header substrings.
- **`scoreSheet` row-range cap (Plan 5 lesson):** the resolver only scans plain-string cells in rows 1-5. Signature substrings must come from cells in those rows. Verify in Step 0 of Task 2 — a tinker inspection that classifies each cell as plain string, numeric, or RichText.
- **Prior-year cols (2025 actuals) ignored.** Workbook has 2025 jan-dec / jan-jun / jan-mar columns; DATA blob doesn't carry them.
- **Growth multiplier string col (e.g. `"1,7 бар."`) ignored.** Stored as string in workbook; not in DATA blob; not in cube schema.
- **Sub-breakdown cols (q1_plan_targeted, q1_carryover, etc.) ignored.** DATA blob only has the rollup q1_plan; we mirror that.
- **Indicator code is `investment`** (not `foreign_invest` — that's the module_code).
- **`unit = 'млн доллар'`** (NOT 'млн сўм' like budget_invest). Same cube, distinguished by the `unit` column.

## 3. Architecture deltas

**New files:**
- `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php`
- `backend/tests/Feature/Import/IndicatorSeederForeignInvestPeriodsTest.php`
- `backend/tests/Feature/Import/SheetResolverForeignInvestTest.php`
- `backend/tests/Feature/Import/ForeignInvestModuleParserTest.php`
- `backend/tests/Feature/Import/ImportRegionCommandForeignInvestTest.php`
- `backend/tests/Feature/Import/AndijanForeignInvestParityTest.php`

**Modified:**
- `backend/database/seeders/IndicatorSeeder.php` — change `investment`'s `supported_periods` from default `["q1","h1","m9","year"]` to `["q1","h1","year"]`.
- `backend/app/Services/Import/SheetResolver.php` — add `'foreign_invest'` to `SIGNATURES`.
- `backend/app/Console/Commands/ImportRegionCommand.php` — register `ForeignInvestModuleParser`.

No new DTO. `IndicatorFactDto` covers the cube. No new schema, no migrations.

## 4. `ForeignInvestModuleParser`

### Per-period emission table

| Period | plan_value | expected_value | actual_hokimyat | pct_of_plan | count_extra | count_extra_2 |
| --- | --- | --- | --- | --- | --- | --- |
| `q1` | `q1_plan` | NULL | `q1_actual` | `q1_pct` | `q1_projects` | NULL |
| `h1` | `h1_plan` | `h1_expected` | NULL | `h1_pct` | `h1_projects` | `h1_jobs` |
| `year` | `year_forecast` | `year_expected` | NULL | `year_pct` | NULL | NULL |

All rows share `indicator_code='investment'`, `unit='млн доллар'`, `region_code=ctx.regionCode()`, `district_code=...` (NULL for rollup).

### Row classifier

```php
private function classifyRow(mixed $colA, mixed $colB): string
{
    if (is_string($colA) && str_contains($colA, 'вилояти')) return 'rollup';
    if (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA)))) {
        if (! is_string($colB) || trim($colB) === '') return 'skip';
        return 'district';
    }
    return 'skip';
}
```

The Andijan workbook puts `"Андижон вилояти"` in col A on the rollup row (different from budget_invest's `"Жами"`). Districts use col A int + col B district name.

### Column constants (verify in Step 0 of Task 3)

Plan 1's `tmp_inspect.py` only dumped 12 cols; the workbook's `dim=A1:AJ24` extends to col AJ (36). Year-period cols, h1_jobs, h1_projects all live in cols M-AJ. Confirmed from Plan 1 row 7:

```
A1='Андижон вилояти' B2='' C3='Андижон вилояти' D4=1977.97 E5=835.19 F6=412.23
G7=3341.74 (year_forecast) H8='1,7 бар.' (growth — IGNORE) I9=807.43 (q1_plan)
J10=531.87 (q1_plan_targeted — IGNORE) K11=275.56 (q1_carryover — IGNORE) L12=879.97 (q1_actual)
```

Tentative constants (Step 0 confirms or adjusts):
```php
private const COL_DISTRICT_NAME       = 2;   // B
private const COL_YEAR_FORECAST       = 7;   // G
private const COL_Q1_PLAN             = 9;   // I
private const COL_Q1_ACTUAL           = 12;  // L
// cols M-AJ unverified — Step 0 finds:
//   COL_H1_PLAN, COL_H1_EXPECTED, COL_H1_PCT, COL_H1_PROJECTS, COL_H1_JOBS
//   COL_YEAR_EXPECTED, COL_YEAR_PCT
//   COL_Q1_PCT, COL_Q1_PROJECTS
```

**Step 0 mandatory:** tinker dump rows 4-12 cols 1-36 (A-AJ). Find each value matching the DATA blob's Andijan rollup numbers (q1_plan=807.4, q1_actual=880.0, q1_pct=1.1, q1_projects=101, h1_plan=1760.8, h1_expected=1783.3, h1_pct=1.0, h1_projects=155, h1_jobs=8989, year_forecast=3341.7, year_expected=3508.6, year_pct=1.0).

### Total expected for Andijan: **51 staging rows** (1 rollup × 3 + 16 districts × 3) under `indicator_code='investment'`.

## 5. SheetResolver signature

Add to `SIGNATURES`:

```php
'foreign_invest' => ['Хорижий инвестициялар', 'млн долл.', 'I чорак'],
```

**Verify in Step 3 of Task 2** that these are plain-string cells (not RichText) in rows 1-5. If RichText, swap for plain-string column-header substrings.

## 6. CLI integration

```php
$parsers = [
    'macro'          => new MacroModuleParser(...),
    'inflation'      => new InflationModuleParser(...),
    'budget'         => new BudgetModuleParser(...),
    'budget_invest'  => new BudgetInvestModuleParser(...),
    'foreign_invest' => new ForeignInvestModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

Usage:
```
php artisan import:region andijan 2026 --module=foreign_invest
php artisan import:region andijan 2026                              # all 5 modules: 212 + 28 + 51 + 51 + 51 = 393 rows
```

## 7. Parity test

`AndijanForeignInvestParityTest`:

```
1. RefreshDatabase + seed.
2. Artisan::call('import:region', [..., '--module' => 'foreign_invest']).
3. Read import_staging_indicator_facts where indicator_code='investment' for the latest run.
4. Assert exact row count: 51.
5. Region rollup (district_code=NULL) for each period (q1, h1, year):
     For q1: plan_value=q1_plan, actual_hokimyat=q1_actual, expected_value=NULL, count_extra=q1_projects, count_extra_2=NULL.
     For h1: plan_value=h1_plan, expected_value=h1_expected, actual_hokimyat=NULL, count_extra=h1_projects, count_extra_2=h1_jobs.
     For year: plan_value=year_forecast, expected_value=year_expected, actual_hokimyat=NULL, count_extra=NULL, count_extra_2=NULL.
     pct_of_plan matches DATA's {period}_pct within 0.05.
6. District rows: same pattern for each of 16 districts × 3 periods.
7. Assert ImportRun status='awaiting_review', issues_blocker_count=0.
```

Tolerance: `0.5` for value floats (millions of dollars; 1dp rounding); `0.05` for pct fields; exact `toBe()` for integer counts.

## 8. Out of scope (deferred)

- Plans 7-8: `export`, `employment` modules.
- Plan 9: roll out to 12 non-Navoi regions.
- Plan 10: Filament admin UI / promote-reject flow.
- Plan 11: cross-region contamination detector (Navoi unblock).
- **Prior-year cols (2025 jan-dec / jan-jun / jan-mar):** workbook carries them but DATA blob doesn't; importer ignores. Future plan could add a sibling `prior_year_*` table if dashboard wants year-over-year deltas.
- **`ўсиш, %` multiplier string** (e.g. `"1,7 бар."`): workbook col H. Not numeric, not on cube. Future plan could add a `growth_text` column.
- **q1_plan_targeted / q1_carryover sub-breakdowns:** workbook cols J, K. DATA blob carries only the rollup q1_plan. Future plan could capture the breakdown in a sibling table if dashboard surfaces them.

## 9. Migration plan summary (no migrations)

5 new files, 3 modified files. Estimated 6 tasks for the implementation plan, with one buffer for col M-AJ verification in Step 0 of Task 3. Total estimated plan length: 800-1000 lines.
