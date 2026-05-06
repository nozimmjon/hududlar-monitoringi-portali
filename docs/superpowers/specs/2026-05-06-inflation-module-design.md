# Inflation Module Importer — Design

**Date:** 2026-05-06
**Status:** Approved through Sections 1-3 in brainstorming. Awaiting user spec review before implementation plan.
**Branch:** `v7-design-polish`
**Scope:** Add the `inflation` module to the importer. Two source sheets (`1.1 Баланс` and `1.2 Омборлар`) write to the existing `food_balance` and `warehouses` production-shape tables (via their staging twins). Andijan parity assertions for both. Subsequent plans (4-8) extend to the remaining 5 modules.

**Predecessors:**
- Schema: `docs/superpowers/specs/2026-05-05-fact-tables-and-import-schema-design.md` (Plan 1, schema with `food_balance`, `warehouses`, `import_staging_food_balance`, `import_staging_warehouses` tables).
- Importer infrastructure: `docs/superpowers/specs/2026-05-06-importer-tracer-bullet-design.md` (Plan 2, the `WorkbookLocator`, `SheetResolver`, `HeaderDetector`, `DistrictResolver`, `StagingWriter`, `IssueCollector`, `ModuleParser` base, and the `MacroModuleParser` example).

---

## 1. Context

Plan 2 built the entire importer pipeline and validated it end-to-end via the Andijan macro module (212 staged rows, 1394 assertions all green). The shared infrastructure handles sheet resolution by content-pattern, header detection, district name matching against `alt_labels`, buffered bulk inserts, and `data_quality_issues` collection. It is genuinely reusable.

This plan adds **one new `ModuleParser` subclass**, two new DTOs, two parity tests, and a one-line registration into `ImportRegionCommand`'s parser registry. No new schema, no new migrations, no new shared services.

The inflation workbook differs structurally from macro:
- It writes to **two production-shape tables** (food_balance, warehouses) — not the indicator_facts cube.
- Sheet `1.1 Баланс` is **region-only** (no district dimension; food balance is a regional rollup).
- Sheet `1.2 Омборлар` has a district dimension AND an explicit region rollup row at the top — the parser writes both as `warehouses` rows (district_code NULL for the rollup, district_code set for the 16 district rows).

## 2. Constraints

- **Tracer-bullet scope:** Andijan only. Plan 9 rolls out to other regions.
- **Module:** `inflation` only. The other 5 modules (budget, budget_invest, foreign_invest, export, employment) are Plans 4-8.
- **No sentinel handling.** The string `"холи ҳудуд"` doesn't appear in inflation sheets. First exposure is Plan 8 (employment).
- **`year_end_stock` is NOT asserted in parity.** The food_balance DATA blob shows derived values (e.g. Ун has year_end_stock=23.7) but the workbook column producing that value isn't documented or visible in the standard balance row layout. Importer reads NULL for now; follow-up plan resolves the derivation when source documentation is clearer or other regions' workbooks reveal the column.
- **Sheet-name variance** (from the cross-region survey in `tmp_analysis_all.txt`): Andijan uses `1.1. Баланс` / `1.2. Омборлар`; Qashqadaryo and Surkhandarya use `2.1./2.2.`; Samarkand/Tashkent obl/Khorezm/Tashkent city use `1.2. Омборлар (2)` (duplicate). The hybrid `SheetResolver` from Plan 2 handles this via content-pattern signatures. We add 2 new `logical_kind` entries; signature strings cover all 4 patterns.

## 3. Architecture deltas

**New files:**
- `backend/app/Services/Import/Modules/InflationModuleParser.php`
- `backend/app/Support/Import/FoodBalanceDto.php`
- `backend/app/Support/Import/WarehouseDto.php`
- `backend/tests/Feature/Import/InflationModuleParserTest.php`
- `backend/tests/Feature/Import/AndijanFoodBalanceParityTest.php`
- `backend/tests/Feature/Import/AndijanWarehousesParityTest.php`

**Modified:**
- `backend/app/Console/Commands/ImportRegionCommand.php` — one new entry in the parser registry (`'inflation' => new InflationModuleParser(...)`).
- `backend/app/Services/Import/SheetResolver.php` — two new entries in `SIGNATURES`:
  ```php
  'food_balance'              => ['Балансини асос', 'Маҳсулот номи'],
  'warehouses_district_table' => ['Захира омборлари', 'совутгичли омборлар'],
  ```

No schema changes, no migrations, no new services.

## 4. InflationModuleParser

```php
class InflationModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'inflation'; }

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $count = 0;
        $count += $this->parseFoodBalance($ctx, $book, $regionWorkbookId, $filePath);
        $count += $this->parseWarehouses($ctx, $book, $regionWorkbookId, $filePath);
        return $count;
    }
}
```

### `parseFoodBalance`

Resolves sheet via `SheetResolver::resolve($ctx, $book, $rwbId, 'inflation', 'food_balance')`. Detects header row via `HeaderDetector` (looks for product names; `1.1 Баланс` data starts at row 6).

Walks rows 6 → 6+15 (12 products + buffer for safety). For each row where col A is integer (product number) and col B is a string (product name):

| Workbook col | DTO field |
| --- | --- |
| C | `resourceTotal` |
| D | `yearStartStock` |
| E | `production` |
| F | `importVolume` |
| G | `useTotal` |
| H | `useHousehold` |
| I | `useProcessing` |
| J | `useOther` |
| K | `perCapitaNorm` |
| L | `perCapitaBalance` |

**Derived:** `localSupplyRatio = production / useTotal` when `useTotal > 0`, else NULL.
**Not derived (NULL):** `yearEndStock`. Documented as a known gap.

Buffers 12 `FoodBalanceDto` rows (one per product) into `import_staging_food_balance`.

### `parseWarehouses`

Resolves sheet via `SheetResolver::resolve($ctx, $book, $rwbId, 'inflation', 'warehouses_district_table')`. Detects header row.

The first data-area row is a region rollup (col A empty, col B contains "вилояти"). The parser emits **one warehouses row with `districtCode=NULL`** for it.

Subsequent rows (col A integer 1..16, col B district name) go through `DistrictResolver`. Same column layout for the rollup row and district rows:

| Workbook col | DTO field |
| --- | --- |
| C | `reserveWarehouses` |
| D | `reserveCapacityT` |
| E | `coldStorageCount` |
| F | `coldStorageCapacityT` |
| G | `newSmallColdCount` |
| H | `newSmallColdCapacityT` |
| I | `newSmallColdMfys` |
| J | `newLargeColdCount` |
| K | `newLargeColdCapacityT` |

Empty cells → NULL on the DTO field.

Buffers 17 `WarehouseDto` rows (1 rollup + 16 districts) into `import_staging_warehouses`.

### Total expected for Andijan inflation: **29 rows**.

## 5. DTOs

### `FoodBalanceDto`

```php
final readonly class FoodBalanceDto
{
    public function __construct(
        public string $regionCode,
        public int $year,
        public string $product,
        public int $productSortOrder,
        public ?float $resourceTotal,
        public ?float $yearStartStock,
        public ?float $production,
        public ?float $importVolume,
        public ?float $useTotal,
        public ?float $useHousehold,
        public ?float $useProcessing,
        public ?float $useOther,
        public ?float $perCapitaNorm,
        public ?float $perCapitaBalance,
        public ?float $localSupplyRatio,
        public ?float $yearEndStock,
        public string $sourceLabel,
    ) {}

    public function toStagingRow(int $importRunId): array;
    // Returns row array matching import_staging_food_balance schema,
    // with staging_status='pending' and created_at/updated_at = now().
}
```

### `WarehouseDto`

```php
final readonly class WarehouseDto
{
    public function __construct(
        public string $regionCode,
        public ?string $districtCode,        // NULL = region rollup
        public int $year,
        public ?int $reserveWarehouses,
        public ?int $reserveCapacityT,
        public ?int $coldStorageCount,
        public ?int $coldStorageCapacityT,
        public ?int $newSmallColdCount,
        public ?int $newSmallColdCapacityT,
        public ?int $newSmallColdMfys,
        public ?int $newLargeColdCount,
        public ?int $newLargeColdCapacityT,
        public string $sourceLabel,
    ) {}

    public function toStagingRow(int $importRunId): array;
}
```

Both DTOs follow the `IndicatorFactDto` pattern from Plan 2: `final readonly`, all-nullable except identity columns, and `toStagingRow($importRunId)` that returns a flat row array including `staging_status='pending'`, `created_at`, `updated_at`.

## 6. CLI integration

`ImportRegionCommand` adds one line:

```php
$parsers = [
    'macro'     => new MacroModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'inflation' => new InflationModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

Usage:
```
php artisan import:region andijan 2026 --module=inflation       # inflation only
php artisan import:region andijan 2026                           # macro + inflation in one run
```

The existing `--dry-run` flag, Navoi-skip path, and `import_runs.status` decision logic from Plan 2 work unchanged.

## 7. Parity tests

Two tests, split for diagnosability — when one fails, the other still runs and reports independently.

### `AndijanFoodBalanceParityTest`

```
1. RefreshDatabase + seed.
2. Artisan::call('import:region', ['region_code'=>'andijan', 'year'=>2026, '--module'=>'inflation']).
3. Read import_staging_food_balance for the latest import_run_id.
4. Extract DATA.regional.food_balance from index.html (12 products).
5. For each expected product, find the staging row by `product` field. Assert:
     resource_total, production, import_volume, use_total within tolerance 0.05.
     local_supply_ratio within tolerance 0.05.
     (year_end_stock skipped per the known gap.)
6. Assert ImportRun::latest()->status === awaiting_review and issues_blocker_count === 0.
7. Assert exact row count: 12 food_balance staging rows.
```

### `AndijanWarehousesParityTest`

```
1. RefreshDatabase + seed.
2. Artisan::call(... '--module' => 'inflation').
3. Read import_staging_warehouses for the latest run.
4. Extract DATA.districts[*].data.warehouses from index.html (16 districts).
5. For each expected district:
     find staging row by district_code (resolved via andijanDistrictCode helper from Plan 2's parity test).
     assert the 4 always-present integer fields:
        reserve_warehouses, reserve_capacity_t, cold_storage_count, cold_storage_capacity_t.
     assert new_small_cold_storage_count and new_large_cold_storage_count
       (DATA blob has these as nullable; tolerate NULL on either side).
6. Assert exact row count: 17 (1 rollup + 16 districts).
```

Both tests use `toBeNumericallyClose` (custom Pest expectation from Plan 2) with tolerance `0.05` for floats and exact integer equality for the warehouse counts.

## 8. Out of scope (deferred)

- **`year_end_stock` derivation in food_balance.** Importer leaves NULL. Follow-up plan resolves once source is clearer.
- **Other 5 modules** — Plans 4-8.
- **Roll out to other 12 regions** — Plan 9.
- **Filament admin UI / promote-reject flow** — Plan 10.
- **Sentinel handling** — Plan 8 (employment, where `холи ҳудуд` first appears).

## 9. Migration plan summary (no migrations)

This plan creates 6 new files and modifies 2 existing files. Estimated 10 tasks for the implementation plan, plus 1-2 small fix tasks budgeted for real-data discoveries (Plan 2 had 2 such fixes — formula strings in col A, missing alt_label spellings — which we should expect again). Total estimated plan length: 700-900 lines.
