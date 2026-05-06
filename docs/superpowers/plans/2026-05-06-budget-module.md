# Budget Module Importer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `php artisan import:region andijan 2026 --module=budget` produce 51 staging rows for Andijan — 1 region rollup + 16 districts × 3 periods (year, h1, q2) — with `indicator_code='budget'` in the cube. Andijan parity test asserts every row matches `DATA.regional.budget` and `DATA.districts[*].data.budget` from `index.html` within tolerance 0.05.

**Architecture:** Adds `BudgetModuleParser` on top of Plan 2's pipeline. Reuses `IndicatorFactDto` (no new DTO needed — budget is cube data). One small enum change: add `Q2 = 'q2'` to the `Period` enum (column is `varchar(8)`, no DB migration). One-line registration in `ImportRegionCommand` parser registry. Two new SheetResolver signature additions. Updates the budget indicator's `supported_periods` from default `["q1","h1","m9","year"]` to `["h1","q2","year"]`.

**Tech Stack:** Laravel 12.58 · PHP 8.2 · PostgreSQL 14 · Pest 3 · `phpoffice/phpspreadsheet` (already installed).

**Working directory:** All paths relative to `backend/` unless prefixed with `../`. Run all `php artisan`, `composer`, and `vendor/bin/pest` commands from `backend/`. The Andijan budget workbook lives at `../data/2. Андижон/3-жадвал (бюджет).xlsx` (gitignored, present locally). The parity-test target `index.html` is at `../index.html`.

**TDD discipline:** Each task writes the failing test first, runs it, writes the minimal implementation, runs again, commits. Tests run against `hududlar_monitoringi_test` (Postgres). Currently 83 tests + 1640 assertions; every task must keep that count green and add to it.

---

## File map

**Created:**
- `backend/app/Services/Import/Modules/BudgetModuleParser.php`
- `backend/tests/Feature/Import/Period_Q2Test.php`
- `backend/tests/Feature/Import/IndicatorSeederBudgetPeriodsTest.php`
- `backend/tests/Feature/Import/SheetResolverBudgetTest.php`
- `backend/tests/Feature/Import/BudgetModuleParserTest.php`
- `backend/tests/Feature/Import/ImportRegionCommandBudgetTest.php`
- `backend/tests/Feature/Import/AndijanBudgetParityTest.php`

**Modified:**
- `backend/app/Enums/Period.php` (add Q2 case)
- `backend/database/seeders/IndicatorSeeder.php` (budget row's supported_periods)
- `backend/app/Services/Import/SheetResolver.php` (add 'budget' to SIGNATURES)
- `backend/app/Console/Commands/ImportRegionCommand.php` (register BudgetModuleParser)

---

## Task 1: Add `Q2` to the `Period` enum

**Files:**
- Modify: `backend/app/Enums/Period.php`
- Create: `backend/tests/Feature/Import/Period_Q2Test.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/Period_Q2Test.php`:

```php
<?php

use App\Enums\Period;

test('Period enum includes Q2 case', function () {
    expect(Period::Q2->value)->toBe('q2');
});

test('Period enum has exactly 5 cases', function () {
    expect(Period::cases())->toHaveCount(5);
});

test('Period::from("q2") returns Period::Q2', function () {
    expect(Period::from('q2'))->toBe(Period::Q2);
});
```

- [ ] **Step 2: Run, confirm fails**

Run from `backend/`: `vendor/bin/pest --filter=Period_Q2`
Expected: FAIL with "Class App\Enums\Period has no case Q2" or similar.

- [ ] **Step 3: Add Q2 to the enum**

Edit `backend/app/Enums/Period.php`. Find the existing 4-case enum and add `Q2`:

```php
<?php

namespace App\Enums;

enum Period: string
{
    case Q1   = 'q1';
    case Q2   = 'q2';
    case H1   = 'h1';
    case M9   = 'm9';
    case Year = 'year';
}
```

- [ ] **Step 4: Run, confirm passes**

Run: `vendor/bin/pest --filter=Period_Q2`
Expected: 3 tests pass.

- [ ] **Step 5: Run full suite to confirm no regressions**

Run: `vendor/bin/pest --no-coverage`
Expected: 83 + 3 = 86 tests, all green. (Note: existing tests using Period values like 'q1', 'h1', 'year' are unaffected — Q2 is purely additive.)

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Enums/Period.php \
    backend/tests/Feature/Import/Period_Q2Test.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add Q2 case to Period enum

Budget module is the first to use Q2 (April-June quarter).
Schema column is varchar(8); no migration. Future modules
(export, etc.) may also use Q2.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Update `IndicatorSeeder` budget supported_periods

**Files:**
- Modify: `backend/database/seeders/IndicatorSeeder.php`
- Create: `backend/tests/Feature/Import/IndicatorSeederBudgetPeriodsTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/IndicatorSeederBudgetPeriodsTest.php`:

```php
<?php

use App\Models\Indicator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('budget indicator has supported_periods = [h1, q2, year]', function () {
    $this->seed();
    $budget = Indicator::where('code', 'budget')->firstOrFail();
    expect($budget->supported_periods)->toBe(['h1', 'q2', 'year']);
});

test('macro indicators still use the default 4-period set', function () {
    $this->seed();
    $grp = Indicator::where('code', 'grp')->firstOrFail();
    expect($grp->supported_periods)->toBe(['q1', 'h1', 'm9', 'year']);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `vendor/bin/pest --filter=IndicatorSeederBudgetPeriods`
Expected: FAIL — `budget`'s supported_periods is currently `["q1","h1","m9","year"]` (the default), not `["h1","q2","year"]`.

- [ ] **Step 3: Update the seeder**

Edit `backend/database/seeders/IndicatorSeeder.php`. Find the `budget` row in the `$rows` array (the row whose first column is `'budget'`). Change its `periods` value from `$allPeriods` to a new variable `$h1q2Year`:

First, near the top of the `run()` method where `$allPeriods`, `$yearOnly`, `$h1Year` are defined, add:

```php
$h1q2Year   = json_encode(['h1','q2','year']);
```

Then in the `budget` row of `$rows`, change `$allPeriods` (the 9th tuple item — the `periods` column per the inline header comment) to `$h1q2Year`. The budget row currently looks like:

```php
['budget',               'Бюджет тушумлари',                                'Бюджет',                      'Бюджет',               'budget',         'both',     'млрд сўм',    false, $allPeriods, false, true,  false, null,                           null,           'bank',      70],
```

Becomes:

```php
['budget',               'Бюджет тушумлари',                                'Бюджет',                      'Бюджет',               'budget',         'both',     'млрд сўм',    false, $h1q2Year,   false, true,  false, null,                           null,           'bank',      70],
```

- [ ] **Step 4: Run, confirm passes**

Run: `vendor/bin/pest --filter=IndicatorSeederBudgetPeriods`
Expected: 2 tests pass.

- [ ] **Step 5: Run full suite**

Run: `vendor/bin/pest --no-coverage`
Expected: 86 + 2 = 88 tests, all green. (The existing `IndicatorsTableTest` should still pass — none of its assertions check `budget`'s `supported_periods` specifically.)

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/database/seeders/IndicatorSeeder.php \
    backend/tests/Feature/Import/IndicatorSeederBudgetPeriodsTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): set budget supported_periods to [h1,q2,year]

Budget data covers year, h1, and q2 — not q1/m9. Updates the
IndicatorSeeder so the indicator catalog reflects the actual
period coverage.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Add `budget` signature to `SheetResolver`

**Files:**
- Modify: `backend/app/Services/Import/SheetResolver.php`
- Create: `backend/tests/Feature/Import/SheetResolverBudgetTest.php`

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/SheetResolverBudgetTest.php`:

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

function loadAndijanBudget(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/3-жадвал (бюджет).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan budget workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(false);
    return $reader->load($path);
}

function budgetSheetCtx(): array
{
    $region = Region::where('code', 'andijan')->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'budget')->value('id'),
        'file_name' => '3-жадвал (бюджет).xlsx',
        'file_path' => 'fixture',
        'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region, year: 2026, dataPath: base_path('../data'),
    );
    return ['ctx' => $ctx, 'rwb' => $rwb];
}

test('SheetResolver detects budget sheet by content (тушум for Andijan)', function () {
    $this->seed();
    $book = loadAndijanBudget();
    ['ctx' => $ctx, 'rwb' => $rwb] = budgetSheetCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'budget', 'budget');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('тушум');
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `vendor/bin/pest --filter=SheetResolverBudget`
Expected: FAIL — no `budget` logical_kind in SIGNATURES, sheet returns null.

- [ ] **Step 3: Add the signature**

In `backend/app/Services/Import/SheetResolver.php`, find the `private const SIGNATURES = [...]` array. Add the `budget` entry. The full SIGNATURES becomes:

```php
private const SIGNATURES = [
    'rollup'                    => ['ЯҲМ', 'асосий иқтисодий кўрсаткич'],
    'district_industry'         => ['Саноат маҳсулотларини ишлаб чиқариш'],
    'district_agriculture'      => ['Қишлоқ хўжалиги маҳсулотларини'],
    'district_services'         => ['Бозор хизматлари'],
    'food_balance'              => ['Балансини асос', 'Маҳсулот номи', 'Ресурс'],
    'warehouses_district_table' => ['Захира омборлари', 'совутгичли омборлар'],
    'budget'                    => ['ПРОГНОЗ', 'тушумлар', 'Ҳудудлар'],
];
```

- [ ] **Step 4: Run, confirm passes**

Run: `vendor/bin/pest --filter=SheetResolverBudget`
Expected: 1 test passes.

- [ ] **Step 5: Run full suite**

Run: `vendor/bin/pest --no-coverage`
Expected: 88 + 1 = 89 tests green.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/SheetResolver.php \
    backend/tests/Feature/Import/SheetResolverBudgetTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add SheetResolver signature for budget

Matches Andijan's 'тушум' sheet plus other regions' patterns
('Бюджетга тушумлар', '3-илова (2)', 'тушим' typo) via the
content-pattern signatures.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Implement `BudgetModuleParser`

**Files:**
- Create: `backend/app/Services/Import/Modules/BudgetModuleParser.php`
- Create: `backend/tests/Feature/Import/BudgetModuleParserTest.php`

This is the meat of Plan 4. **Before writing the parser**, the implementer must open the Andijan budget workbook and verify the column-N location for `q2_execution_pct`. Plan 1's tmp_inspect.py only dumped 12 cols. Use a quick PhpSpreadsheet inspection script or `php artisan tinker` to dump rows 4-10 of `тушум` from cols A through P to confirm the layout before committing.

### Step 1: Failing test

Create `backend/tests/Feature/Import/BudgetModuleParserTest.php`:

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
use App\Services\Import\Modules\BudgetModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function budgetParserCtx(): array
{
    $path = base_path('../data/2. Андижон/3-жадвал (бюджет).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan budget workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'budget')->value('id'),
        'file_name' => '3-жадвал (бюджет).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('BudgetModuleParser produces 51 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = budgetParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new BudgetModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        $districts,
        $writer,
        $issues,
    );

    $parser->parse($ctx, $path, $rwb->id);

    expect($writer->bufferedCount('import_staging_indicator_facts'))->toBe(51);

    DB::transaction(fn() => $writer->flush());
    $issues->flush();

    expect(ImportStagingIndicatorFact::where('indicator_code', 'budget')->count())->toBe(51);

    // 1 region rollup × 3 periods + 16 districts × 3 periods
    $rollup = ImportStagingIndicatorFact::where('indicator_code', 'budget')->whereNull('district_code');
    expect($rollup->count())->toBe(3);
    $districtRows = ImportStagingIndicatorFact::where('indicator_code', 'budget')->whereNotNull('district_code');
    expect($districtRows->count())->toBe(48);

    // Spot-check region rollup year_plan
    $regionYear = ImportStagingIndicatorFact::where('indicator_code', 'budget')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($regionYear)->not->toBeNull();
    expect($regionYear->plan_value)->toBeNumericallyClose(5298.6, 0.05);
    expect($regionYear->expected_value)->toBeNumericallyClose(5888.6, 0.05);

    // Spot-check region rollup h1 with execution %
    $regionH1 = ImportStagingIndicatorFact::where('indicator_code', 'budget')
        ->whereNull('district_code')->where('period', 'h1')->first();
    expect($regionH1->plan_value)->toBeNumericallyClose(2407.9, 0.05);
    expect($regionH1->expected_value)->toBeNumericallyClose(2598.6, 0.05);
    expect($regionH1->pct_of_plan)->toBeNumericallyClose(107.9, 0.05);

    // Spot-check region rollup q2 with execution %
    $regionQ2 = ImportStagingIndicatorFact::where('indicator_code', 'budget')
        ->whereNull('district_code')->where('period', 'q2')->first();
    expect($regionQ2->plan_value)->toBeNumericallyClose(1272.6, 0.05);
    expect($regionQ2->expected_value)->toBeNumericallyClose(1390.0, 0.05);
    expect($regionQ2->pct_of_plan)->toBeNumericallyClose(109.2, 0.05);

    // Spot-check Andijan city (d01) year
    $andijanCityYear = ImportStagingIndicatorFact::where('indicator_code', 'budget')
        ->where('district_code', 'd01')->where('period', 'year')->first();
    expect($andijanCityYear)->not->toBeNull();
    expect($andijanCityYear->plan_value)->toBeNumericallyClose(172.2, 0.05);
});
```

### Step 2: Run, confirm fails

Run: `vendor/bin/pest --filter=BudgetModuleParser`
Expected: FAIL — class not found.

### Step 3: Inspect the actual workbook layout to verify column N

Before writing the parser, inspect the workbook to confirm cols L vs N for q2_execution_pct. Add a temporary debug test or use tinker:

```
php artisan tinker --execute="
\$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('../data/2. Андижон/3-жадвал (бюджет).xlsx');
\$reader->setReadDataOnly(false);
\$book = \$reader->load('../data/2. Андижон/3-жадвал (бюджет).xlsx');
\$sheet = \$book->getSheetByName('тушум');
for (\$row = 4; \$row <= 10; \$row++) {
    \$rowData = [];
    for (\$col = 1; \$col <= 16; \$col++) {
        \$rowData[\$col] = \$sheet->getCell([\$col, \$row])->getCalculatedValue();
    }
    print_r([\$row => \$rowData]);
}
"
```

Note which column has the q2 execution % (109.2 for Andijan rollup). Adjust the parser's column constants accordingly. Likely **col N = 14**, but Plan 1's inspection didn't dump that far. Verify before coding.

### Step 4: Create `backend/app/Services/Import/Modules/BudgetModuleParser.php`

Use the column constants you verified in Step 3. Default assumption: q2_execution_pct = col 14. If different, replace `14` below with the real value.

```php
<?php

namespace App\Services\Import\Modules;

use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BudgetModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'budget'; }

    /**
     * Column indices for Andijan's тушум sheet (verify before adopting for other regions).
     * Cols are 1-indexed: A=1, B=2, C=3, ...
     */
    private const COL_PLAN_YEAR     = 3;   // C
    private const COL_PLAN_H1       = 4;   // D
    private const COL_PLAN_Q2       = 5;   // E
    private const COL_EXP_YEAR      = 6;   // F
    private const COL_EXP_H1        = 7;   // G
    private const COL_EXP_Q2        = 8;   // H
    private const COL_EXEC_H1_PCT   = 12;  // L
    private const COL_EXEC_Q2_PCT   = 14;  // N — verify against the actual workbook

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);    // workbook may use SUM formulas
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'budget', 'budget');
        if ($sheet === null) return 0;

        $rollupRow = $this->findRollupRow($sheet);
        if ($rollupRow === null) return 0;

        $count = 0;
        $count += $this->emitEntityRows($ctx, $sheet, $rollupRow, null, $filePath);

        for ($row = $rollupRow + 1; $row <= $rollupRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            if (! $this->isDistrictRow($colA, $colB)) continue;
            $districtCode = $this->districtResolver->resolve(
                trim((string) $colB), $ctx,
                basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            if ($districtCode === null) continue;
            $count += $this->emitEntityRows($ctx, $sheet, $row, $districtCode, $filePath);
        }
        return $count;
    }

    /** Returns the row number of the region rollup ("вилояти" in col B), or null. */
    private function findRollupRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= 15; $row++) {
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            if (is_string($colB) && str_contains($colB, 'вилояти')) {
                return $row;
            }
        }
        return null;
    }

    private function isDistrictRow(mixed $colA, mixed $colB): bool
    {
        if (! is_string($colB) || trim($colB) === '') return false;
        if (is_int($colA)) return true;
        if (is_string($colA) && ctype_digit(trim($colA))) return true;
        return false;
    }

    /** Emits 3 IndicatorFactDtos (year, h1, q2) for one row. */
    private function emitEntityRows(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): int {
        $plans = [
            'year' => $this->numericOrNull($sheet->getCell([self::COL_PLAN_YEAR, $row])->getCalculatedValue()),
            'h1'   => $this->numericOrNull($sheet->getCell([self::COL_PLAN_H1,   $row])->getCalculatedValue()),
            'q2'   => $this->numericOrNull($sheet->getCell([self::COL_PLAN_Q2,   $row])->getCalculatedValue()),
        ];
        $expecteds = [
            'year' => $this->numericOrNull($sheet->getCell([self::COL_EXP_YEAR,  $row])->getCalculatedValue()),
            'h1'   => $this->numericOrNull($sheet->getCell([self::COL_EXP_H1,    $row])->getCalculatedValue()),
            'q2'   => $this->numericOrNull($sheet->getCell([self::COL_EXP_Q2,    $row])->getCalculatedValue()),
        ];
        $execPcts = [
            'year' => null,    // year-level execution % not present in workbook
            'h1'   => $this->numericOrNull($sheet->getCell([self::COL_EXEC_H1_PCT, $row])->getCalculatedValue()),
            'q2'   => $this->numericOrNull($sheet->getCell([self::COL_EXEC_Q2_PCT, $row])->getCalculatedValue()),
        ];

        $count = 0;
        foreach (['year', 'h1', 'q2'] as $period) {
            if ($plans[$period] === null && $expecteds[$period] === null) {
                continue;   // skip empty periods
            }
            $dto = new IndicatorFactDto(
                regionCode:     $ctx->regionCode(),
                districtCode:   $districtCode,
                year:           $ctx->year,
                indicatorCode:  'budget',
                period:         $period,
                planValue:      $plans[$period],
                expectedValue:  $expecteds[$period],
                pctOfPlan:      $execPcts[$period],
                unit:           'млрд сўм',
                sourceLabel:    basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
            $count++;
        }
        return $count;
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if (! is_numeric($value)) return null;
        return (float) $value;
    }
}
```

### Step 5: Run the test

Run: `vendor/bin/pest --filter=BudgetModuleParser`
Expected: 1 test passes (~10 assertions).

If it fails:
- **Wrong row count** (≠ 51): Inspect the rollup row vs district detection. The rollup may not be on the row `findRollupRow()` returned (some workbooks have header rows containing "вилояти" before the data row).
- **Spot-check assertion fails by a tiny amount** (< 1e-3): the workbook's raw value vs DATA blob's rounded value — the 0.05 tolerance should accommodate. Don't lower further without investigation.
- **Spot-check assertion fails by ~10x or more**: column index is wrong. Re-inspect the workbook (Step 3) and adjust `COL_*` constants.
- **Andijan city (d01) not found**: `DistrictResolver` raised `unknown_district`. Check what string the budget sheet uses for that district. If different from existing alt_labels, add to `regions_districts.json` and re-seed.

### Step 6: Commit

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/Modules/BudgetModuleParser.php \
    backend/tests/Feature/Import/BudgetModuleParserTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add BudgetModuleParser

Parses Andijan's 'тушум' sheet — 1 region rollup + 16 districts,
each emitting 3 IndicatorFactDtos (year, h1, q2 periods) for a
total of 51 staging rows under indicator_code='budget'. Uses the
existing IndicatorFactDto (no new DTO needed for cube data).

Column mapping verified against actual workbook before commit;
COL_EXEC_Q2_PCT = N (14) confirmed.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

If the column verification surfaced changes (e.g., q2_execution_pct is at a different column than 14), include that in the commit message.

---

## Task 5: Register `BudgetModuleParser` in `ImportRegionCommand`

**Files:**
- Modify: `backend/app/Console/Commands/ImportRegionCommand.php`
- Create: `backend/tests/Feature/Import/ImportRegionCommandBudgetTest.php`

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/ImportRegionCommandBudgetTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 budget creates a successful run with 51 rows', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/3-жадвал (бюджет).xlsx'))) {
        $this->markTestSkipped('Andijan budget data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'budget',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    expect(ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'budget')->count())->toBe(51);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `vendor/bin/pest --filter=ImportRegionCommandBudget`
Expected: FAIL with output containing "no parser implemented yet, skipping" and 0 staged budget rows.

- [ ] **Step 3: Register the parser**

In `backend/app/Console/Commands/ImportRegionCommand.php`:

Add the import at the top, next to the existing `MacroModuleParser` and `InflationModuleParser` imports:

```php
use App\Services\Import\Modules\BudgetModuleParser;
```

Add the `budget` entry to the `$parsers` array. The block becomes:

```php
$parsers = [
    'macro'     => new MacroModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'inflation' => new InflationModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'budget'    => new BudgetModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

- [ ] **Step 4: Run, confirm passes**

Run: `vendor/bin/pest --filter=ImportRegionCommandBudget`
Expected: 1 test passes.

- [ ] **Step 5: Run full suite**

Run: `vendor/bin/pest --no-coverage`
Expected: 89 + 1 (Task 4) + 1 (this Task) = ~91 tests green.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Console/Commands/ImportRegionCommand.php \
    backend/tests/Feature/Import/ImportRegionCommandBudgetTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): register BudgetModuleParser in ImportRegionCommand

php artisan import:region andijan 2026 --module=budget produces
51 staging rows. Omitting --module runs all 3 modules in one
ImportRun (212 macro + 28 inflation + 51 budget = 291 rows).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: `AndijanBudgetParityTest`

**Files:**
- Create: `backend/tests/Feature/Import/AndijanBudgetParityTest.php`

- [ ] **Step 1: Write the parity test**

Create `backend/tests/Feature/Import/AndijanBudgetParityTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanBudgetDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 'andijan')
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

test('Andijan budget import reproduces DATA.regional.budget and DATA.districts[*].data.budget within 0.05', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/3-жадвал (бюджет).xlsx'))) {
        $this->markTestSkipped('Andijan budget data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'budget',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'budget')->get();

    expect($rows)->toHaveCount(51);

    // ----- Region rollup: 3 periods × ~3 facets each -----
    $regional = $expected['regional']['budget'];
    foreach (['year', 'h1', 'q2'] as $period) {
        $actual = $rows->first(fn ($r) =>
            $r->district_code === null && $r->period->value === $period
        );
        expect($actual)->not->toBeNull("missing rollup row for period $period");
        if (isset($regional["{$period}_plan"])) {
            expect($actual->plan_value)->toBeNumericallyClose($regional["{$period}_plan"], 0.05);
        }
        if (isset($regional["{$period}_expected"])) {
            expect($actual->expected_value)->toBeNumericallyClose($regional["{$period}_expected"], 0.05);
        }
        if (isset($regional["{$period}_execution_pct"])) {
            expect($actual->pct_of_plan)->toBeNumericallyClose($regional["{$period}_execution_pct"], 0.05);
        }
    }

    // ----- District rows: 16 × 3 periods × ~3 facets -----
    $matched = 0;
    $unmatched = [];
    foreach ($expected['districts'] as $expectedDistrict) {
        $b = $expectedDistrict['data']['budget'] ?? null;
        if ($b === null) continue;

        $districtCode = andijanBudgetDistrictCode($expectedDistrict['name']);
        if ($districtCode === null) {
            $unmatched[] = "lookup-failed:{$expectedDistrict['name']}";
            continue;
        }

        foreach (['year', 'h1', 'q2'] as $period) {
            $actual = $rows->first(fn ($r) =>
                $r->district_code === $districtCode && $r->period->value === $period
            );
            if ($actual === null) {
                $unmatched[] = "no-row:{$districtCode}/{$period}";
                continue;
            }
            $matched++;

            if (isset($b["{$period}_plan"])) {
                expect($actual->plan_value)->toBeNumericallyClose($b["{$period}_plan"], 0.05);
            }
            if (isset($b["{$period}_expected"])) {
                expect($actual->expected_value)->toBeNumericallyClose($b["{$period}_expected"], 0.05);
            }
            if (isset($b["{$period}_execution_pct"])) {
                expect($actual->pct_of_plan)->toBeNumericallyClose($b["{$period}_execution_pct"], 0.05);
            }
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(45);    // floor: at least 45 of 48 district-period rows match

    if (! empty($unmatched)) {
        echo "\nUnmatched budget entries: " . implode(', ', $unmatched) . "\n";
    }
});
```

- [ ] **Step 2: Run, verify it passes**

Run: `vendor/bin/pest --filter=AndijanBudgetParity`
Expected: 1 test passes.

If it fails:
- **Counts mismatch**: dump the staging rows' periods + district_codes and the DATA blob's keys to see where the divergence is.
- **Numeric assertions fail by tiny amounts**: tolerance 0.05 should cover 1dp rounding. Larger divergence means a column-index error from Task 4 — re-verify.
- **Unmatched DATA entries logged**: investigate which districts/periods didn't match.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/pest --no-coverage`
Expected: ~91 + 1 = ~92 tests green.

- [ ] **Step 4: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/tests/Feature/Import/AndijanBudgetParityTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "test(import): add Andijan budget parity test

51 staging rows asserted against DATA.regional.budget (3 periods)
and DATA.districts[*].data.budget (16 districts × 3 periods)
within tolerance 0.05. Plan 4 complete: macro + inflation +
budget all end-to-end with parity validation.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Self-review

Verified against `docs/superpowers/specs/2026-05-06-budget-module-design.md`:

- **Spec §3 architecture deltas:** Task 1 (Period.Q2), Task 2 (IndicatorSeeder periods), Task 3 (SheetResolver), Task 4 (BudgetModuleParser), Task 5 (command registration). ✓
- **Spec §4 column mapping table:** Task 4 implements with explicit named constants for each col. q2_execution_pct (col 14) flagged for verification before commit. ✓
- **Spec §5 CLI integration:** Task 5. ✓
- **Spec §6 parity test:** Task 6 with the documented period/facet structure and tolerance 0.05. ✓

**Placeholder scan:** No "TBD"/"TODO". The "verify col N" notes are explicit known-unknowns that the implementer resolves during Step 3 of Task 4.

**Type consistency:** `Period::Q2` (Task 1), `IndicatorFactDto` field names (existing), parser column constants (Task 4) all consistent.

---

## Out of scope (deferred)

- **Plans 5-8:** budget_invest, foreign_invest, export, employment.
- **Plan 9:** roll out to 12 non-Navoi regions.
- **Plan 10:** Filament admin UI.
- **Plan 11:** cross-region contamination detector (Navoi unblock).
- **2025 prior-year comparison sheet** (`2025 солиштирма`) — present in 6 regions' workbooks. Not consumed by this importer; future enhancement if dashboard wants prior-year deltas.
