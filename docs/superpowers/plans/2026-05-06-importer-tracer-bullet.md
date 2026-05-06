# Importer Tracer Bullet Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `php artisan import:region andijan 2026 --module=macro` end-to-end. The command reads the Andijan macro workbook from `../data/2. Андижон/`, parses 4 sheets (1.1 ЯҲМ + 1.2/1.4/1.5 district tables), populates `import_staging_indicator_facts` with 212 rows, and the parity test asserts those rows reproduce the inlined `DATA` blob in `../index.html` within numeric tolerance.

**Architecture:** Two-tier pipeline. Shared infrastructure services (WorkbookLocator, SheetResolver, HeaderDetector, DistrictResolver, StagingWriter, IssueCollector) handle cross-cutting concerns. One per-module parser (MacroModuleParser) owns the row-layout interpretation. The artisan command orchestrates a single ImportRun. Hybrid sheet resolution: config-first lookup against `region_workbook_sheets`, content-pattern fallback that writes the detected sheet name back to the cache.

**Tech Stack:** Laravel 12.58 · PHP 8.2 · PostgreSQL 14 · Pest 3 · `phpoffice/phpspreadsheet` (added in Task 1).

**Working directory:** All paths in this plan are relative to `backend/` unless prefixed with `../`. Run all `php artisan`, `composer`, and `vendor/bin/pest` commands from `backend/`. The Andijan source data lives at `../data/2. Андижон/` (gitignored, but present locally). The parity-test target `index.html` is at `../index.html`.

**TDD discipline:** Each task writes the failing test first, runs it to confirm failure, writes the minimal implementation, runs the test to confirm pass, then commits. Tests run against the Postgres test database `hududlar_monitoringi_test` (already configured in `phpunit.xml` from Plan 1).

---

## File map

**Created:**
- `backend/app/Enums/IssueKind.php`
- `backend/app/Support/Import/IndicatorFactDto.php`
- `backend/app/Services/Import/ImportContext.php`
- `backend/app/Services/Import/WorkbookLocator.php`
- `backend/app/Services/Import/SheetResolver.php`
- `backend/app/Services/Import/HeaderDetector.php`
- `backend/app/Services/Import/DistrictResolver.php`
- `backend/app/Services/Import/StagingWriter.php`
- `backend/app/Services/Import/IssueCollector.php`
- `backend/app/Services/Import/Modules/ModuleParser.php`
- `backend/app/Services/Import/Modules/MacroModuleParser.php`
- `backend/app/Console/Commands/ImportRegionCommand.php`
- `backend/config/import.php`
- `backend/tests/Helpers/IndexHtmlDataExtractor.php`
- `backend/tests/Feature/Import/WorkbookLocatorTest.php`
- `backend/tests/Feature/Import/SheetResolverTest.php`
- `backend/tests/Feature/Import/HeaderDetectorTest.php`
- `backend/tests/Feature/Import/DistrictResolverTest.php`
- `backend/tests/Feature/Import/StagingWriterTest.php`
- `backend/tests/Feature/Import/IssueCollectorTest.php`
- `backend/tests/Feature/Import/IndexHtmlDataExtractorTest.php`
- `backend/tests/Feature/Import/MacroModuleParserTest.php`
- `backend/tests/Feature/Import/ImportRegionCommandTest.php`
- `backend/tests/Feature/Import/AndijanMacroParityTest.php`

**Modified:**
- `backend/composer.json` (Task 1: add phpspreadsheet)
- `backend/.env` (Task 2: add IMPORT_DATA_PATH)
- `backend/.env.example` (Task 2: same)
- `backend/bootstrap/app.php` or `backend/bootstrap/providers.php` (Task 13: command auto-discovered)
- `backend/tests/Pest.php` (Task 3: add toBeNumericallyClose expectation)

---

## Task 1: Install PhpSpreadsheet

**Files:**
- Modify: `backend/composer.json`, `backend/composer.lock`

- [ ] **Step 1: Install via Composer**

Run from `backend/`:
```
composer require phpoffice/phpspreadsheet --with-all-dependencies
```
Expected: composer reports `Installing phpoffice/phpspreadsheet (v...)` and writes a new entry to `composer.json` under `require`. Several transitive dependencies install (markbaker/matrix, markbaker/complex, etc.).

- [ ] **Step 2: Verify autoload + existing suite**

Run: `vendor/bin/pest --no-coverage`
Expected: 39 schema tests still pass. No regression from the new dependency.

- [ ] **Step 3: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/composer.json backend/composer.lock
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "chore(deps): add phpoffice/phpspreadsheet for the importer

Plan 2's importer reads .xlsx workbooks from data/{region}/.
Plan 3+ will reuse this for the remaining six modules."
```

---

## Task 2: Add `IMPORT_DATA_PATH` env var + config

**Files:**
- Create: `backend/config/import.php`
- Modify: `backend/.env`, `backend/.env.example`

- [ ] **Step 1: Create the config file**

Create `backend/config/import.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Source data path
    |--------------------------------------------------------------------------
    |
    | Absolute or project-relative path to the directory holding region
    | workbook folders, e.g. "../data" produces region paths like
    | "../data/2. Андижон/" relative to backend/.
    |
    */
    'data_path' => env('IMPORT_DATA_PATH', base_path('../data')),
];
```

- [ ] **Step 2: Add the env var to `.env` and `.env.example`**

Edit `backend/.env`. Add this line below the existing `DB_PASSWORD=...` line:

```
IMPORT_DATA_PATH=../data
```

Edit `backend/.env.example` the same way (add the same line in the same place).

- [ ] **Step 3: Smoke test the config resolves**

Run: `php artisan tinker --execute="dump(config('import.data_path'));"`
Expected output: a string ending with `\data` that resolves to the real `data/` directory at the project root.

- [ ] **Step 4: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/config/import.php \
    backend/.env.example
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add IMPORT_DATA_PATH config

Resolves to the project root's data/ folder by default. Override
in .env when workbooks live elsewhere (e.g., a mounted volume in
production)."
```

(`.env` itself is gitignored.)

---

## Task 3: Add `toBeNumericallyClose` Pest expectation

**Files:**
- Modify: `backend/tests/Pest.php`
- Create: `backend/tests/Feature/Import/PestExpectationsTest.php`

The parity test compares decimal columns to JSON-decoded floats. PHP's `assertSame` won't work for `numeric(20,6)` strings vs PHP floats. Define a Pest expectation that absorbs the type difference and applies a tolerance.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/PestExpectationsTest.php`:

```php
<?php

test('toBeNumericallyClose accepts equal floats', function () {
    expect(1.0)->toBeNumericallyClose(1.0, 1e-9);
});

test('toBeNumericallyClose accepts within-tolerance floats', function () {
    expect(108.6)->toBeNumericallyClose(108.5999999, 1e-3);
});

test('toBeNumericallyClose rejects out-of-tolerance values', function () {
    expect(fn () => expect(108.6)->toBeNumericallyClose(108.0, 1e-3))
        ->toThrow(\PHPUnit\Framework\ExpectationFailedException::class);
});

test('toBeNumericallyClose accepts numeric strings (decimal column types)', function () {
    expect('108.600000')->toBeNumericallyClose(108.6, 1e-6);
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=toBeNumericallyClose`
Expected: 4 tests fail with "Undefined method `toBeNumericallyClose`" or similar.

- [ ] **Step 3: Add the expectation to `tests/Pest.php`**

In `backend/tests/Pest.php`, find the existing `expect()->extend('toBeOne', ...)` block and add this immediately after it:

```php
expect()->extend('toBeNumericallyClose', function (float|int|string $expected, float $tolerance = 1e-6) {
    $actual = is_numeric($this->value) ? (float) $this->value : null;
    $expectedFloat = is_numeric($expected) ? (float) $expected : null;

    if ($actual === null || $expectedFloat === null) {
        return $this->toBe($expected);   // fall through to standard equality if types are wrong
    }

    return expect(abs($actual - $expectedFloat))
        ->toBeLessThanOrEqual(
            $tolerance,
            sprintf('Expected %s ± %s, got %s', $expected, $tolerance, $this->value)
        );
});
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=toBeNumericallyClose`
Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/tests/Pest.php \
    backend/tests/Feature/Import/PestExpectationsTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "test: add toBeNumericallyClose Pest expectation

Accepts numeric strings (Eloquent decimal:N casts return strings)
and applies an absolute tolerance. Used by the upcoming Andijan
parity test to compare staged rows against the inlined DATA blob."
```

---

## Task 4: `IssueKind` enum

**Files:**
- Create: `backend/app/Enums/IssueKind.php`
- Create: `backend/tests/Feature/Import/IssueKindTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/IssueKindTest.php`:

```php
<?php

use App\Enums\IssueKind;

test('IssueKind has the expected ten cases', function () {
    expect(IssueKind::cases())->toHaveCount(10);
});

test('IssueKind values are stable snake_case strings', function () {
    expect(IssueKind::SheetMissing->value)->toBe('sheet_missing');
    expect(IssueKind::HeaderNotFound->value)->toBe('header_not_found');
    expect(IssueKind::UnknownDistrict->value)->toBe('unknown_district');
    expect(IssueKind::CrossRegionData->value)->toBe('cross_region_data');
    expect(IssueKind::Sentinel->value)->toBe('sentinel');
    expect(IssueKind::SumMismatch->value)->toBe('sum_mismatch');
    expect(IssueKind::NegativeValue->value)->toBe('negative_value');
    expect(IssueKind::UnitMismatch->value)->toBe('unit_mismatch');
    expect(IssueKind::MissingRow->value)->toBe('missing_row');
    expect(IssueKind::Typo->value)->toBe('typo');
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=IssueKind`
Expected: 2 tests fail with "Class App\Enums\IssueKind not found".

- [ ] **Step 3: Create the enum**

Create `backend/app/Enums/IssueKind.php`:

```php
<?php

namespace App\Enums;

enum IssueKind: string
{
    case SheetMissing    = 'sheet_missing';
    case HeaderNotFound  = 'header_not_found';
    case UnknownDistrict = 'unknown_district';
    case CrossRegionData = 'cross_region_data';
    case Sentinel        = 'sentinel';
    case SumMismatch     = 'sum_mismatch';
    case NegativeValue   = 'negative_value';
    case UnitMismatch    = 'unit_mismatch';
    case MissingRow      = 'missing_row';
    case Typo            = 'typo';
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=IssueKind`
Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Enums/IssueKind.php \
    backend/tests/Feature/Import/IssueKindTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add IssueKind enum

Ten cases covering the issue taxonomy from the spec
(infrastructure, data quality, source-side anomalies)."
```

---

## Task 5: `IndicatorFactDto`

**Files:**
- Create: `backend/app/Support/Import/IndicatorFactDto.php`
- Create: `backend/tests/Feature/Import/IndicatorFactDtoTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/IndicatorFactDtoTest.php`:

```php
<?php

use App\Support\Import\IndicatorFactDto;

test('toStagingRow produces a complete row array', function () {
    $dto = new IndicatorFactDto(
        regionCode:     'andijan',
        districtCode:   null,
        year:           2026,
        indicatorCode:  'grp',
        period:         'h1',
        planValue:      52100.81,
        actualHokimyat: null,
        growthPct:      107.16,
        unit:           'млрд сўм',
        sourceLabel:    '1.1-1.5-жадваллар (макро).xlsx · 1.1. ЯҲМ · row 6',
    );

    $row = $dto->toStagingRow(importRunId: 42);

    expect($row['region_code'])->toBe('andijan');
    expect($row['district_code'])->toBeNull();
    expect($row['year'])->toBe(2026);
    expect($row['indicator_code'])->toBe('grp');
    expect($row['period'])->toBe('h1');
    expect((float) $row['plan_value'])->toBe(52100.81);
    expect($row['actual_hokimyat'])->toBeNull();
    expect((float) $row['growth_pct'])->toBe(107.16);
    expect($row['unit'])->toBe('млрд сўм');
    expect($row['source_label'])->toContain('1.1. ЯҲМ');
    expect($row['import_run_id'])->toBe(42);
    expect($row['staging_status'])->toBe('pending');
    expect($row['is_sentinel'])->toBeFalse();
    expect($row['sentinel_label'])->toBeNull();
    expect($row['created_at'])->not->toBeNull();
    expect($row['updated_at'])->not->toBeNull();
});

test('sentinel DTO sets is_sentinel and sentinel_label', function () {
    $dto = new IndicatorFactDto(
        regionCode: 'andijan', districtCode: 'd01', year: 2026,
        indicatorCode: 'poverty', period: 'year',
        unit: '%', sourceLabel: 'fixture',
        isSentinel: true, sentinelLabel: 'холи ҳудуд',
    );

    $row = $dto->toStagingRow(1);

    expect($row['is_sentinel'])->toBeTrue();
    expect($row['sentinel_label'])->toBe('холи ҳудуд');
    expect($row['plan_value'])->toBeNull();
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=IndicatorFactDto`
Expected: tests fail with "Class App\Support\Import\IndicatorFactDto not found".

- [ ] **Step 3: Create the DTO**

Create `backend/app/Support/Import/IndicatorFactDto.php`:

```php
<?php

namespace App\Support\Import;

final readonly class IndicatorFactDto
{
    public function __construct(
        public string $regionCode,
        public ?string $districtCode,
        public int $year,
        public string $indicatorCode,
        public string $period,
        public ?float $planValue = null,
        public ?float $expectedValue = null,
        public ?float $actualHokimyat = null,
        public ?float $actualStatkom = null,
        public ?float $growthPct = null,
        public ?float $pctOfPlan = null,
        public ?int $countExtra = null,
        public ?int $countExtra2 = null,
        public bool $isSentinel = false,
        public ?string $sentinelLabel = null,
        public string $unit = '',
        public string $sourceLabel = '',
    ) {}

    public function toStagingRow(int $importRunId): array
    {
        $now = now();
        return [
            'import_run_id'    => $importRunId,
            'region_code'      => $this->regionCode,
            'district_code'    => $this->districtCode,
            'year'             => $this->year,
            'indicator_code'   => $this->indicatorCode,
            'period'           => $this->period,
            'plan_value'       => $this->planValue,
            'expected_value'   => $this->expectedValue,
            'actual_hokimyat'  => $this->actualHokimyat,
            'actual_statkom'   => $this->actualStatkom,
            'growth_pct'       => $this->growthPct,
            'pct_of_plan'      => $this->pctOfPlan,
            'count_extra'      => $this->countExtra,
            'count_extra_2'    => $this->countExtra2,
            'is_sentinel'      => $this->isSentinel,
            'sentinel_label'   => $this->sentinelLabel,
            'unit'             => $this->unit,
            'source_label'     => $this->sourceLabel,
            'staging_status'   => 'pending',
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=IndicatorFactDto`
Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Support/Import/IndicatorFactDto.php \
    backend/tests/Feature/Import/IndicatorFactDtoTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add IndicatorFactDto for buffered staging rows"
```

---

## Task 6: `ImportContext`

**Files:**
- Create: `backend/app/Services/Import/ImportContext.php`
- Create: `backend/tests/Feature/Import/ImportContextTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/ImportContextTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\Region;
use App\Services\Import\ImportContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ImportContext exposes region code via helper method', function () {
    $this->seed();
    $region = Region::where('code', 'andijan')->firstOrFail();
    $run = ImportRun::create([
        'region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli',
        'status' => 'parsing', 'started_at' => now(),
    ]);

    $ctx = new ImportContext(
        run: $run, region: $region, year: 2026,
        dataPath: '/tmp/data',
    );

    expect($ctx->regionCode())->toBe('andijan');
    expect($ctx->year)->toBe(2026);
    expect($ctx->run->id)->toBe($run->id);
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=ImportContext`
Expected: fails with "Class App\Services\Import\ImportContext not found".

- [ ] **Step 3: Create the value object**

Create `backend/app/Services/Import/ImportContext.php`:

```php
<?php

namespace App\Services\Import;

use App\Models\ImportRun;
use App\Models\Region;

final readonly class ImportContext
{
    public function __construct(
        public ImportRun $run,
        public Region $region,
        public int $year,
        public string $dataPath,
    ) {}

    public function regionCode(): string
    {
        return $this->region->code;
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=ImportContext`
Expected: 1 test passes.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/ImportContext.php \
    backend/tests/Feature/Import/ImportContextTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add ImportContext value object

Threads ImportRun + Region + year + dataPath through the pipeline.
Immutable readonly class, regionCode() helper for ergonomics."
```

---

## Task 7: `WorkbookLocator`

**Files:**
- Create: `backend/app/Services/Import/WorkbookLocator.php`
- Create: `backend/tests/Feature/Import/WorkbookLocatorTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/WorkbookLocatorTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\Region;
use App\Services\Import\ImportContext;
use App\Services\Import\WorkbookLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAndijanContext(): ImportContext
{
    $region = Region::where('code', 'andijan')->firstOrFail();
    $run = ImportRun::create([
        'region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli',
        'status' => 'parsing', 'started_at' => now(),
    ]);
    return new ImportContext(
        run: $run, region: $region, year: 2026,
        dataPath: base_path('../data'),
    );
}

test('locate returns the macro file for Andijan', function () {
    $this->seed();
    $ctx = makeAndijanContext();

    if (! is_dir(base_path('../data/2. Андижон'))) {
        $this->markTestSkipped('Andijan data folder not present at ../data/2. Андижон');
    }

    $locator = new WorkbookLocator();
    $files = $locator->locate($ctx, moduleFilter: 'macro');

    expect($files)->toHaveKey('macro');
    expect($files['macro'])->toContain('1.1-1.5-жадваллар (макро).xlsx');
    expect(file_exists($files['macro']))->toBeTrue();
});

test('locate filters out non-requested modules', function () {
    $this->seed();
    $ctx = makeAndijanContext();

    if (! is_dir(base_path('../data/2. Андижон'))) {
        $this->markTestSkipped('Andijan data folder not present');
    }

    $locator = new WorkbookLocator();
    $files = $locator->locate($ctx, moduleFilter: 'macro');

    expect($files)->toHaveCount(1);
    expect($files)->toHaveKey('macro');
});

test('locate registers an import_files row for each found file', function () {
    $this->seed();
    $ctx = makeAndijanContext();

    if (! is_dir(base_path('../data/2. Андижон'))) {
        $this->markTestSkipped('Andijan data folder not present');
    }

    $locator = new WorkbookLocator();
    $locator->locate($ctx, moduleFilter: 'macro');

    expect(\App\Models\ImportFile::where('import_run_id', $ctx->run->id)->count())->toBe(1);
    $file = \App\Models\ImportFile::where('import_run_id', $ctx->run->id)->first();
    expect($file->module_code)->toBe('macro');
    expect($file->sha256)->toHaveLength(64);
    expect($file->size_bytes)->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=WorkbookLocator`
Expected: tests fail with "Class App\Services\Import\WorkbookLocator not found".

- [ ] **Step 3: Create `WorkbookLocator`**

Create `backend/app/Services/Import/WorkbookLocator.php`:

```php
<?php

namespace App\Services\Import;

use App\Models\ImportFile;

class WorkbookLocator
{
    /**
     * Filename regex per module_code. Matches the patterns observed across all
     * 14 region folders in the data/ inventory survey.
     */
    private const PATTERNS = [
        'macro'          => '/^1\.1-1\.[45].*макро.*\.xlsx$/u',
        'inflation'      => '/^2\.1-2\.2.*инфляция.*\.xlsx$/u',
        'budget'         => '/^3-жадвал.*бюджет.*\.xlsx$/u',
        'budget_invest'  => '/^4\.1.*бюджет.*инвест.*\.xlsx$/u',
        'foreign_invest' => '/^4\.2.*инвестиция.*\.xlsx$/u',
        'export'         => '/^5\.1-5\.2.*экспорт.*\.xlsx$/u',
        'employment'     => '/^6-жадвал.*бандлик.*\.xlsx$/u',
    ];

    /**
     * Scan the region's data folder, return a module_code => absolute file path map.
     * Records each found file as an import_files row for audit.
     */
    public function locate(ImportContext $ctx, ?string $moduleFilter = null): array
    {
        $regionFolder = $this->resolveRegionFolder($ctx);
        if (! is_dir($regionFolder)) {
            return [];
        }

        $found = [];
        foreach (scandir($regionFolder) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            foreach (self::PATTERNS as $module => $pattern) {
                if ($moduleFilter !== null && $module !== $moduleFilter) {
                    continue;
                }
                if (preg_match($pattern, $entry)) {
                    $absolute = $regionFolder . DIRECTORY_SEPARATOR . $entry;
                    $found[$module] = $absolute;
                    $this->recordImportFile($ctx, $module, $entry, $absolute);
                    break;
                }
            }
        }

        return $found;
    }

    private function resolveRegionFolder(ImportContext $ctx): string
    {
        $region = $ctx->region;
        // Folder names are like "2. Андижон" — sort_order + region name_short.
        // Use folder_name when set in the regions table, otherwise compose.
        if ($region->folder_name) {
            return rtrim($ctx->dataPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $region->folder_name;
        }
        return rtrim($ctx->dataPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . sprintf('%d. %s', $region->sort_order, $region->name_short);
    }

    private function recordImportFile(ImportContext $ctx, string $moduleCode, string $fileName, string $absolutePath): void
    {
        $size = filesize($absolutePath);
        $sha = hash_file('sha256', $absolutePath);

        ImportFile::create([
            'import_run_id' => $ctx->run->id,
            'module_code'   => $moduleCode,
            'file_name'     => $fileName,
            'file_path'     => $absolutePath,
            'sha256'        => $sha,
            'size_bytes'    => $size,
            'sheet_count'   => null,        // populated later if/when SheetResolver opens the workbook
            'parsed_ok'     => false,       // flipped to true on successful module parse
        ]);
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=WorkbookLocator`
Expected: 3 tests pass (or skipped if data folder absent).

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/WorkbookLocator.php \
    backend/tests/Feature/Import/WorkbookLocatorTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add WorkbookLocator

Filesystem scan that maps file names to module_code via regex
patterns derived from the cross-region data inventory survey.
Records each found file as an import_files audit row before
parsing begins."
```

---

## Task 8: `SheetResolver`

**Files:**
- Create: `backend/app/Services/Import/SheetResolver.php`
- Create: `backend/tests/Feature/Import/SheetResolverTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/SheetResolverTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\RegionWorkbook;
use App\Models\RegionWorkbookSheet;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\SheetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

function loadAndijanMacro(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/1.1-1.5-жадваллар (макро).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan macro workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    return $reader->load($path);
}

function makeRegionWorkbook(int $runId): RegionWorkbook
{
    return RegionWorkbook::create([
        'region_code'       => 'andijan',
        'reporting_year_id' => 1,
        'module_id'         => 1,
        'file_name'         => '1.1-1.5-жадваллар (макро).xlsx',
        'file_path'         => 'fixture',
        'last_seen_at'      => now(),
    ]);
}

test('SheetResolver detects rollup sheet on cache miss and writes to cache', function () {
    $this->seed();
    $book = loadAndijanMacro();
    $rwb = makeRegionWorkbook(1);
    $issues = new IssueCollector();
    $resolver = new SheetResolver($issues);

    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: \App\Models\Region::where('code', 'andijan')->first(),
        year: 2026,
        dataPath: base_path('../data'),
    );

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'macro', 'rollup');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('1.1. ЯҲМ');

    // Cache row was written
    $cached = RegionWorkbookSheet::where('region_workbook_id', $rwb->id)
        ->where('logical_kind', 'rollup')->first();
    expect($cached)->not->toBeNull();
    expect($cached->sheet_name)->toBe('1.1. ЯҲМ');
});

test('SheetResolver uses cached sheet on subsequent calls', function () {
    $this->seed();
    $book = loadAndijanMacro();
    $rwb = makeRegionWorkbook(1);

    // Pre-populate cache with the wrong sheet name to prove cache is used
    RegionWorkbookSheet::create([
        'region_workbook_id' => $rwb->id,
        'sheet_name'         => '1.2. Саноат',          // intentionally wrong for 'rollup'
        'logical_kind'       => 'rollup',
    ]);

    $resolver = new SheetResolver(new IssueCollector());
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: \App\Models\Region::where('code', 'andijan')->first(),
        year: 2026, dataPath: base_path('../data'),
    );

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'macro', 'rollup');

    expect($sheet->getTitle())->toBe('1.2. Саноат');   // proves we used the cached value
});

test('SheetResolver detects district_industry sheet by content', function () {
    $this->seed();
    $book = loadAndijanMacro();
    $rwb = makeRegionWorkbook(1);
    $resolver = new SheetResolver(new IssueCollector());

    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: \App\Models\Region::where('code', 'andijan')->first(),
        year: 2026, dataPath: base_path('../data'),
    );

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'macro', 'district_industry');

    expect($sheet->getTitle())->toBe('1.2. Саноат');
});

test('SheetResolver raises SheetMissing blocker when no signature matches', function () {
    $this->seed();
    $book = loadAndijanMacro();
    $rwb = makeRegionWorkbook(1);
    $issues = new IssueCollector();
    $resolver = new SheetResolver($issues);

    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: \App\Models\Region::where('code', 'andijan')->first(),
        year: 2026, dataPath: base_path('../data'),
    );

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'macro', 'no_such_kind');

    expect($sheet)->toBeNull();
    expect($issues->blockerCount())->toBe(1);
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=SheetResolver`
Expected: tests fail with "Class App\Services\Import\SheetResolver not found".

- [ ] **Step 3: Create `SheetResolver`**

Create `backend/app/Services/Import/SheetResolver.php`:

```php
<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\RegionWorkbookSheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SheetResolver
{
    /**
     * Signature strings expected in rows 1-5 of a sheet of the given logical_kind.
     * Match counts of any signature score the sheet; the highest-scoring sheet wins.
     */
    private const SIGNATURES = [
        'rollup'              => ['ЯҲМ', 'асосий иқтисодий кўрсаткич'],
        'district_industry'   => ['Саноат маҳсулотларини ишлаб чиқариш'],
        'district_agriculture'=> ['Қишлоқ хўжалиги маҳсулотларини'],
        'district_services'   => ['Бозор хизматлари'],
    ];

    public function __construct(private IssueCollector $issues) {}

    public function resolve(
        ImportContext $ctx,
        Spreadsheet $book,
        int $regionWorkbookId,
        string $moduleCode,
        string $logicalKind,
    ): ?Worksheet {
        // 1. Cache lookup
        $cached = RegionWorkbookSheet::where('region_workbook_id', $regionWorkbookId)
            ->where('logical_kind', $logicalKind)->first();
        if ($cached && $book->sheetNameExists($cached->sheet_name)) {
            return $book->getSheetByName($cached->sheet_name);
        }

        // 2. Content scan
        $signatures = self::SIGNATURES[$logicalKind] ?? null;
        if ($signatures === null) {
            $this->issues->add(
                kind: IssueKind::SheetMissing,
                severity: IssueSeverity::Blocker,
                detail: "No signature definition for logical_kind '$logicalKind'",
                regionCode: $ctx->regionCode(),
                importRunId: $ctx->run->id,
            );
            return null;
        }

        $bestSheet = null;
        $bestScore = 0;
        foreach ($book->getAllSheets() as $sheet) {
            $score = $this->scoreSheet($sheet, $signatures);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSheet = $sheet;
            }
        }

        if ($bestSheet === null || $bestScore === 0) {
            $this->issues->add(
                kind: IssueKind::SheetMissing,
                severity: IssueSeverity::Blocker,
                detail: "No sheet matched signatures for logical_kind '$logicalKind' in module '$moduleCode'",
                regionCode: $ctx->regionCode(),
                importRunId: $ctx->run->id,
            );
            return null;
        }

        // 3. Write back to cache
        RegionWorkbookSheet::create([
            'region_workbook_id' => $regionWorkbookId,
            'sheet_name'         => $bestSheet->getTitle(),
            'logical_kind'       => $logicalKind,
            'detection_hints'    => json_encode(['score' => $bestScore, 'signatures' => $signatures], JSON_UNESCAPED_UNICODE),
        ]);

        return $bestSheet;
    }

    private function scoreSheet(Worksheet $sheet, array $signatures): int
    {
        $score = 0;
        for ($row = 1; $row <= 5; $row++) {
            $rowText = '';
            foreach ($sheet->getRowIterator($row, $row) as $r) {
                foreach ($r->getCellIterator() as $cell) {
                    $val = $cell->getValue();
                    if (is_string($val)) $rowText .= ' ' . $val;
                }
            }
            foreach ($signatures as $sig) {
                if (mb_stripos($rowText, $sig) !== false) {
                    $score++;
                }
            }
        }
        return $score;
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=SheetResolver`
Expected: 4 tests pass (or skip if data folder absent).

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/SheetResolver.php \
    backend/tests/Feature/Import/SheetResolverTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add SheetResolver with hybrid cache + content match

Cache hit returns the previously-detected sheet; cache miss scans
all sheets, scores each against signature strings expected in
rows 1-5, picks the highest-scoring match, writes the result back
to region_workbook_sheets so subsequent runs skip detection."
```

---

## Task 9: `HeaderDetector`

**Files:**
- Create: `backend/app/Services/Import/HeaderDetector.php`
- Create: `backend/tests/Feature/Import/HeaderDetectorTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/HeaderDetectorTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbookSheet;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

function loadAndijanMacroSheet(string $sheetName): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
{
    $path = base_path('../data/2. Андижон/1.1-1.5-жадваллар (макро).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan macro workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    return $reader->load($path)->getSheetByName($sheetName);
}

test('HeaderDetector finds row 6 as data start in 1.1 ЯҲМ', function () {
    $this->seed();
    $sheet = loadAndijanMacroSheet('1.1. ЯҲМ');
    $detector = new HeaderDetector(new IssueCollector());

    $rwSheet = RegionWorkbookSheet::create([
        'region_workbook_id' => 1, 'sheet_name' => '1.1. ЯҲМ', 'logical_kind' => 'rollup',
    ]);

    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: Region::where('code', 'andijan')->first(),
        year: 2026, dataPath: base_path('../data'),
    );

    $row = $detector->detect($sheet, $ctx, $rwSheet->id);

    expect($row)->toBe(6);
    expect($rwSheet->fresh()->header_row)->toBe(6);
});

test('HeaderDetector finds row 8 in 1.2 Саноат (region rollup row 7, districts start row 8)', function () {
    $this->seed();
    $sheet = loadAndijanMacroSheet('1.2. Саноат');
    $detector = new HeaderDetector(new IssueCollector());
    $rwSheet = RegionWorkbookSheet::create([
        'region_workbook_id' => 1, 'sheet_name' => '1.2. Саноат', 'logical_kind' => 'district_industry',
    ]);

    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: Region::where('code', 'andijan')->first(),
        year: 2026, dataPath: base_path('../data'),
    );

    $row = $detector->detect($sheet, $ctx, $rwSheet->id);

    expect($row)->toBe(8);
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=HeaderDetector`
Expected: tests fail with "Class App\Services\Import\HeaderDetector not found".

- [ ] **Step 3: Create `HeaderDetector`**

Create `backend/app/Services/Import/HeaderDetector.php`:

```php
<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\RegionWorkbookSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HeaderDetector
{
    /**
     * The first row where:
     *   - column A is an integer (or numeric string), AND
     *   - some row above contains a unit signature like "ҳажми (млрд.сўм)"
     *     or "ҳажм" (any case-insensitive substring).
     * Caches into region_workbook_sheets.header_row.
     */
    public function __construct(private IssueCollector $issues) {}

    public function detect(Worksheet $sheet, ImportContext $ctx, int $regionWorkbookSheetId): ?int
    {
        $cached = RegionWorkbookSheet::find($regionWorkbookSheetId);
        if ($cached && $cached->header_row) {
            return $cached->header_row;
        }

        $hasUnitAbove = false;
        for ($row = 1; $row <= 15; $row++) {
            $colA = $sheet->getCell([1, $row])->getValue();
            $rowText = '';
            foreach ($sheet->getRowIterator($row, $row) as $r) {
                foreach ($r->getCellIterator() as $cell) {
                    $v = $cell->getValue();
                    if (is_string($v)) $rowText .= ' ' . $v;
                }
            }
            if (mb_stripos($rowText, 'ҳажм') !== false || mb_stripos($rowText, 'млрд.сўм') !== false) {
                $hasUnitAbove = true;
            }

            if ($hasUnitAbove && (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA))))) {
                if ($cached) {
                    $cached->update(['header_row' => $row]);
                }
                return $row;
            }
        }

        $this->issues->add(
            kind: IssueKind::HeaderNotFound,
            severity: IssueSeverity::Blocker,
            detail: "Could not locate data start row in sheet '{$sheet->getTitle()}'",
            regionCode: $ctx->regionCode(),
            sourceLabel: $sheet->getTitle(),
            importRunId: $ctx->run->id,
        );
        return null;
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=HeaderDetector`
Expected: 2 tests pass (or skip).

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/HeaderDetector.php \
    backend/tests/Feature/Import/HeaderDetectorTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add HeaderDetector

Walks rows 1-15, finds first row where column A is an integer and
a row above contains a unit signature ('ҳажм', 'млрд.сўм').
Caches detected row into region_workbook_sheets.header_row so
subsequent imports of the same workbook skip detection."
```

---

## Task 10: `IssueCollector`

**Files:**
- Create: `backend/app/Services/Import/IssueCollector.php`
- Create: `backend/tests/Feature/Import/IssueCollectorTest.php`

(IssueCollector comes before DistrictResolver because DistrictResolver depends on it.)

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/IssueCollectorTest.php`:

```php
<?php

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\DataQualityIssue;
use App\Models\ImportRun;
use App\Services\Import\IssueCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('IssueCollector buffers and flushes issues to data_quality_issues', function () {
    $this->seed();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);

    $collector = new IssueCollector();
    $collector->add(
        kind: IssueKind::UnknownDistrict, severity: IssueSeverity::High,
        detail: 'unknown district X', regionCode: 'andijan',
        detectedValue: 'Some unknown', importRunId: $run->id,
    );
    $collector->add(
        kind: IssueKind::Sentinel, severity: IssueSeverity::Medium,
        detail: 'холи ҳудуд in poverty.year', regionCode: 'andijan',
        importRunId: $run->id,
    );

    expect($collector->blockerCount())->toBe(0);
    $written = $collector->flush();
    expect($written)->toBe(2);
    expect(DataQualityIssue::count())->toBe(2);
});

test('IssueCollector counts blocker severity issues', function () {
    $collector = new IssueCollector();
    $collector->add(IssueKind::HeaderNotFound, IssueSeverity::Blocker, 'detail', regionCode: 'andijan');
    $collector->add(IssueKind::SheetMissing, IssueSeverity::Blocker, 'detail', regionCode: 'andijan');
    $collector->add(IssueKind::Typo, IssueSeverity::Low, 'detail', regionCode: 'andijan');

    expect($collector->blockerCount())->toBe(2);
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=IssueCollector`
Expected: tests fail with "Class App\Services\Import\IssueCollector not found".

- [ ] **Step 3: Create `IssueCollector`**

Create `backend/app/Services/Import/IssueCollector.php`:

```php
<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use Illuminate\Support\Facades\DB;

class IssueCollector
{
    private array $buffer = [];

    public function add(
        IssueKind $kind,
        IssueSeverity $severity,
        string $detail,
        ?string $regionCode = null,
        ?string $districtCode = null,
        ?string $indicatorCode = null,
        ?int $year = null,
        ?string $period = null,
        ?string $detectedValue = null,
        ?string $expectedValue = null,
        ?string $sourceLabel = null,
        ?int $importRunId = null,
    ): void {
        $now = now();
        $this->buffer[] = [
            'import_run_id'   => $importRunId,
            'region_code'     => $regionCode ?? '',
            'district_code'   => $districtCode,
            'indicator_code'  => $indicatorCode,
            'year'            => $year,
            'period'          => $period,
            'issue_kind'      => $kind->value,
            'severity'        => $severity->value,
            'detail'          => $detail,
            'detected_value'  => $detectedValue,
            'expected_value'  => $expectedValue,
            'source_label'    => $sourceLabel,
            'detected_at'     => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];
    }

    public function blockerCount(): int
    {
        return array_sum(array_map(fn($i) => $i['severity'] === IssueSeverity::Blocker->value ? 1 : 0, $this->buffer));
    }

    public function bufferedCount(): int
    {
        return count($this->buffer);
    }

    public function flush(): int
    {
        if (empty($this->buffer)) {
            return 0;
        }
        $count = 0;
        foreach (array_chunk($this->buffer, 200) as $chunk) {
            DB::table('data_quality_issues')->insert($chunk);
            $count += count($chunk);
        }
        $this->buffer = [];
        return $count;
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=IssueCollector`
Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/IssueCollector.php \
    backend/tests/Feature/Import/IssueCollectorTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add IssueCollector

Buffers data_quality_issues entries; flush() bulk-inserts them in
chunks of 200. blockerCount() drives the post-parse run-status
decision (failed vs awaiting_review)."
```

---

## Task 11: `DistrictResolver`

**Files:**
- Create: `backend/app/Services/Import/DistrictResolver.php`
- Create: `backend/tests/Feature/Import/DistrictResolverTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/DistrictResolverTest.php`:

```php
<?php

use App\Enums\IssueSeverity;
use App\Models\ImportRun;
use App\Models\Region;
use App\Services\Import\DistrictResolver;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAndijanCtx(): ImportContext
{
    return new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: Region::where('code', 'andijan')->first(),
        year: 2026, dataPath: base_path('../data'),
    );
}

test('DistrictResolver returns code for known full name', function () {
    $this->seed();
    $issues = new IssueCollector();
    $resolver = new DistrictResolver($issues);
    $resolver->loadFor('andijan');

    $code = $resolver->resolve('Андижон шаҳри', makeAndijanCtx(), 'fixture');

    expect($code)->toBe('d01');
    expect($issues->bufferedCount())->toBe(0);
});

test('DistrictResolver raises UnknownDistrict issue and returns null on miss', function () {
    $this->seed();
    $issues = new IssueCollector();
    $resolver = new DistrictResolver($issues);
    $resolver->loadFor('andijan');

    $code = $resolver->resolve('Совершенно неизвестный район', makeAndijanCtx(), 'fixture-row-99');

    expect($code)->toBeNull();
    expect($issues->bufferedCount())->toBe(1);
    // verify the buffered issue is severity=high, kind=unknown_district
    $reflection = new ReflectionClass($issues);
    $bufferProp = $reflection->getProperty('buffer');
    $bufferProp->setAccessible(true);
    $buffer = $bufferProp->getValue($issues);
    expect($buffer[0]['issue_kind'])->toBe('unknown_district');
    expect($buffer[0]['severity'])->toBe('high');
});

test('DistrictResolver trims whitespace before lookup', function () {
    $this->seed();
    $issues = new IssueCollector();
    $resolver = new DistrictResolver($issues);
    $resolver->loadFor('andijan');

    $code = $resolver->resolve("  Андижон шаҳри  \n", makeAndijanCtx(), 'fixture');

    expect($code)->toBe('d01');
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=DistrictResolver`
Expected: tests fail with "Class App\Services\Import\DistrictResolver not found".

- [ ] **Step 3: Create `DistrictResolver`**

Create `backend/app/Services/Import/DistrictResolver.php`:

```php
<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\District;

class DistrictResolver
{
    private array $aliasToCode = [];   // keyed by trimmed alias string

    public function __construct(private IssueCollector $issues) {}

    public function loadFor(string $regionCode): void
    {
        $this->aliasToCode = [];
        District::where('region_code', $regionCode)->get()->each(function (District $d) {
            $aliases = json_decode($d->alt_labels ?? '[]', true) ?: [];
            $aliases[] = $d->name_short;
            $aliases[] = $d->name_full;
            if ($d->name_latin) {
                $aliases[] = $d->name_latin;
            }
            foreach ($aliases as $alias) {
                $key = trim($alias);
                if ($key !== '') {
                    $this->aliasToCode[$key] = $d->code;
                }
            }
        });
    }

    public function resolve(string $workbookString, ImportContext $ctx, string $sourceLabel): ?string
    {
        $key = trim($workbookString);
        if (isset($this->aliasToCode[$key])) {
            return $this->aliasToCode[$key];
        }
        $this->issues->add(
            kind: IssueKind::UnknownDistrict,
            severity: IssueSeverity::High,
            detail: "District string did not match any alt_label in region {$ctx->regionCode()}",
            regionCode: $ctx->regionCode(),
            detectedValue: $key,
            sourceLabel: $sourceLabel,
            importRunId: $ctx->run->id,
        );
        return null;
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=DistrictResolver`
Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/DistrictResolver.php \
    backend/tests/Feature/Import/DistrictResolverTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add DistrictResolver

Eager-loads districts.alt_labels for the region into an in-memory
map at loadFor(). resolve() does exact match against trimmed input;
on miss, raises an UnknownDistrict issue (severity=high) and
returns null so the parser can skip the row."
```

---

## Task 12: `StagingWriter`

**Files:**
- Create: `backend/app/Services/Import/StagingWriter.php`
- Create: `backend/tests/Feature/Import/StagingWriterTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/StagingWriterTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Services\Import\StagingWriter;
use App\Support\Import\IndicatorFactDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('StagingWriter buffers and flushes DTOs to staging table', function () {
    $this->seed();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);

    $writer = new StagingWriter();
    $dto = new IndicatorFactDto(
        regionCode: 'andijan', districtCode: null, year: 2026,
        indicatorCode: 'grp', period: 'h1',
        planValue: 52100.81, growthPct: 107.16,
        unit: 'млрд сўм', sourceLabel: 'fixture',
    );

    $writer->buffer('import_staging_indicator_facts', $dto->toStagingRow($run->id));
    expect($writer->bufferedCount('import_staging_indicator_facts'))->toBe(1);

    DB::transaction(fn() => $writer->flush());

    expect(ImportStagingIndicatorFact::count())->toBe(1);
    expect((float) ImportStagingIndicatorFact::first()->plan_value)->toBe(52100.81);
});

test('StagingWriter discard() empties the buffer without writing', function () {
    $this->seed();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);

    $writer = new StagingWriter();
    $dto = new IndicatorFactDto(
        regionCode: 'andijan', districtCode: null, year: 2026,
        indicatorCode: 'grp', period: 'h1',
        unit: 'млрд сўм', sourceLabel: 'fixture',
    );
    $writer->buffer('import_staging_indicator_facts', $dto->toStagingRow($run->id));
    $writer->discard();

    expect($writer->bufferedCount('import_staging_indicator_facts'))->toBe(0);
    expect(ImportStagingIndicatorFact::count())->toBe(0);
});

test('StagingWriter flushes 250 rows in chunks', function () {
    $this->seed();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);

    $writer = new StagingWriter();
    for ($i = 1; $i <= 250; $i++) {
        $dto = new IndicatorFactDto(
            regionCode: 'andijan', districtCode: 'd' . str_pad((string)(($i % 16) + 1), 2, '0', STR_PAD_LEFT),
            year: 2026,
            indicatorCode: 'industry', period: 'h1',
            planValue: $i, unit: 'млрд сўм', sourceLabel: "row $i",
        );
        $writer->buffer('import_staging_indicator_facts', $dto->toStagingRow($run->id));
    }
    DB::transaction(fn() => $writer->flush());

    expect(ImportStagingIndicatorFact::count())->toBe(250);
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=StagingWriter`
Expected: tests fail with "Class App\Services\Import\StagingWriter not found".

- [ ] **Step 3: Create `StagingWriter`**

Create `backend/app/Services/Import/StagingWriter.php`:

```php
<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;

class StagingWriter
{
    private array $buffers = [];   // table_name => array of row arrays

    public function buffer(string $table, array $row): void
    {
        $this->buffers[$table][] = $row;
    }

    public function bufferedCount(string $table): int
    {
        return count($this->buffers[$table] ?? []);
    }

    public function totalCount(): int
    {
        return array_sum(array_map('count', $this->buffers));
    }

    public function discard(): void
    {
        $this->buffers = [];
    }

    /**
     * Bulk-insert all buffered rows. Caller wraps in DB::transaction for atomicity.
     * Returns total rows flushed across all tables.
     */
    public function flush(): int
    {
        $count = 0;
        foreach ($this->buffers as $table => $rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table($table)->insert($chunk);
                $count += count($chunk);
            }
        }
        $this->buffers = [];
        return $count;
    }
}
```

- [ ] **Step 4: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=StagingWriter`
Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/StagingWriter.php \
    backend/tests/Feature/Import/StagingWriterTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add StagingWriter

Buffers DTOs by table name. flush() does chunked bulk INSERTs
(200 rows per chunk). Caller wraps in DB::transaction. discard()
empties without writing — used when blocker issues prevent
promotion of any rows."
```

---

## Task 13: `IndexHtmlDataExtractor`

**Files:**
- Create: `backend/tests/Helpers/IndexHtmlDataExtractor.php`
- Create: `backend/tests/Feature/Import/IndexHtmlDataExtractorTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/IndexHtmlDataExtractorTest.php`:

```php
<?php

use Tests\Helpers\IndexHtmlDataExtractor;

test('extract returns regional and districts keys from the v7 prototype', function () {
    $path = base_path('../index.html');
    if (! file_exists($path)) {
        $this->markTestSkipped('index.html not present');
    }

    $data = (new IndexHtmlDataExtractor())->extract($path);

    expect($data)->toHaveKey('regional');
    expect($data)->toHaveKey('districts');
    expect($data['meta']['region'])->toBe('Андижон вилояти');
});

test('extract surfaces 5 macro indicators in regional', function () {
    $path = base_path('../index.html');
    if (! file_exists($path)) {
        $this->markTestSkipped('index.html not present');
    }

    $data = (new IndexHtmlDataExtractor())->extract($path);

    expect($data['regional']['macro'])->toHaveCount(5);
    expect($data['regional']['macro'][0]['indicator'])->toBe('ЯҲМ');
});

test('extract surfaces 16 districts each with industry/agriculture/services blocks', function () {
    $path = base_path('../index.html');
    if (! file_exists($path)) {
        $this->markTestSkipped('index.html not present');
    }

    $data = (new IndexHtmlDataExtractor())->extract($path);

    expect($data['districts'])->toHaveCount(16);
    expect($data['districts'][0]['data'])->toHaveKey('industry');
    expect($data['districts'][0]['data'])->toHaveKey('agriculture');
    expect($data['districts'][0]['data'])->toHaveKey('services');
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=IndexHtmlDataExtractor`
Expected: tests fail with "Class Tests\Helpers\IndexHtmlDataExtractor not found".

- [ ] **Step 3: Create the helper**

Create `backend/tests/Helpers/IndexHtmlDataExtractor.php`:

```php
<?php

namespace Tests\Helpers;

use RuntimeException;

class IndexHtmlDataExtractor
{
    /**
     * Find `const DATA = {...};` in the html file, json_decode the captured object literal.
     * The DATA blob is on a single line; the regex is non-greedy and uses the `s` flag so
     * `.` matches newlines if the structure ever changes.
     */
    public function extract(string $htmlPath): array
    {
        $html = file_get_contents($htmlPath);
        if ($html === false) {
            throw new RuntimeException("Could not read $htmlPath");
        }

        if (! preg_match('/const DATA\s*=\s*(\{.*?\});\s*\n/s', $html, $m)) {
            throw new RuntimeException("Could not locate `const DATA = {...};` in $htmlPath");
        }

        $decoded = json_decode($m[1], true);
        if ($decoded === null) {
            throw new RuntimeException('Failed to json_decode DATA blob: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
```

- [ ] **Step 4: Register the Helpers namespace in autoload**

Edit `backend/composer.json`. In `autoload-dev.psr-4`, add `"Tests\\": "tests/"` if it isn't already there (Laravel's default Pest setup includes this). Verify it's set up:

```
grep -A 4 'autoload-dev' backend/composer.json
```

If the `Tests\\` entry is missing, add it:

```json
"autoload-dev": {
    "psr-4": {
        "Tests\\": "tests/"
    }
}
```

Then run `composer dump-autoload`.

- [ ] **Step 5: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=IndexHtmlDataExtractor`
Expected: 3 tests pass.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/tests/Helpers/IndexHtmlDataExtractor.php \
    backend/tests/Feature/Import/IndexHtmlDataExtractorTest.php \
    backend/composer.json backend/composer.lock
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "test: add IndexHtmlDataExtractor helper

Regex-extracts the const DATA = {...}; line from the v7 prototype's
index.html and json_decodes it. Used as the expected-data oracle by
the upcoming Andijan macro parity test."
```

---

## Task 14: `ModuleParser` abstract base + `MacroModuleParser`

**Files:**
- Create: `backend/app/Services/Import/Modules/ModuleParser.php`
- Create: `backend/app/Services/Import/Modules/MacroModuleParser.php`
- Create: `backend/tests/Feature/Import/MacroModuleParserTest.php`

This is the longest task — the parser orchestrates 4 sheets, builds 212 DTOs, and exercises the entire shared infrastructure. Test asserts the resulting buffered DTO count + spot-checks two specific values.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/MacroModuleParserTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\MacroModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function macroParserCtx(): array
{
    $path = base_path('../data/2. Андижон/1.1-1.5-жадваллар (макро).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan macro workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_code' => 'andijan', 'reporting_year_id' => 1, 'module_id' => 1,
        'file_name' => '1.1-1.5-жадваллар (макро).xlsx', 'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('MacroModuleParser produces 212 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = macroParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new MacroModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        $districts,
        $writer,
        $issues,
    );

    $parser->parse($ctx, $path, $rwb->id);

    expect($writer->bufferedCount('import_staging_indicator_facts'))->toBe(212);

    DB::transaction(fn() => $writer->flush());
    $issues->flush();

    // Region rollup: 5 indicators × 4 periods
    $rollup = ImportStagingIndicatorFact::whereNull('district_code')->count();
    expect($rollup)->toBe(20);

    // District rows: 16 × 3 indicators × 4 periods
    $district = ImportStagingIndicatorFact::whereNotNull('district_code')->count();
    expect($district)->toBe(192);

    // Spot-check: GRP year value for Andijan rollup matches the workbook (124,778.118)
    $grpYear = ImportStagingIndicatorFact::where('region_code','andijan')
        ->whereNull('district_code')->where('indicator_code','grp')->where('period','year')->first();
    expect((float) $grpYear->plan_value)->toBeNumericallyClose(124778.117923571, 1e-6);

    // Spot-check: industry for "Андижон шаҳри" (d01) Q1 = 4600.87…
    $industryQ1 = ImportStagingIndicatorFact::where('region_code','andijan')
        ->where('district_code','d01')->where('indicator_code','industry')->where('period','q1')->first();
    expect((float) $industryQ1->plan_value)->toBeNumericallyClose(4600.872899834, 1e-6);
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=MacroModuleParser`
Expected: fails with "Class App\Services\Import\Modules\MacroModuleParser not found".

- [ ] **Step 3: Create `ModuleParser` abstract base**

Create `backend/app/Services/Import/Modules/ModuleParser.php`:

```php
<?php

namespace App\Services\Import\Modules;

use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;

abstract class ModuleParser
{
    public function __construct(
        protected SheetResolver $sheetResolver,
        protected HeaderDetector $headerDetector,
        protected DistrictResolver $districtResolver,
        protected StagingWriter $stagingWriter,
        protected IssueCollector $issueCollector,
    ) {}

    abstract public function moduleCode(): string;

    /**
     * Parse the module's workbook for this run's region+year. Buffers DTOs into
     * the StagingWriter and issues into the IssueCollector. Returns the count of
     * buffered staging rows (across all tables this module writes to).
     */
    abstract public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int;
}
```

- [ ] **Step 4: Create `MacroModuleParser`**

Create `backend/app/Services/Import/Modules/MacroModuleParser.php`:

```php
<?php

namespace App\Services\Import\Modules;

use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MacroModuleParser extends ModuleParser
{
    private const INDICATOR_BY_LABEL = [
        'ЯҲМ'                            => 'grp',
        'Саноат маҳсулотлари'            => 'industry',
        'Қишлоқ хўжалиги маҳсулотлари'   => 'agriculture',
        'Қурилиш ишлари'                 => 'construction',
        'Бозор хизматлари'               => 'services',
    ];

    private const PERIOD_COLUMNS = [
        'q1'   => ['value' => 3, 'growth' => 4],   // col C, D
        'h1'   => ['value' => 5, 'growth' => 6],   // col E, F
        'm9'   => ['value' => 7, 'growth' => 8],   // col G, H
        'year' => ['value' => 9, 'growth' => 10],  // col I, J
    ];

    public function moduleCode(): string { return 'macro'; }

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $count = 0;
        $count += $this->parseRollupSheet($ctx, $book, $regionWorkbookId, $filePath);
        $count += $this->parseDistrictSheet($ctx, $book, $regionWorkbookId, $filePath, 'district_industry',    'industry',    'млрд сўм');
        $count += $this->parseDistrictSheet($ctx, $book, $regionWorkbookId, $filePath, 'district_agriculture', 'agriculture', 'млрд сўм');
        $count += $this->parseDistrictSheet($ctx, $book, $regionWorkbookId, $filePath, 'district_services',    'services',    'млрд сўм');
        return $count;
    }

    private function parseRollupSheet(ImportContext $ctx, $book, int $rwbId, string $filePath): int
    {
        $sheet = $this->sheetResolver->resolve($ctx, $book, $rwbId, 'macro', 'rollup');
        if ($sheet === null) return 0;

        $rwSheet = \App\Models\RegionWorkbookSheet::where('region_workbook_id', $rwbId)
            ->where('logical_kind', 'rollup')->firstOrFail();
        $startRow = $this->headerDetector->detect($sheet, $ctx, $rwSheet->id);
        if ($startRow === null) return 0;

        $count = 0;
        for ($row = $startRow; $row <= $startRow + 5; $row++) {
            $colA = $sheet->getCell([1, $row])->getValue();
            $colB = $sheet->getCell([2, $row])->getValue();
            if (! is_string($colB)) continue;

            $label = trim($colB);
            $indicator = self::INDICATOR_BY_LABEL[$label] ?? null;
            if ($indicator === null) continue;

            foreach (self::PERIOD_COLUMNS as $period => $cols) {
                $value = $sheet->getCell([$cols['value'], $row])->getValue();
                $growth = $sheet->getCell([$cols['growth'], $row])->getValue();
                if (! is_numeric($value)) continue;

                $dto = new IndicatorFactDto(
                    regionCode:     $ctx->regionCode(),
                    districtCode:   null,
                    year:           $ctx->year,
                    indicatorCode:  $indicator,
                    period:         $period,
                    planValue:      (float) $value,
                    actualHokimyat: $period === 'q1' ? (float) $value : null,
                    growthPct:      is_numeric($growth) ? (float) $growth : null,
                    unit:           'млрд сўм',
                    sourceLabel:    basename($filePath) . " · {$sheet->getTitle()} · row $row",
                );
                $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
                $count++;
            }
        }
        return $count;
    }

    private function parseDistrictSheet(
        ImportContext $ctx, $book, int $rwbId, string $filePath,
        string $logicalKind, string $indicatorCode, string $unit,
    ): int {
        $sheet = $this->sheetResolver->resolve($ctx, $book, $rwbId, 'macro', $logicalKind);
        if ($sheet === null) return 0;

        $rwSheet = \App\Models\RegionWorkbookSheet::where('region_workbook_id', $rwbId)
            ->where('logical_kind', $logicalKind)->firstOrFail();
        $startRow = $this->headerDetector->detect($sheet, $ctx, $rwSheet->id);
        if ($startRow === null) return 0;

        $count = 0;
        for ($row = $startRow; $row <= $startRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getValue();
            $colB = $sheet->getCell([2, $row])->getValue();

            // District rows have integer in col A; rollup row has empty A and "вилояти" in B; skip the latter.
            $isDistrict = is_int($colA) || (is_string($colA) && ctype_digit(trim($colA)));
            if (! $isDistrict) continue;
            if (! is_string($colB)) continue;

            $districtCode = $this->districtResolver->resolve($colB, $ctx, basename($filePath) . " · {$sheet->getTitle()} · row $row");
            if ($districtCode === null) continue;   // unknown district → issue raised, skip row

            foreach (self::PERIOD_COLUMNS as $period => $cols) {
                $value = $sheet->getCell([$cols['value'], $row])->getValue();
                $growth = $sheet->getCell([$cols['growth'], $row])->getValue();
                if (! is_numeric($value)) continue;

                $dto = new IndicatorFactDto(
                    regionCode:     $ctx->regionCode(),
                    districtCode:   $districtCode,
                    year:           $ctx->year,
                    indicatorCode:  $indicatorCode,
                    period:         $period,
                    planValue:      (float) $value,
                    actualHokimyat: $period === 'q1' ? (float) $value : null,
                    growthPct:      is_numeric($growth) ? (float) $growth : null,
                    unit:           $unit,
                    sourceLabel:    basename($filePath) . " · {$sheet->getTitle()} · row $row",
                );
                $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
                $count++;
            }
        }
        return $count;
    }
}
```

- [ ] **Step 5: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=MacroModuleParser`
Expected: 1 test passes.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/Modules/ModuleParser.php \
    backend/app/Services/Import/Modules/MacroModuleParser.php \
    backend/tests/Feature/Import/MacroModuleParserTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add MacroModuleParser

Parses the 4 macro sheets — 1.1 ЯҲМ for the region rollup
(5 indicators × 4 periods = 20 rows) and 1.2/1.4/1.5 for the
district breakdowns (16 districts × 3 indicators × 4 periods =
192 rows). Sheet 1.3 (Ҳудудий саноат, special-zone detail) is
intentionally deferred to Plan 3.

Builds IndicatorFactDtos and pushes to StagingWriter. The
period column layout is fixed by the workbook template across
all 14 regions: cols C/D, E/F, G/H, I/J for q1/h1/m9/year value
and growth pairs."
```

---

## Task 15: `ImportRegionCommand`

**Files:**
- Create: `backend/app/Console/Commands/ImportRegionCommand.php`
- Create: `backend/tests/Feature/Import/ImportRegionCommandTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/ImportRegionCommandTest.php`:

```php
<?php

use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 macro creates a successful run', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/1.1-1.5-жадваллар (макро).xlsx'))) {
        $this->markTestSkipped('Andijan data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'macro',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->region_code)->toBe('andijan');
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);
    expect($run->rows_staged)->toBe(212);
});

test('import:region rejects unknown region', function () {
    $this->seed();
    $exitCode = Artisan::call('import:region', [
        'region_code' => 'atlantis', 'year' => 2026, '--module' => 'macro',
    ]);
    expect($exitCode)->not->toBe(0);
});

test('import:region skips Navoi with a warning', function () {
    $this->seed();
    $exitCode = Artisan::call('import:region', [
        'region_code' => 'navoiy', 'year' => 2026, '--module' => 'macro',
    ]);
    expect($exitCode)->toBe(0);
    expect(ImportRun::count())->toBe(0);   // no run created
    expect(Artisan::output())->toContain('Skipped');
});
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `vendor/bin/pest --filter=ImportRegionCommand`
Expected: tests fail with "command import:region is not defined" or similar.

- [ ] **Step 3: Create `ImportRegionCommand`**

Create `backend/app/Console/Commands/ImportRegionCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\MacroModuleParser;
use App\Services\Import\Modules\ModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use App\Services\Import\WorkbookLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRegionCommand extends Command
{
    protected $signature = 'import:region {region_code} {year} {--module=} {--dry-run}';
    protected $description = 'Import workbook data for one region+year into staging tables.';

    public function handle(): int
    {
        $regionCode = (string) $this->argument('region_code');
        $year = (int) $this->argument('year');

        $region = Region::where('code', $regionCode)->first();
        if (! $region) {
            $this->error("Unknown region code: $regionCode");
            return 1;
        }

        if ($regionCode === 'navoiy') {
            $this->warn("Skipped 'navoiy' — see data_quality_issues for upstream macro 1.2 contamination.");
            return 0;
        }

        $run = ImportRun::create([
            'region_code'   => $regionCode,
            'year'          => $year,
            'trigger_kind'  => 'cli',
            'status'        => ImportRunStatus::Parsing,
            'started_at'    => now(),
        ]);

        $ctx = new ImportContext(
            run: $run,
            region: $region,
            year: $year,
            dataPath: config('import.data_path'),
        );

        $issues = new IssueCollector();
        $writer = new StagingWriter();
        $sheetResolver = new SheetResolver($issues);
        $headerDetector = new HeaderDetector($issues);
        $districtResolver = new DistrictResolver($issues);

        $locator = new WorkbookLocator();
        $files = $locator->locate($ctx, $this->option('module'));

        $parsers = [
            'macro' => new MacroModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
        ];

        $this->info("Importing region '$regionCode' year $year (modules: " . implode(', ', array_keys($files)) . ")…");

        $filesProcessed = 0;
        foreach ($files as $module => $filePath) {
            if (! isset($parsers[$module])) {
                $this->line("  · $module: no parser implemented yet, skipping");
                continue;
            }
            $rwb = RegionWorkbook::firstOrCreate(
                ['region_code' => $regionCode, 'reporting_year_id' => 1, 'module_id' => 1],
                ['file_name' => basename($filePath), 'file_path' => $filePath, 'last_seen_at' => now()],
            );
            $count = $parsers[$module]->parse($ctx, $filePath, $rwb->id);
            $this->line("  · $module: $count rows buffered");
            $filesProcessed++;
        }

        $blockerCount = $issues->blockerCount();

        if ($blockerCount > 0) {
            $writer->discard();
            $issues->flush();
            $run->update([
                'status' => ImportRunStatus::Failed, 'failed_at' => now(),
                'files_processed' => $filesProcessed,
                'issues_open_count' => 0, 'issues_blocker_count' => $blockerCount,
            ]);
            $this->error("Run #{$run->id} failed: $blockerCount blocker issue(s).");
            return 1;
        }

        if ($this->option('dry-run')) {
            $rows = $writer->totalCount();
            $writer->discard();
            $issues->flush();
            $run->update([
                'status' => ImportRunStatus::AwaitingReview, 'parsed_at' => now(),
                'files_processed' => $filesProcessed,
                'rows_staged' => 0,
                'issues_open_count' => $issues->bufferedCount(),
                'issues_blocker_count' => 0,
                'notes' => "Dry run: $rows rows would have been staged.",
            ]);
            $this->info("Dry run complete. Would have staged $rows rows.");
            return 0;
        }

        DB::transaction(fn() => $writer->flush());
        $issuesWritten = $issues->flush();

        $rowsStaged = DB::table('import_staging_indicator_facts')->where('import_run_id', $run->id)->count();
        $run->update([
            'status' => ImportRunStatus::AwaitingReview,
            'parsed_at' => now(),
            'files_processed' => $filesProcessed,
            'rows_staged' => $rowsStaged,
            'issues_open_count' => $issuesWritten,
            'issues_blocker_count' => 0,
        ]);

        $this->info("Run #{$run->id}: $rowsStaged rows staged, $issuesWritten issues. Status: awaiting_review.");
        return 0;
    }
}
```

- [ ] **Step 4: Verify command auto-registration**

Laravel 12 auto-registers commands in `app/Console/Commands/`. Confirm by running:

```
php artisan list import
```

Expected: `import:region` listed.

- [ ] **Step 5: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=ImportRegionCommand`
Expected: 3 tests pass.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Console/Commands/ImportRegionCommand.php \
    backend/tests/Feature/Import/ImportRegionCommandTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add ImportRegionCommand

CLI entrypoint: php artisan import:region <code> <year> [--module]
[--dry-run]. Validates region, refuses navoiy with a one-line skip
notice, opens an import_runs row, runs WorkbookLocator + the
configured ModuleParsers, decides final status from blocker count,
flushes StagingWriter (or discards on failure) and IssueCollector."
```

---

## Task 16: Andijan macro parity test

**Files:**
- Create: `backend/tests/Feature/Import/AndijanMacroParityTest.php`

This is the integration milestone. It runs the full importer end-to-end and asserts the staged rows reproduce the inlined `DATA` blob in `index.html` within numeric tolerance.

- [ ] **Step 1: Write the parity test**

Create `backend/tests/Feature/Import/AndijanMacroParityTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

/**
 * Map an Andijan district name from the inlined DATA blob to its seeded `code`.
 * District codes in the seeder are 'd01'..'d16' (sort_order zero-padded).
 */
function andijanDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 'andijan')
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

test('Andijan macro import reproduces the inlined DATA blob within 1e-6', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/1.1-1.5-жадваллар (макро).xlsx'))) {
        $this->markTestSkipped('Andijan data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'macro',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);
    expect($run->rows_staged)->toBe(212);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)->get();

    // ----- Region rollup: 5 indicators × 4 periods = 20 rows -----
    $rollupIndicators = ['grp', 'industry', 'agriculture', 'construction', 'services'];
    foreach ($expected['regional']['macro'] as $i => $expectedRow) {
        $code = $rollupIndicators[$i];
        foreach (['q1', 'h1', 'm9', 'year'] as $period) {
            $actual = $rows->first(fn ($r) =>
                $r->indicator_code === $code &&
                $r->district_code === null &&
                $r->period->value === $period
            );
            expect($actual)->not->toBeNull("missing rollup row $code/$period");
            expect($actual->plan_value)->toBeNumericallyClose($expectedRow["{$period}_value"], 1e-6);
            if ($expectedRow["{$period}_growth"] !== null) {
                expect($actual->growth_pct)->toBeNumericallyClose($expectedRow["{$period}_growth"], 1e-4);
            }
        }
    }

    // ----- District rows: 16 × 3 indicators × 4 periods = 192 rows -----
    foreach ($expected['districts'] as $expectedDistrict) {
        $districtCode = andijanDistrictCode($expectedDistrict['name']);
        expect($districtCode)->not->toBeNull("district code lookup failed for {$expectedDistrict['name']}");

        foreach (['industry', 'agriculture', 'services'] as $indicator) {
            $block = $expectedDistrict['data'][$indicator] ?? null;
            if ($block === null) continue;

            foreach (['q1', 'h1', 'm9', 'year'] as $period) {
                $actual = $rows->first(fn ($r) =>
                    $r->indicator_code === $indicator &&
                    $r->district_code === $districtCode &&
                    $r->period->value === $period
                );
                expect($actual)->not->toBeNull("missing $districtCode/$indicator/$period");
                expect($actual->plan_value)->toBeNumericallyClose($block["{$period}_value"], 1e-6);
                if (isset($block["{$period}_growth"]) && $block["{$period}_growth"] !== null) {
                    expect($actual->growth_pct)->toBeNumericallyClose($block["{$period}_growth"], 1e-4);
                }
            }
        }
    }
});
```

- [ ] **Step 2: Run the test, confirm it passes**

Run: `vendor/bin/pest --filter=AndijanMacroParityTest`
Expected: 1 test passes (could take 5-10 seconds — it loads the workbook, runs the full importer, reads ~200 rows from staging, compares each to the JSON blob).

- [ ] **Step 3: Run the full suite to confirm no regressions**

Run: `vendor/bin/pest --no-coverage`
Expected: all schema tests + all import tests green. Total assertion count well above 350 (212 rows × ~2 assertions each in the parity test, plus all the smaller component tests).

- [ ] **Step 4: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/tests/Feature/Import/AndijanMacroParityTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "test(import): add Andijan macro parity test

End-to-end integration milestone. Runs the full
import:region andijan 2026 --module=macro, then asserts every
row in the staged indicator_facts reproduces the inlined DATA
blob from index.html within 1e-6 (values) / 1e-4 (growth_pct)
tolerance. 212 expected rows: 20 region rollup + 192 districts
× 3 indicators × 4 periods.

Plan 2 complete. Architecture validated against real source data."
```

---

## Self-review

Verified against `docs/superpowers/specs/2026-05-06-importer-tracer-bullet-design.md`:

- **Spec §2 constraints:** PhpSpreadsheet (Task 1), Postgres test DB (already from Plan 1), Pest 3 (Task 3 + all subsequent tests use Pest functional syntax), Navoi skip (Task 15), hybrid sheet resolution (Task 8), strict alt_labels matching (Task 11). ✓
- **Spec §3 architecture:** All 12 services + 1 abstract base + 1 enum + 1 DTO + 1 helper + 1 command exist. ✓
- **Spec §4 data flow:** Task 15 implements the orchestration: insert run → locate files → per-module parse → blocker decision → flush or discard → final status. ✓
- **Spec §5 component contracts:** Each task implements exactly the public surface from the spec. ✓
- **Spec §6 MacroModuleParser:** Task 14 parses 1.1 + 1.2 + 1.4 + 1.5, skips 1.3, produces 212 rows. Indicator label-to-code map matches spec. Period column layout matches spec. District-vs-rollup row detection matches spec. ✓
- **Spec §7 CLI:** Task 15 implements signature + flags + Navoi skip + sample output formatting. ✓
- **Spec §8 parity test:** Task 16 implements the full assertion suite with the documented tolerances. ✓

**Placeholder scan:** No `TBD`/`TODO`/`implement later`/`similar to`. Each step has actual code. ✓

**Type consistency:** Service constructor parameters consistent across tasks. `ImportContext`, `IssueCollector`, `IssueKind`, `IssueSeverity`, `StagingWriter`, `IndicatorFactDto` field names and signatures match across all task references. ✓

---

## Out of scope (covered in later plans)

- **Plan 3:** `inflation` module — adds `food_balance` and `warehouses` table writes, exercises the sentinel handler (`холи ҳудуд` doesn't appear in macro but does in employment).
- **Plan 4:** `budget` module.
- **Plan 5:** `budget_invest` module.
- **Plan 6:** `foreign_invest` module.
- **Plan 7:** `export` module.
- **Plan 8:** `employment` module — first module to exercise `is_sentinel`/`sentinel_label`.
- **Plan 9:** Roll out to the 12 non-Navoi regions. No new code expected; surfacing data quality issues for triage.
- **Plan 10:** Filament admin UI — `import_runs` list + per-run review with staging vs production diff + Promote/Reject actions.
- **Plan 11:** Cross-region contamination detector (the Navoi failure mode), unblocked once an upstream fix arrives.
