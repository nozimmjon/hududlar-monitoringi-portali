# Inflation Module Importer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `php artisan import:region andijan 2026 --module=inflation` produce 29 staging rows for Andijan — 12 food_balance products + 17 warehouses (1 region rollup + 16 districts) — that reproduce the inlined `DATA.regional.food_balance` and `DATA.districts[*].data.warehouses` blocks in `index.html` within numeric tolerance.

**Architecture:** Adds one new `ModuleParser` subclass (`InflationModuleParser`) on top of the Plan 2 infrastructure. Two new DTOs (`FoodBalanceDto`, `WarehouseDto`). One-line addition to `ImportRegionCommand`'s parser registry. Two new entries in `SheetResolver::SIGNATURES`. Zero schema changes, zero migrations.

**Tech Stack:** Laravel 12.58 · PHP 8.2 · PostgreSQL 14 · Pest 3 · `phpoffice/phpspreadsheet` (already installed).

**Working directory:** All paths relative to `backend/` unless prefixed with `../`. Run all `php artisan`, `composer`, and `vendor/bin/pest` commands from `backend/`. The Andijan inflation workbook lives at `../data/2. Андижон/2.1-2.2-жадваллар (инфляция).xlsx` (gitignored, present locally). The parity-test target `index.html` is at `../index.html`.

**TDD discipline:** Each task writes the failing test first, runs it to confirm failure, writes the minimal implementation, runs the test to confirm pass, commits. Tests run against the Postgres test database `hududlar_monitoringi_test` (configured in `phpunit.xml` from Plan 1). 73 tests + 1394 assertions currently passing — every task must keep that count green.

---

## File map

**Created:**
- `backend/app/Support/Import/FoodBalanceDto.php`
- `backend/app/Support/Import/WarehouseDto.php`
- `backend/app/Services/Import/Modules/InflationModuleParser.php`
- `backend/tests/Feature/Import/FoodBalanceDtoTest.php`
- `backend/tests/Feature/Import/WarehouseDtoTest.php`
- `backend/tests/Feature/Import/SheetResolverInflationTest.php`
- `backend/tests/Feature/Import/InflationModuleParserTest.php`
- `backend/tests/Feature/Import/AndijanFoodBalanceParityTest.php`
- `backend/tests/Feature/Import/AndijanWarehousesParityTest.php`

**Modified:**
- `backend/app/Services/Import/SheetResolver.php` (Task 3: 2 new SIGNATURES entries)
- `backend/app/Console/Commands/ImportRegionCommand.php` (Task 5: 1 new parser-registry entry)

---

## Task 1: `FoodBalanceDto`

**Files:**
- Create: `backend/app/Support/Import/FoodBalanceDto.php`
- Create: `backend/tests/Feature/Import/FoodBalanceDtoTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/FoodBalanceDtoTest.php`:

```php
<?php

use App\Support\Import\FoodBalanceDto;

test('FoodBalanceDto.toStagingRow produces a complete row array', function () {
    $dto = new FoodBalanceDto(
        regionCode: 'andijan', year: 2026, product: 'Ун', productSortOrder: 1,
        resourceTotal: 430.27, yearStartStock: 21.84,
        production: 368.34, importVolume: 40.09,
        useTotal: 260.82, useHousehold: 86.93,
        useProcessing: 173.89, useOther: null,
        perCapitaNorm: null, perCapitaBalance: null,
        localSupplyRatio: 1.41, yearEndStock: null,
        sourceLabel: '2.1-2.2-жадваллар (инфляция).xlsx · 1.1. Баланс · row 6',
    );

    $row = $dto->toStagingRow(importRunId: 99);

    expect($row['import_run_id'])->toBe(99);
    expect($row['region_code'])->toBe('andijan');
    expect($row['year'])->toBe(2026);
    expect($row['product'])->toBe('Ун');
    expect($row['product_sort_order'])->toBe(1);
    expect((float) $row['resource_total'])->toBe(430.27);
    expect((float) $row['year_start_stock'])->toBe(21.84);
    expect((float) $row['production'])->toBe(368.34);
    expect((float) $row['import_volume'])->toBe(40.09);
    expect((float) $row['use_total'])->toBe(260.82);
    expect((float) $row['local_supply_ratio'])->toBe(1.41);
    expect($row['year_end_stock'])->toBeNull();
    expect($row['use_other'])->toBeNull();
    expect($row['per_capita_norm'])->toBeNull();
    expect($row['source_label'])->toContain('1.1. Баланс');
    expect($row['staging_status'])->toBe('pending');
    expect($row['created_at'])->not->toBeNull();
    expect($row['updated_at'])->not->toBeNull();
});

test('FoodBalanceDto handles all-nullable optional fields', function () {
    $dto = new FoodBalanceDto(
        regionCode: 'andijan', year: 2026, product: 'Шакар', productSortOrder: 3,
        resourceTotal: null, yearStartStock: null,
        production: null, importVolume: null,
        useTotal: null, useHousehold: null,
        useProcessing: null, useOther: null,
        perCapitaNorm: null, perCapitaBalance: null,
        localSupplyRatio: null, yearEndStock: null,
        sourceLabel: 'fixture',
    );

    $row = $dto->toStagingRow(1);

    expect($row['resource_total'])->toBeNull();
    expect($row['use_total'])->toBeNull();
    expect($row['local_supply_ratio'])->toBeNull();
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=FoodBalanceDto`
Expected: FAIL with "Class App\Support\Import\FoodBalanceDto not found".

- [ ] **Step 3: Create the DTO**

Create `backend/app/Support/Import/FoodBalanceDto.php`:

```php
<?php

namespace App\Support\Import;

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

    public function toStagingRow(int $importRunId): array
    {
        $now = now();
        return [
            'import_run_id'      => $importRunId,
            'region_code'        => $this->regionCode,
            'year'               => $this->year,
            'product'            => $this->product,
            'product_sort_order' => $this->productSortOrder,
            'resource_total'     => $this->resourceTotal,
            'year_start_stock'   => $this->yearStartStock,
            'production'         => $this->production,
            'import_volume'      => $this->importVolume,
            'use_total'          => $this->useTotal,
            'use_household'      => $this->useHousehold,
            'use_processing'     => $this->useProcessing,
            'use_other'          => $this->useOther,
            'per_capita_norm'    => $this->perCapitaNorm,
            'per_capita_balance' => $this->perCapitaBalance,
            'local_supply_ratio' => $this->localSupplyRatio,
            'year_end_stock'     => $this->yearEndStock,
            'source_label'       => $this->sourceLabel,
            'staging_status'     => 'pending',
            'created_at'         => $now,
            'updated_at'         => $now,
        ];
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=FoodBalanceDto`
Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Support/Import/FoodBalanceDto.php \
    backend/tests/Feature/Import/FoodBalanceDtoTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add FoodBalanceDto

Readonly DTO buffering food_balance staging rows for the inflation
module. Mirrors IndicatorFactDto pattern; all numeric fields
nullable; toStagingRow returns the full row array including
staging_status='pending' and created_at/updated_at timestamps.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: `WarehouseDto`

**Files:**
- Create: `backend/app/Support/Import/WarehouseDto.php`
- Create: `backend/tests/Feature/Import/WarehouseDtoTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/WarehouseDtoTest.php`:

```php
<?php

use App\Support\Import\WarehouseDto;

test('WarehouseDto.toStagingRow produces complete row for a district', function () {
    $dto = new WarehouseDto(
        regionCode: 'andijan', districtCode: 'd01', year: 2026,
        reserveWarehouses: 3, reserveCapacityT: 600,
        coldStorageCount: 10, coldStorageCapacityT: 10000,
        newSmallColdCount: null, newSmallColdCapacityT: null, newSmallColdMfys: null,
        newLargeColdCount: null, newLargeColdCapacityT: null,
        sourceLabel: '2.1-2.2-жадваллар (инфляция).xlsx · 1.2. Омборлар · row 8',
    );

    $row = $dto->toStagingRow(importRunId: 5);

    expect($row['import_run_id'])->toBe(5);
    expect($row['region_code'])->toBe('andijan');
    expect($row['district_code'])->toBe('d01');
    expect($row['year'])->toBe(2026);
    expect($row['reserve_warehouses'])->toBe(3);
    expect($row['reserve_capacity_t'])->toBe(600);
    expect($row['cold_storage_count'])->toBe(10);
    expect($row['cold_storage_capacity_t'])->toBe(10000);
    expect($row['new_small_cold_count'])->toBeNull();
    expect($row['new_large_cold_count'])->toBeNull();
    expect($row['source_label'])->toContain('1.2. Омборлар');
    expect($row['staging_status'])->toBe('pending');
});

test('WarehouseDto allows null district_code for region rollup row', function () {
    $dto = new WarehouseDto(
        regionCode: 'andijan', districtCode: null, year: 2026,
        reserveWarehouses: 89, reserveCapacityT: 36321,
        coldStorageCount: 320, coldStorageCapacityT: 109235,
        newSmallColdCount: 1, newSmallColdCapacityT: 80, newSmallColdMfys: 1,
        newLargeColdCount: 32, newLargeColdCapacityT: 8730,
        sourceLabel: '2.1-2.2-жадваллар (инфляция).xlsx · 1.2. Омборлар · row 5',
    );

    $row = $dto->toStagingRow(1);

    expect($row['district_code'])->toBeNull();
    expect($row['reserve_warehouses'])->toBe(89);
    expect($row['new_large_cold_count'])->toBe(32);
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=WarehouseDto`
Expected: FAIL with "Class App\Support\Import\WarehouseDto not found".

- [ ] **Step 3: Create the DTO**

Create `backend/app/Support/Import/WarehouseDto.php`:

```php
<?php

namespace App\Support\Import;

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

    public function toStagingRow(int $importRunId): array
    {
        $now = now();
        return [
            'import_run_id'              => $importRunId,
            'region_code'                => $this->regionCode,
            'district_code'              => $this->districtCode,
            'year'                       => $this->year,
            'reserve_warehouses'         => $this->reserveWarehouses,
            'reserve_capacity_t'         => $this->reserveCapacityT,
            'cold_storage_count'         => $this->coldStorageCount,
            'cold_storage_capacity_t'    => $this->coldStorageCapacityT,
            'new_small_cold_count'       => $this->newSmallColdCount,
            'new_small_cold_capacity_t'  => $this->newSmallColdCapacityT,
            'new_small_cold_mfys'        => $this->newSmallColdMfys,
            'new_large_cold_count'       => $this->newLargeColdCount,
            'new_large_cold_capacity_t'  => $this->newLargeColdCapacityT,
            'source_label'               => $this->sourceLabel,
            'staging_status'             => 'pending',
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ];
    }
}
```

- [ ] **Step 4: Run, confirm passes**

Run: `vendor/bin/pest --filter=WarehouseDto`
Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Support/Import/WarehouseDto.php \
    backend/tests/Feature/Import/WarehouseDtoTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add WarehouseDto

Readonly DTO for warehouses staging rows. district_code nullable
for region rollup rows.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: SheetResolver signatures for inflation

**Files:**
- Modify: `backend/app/Services/Import/SheetResolver.php`
- Create: `backend/tests/Feature/Import/SheetResolverInflationTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/SheetResolverInflationTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\SheetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

function loadAndijanInflation(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/2.1-2.2-жадваллар (инфляция).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan inflation workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    return $reader->load($path);
}

function makeInflationCtx(): array
{
    $region = Region::where('code', 'andijan')->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'inflation')->value('id'),
        'file_name' => '2.1-2.2-жадваллар (инфляция).xlsx',
        'file_path' => 'fixture',
        'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region, year: 2026, dataPath: base_path('../data'),
    );
    return ['ctx' => $ctx, 'rwb' => $rwb];
}

test('SheetResolver detects food_balance sheet by content', function () {
    $this->seed();
    $book = loadAndijanInflation();
    ['ctx' => $ctx, 'rwb' => $rwb] = makeInflationCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'inflation', 'food_balance');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('1.1. Баланс');
});

test('SheetResolver detects warehouses_district_table sheet by content', function () {
    $this->seed();
    $book = loadAndijanInflation();
    ['ctx' => $ctx, 'rwb' => $rwb] = makeInflationCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'inflation', 'warehouses_district_table');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('1.2. Омборлар');
});
```

- [ ] **Step 2: Run, confirm it fails**

Run: `vendor/bin/pest --filter=SheetResolverInflation`
Expected: FAIL — both tests raise the SheetMissing blocker because the new logical_kind values aren't in `SIGNATURES`.

- [ ] **Step 3: Add the signatures**

In `backend/app/Services/Import/SheetResolver.php`, find the `private const SIGNATURES = [...]` array. Add two new entries:

```php
        'food_balance'              => ['Балансини асос', 'Маҳсулот номи', 'Ресурс'],
        'warehouses_district_table' => ['Захира омборлари', 'совутгичли омборлар'],
```

The full SIGNATURES array should become:

```php
private const SIGNATURES = [
    'rollup'                    => ['ЯҲМ', 'асосий иқтисодий кўрсаткич'],
    'district_industry'         => ['Саноат маҳсулотларини ишлаб чиқариш'],
    'district_agriculture'      => ['Қишлоқ хўжалиги маҳсулотларини'],
    'district_services'         => ['Бозор хизматлари'],
    'food_balance'              => ['Балансини асос', 'Маҳсулот номи', 'Ресурс'],
    'warehouses_district_table' => ['Захира омборлари', 'совутгичли омборлар'],
];
```

- [ ] **Step 4: Run, confirm passes**

Run: `vendor/bin/pest --filter=SheetResolverInflation`
Expected: 2 tests pass.

- [ ] **Step 5: Run full Inflation Sheet test + existing macro tests, confirm no regression**

Run: `vendor/bin/pest --no-coverage --filter='SheetResolver'`
Expected: all SheetResolver tests pass (Plan 2's macro tests + the 2 new inflation ones).

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/SheetResolver.php \
    backend/tests/Feature/Import/SheetResolverInflationTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add SheetResolver signatures for inflation module

Two new logical_kind entries:
- food_balance — matches '1.1. Баланс' / '2.1. Баланс' patterns.
- warehouses_district_table — matches '1.2. Омборлар' /
  '2.2. Омборлар' / '1.2. Омборлар (2)' patterns.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: `InflationModuleParser`

**Files:**
- Create: `backend/app/Services/Import/Modules/InflationModuleParser.php`
- Create: `backend/tests/Feature/Import/InflationModuleParserTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/InflationModuleParserTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingFoodBalance;
use App\Models\ImportStagingWarehouse;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\InflationModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function inflationParserCtx(): array
{
    $path = base_path('../data/2. Андижон/2.1-2.2-жадваллар (инфляция).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan inflation workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'inflation')->value('id'),
        'file_name' => '2.1-2.2-жадваллар (инфляция).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('InflationModuleParser produces 12 food_balance + 17 warehouses staging rows', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = inflationParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new InflationModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        $districts,
        $writer,
        $issues,
    );

    $parser->parse($ctx, $path, $rwb->id);

    expect($writer->bufferedCount('import_staging_food_balance'))->toBe(12);
    expect($writer->bufferedCount('import_staging_warehouses'))->toBe(17);

    DB::transaction(fn() => $writer->flush());
    $issues->flush();

    expect(ImportStagingFoodBalance::count())->toBe(12);
    expect(ImportStagingWarehouse::count())->toBe(17);

    // Region rollup vs district counts
    expect(ImportStagingWarehouse::whereNull('district_code')->count())->toBe(1);
    expect(ImportStagingWarehouse::whereNotNull('district_code')->count())->toBe(16);

    // Spot-check a food_balance row: Ун (flour, sort_order=1)
    $flour = ImportStagingFoodBalance::where('region_code', 'andijan')
        ->where('product', 'Ун')->first();
    expect($flour)->not->toBeNull();
    expect($flour->resource_total)->toBeNumericallyClose(430.27, 0.05);
    expect($flour->production)->toBeNumericallyClose(368.34, 0.05);

    // Spot-check a warehouse row: Andijan city (d01)
    $andijanCity = ImportStagingWarehouse::where('region_code', 'andijan')
        ->where('district_code', 'd01')->first();
    expect($andijanCity)->not->toBeNull();
    expect($andijanCity->reserve_warehouses)->toBe(3);
    expect($andijanCity->cold_storage_count)->toBe(10);

    // Region rollup row
    $rollup = ImportStagingWarehouse::where('region_code', 'andijan')
        ->whereNull('district_code')->first();
    expect($rollup)->not->toBeNull();
    expect($rollup->reserve_warehouses)->toBe(89);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `vendor/bin/pest --filter=InflationModuleParser`
Expected: FAIL — Class App\Services\Import\Modules\InflationModuleParser not found.

- [ ] **Step 3: Create `InflationModuleParser`**

Create `backend/app/Services/Import/Modules/InflationModuleParser.php`:

```php
<?php

namespace App\Services\Import\Modules;

use App\Models\RegionWorkbookSheet;
use App\Services\Import\ImportContext;
use App\Support\Import\FoodBalanceDto;
use App\Support\Import\WarehouseDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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

    private function parseFoodBalance(
        ImportContext $ctx,
        Spreadsheet $book,
        int $rwbId,
        string $filePath,
    ): int {
        $sheet = $this->sheetResolver->resolve($ctx, $book, $rwbId, 'inflation', 'food_balance');
        if ($sheet === null) return 0;

        $rwSheet = RegionWorkbookSheet::where('region_workbook_id', $rwbId)
            ->where('logical_kind', 'food_balance')->firstOrFail();
        $startRow = $this->headerDetector->detect($sheet, $ctx, $rwSheet->id);
        if ($startRow === null) return 0;

        $count = 0;
        // 12 products with a buffer for safety
        for ($row = $startRow; $row <= $startRow + 20; $row++) {
            $colA = $sheet->getCell([1, $row])->getValue();
            $colB = $sheet->getCell([2, $row])->getValue();

            // Product rows: col A is integer (1, 2, 3, ...) or numeric string ("1.", "2.")
            // Stop when we hit a row with neither.
            $isProductRow = false;
            if (is_int($colA)) {
                $isProductRow = true;
            } elseif (is_string($colA) && preg_match('/^\d+\.?$/', trim($colA))) {
                $isProductRow = true;
            }
            if (! $isProductRow || ! is_string($colB) || trim($colB) === '') {
                continue;
            }

            $product = trim($colB);
            $sortOrder = is_int($colA) ? $colA : (int) trim($colA, '.');

            $resourceTotal   = $this->numericOrNull($sheet->getCell([3, $row])->getValue());
            $yearStartStock  = $this->numericOrNull($sheet->getCell([4, $row])->getValue());
            $production      = $this->numericOrNull($sheet->getCell([5, $row])->getValue());
            $importVolume    = $this->numericOrNull($sheet->getCell([6, $row])->getValue());
            $useTotal        = $this->numericOrNull($sheet->getCell([7, $row])->getValue());
            $useHousehold    = $this->numericOrNull($sheet->getCell([8, $row])->getValue());
            $useProcessing   = $this->numericOrNull($sheet->getCell([9, $row])->getValue());
            $useOther        = $this->numericOrNull($sheet->getCell([10, $row])->getValue());
            $perCapitaNorm   = $this->numericOrNull($sheet->getCell([11, $row])->getValue());
            $perCapitaBalance= $this->numericOrNull($sheet->getCell([12, $row])->getValue());

            // Derived: local_supply_ratio = production / use_total (when use_total > 0)
            $localSupplyRatio = ($production !== null && $useTotal !== null && $useTotal > 0)
                ? $production / $useTotal
                : null;

            $dto = new FoodBalanceDto(
                regionCode:        $ctx->regionCode(),
                year:              $ctx->year,
                product:           $product,
                productSortOrder:  $sortOrder,
                resourceTotal:     $resourceTotal,
                yearStartStock:    $yearStartStock,
                production:        $production,
                importVolume:      $importVolume,
                useTotal:          $useTotal,
                useHousehold:      $useHousehold,
                useProcessing:     $useProcessing,
                useOther:          $useOther,
                perCapitaNorm:     $perCapitaNorm,
                perCapitaBalance:  $perCapitaBalance,
                localSupplyRatio:  $localSupplyRatio,
                yearEndStock:      null,    // documented gap
                sourceLabel:       basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            $this->stagingWriter->buffer('import_staging_food_balance', $dto->toStagingRow($ctx->run->id));
            $count++;
        }
        return $count;
    }

    private function parseWarehouses(
        ImportContext $ctx,
        Spreadsheet $book,
        int $rwbId,
        string $filePath,
    ): int {
        $sheet = $this->sheetResolver->resolve($ctx, $book, $rwbId, 'inflation', 'warehouses_district_table');
        if ($sheet === null) return 0;

        $rwSheet = RegionWorkbookSheet::where('region_workbook_id', $rwbId)
            ->where('logical_kind', 'warehouses_district_table')->firstOrFail();
        $startRow = $this->headerDetector->detect($sheet, $ctx, $rwSheet->id);
        if ($startRow === null) return 0;

        $count = 0;
        // The first data-area row is a region rollup (col A empty, col B "вилояти").
        // Subsequent rows are 16 districts (col A integer 1..16).
        // HeaderDetector returns the first row where col A is integer; the rollup row sits
        // at startRow - 1. Detect and emit it explicitly.
        $rollupRow = $startRow - 1;
        $rollupColB = $sheet->getCell([2, $rollupRow])->getValue();
        if (is_string($rollupColB) && str_contains($rollupColB, 'вилояти')) {
            $this->emitWarehouseRow($ctx, $sheet, $rollupRow, null, $filePath);
            $count++;
        }

        for ($row = $startRow; $row <= $startRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getValue();
            $colB = $sheet->getCell([2, $row])->getValue();

            $isDistrict = is_int($colA) || (is_string($colA) && ctype_digit(trim($colA)));
            if (! $isDistrict) continue;
            if (! is_string($colB) || trim($colB) === '') continue;

            $districtCode = $this->districtResolver->resolve(
                $colB, $ctx,
                basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            if ($districtCode === null) continue;

            $this->emitWarehouseRow($ctx, $sheet, $row, $districtCode, $filePath);
            $count++;
        }
        return $count;
    }

    private function emitWarehouseRow(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): void {
        $dto = new WarehouseDto(
            regionCode:               $ctx->regionCode(),
            districtCode:             $districtCode,
            year:                     $ctx->year,
            reserveWarehouses:        $this->intOrNull($sheet->getCell([3, $row])->getValue()),
            reserveCapacityT:         $this->intOrNull($sheet->getCell([4, $row])->getValue()),
            coldStorageCount:         $this->intOrNull($sheet->getCell([5, $row])->getValue()),
            coldStorageCapacityT:     $this->intOrNull($sheet->getCell([6, $row])->getValue()),
            newSmallColdCount:        $this->intOrNull($sheet->getCell([7, $row])->getValue()),
            newSmallColdCapacityT:    $this->intOrNull($sheet->getCell([8, $row])->getValue()),
            newSmallColdMfys:         $this->intOrNull($sheet->getCell([9, $row])->getValue()),
            newLargeColdCount:        $this->intOrNull($sheet->getCell([10, $row])->getValue()),
            newLargeColdCapacityT:    $this->intOrNull($sheet->getCell([11, $row])->getValue()),
            sourceLabel:              basename($filePath) . " · {$sheet->getTitle()} · row $row",
        );
        $this->stagingWriter->buffer('import_staging_warehouses', $dto->toStagingRow($ctx->run->id));
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if (! is_numeric($value)) return null;
        return (float) $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (! is_numeric($value)) return null;
        return (int) $value;
    }
}
```

- [ ] **Step 4: Run the test**

Run: `vendor/bin/pest --filter=InflationModuleParser`
Expected: 1 test passes (12 food_balance + 17 warehouses, all spot-checks green).

If it fails:
- If the food_balance count is wrong, dump `$writer->bufferedCount('import_staging_food_balance')` and the actual count of product rows in the workbook (might be 11 or 13 instead of 12).
- If a spot-check assertion misses by tiny amount, lower the tolerance from 0.05 in that one assertion (don't change the DTO defaults).
- If `andijanCity` (d01) isn't found, check `DistrictResolver` — the district name string in the inflation workbook may differ from the macro one (`Андижон ш.` instead of `Андижон шаҳри`). Add the alt_label to `regions_districts.json` if needed.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/Modules/InflationModuleParser.php \
    backend/tests/Feature/Import/InflationModuleParserTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add InflationModuleParser

Parses 1.1 Баланс (12 food balance products) and 1.2 Омборлар
(1 region rollup + 16 districts = 17 warehouse rows). Total 29
staging rows for Andijan inflation. local_supply_ratio derived as
production/use_total when use_total>0. year_end_stock left NULL
per the spec's known-gap.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Register `InflationModuleParser` in `ImportRegionCommand`

**Files:**
- Modify: `backend/app/Console/Commands/ImportRegionCommand.php`
- Create: `backend/tests/Feature/Import/ImportRegionCommandInflationTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/ImportRegionCommandInflationTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingFoodBalance;
use App\Models\ImportStagingWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 inflation creates a successful run with 29 rows', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/2.1-2.2-жадваллар (инфляция).xlsx'))) {
        $this->markTestSkipped('Andijan inflation data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'inflation',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    expect(ImportStagingFoodBalance::where('import_run_id', $run->id)->count())->toBe(12);
    expect(ImportStagingWarehouse::where('import_run_id', $run->id)->count())->toBe(17);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `vendor/bin/pest --filter=ImportRegionCommandInflation`
Expected: FAIL with "no parser implemented yet, skipping" output and 0 staged rows.

- [ ] **Step 3: Register the parser**

In `backend/app/Console/Commands/ImportRegionCommand.php`, find the `$parsers = [...]` array (currently has only `'macro'` entry). Add the imports at the top of the file:

```php
use App\Services\Import\Modules\InflationModuleParser;
```

(Should be added next to the existing `use App\Services\Import\Modules\MacroModuleParser;`.)

Then add the `inflation` entry to the `$parsers` array. The block should become:

```php
$parsers = [
    'macro'     => new MacroModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'inflation' => new InflationModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

- [ ] **Step 4: Run the test, confirm passes**

Run: `vendor/bin/pest --filter=ImportRegionCommandInflation`
Expected: 1 test passes.

- [ ] **Step 5: Run full suite to confirm no regressions**

Run: `vendor/bin/pest --no-coverage`
Expected: existing 73+ tests still pass (Plan 2 macro suite stays green) plus the new inflation tests.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Console/Commands/ImportRegionCommand.php \
    backend/tests/Feature/Import/ImportRegionCommandInflationTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): register InflationModuleParser in ImportRegionCommand

php artisan import:region andijan 2026 --module=inflation now
produces 12 food_balance + 17 warehouses = 29 staging rows.
Omitting --module runs both macro and inflation in one ImportRun.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: `AndijanFoodBalanceParityTest`

**Files:**
- Create: `backend/tests/Feature/Import/AndijanFoodBalanceParityTest.php`

- [ ] **Step 1: Write the parity test**

Create `backend/tests/Feature/Import/AndijanFoodBalanceParityTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingFoodBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

test('Andijan food_balance import reproduces DATA.regional.food_balance within 0.05', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/2.1-2.2-жадваллар (инфляция).xlsx'))) {
        $this->markTestSkipped('Andijan inflation data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'inflation',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingFoodBalance::where('import_run_id', $run->id)->get();

    expect($rows)->toHaveCount(12);

    foreach ($expected['regional']['food_balance'] as $expectedRow) {
        $actual = $rows->firstWhere('product', $expectedRow['product']);
        expect($actual)->not->toBeNull("missing product {$expectedRow['product']}");

        // Always-present numeric fields the DATA blob carries
        expect($actual->resource_total)->toBeNumericallyClose($expectedRow['resource_total'], 0.05);
        expect($actual->production)->toBeNumericallyClose($expectedRow['production'], 0.05);
        expect($actual->import_volume)->toBeNumericallyClose($expectedRow['import'], 0.05);
        expect($actual->use_total)->toBeNumericallyClose($expectedRow['use_total'], 0.05);
        if (isset($expectedRow['local_supply_ratio'])) {
            expect($actual->local_supply_ratio)->toBeNumericallyClose($expectedRow['local_supply_ratio'], 0.05);
        }
        // year_end_stock skipped — known gap (importer leaves NULL)
    }
});
```

- [ ] **Step 2: Run, confirm passes**

Run: `vendor/bin/pest --filter=AndijanFoodBalanceParity`
Expected: 1 test passes (~12 products × 5 columns = ~60 assertions).

If it fails:
- Print the first mismatch with `dump($actual->toArray(), $expectedRow)`. Tolerance might need bumping for one or two products if the workbook stores raw precision but DATA blob rounded to 1dp; 0.05 should cover most cases.
- Note: the DATA blob field is named `import` (PHP-keyword colliding) but our column is `import_volume`. The test maps `expectedRow['import']` → `actual->import_volume` correctly.

- [ ] **Step 3: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/tests/Feature/Import/AndijanFoodBalanceParityTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "test(import): add Andijan food_balance parity test

12 products × 5 columns asserted within tolerance 0.05.
year_end_stock skipped per the known-gap in the spec.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: `AndijanWarehousesParityTest`

**Files:**
- Create: `backend/tests/Feature/Import/AndijanWarehousesParityTest.php`

- [ ] **Step 1: Write the parity test**

Create `backend/tests/Feature/Import/AndijanWarehousesParityTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanWhDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 'andijan')
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

test('Andijan warehouses import reproduces DATA.districts[*].data.warehouses', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/2.1-2.2-жадваллар (инфляция).xlsx'))) {
        $this->markTestSkipped('Andijan inflation data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'inflation',
    ]);

    $run = ImportRun::latest()->first();
    $rows = ImportStagingWarehouse::where('import_run_id', $run->id)->get();

    expect($rows)->toHaveCount(17);
    expect($rows->whereNull('district_code'))->toHaveCount(1);
    expect($rows->whereNotNull('district_code'))->toHaveCount(16);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));

    foreach ($expected['districts'] as $expectedDistrict) {
        $w = $expectedDistrict['data']['warehouses'] ?? null;
        if ($w === null) continue;

        $districtCode = andijanWhDistrictCode($expectedDistrict['name']);
        expect($districtCode)->not->toBeNull("district code lookup failed for {$expectedDistrict['name']}");

        $actual = $rows->firstWhere('district_code', $districtCode);
        expect($actual)->not->toBeNull("missing warehouses row for {$districtCode}");

        // Always-present integer fields
        expect($actual->reserve_warehouses)->toBe($w['reserve_warehouses']);
        expect($actual->reserve_capacity_t)->toBe($w['reserve_capacity_t']);
        expect($actual->cold_storage_count)->toBe($w['cold_storage_count']);
        expect($actual->cold_storage_capacity_t)->toBe($w['cold_storage_capacity_t']);

        // Optional fields — DATA blob has these as null for most districts
        if ($w['new_small_cold_storage_count'] !== null) {
            expect($actual->new_small_cold_count)->toBe($w['new_small_cold_storage_count']);
        }
        if ($w['new_large_cold_storage_count'] !== null) {
            expect($actual->new_large_cold_count)->toBe($w['new_large_cold_storage_count']);
        }
    }
});
```

- [ ] **Step 2: Run, confirm passes**

Run: `vendor/bin/pest --filter=AndijanWarehousesParity`
Expected: 1 test passes (~16 districts × ~6 columns = ~80 assertions plus the count assertions).

If it fails:
- If counts mismatch, print `$rows->pluck('district_code')` to see which districts the importer found vs which the DATA blob expects.
- DATA blob column names use `new_small_cold_storage_count` / `new_large_cold_storage_count`, but the schema uses `new_small_cold_count` / `new_large_cold_count` (no "storage"). The test maps these — verify the mapping.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/pest --no-coverage`
Expected: 73 (Plan 2) + ~14 (Plan 3) = ~87 tests, all green.

- [ ] **Step 4: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/tests/Feature/Import/AndijanWarehousesParityTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "test(import): add Andijan warehouses parity test

17 staged rows (1 region rollup + 16 districts) asserted against
DATA.districts[*].data.warehouses. Plan 3 complete.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Self-review

Verified against `docs/superpowers/specs/2026-05-06-inflation-module-design.md`:

- **Spec §3 architecture:** Tasks 1+2 add the 2 DTOs. Task 3 adds the SheetResolver signatures. Task 4 adds InflationModuleParser. Task 5 wires it into the command. ✓
- **Spec §4 parser internals:** Task 4 implements `parseFoodBalance` (col mapping A-L, 12 product rows, derived `local_supply_ratio`, `year_end_stock=NULL`) and `parseWarehouses` (region rollup row at startRow-1 + 16 districts, col mapping C-K). ✓
- **Spec §5 DTOs:** Tasks 1+2 define both DTOs with the exact field set from the spec. ✓
- **Spec §6 CLI integration:** Task 5 adds the one-line registry entry. ✓
- **Spec §7 parity tests:** Tasks 6+7 implement both with tolerance 0.05 + the documented field-name mappings (`expected['import']` → `actual->import_volume`, `new_small_cold_storage_count` → `new_small_cold_count`). ✓
- **Spec §8 scope guardrails:** `year_end_stock` skipped in food_balance parity assertions per the known-gap note. ✓

**Placeholder scan:** No "TBD"/"TODO"/"similar to". Each task has full code.

**Type consistency:** `FoodBalanceDto`, `WarehouseDto`, `InflationModuleParser` field names match across Tasks 1, 2, 4, 6, 7.

---

## Out of scope (deferred)

- **Plan 4-7:** budget, budget_invest, foreign_invest, export modules.
- **Plan 8:** employment module (first sentinel exposure).
- **Plan 9:** roll out to 12 non-Navoi regions.
- **Plan 10:** Filament admin UI.
- **`year_end_stock` derivation** — re-opens once source documentation clarifies the column origin.
