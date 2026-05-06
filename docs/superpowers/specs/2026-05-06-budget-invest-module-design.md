# Budget Investment Module Importer — Design

**Date:** 2026-05-06
**Status:** Approved through Sections 1-3 in brainstorming. Awaiting user spec review before implementation plan.
**Branch:** `v7-design-polish`
**Scope:** Add the `budget_invest` module to the importer. One source workbook with a region-coded sheet name (e.g. `2.Анд` for Andijan). Three periods per row: `q1`, `h1`, `year`. Row classifier skips ownership-breakdown rows. Andijan parity assertions for 1 region rollup + 16 districts × 3 periods = 51 staging rows.

**Predecessors:**
- Schema: `docs/superpowers/specs/2026-05-05-fact-tables-and-import-schema-design.md` (Plan 1)
- Importer infrastructure: `docs/superpowers/specs/2026-05-06-importer-tracer-bullet-design.md` (Plan 2)
- Inflation module precedent: `docs/superpowers/specs/2026-05-06-inflation-module-design.md` (Plan 3)
- Budget module precedent: `docs/superpowers/specs/2026-05-06-budget-module-design.md` (Plan 4)

---

## 1. Context

Budget investment is the fourth module to onboard. Like budget (Plan 4), it writes to the `indicator_facts` cube with `indicator_code='budget_invest'`. New twists vs prior modules:

1. **Region-coded sheet name.** Every region uses a different sheet suffix (`1.ҚР`, `2.Анд`, `3.Бух`, …, `14.Тош ш.`). Cannot hardcode. `SheetResolver` content-pattern matching handles all 14 variants.
2. **Nested ownership breakdown rows.** The Andijan workbook has rows for `Жами` (rollup) → `шу жумладан:` (section divider) → `Вилоят ҳокимлиги буюртмачилигида:` (regional government breakdown) → `Шаҳар-туман ҳокимликлари буюртмачилигида:` (district government breakdown) → 16 actual district rows. The parser skips ownership-breakdown rows; they aggregate across districts and don't map to any single district_code.
3. **Two count_extra columns.** Workbook carries both `objects` (annual count, replicates across periods) and `commissioning_year_count` (year-only count of commissioned objects). They populate `count_extra` and `count_extra_2` on the cube respectively.
4. **`limit` replicated as plan_value across all 3 periods.** Single annual target value. Replicating means the dashboard can read "of annual target X, absorbed Y" at any period without joining year-period rows.
5. **`commissioning_year_value` is discarded.** No cube column for year-only monetary ancillary; future plan can add it if needed.

## 2. Constraints

- **Andijan only.** Plan 9 rolls out to other regions.
- **Module:** `budget_invest` only. Plans 6-8 add `foreign_invest`, `export`, `employment`.
- **No sentinel handling.** Budget investment doesn't produce `холи ҳудуд` values.
- **Sheet-name variance** is region-coded; one regex won't cover all 14. Resolved by content-pattern signatures.
- **молиялаштириш (financing) columns ignored.** The workbook tracks both ўзлаштириш (absorption / utilization) and молиялаштириш (financing released). DATA blob only carries absorption; importer mirrors that shape.
- **Ownership-breakdown rows skipped silently.** `шу жумладан:` and `*ҳокимлиги буюртмачилигида:` rows are expected; no `data_quality_issue` raised.
- **Column positions beyond col 12 unverified.** Plan 1's inspection only dumped cols A-L; year_absorption, year_pct, commissioning_year_count, commissioning_year_value all live somewhere in cols M-V (sheet `dim=A1:V41`). Inspection is the first step of the parser task.

## 3. Architecture deltas

**New files:**
- `backend/app/Services/Import/Modules/BudgetInvestModuleParser.php`
- `backend/tests/Feature/Import/SheetResolverBudgetInvestTest.php`
- `backend/tests/Feature/Import/BudgetInvestModuleParserTest.php`
- `backend/tests/Feature/Import/ImportRegionCommandBudgetInvestTest.php`
- `backend/tests/Feature/Import/AndijanBudgetInvestParityTest.php`

**Modified:**
- `backend/database/seeders/IndicatorSeeder.php` — change `budget_invest`'s `supported_periods` from default `["q1","h1","m9","year"]` to `["q1","h1","year"]`.
- `backend/app/Services/Import/SheetResolver.php` — add `'budget_invest'` to `SIGNATURES`.
- `backend/app/Console/Commands/ImportRegionCommand.php` — register `BudgetInvestModuleParser`.

No new DTO. `IndicatorFactDto` already covers cube data including `countExtra` and `countExtra2`. No new schema, no migrations.

## 4. `BudgetInvestModuleParser`

```php
class BudgetInvestModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'budget_invest'; }

    /**
     * Column constants — verify against actual workbook in Step 0 of implementation.
     * Cols A-L confirmed from Plan 1 inspection. Cols M+ unverified.
     */
    private const COL_OBJECTS              = 3;   // C
    private const COL_LIMIT                = 4;   // D
    private const COL_Q1_ABSORPTION        = 5;   // E
    private const COL_Q1_PCT               = 6;   // F
    // cols G/H = q1 молиялаштириш — SKIP (DATA blob only has absorption)
    private const COL_H1_ABSORPTION        = 9;   // I
    private const COL_H1_PCT               = 10;  // J
    // cols K/L = h1 молиялаштириш — SKIP
    // Year-period and commissioning columns live somewhere in cols M-V (sheet dim=A1:V41).
    // Implementer's Step 0 inspects rows 4-9 across cols 1-22 and fills these in:
    //   COL_YEAR_ABSORPTION
    //   COL_YEAR_PCT
    //   COL_COMMISSIONING_COUNT
    //   (commissioning_year_value column exists too but is intentionally not read)
}
```

### Row classifier

```php
private function classifyRow(mixed $colA, mixed $colB): string
{
    if (! is_string($colB) || trim($colB) === '') return 'skip';
    $b = trim($colB);
    if ($b === 'Жами') return 'rollup';
    if (str_contains($b, 'жумладан')) return 'skip';        // section divider
    if (str_contains($b, 'буюртмачи')) return 'skip';       // ownership breakdown
    if (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA)))) return 'district';
    return 'skip';
}
```

### Per-entity period emission

For each rollup or district row, emit 3 `IndicatorFactDto`s:

| Period | plan_value | actual_hokimyat | pct_of_plan | count_extra | count_extra_2 |
| --- | --- | --- | --- | --- | --- |
| `q1` | `limit` | q1_absorption | q1_pct | `objects` | NULL |
| `h1` | `limit` | h1_absorption | h1_pct | `objects` | NULL |
| `year` | `limit` | year_absorption | year_pct | `objects` | `commissioning_year_count` |

All rows share `indicator_code='budget_invest'`, `unit='млн сўм'`, `region_code=ctx.regionCode()`, `district_code=...` (NULL for rollup).

### Total expected for Andijan

- 1 rollup × 3 periods = 3 rows
- 16 districts × 3 periods = 48 rows
- **Total: 51 staging rows in `import_staging_indicator_facts`** (all with `indicator_code='budget_invest'`).

## 5. SheetResolver signature

The sheet name is region-coded. Use plain-string content matches that survive across all 14 variants. From Plan 1 row 2 inspection, the title row contains `"ижтимоий ва ишлаб чиқариш инфратузилмасини ривожлантириш дастури"`. Header rows 4-6 contain plain-string cells: `"объект сони"`, `"лимит"`, `"ўзлаш-тириш"`, `"молия-лаштириш"`.

Add to `SIGNATURES`:

```php
'budget_invest' => ['ижтимоий ва ишлаб чиқариш', 'объект сони', 'лимит'],
```

Verify these are plain-string cells (not RichText) during Task 1 — Plan 4 surfaced that the scorer silently skips RichText. If signatures don't match, replace with header-row substrings that do.

## 6. CLI integration

```php
$parsers = [
    'macro'         => new MacroModuleParser(...),
    'inflation'     => new InflationModuleParser(...),
    'budget'        => new BudgetModuleParser(...),
    'budget_invest' => new BudgetInvestModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

Usage:
```
php artisan import:region andijan 2026 --module=budget_invest
php artisan import:region andijan 2026                            # all 4 modules: 212 + 28 + 51 + 51 = 342 rows
```

## 7. Parity test

`AndijanBudgetInvestParityTest`:

```
1. RefreshDatabase + seed.
2. Artisan::call('import:region', [..., '--module' => 'budget_invest']).
3. Read import_staging_indicator_facts where indicator_code='budget_invest' for the latest run.
4. Assert exact row count: 51.
5. Region rollup (district_code=NULL): for each period (q1, h1, year):
     plan_value matches DATA.regional.budget_investment.limit within 0.05.
     actual_hokimyat matches DATA.regional.budget_investment.{period}_absorption within 0.05.
     pct_of_plan matches DATA.regional.budget_investment.{period}_pct within 0.05.
     count_extra matches DATA.regional.budget_investment.objects (exact).
     For year period: count_extra_2 matches commissioning_year_count (exact); else NULL.
6. District rows: same pattern for each of 16 districts × 3 periods.
7. Assert ImportRun status='awaiting_review', issues_blocker_count=0.
```

Tolerance: `0.05` for floats (matches Plans 2-4); exact `toBe()` for integer counts (`objects`, `commissioning_year_count`).

Total assertions: ~17 entities × (3 periods × 4 numeric facets + 1 integer count + 0-1 conditional integer) ≈ ~250 assertions plus the count + structural ones.

## 8. Out of scope (deferred)

- Plans 6-8: `foreign_invest`, `export`, `employment` modules.
- Plan 9: roll out to 12 non-Navoi regions.
- Plan 10: Filament admin UI / promote-reject flow.
- Plan 11: cross-region contamination detector.
- **`commissioning_year_value`** — workbook column exists but no cube schema column. Future plan can add `count_extra_2_value numeric` or a sibling table if dashboard ever shows it.
- **молиялаштириш (financing) columns** — workbook has them; importer ignores them since DATA blob only carries absorption. Future plan could add `actual_hokimyat_financing` if dashboard distinguishes the two.
- **Ownership-breakdown rows** (`Вилоят ҳокимлиги буюртмачилигида:`, `Шаҳар-туман ҳокимликлари буюртмачилигида:`) — currently skipped silently. Future plan could capture them in a dedicated `budget_invest_ownership_breakdown` table if the dashboard wants drill-down.

## 9. Migration plan summary (no migrations)

5 new files, 3 modified files. Estimated 6 tasks for the implementation plan, with one buffer task budgeted for column-position discoveries (cols M+ unverified). Total estimated plan length: 700-900 lines.
