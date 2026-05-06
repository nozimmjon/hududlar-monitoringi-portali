# Export + Employment Modules Importer — Combined Design

**Date:** 2026-05-06
**Status:** Approved through Sections 1-4 in brainstorming. Awaiting user spec review before implementation plan.
**Branch:** `v7-design-polish`
**Scope:** Two modules — `export` and `employment` — combined into one plan because they share infrastructure-touch files (SheetResolver SIGNATURES, IndicatorSeeder, ImportRegionCommand registry). After this plan, all 7 source modules ship. Andijan parity tests for both modules. **First sentinel-handling implementation** for poverty's `холи ҳудуд` values.

**Predecessors:**
- Schema: `docs/superpowers/specs/2026-05-05-fact-tables-and-import-schema-design.md` (Plan 1)
- Importer infrastructure: `docs/superpowers/specs/2026-05-06-importer-tracer-bullet-design.md` (Plan 2)
- Module precedents: Plans 3 (inflation), 4 (budget), 5 (budget_invest), 6 (foreign_invest)

---

## 1. Context

These are the last two source modules. After this plan ships, the importer covers all 7 logical modules of the source workbooks for Andijan. Plan 9 then rolls out to the 12 non-Navoi regions; Plan 10 adds Filament admin UI.

**Combined into one plan** because both touch the same three infrastructure files (SheetResolver, IndicatorSeeder, ImportRegionCommand). Combining avoids two separate brainstorm/plan/review cycles for ~10 tasks of clearly-templated work.

The two modules are independent — different parsers, different sheets, different DTOs (both reuse IndicatorFactDto). Parallelism is at the design level only; implementation is sequential per task to avoid file-conflict races.

### Two notable novelties

1. **Employment writes 6 distinct indicators from one workbook.** Every prior module wrote rows for one `indicator_code` (or one `indicator_code='budget_investment'`-shaped pattern). Employment fans out: `unemployment`, `poverty`, `jobs`, `legalization`, `mfy_clear`, `microprojects` — all from one sheet.

2. **First sentinel handling.** Poverty's `_year` cell is sometimes the string `"холи ҳудуд"` (poverty-free zone) instead of a number. The schema's `is_sentinel` + `sentinel_label` columns from Plan 1 finally get exercised. Parser reads the string, emits a sentinel DTO, raises an `IssueKind::Sentinel` issue (severity=medium) so operators see the cell during review.

## 2. Constraints

- **Andijan only.** Plan 9 onboards the 12 non-Navoi regions.
- **Sheet-name variance for both modules** resolved by content-pattern signatures.
- **`scoreSheet` row-range cap** (Plan 5 lesson): only rows 1-5 plain-string cells are scanned. Signature substrings must come from those cells.
- **Strict rollup detection** (Plan 6 lesson): use `strlen($trimmed) <= 40 && str_ends_with($trimmed, 'вилояти')` (or equivalent), not `str_contains`. Loose contains-based checks falsely match long title rows.
- **Step 0 column inspection mandatory** for both parsers — Plan 1's tmp_inspect.py only dumped 12 cols; some columns (microprojects, year_expected for export) live in cols M+ that need confirmation.
- **`actual_hokimyat` is NULL** across both modules. These workbooks carry forecast / expected / target values. Real actuals come from Statkom later (out of scope).

## 3. Architecture deltas

**New files (12):**
- `backend/app/Services/Import/Modules/ExportModuleParser.php`
- `backend/app/Services/Import/Modules/EmploymentModuleParser.php`
- `backend/tests/Feature/Import/IndicatorSeederExportPeriodsTest.php`
- `backend/tests/Feature/Import/SheetResolverExportTest.php`
- `backend/tests/Feature/Import/SheetResolverEmploymentTest.php`
- `backend/tests/Feature/Import/ExportModuleParserTest.php`
- `backend/tests/Feature/Import/EmploymentModuleParserTest.php`
- `backend/tests/Feature/Import/EmploymentSentinelTest.php`
- `backend/tests/Feature/Import/ImportRegionCommandExportTest.php`
- `backend/tests/Feature/Import/ImportRegionCommandEmploymentTest.php`
- `backend/tests/Feature/Import/AndijanExportParityTest.php`
- `backend/tests/Feature/Import/AndijanEmploymentParityTest.php`

**Modified (3):**
- `backend/database/seeders/IndicatorSeeder.php` (only `export` needs supported_periods update — others are already correct)
- `backend/app/Services/Import/SheetResolver.php` (add 2 SIGNATURES — `export`, `employment`)
- `backend/app/Console/Commands/ImportRegionCommand.php` (register 2 parsers)

No new DTO. No schema changes. No migrations.

## 4. Export module

### Per-period mapping (asymmetric)

DATA blob has `year_forecast` as the only plan-like value; q1 and h1 carry only growth and counts:

| Period | plan_value | expected_value | actual_hokimyat | growth_pct | count_extra |
| --- | --- | --- | --- | --- | --- |
| `q1` | NULL | NULL | `q1_value` | `q1_growth` | `q1_exporters` |
| `h1` | NULL | `h1_expected` | NULL | `h1_growth` | `h1_exporters` |
| `year` | `year_forecast` | `year_expected` | NULL | `year_growth` | `year_exporters` |

All rows: `indicator_code='export'`, `unit='минг доллар'`, `region_code=ctx.regionCode()`. Total **51 staging rows** (1 rollup × 3 + 16 districts × 3).

### Sheet variance

`5-жадвал` (most regions), `5-жадвал (2)` (Jizzakh — typo), `5.1-жадвал` (Qashqadaryo, Surkhandarya). Content-pattern signatures handle all three.

### Column constants (verify in Step 0)

From Plan 1 inspection of Andijan sheet `5-жадвал` row 6 rollup:
```
A1='Андижон вилояти' B2='' C3=967178.43 D4=260 E5=196620.25 F6=173.71 G7=83432.14
H8=275 I9=361620.88 J10=121 K11=62980.18 L12=400
```

Tentative mapping:
- A(1) = "Андижон вилояти" rollup label / district № for districts
- B(2) = district name
- C(3) = `year_forecast`
- D(4) = `q1_exporters`, E(5) = `q1_value`, F(6) = `q1_growth`, G(7) = q1 difference (IGNORE)
- H(8) = `h1_exporters`, I(9) = `h1_expected`, J(10) = `h1_growth`, K(11) = h1 difference (IGNORE)
- L(12) = `year_exporters`, then year_expected, year_growth in cols M-O somewhere — **verify**

### Rollup row detection

Apply Plan 6's strict rule:
```php
if (is_string($colA)) {
    $t = trim($colA);
    if (strlen($t) <= 40 && str_ends_with($t, 'вилояти')) return 'rollup';
}
```

### Indicator catalog change

`export` indicator currently has `supported_periods = ["q1","h1","m9","year"]` (default). Update to `["q1","h1","year"]`.

## 5. Employment module

### One workbook → 6 indicators

Each rollup or district row produces **12 IndicatorFactDtos** (6 indicators × 2 periods). Total: 17 entities × 12 = **204 staging rows**.

### Column mapping (Andijan sheet `6. Камбағаллик`)

Plan 1 inspection shows row 4 has indicator headers, row 6 has period sub-headers (h1/year), row 7 = ЖАМИ rollup. 6 pairs of (h1, year):

| Cols | Indicator | Unit |
| --- | --- | --- |
| C(3), D(4) | `unemployment` | `%` |
| E(5), F(6) | `poverty` | `%` |
| G(7), H(8) | `mfy_clear` | `count` |
| I(9), J(10) | `jobs` | `минг нафар` |
| K(11), L(12) | `legalization` | `минг нафар` |
| M(13), N(14) | `microprojects` | `count` (verify in Step 0 — Plan 1 didn't dump cols past L) |

Per-period mapping:
- `plan_value` = the cell value (or NULL if sentinel)
- `expected_value` = NULL
- `actual_hokimyat` = NULL
- `growth_pct` = NULL
- `pct_of_plan` = NULL
- `count_extra` = NULL
- `unit` = the indicator's `default_unit` from the catalog

### Rollup row detection

Different from prior modules. Col A has `"ЖАМИ"` (uppercase, exact match). Col B is empty.
```php
if (is_string($colA) && trim($colA) === 'ЖАМИ') return 'rollup';
```

### Sentinel handling

When a cell value is a string containing `'холи ҳудуд'` (poverty-free zone — refers to a district that has no measurable poverty), the parser:

1. Emits an IndicatorFactDto with:
   - `planValue=NULL`
   - `isSentinel=true`
   - `sentinelLabel='холи ҳудуд'`
   - All other facets NULL
2. Calls `$issueCollector->add(IssueKind::Sentinel, IssueSeverity::Medium, ...)` so operators see the cell during review.

```php
private function isSentinel(mixed $value): bool
{
    return is_string($value) && str_contains($value, 'холи ҳудуд');
}
```

The cube schema already supports `is_sentinel` boolean + `sentinel_label` varchar (Plan 1 schema spec section 6).

### Sheet variance

`6. Камбағаллик` (most regions), `7. Камбағаллик` (Bukhara, Jizzakh, Navoi, Namangan, Sirdarya, Tashkent oblast, Khorezm — 7 regions), `Камбағаллик` (Qashqadaryo). Content-pattern matching handles all three.

## 6. CLI integration

```php
$parsers = [
    'macro'          => new MacroModuleParser(...),
    'inflation'      => new InflationModuleParser(...),
    'budget'         => new BudgetModuleParser(...),
    'budget_invest'  => new BudgetInvestModuleParser(...),
    'foreign_invest' => new ForeignInvestModuleParser(...),
    'export'         => new ExportModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'employment'     => new EmploymentModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

Usage:
```
php artisan import:region andijan 2026 --module=export       # 51 rows
php artisan import:region andijan 2026 --module=employment   # 204 rows + N sentinel issues
php artisan import:region andijan 2026                       # all 7 modules: 212 + 28 + 51 + 51 + 51 + 51 + 204 = 648 rows
```

## 7. Parity test design

**`AndijanExportParityTest`** — mirrors Plan 6's foreign_invest test but with growth_pct in place of pct_of_plan. Per-period facet asymmetry:
- q1: actual_hokimyat=q1_value, expected=NULL, plan=NULL
- h1: expected=h1_expected, actual=NULL, plan=NULL
- year: plan=year_forecast, expected=year_expected, actual=NULL

Tolerance `0.5` for value floats, `0.05` for growth_pct, exact for integer counts.

**`AndijanEmploymentParityTest`** — iterates 6 DATA-blob keys × 2 periods × 17 entities. Distinguishes sentinel-string vs numeric:

```php
if (is_string($expectedValue) && str_contains($expectedValue, 'холи ҳудуд')) {
    expect($actual->is_sentinel)->toBeTrue();
    expect($actual->sentinel_label)->toContain('холи ҳудуд');
    expect($actual->plan_value)->toBeNull();
} else {
    expect($actual->is_sentinel)->toBeFalse();
    expect($actual->plan_value)->toBeNumericallyClose($expectedValue, 0.05);
}
```

Plus `data_quality_issues` count assertion — Andijan has at least one sentinel (`Андижон шаҳри` poverty_year).

**`EmploymentSentinelTest`** — focused test for sentinel detection logic (parser-level, before parity). Asserts the parser correctly reads `'холи ҳудуд'` from real workbook cells, produces sentinel DTOs, and raises issues. Lets sentinel handling get standalone test coverage that doesn't depend on parity-test scaffolding.

## 8. Scope guardrails

- **No actuals from Statkom.** All `actual_hokimyat` rows are NULL. Statkom integration is Plan 11+.
- **Microprojects column position unverified.** Step 0 inspection in EmploymentModuleParser task confirms.
- **Employment unit field varies per indicator.** Pull from `Indicator::where('code', $code)->first()->default_unit` at DTO construction.
- **Difference columns ignored.** Export workbook cols G/K hold `(+;-)` differences not in DATA blob.
- **2025 prior-year columns ignored.** Export workbook tracks year-over-year deltas; DATA blob doesn't.

## 9. Out of scope (deferred)

- **Plan 9:** roll out to 12 non-Navoi regions.
- **Plan 10:** Filament admin UI / promote-reject flow.
- **Plan 11:** Cross-region contamination detector (Navoi unblock) + Statkom actual_statkom integration.
- **Microprojects unit may be more nuanced than `count`** — DATA blob shows e.g. `microprojects_h1=3834` (looks like project count). Catalog has `default_unit='count'` already; verify it's correct during Step 0.

## 10. Migration plan summary (no migrations)

12 new files, 3 modified files. Estimated ~12 tasks for the combined implementation plan, with two buffer tasks for column-position discoveries (microprojects, export year_expected/year_growth). Total estimated plan length: 1300-1600 lines.
