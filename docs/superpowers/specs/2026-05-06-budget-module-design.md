# Budget Module Importer — Design

**Date:** 2026-05-06
**Status:** Approved through Sections 1-3 in brainstorming. Awaiting user spec review before implementation plan.
**Branch:** `v7-design-polish`
**Scope:** Add the `budget` module to the importer. One source sheet (`тушум` for Andijan; varies across regions) feeds the existing `indicator_facts` cube with `indicator_code='budget'`. Three periods per row: `year`, `h1`, `q2`. Andijan parity assertions for all 17 entities (1 region rollup + 16 districts) × 3 periods = 51 staging rows.

**Predecessors:**
- Schema: `docs/superpowers/specs/2026-05-05-fact-tables-and-import-schema-design.md` (Plan 1)
- Importer infrastructure: `docs/superpowers/specs/2026-05-06-importer-tracer-bullet-design.md` (Plan 2)
- Inflation module precedent: `docs/superpowers/specs/2026-05-06-inflation-module-design.md` (Plan 3)

---

## 1. Context

Budget data is one of the simpler module shapes. Single source sheet, no district vs region structural difference (both shapes write the same row layout to the same `indicator_facts` cube), no sentinel handling, no derived fields beyond what the workbook directly provides.

The novelty is **Q2 period support**. Every prior module produced rows for q1/h1/m9/year. Budget is the first module to use Q2 (the second quarter — April-June). The schema's `period` column is `varchar(8)` so no migration is needed; the PHP `Period` enum needs one new case (`Q2 = 'q2'`).

This plan reuses everything Plan 2 + Plan 3 built. No new DTOs (the existing `IndicatorFactDto` already has the right fields for cube data: `planValue`, `expectedValue`, `pctOfPlan`, `period`).

## 2. Constraints

- **Andijan only.** Plan 9 rolls out to other regions.
- **Module:** `budget` only. Plans 5-8 add `budget_invest`, `foreign_invest`, `export`, `employment`.
- **No sentinel handling.** Budget data has no `"холи ҳудуд"`-equivalent.
- **Sheet-name variance** across all 14 regions handled via `SheetResolver` content-pattern signatures:
  - `тушум` (most regions)
  - `тушим` (Sirdarya — typo)
  - `Бюджетга тушумлар` + extra `2025 солиштирма` sheet (6 regions: Jizzakh, Qashqadaryo, Navoi, Namangan, Samarkand, Surkhandarya). The extra prior-year-comparison sheet is ignored.
  - `3-илова (2)` (Tashkent obl, Ferghana, Khorezm, Tashkent city).
- **HeaderDetector likely bypassed**, same as Plan 3's inflation. Budget sheet doesn't carry the `"ҳажм"`/`"млрд.сўм"` unit signature that Plan 2's macro detector relies on. The parser writes inline start-row detection logic.
- **Column N q2_execution_pct unverified.** Plan 1's `tmp_inspect.py` dumped only 12 cols; the q2 execution column is beyond that range. Task 1 confirms the actual column index by opening the workbook directly before committing.

## 3. Architecture deltas

**New files:**
- `backend/app/Services/Import/Modules/BudgetModuleParser.php`
- `backend/tests/Feature/Import/SheetResolverBudgetTest.php`
- `backend/tests/Feature/Import/BudgetModuleParserTest.php`
- `backend/tests/Feature/Import/ImportRegionCommandBudgetTest.php`
- `backend/tests/Feature/Import/AndijanBudgetParityTest.php`

**Modified:**
- `backend/app/Enums/Period.php` — add `Q2 = 'q2'` case.
- `backend/database/seeders/IndicatorSeeder.php` — change `budget`'s `supported_periods` from default `["q1","h1","m9","year"]` to `["h1","q2","year"]`.
- `backend/app/Services/Import/SheetResolver.php` — add `'budget'` to `SIGNATURES`.
- `backend/app/Console/Commands/ImportRegionCommand.php` — register `BudgetModuleParser`.

**No new DTO.** `IndicatorFactDto` already covers cube rows. No new schema, no new migrations.

## 4. `BudgetModuleParser`

```php
class BudgetModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'budget'; }

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);   // workbook may use SUM formulas (Plan 3 lesson)
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'budget', 'budget');
        if ($sheet === null) return 0;

        // Inline start-row detection — first row where col B contains "вилояти" is the rollup.
        $rollupRow = $this->findRollupRow($sheet);
        if ($rollupRow === null) return 0;
        $startRow = $rollupRow + 1;     // first district row

        $count = 0;
        $count += $this->emitEntityRows($ctx, $sheet, $rollupRow, null, $filePath);
        for ($row = $startRow; $row <= $startRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            if (! $this->isDistrictRow($colA, $colB)) continue;
            $districtCode = $this->districtResolver->resolve(
                $colB, $ctx,
                basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            if ($districtCode === null) continue;
            $count += $this->emitEntityRows($ctx, $sheet, $row, $districtCode, $filePath);
        }
        return $count;
    }
}
```

### Column mapping (Andijan `тушум`)

| Col | Period | Facet |
| --- | --- | --- |
| C (3) | year | plan |
| D (4) | h1   | plan |
| E (5) | q2   | plan |
| F (6) | year | expected |
| G (7) | h1   | expected |
| H (8) | q2   | expected |
| L (12) | h1  | execution_pct |
| **N (14)** | **q2** | **execution_pct (verify during Task 1 — Plan 1 inspection only dumped 12 cols)** |

For each row (rollup or district), emit **3 IndicatorFactDtos** — one per period:

```
year period: plan_value=C, expected_value=F, pct_of_plan=NULL, period='year'
h1 period:   plan_value=D, expected_value=G, pct_of_plan=L,    period='h1'
q2 period:   plan_value=E, expected_value=H, pct_of_plan=N,    period='q2'
```

All three rows share `indicator_code='budget'`, `unit='млрд сўм'`, `region_code=ctx.regionCode()`, `district_code=...` (NULL for rollup).

### Total expected for Andijan budget

- 1 rollup × 3 periods = 3 rows
- 16 districts × 3 periods = 48 rows
- **Total: 51 rows in `import_staging_indicator_facts`** (all with `indicator_code='budget'`).

## 5. CLI integration

```php
$parsers = [
    'macro'     => new MacroModuleParser(...),
    'inflation' => new InflationModuleParser(...),
    'budget'    => new BudgetModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

Usage:
```
php artisan import:region andijan 2026 --module=budget
php artisan import:region andijan 2026                   # all 3 modules in one ImportRun
```

The full-suite `--module=` omitted run produces 212 (macro) + 28 (inflation) + 51 (budget) = **291 staging rows** total in one `ImportRun`.

## 6. Parity test

`AndijanBudgetParityTest`:

```
1. RefreshDatabase + seed.
2. Artisan::call('import:region', [..., '--module'=>'budget']).
3. Read import_staging_indicator_facts where indicator_code='budget' and import_run_id=latest.
4. Assert exact row count 51.
5. Extract DATA.regional.budget (1 dict) + DATA.districts[*].data.budget (16 dicts).
6. For each of 17 entities, find staged rows by (district_code, period) for periods year/h1/q2.
   - plan_value matches DATA.{period}_plan within 0.05.
   - expected_value matches DATA.{period}_expected within 0.05.
   - For h1 and q2: pct_of_plan matches DATA.{period}_execution_pct within 0.05.
7. Assert ImportRun status='awaiting_review', issues_blocker_count=0.
```

~136 numeric assertions plus structural counts.

### Tolerance

`0.05` for floats, matching Plan 2's macro and Plan 3's inflation parity tests. Sufficient for the workbook's 1dp display rounding.

## 7. Out of scope (deferred)

- Plans 5-8: `budget_invest`, `foreign_invest`, `export`, `employment` modules.
- Plan 9: roll out to 12 non-Navoi regions.
- Plan 10: Filament admin UI / promote-reject flow.
- Plan 11: cross-region contamination detector (Navoi unblock).
- **2025 prior-year comparison sheet** (`2025 солиштирма`) — present in 6 regions' workbooks. Not consumed by this importer; future enhancement if dashboard wants prior-year deltas.

## 8. Migration plan summary (no migrations)

5 new files, 4 modified files. Estimated ~7 tasks for the implementation plan, plus 1 buffer task for real-data discoveries (Plan 3 had `setReadDataOnly(false)` and 11-vs-12 product discoveries; Plan 4 will likely surface the col N q2_execution_pct location). Total estimated plan length: 800-1000 lines.
