# Budget Investment Module Importer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `php artisan import:region andijan 2026 --module=budget_invest` produce 51 staging rows for Andijan — 1 region rollup + 16 districts × 3 periods (q1, h1, year) — under `indicator_code='budget_invest'`. Andijan parity test asserts every row matches `DATA.regional.budget_investment` and `DATA.districts[*].data.budget_investment` from `index.html` within tolerance 0.05.

**Architecture:** Adds `BudgetInvestModuleParser` on top of Plan 2's pipeline. Reuses `IndicatorFactDto`. Sheet name is region-coded (different suffix per region: `1.ҚР`, `2.Анд`, …, `14.Тош ш.`); content-pattern matching in `SheetResolver` handles all 14 variants. Row classifier skips ownership-breakdown rows (`шу жумладан:`, `*буюртмачилигида:`). Two count_extra columns: `objects` replicates across periods, `commissioning_year_count` populates only on year-period rows.

**Tech Stack:** Laravel 12.58 · PHP 8.2 · PostgreSQL 14 · Pest 3 · `phpoffice/phpspreadsheet` (already installed).

**Working directory:** All paths relative to `backend/` unless prefixed with `../`. Run `php artisan`, `composer`, `vendor/bin/pest` commands from `backend/`. The Andijan workbook lives at `../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx` (gitignored, present locally). Parity-test target `index.html` is at `../index.html`.

**Memory note (Plan 4 lesson):** PhpSpreadsheet OOMs on the default PHP memory limit. Use `php -d memory_limit=1G vendor/bin/pest ...` for any test that loads workbooks (i.e., everything from Task 3 onward).

**TDD discipline:** Failing test first, run it to confirm failure, write minimal implementation, run again, commit. Tests against `hududlar_monitoringi_test`. Currently 92 tests + 1955 assertions; every task adds tests and keeps the count green.

---

## File map

**Created:**
- `backend/app/Services/Import/Modules/BudgetInvestModuleParser.php`
- `backend/tests/Feature/Import/IndicatorSeederBudgetInvestPeriodsTest.php`
- `backend/tests/Feature/Import/SheetResolverBudgetInvestTest.php`
- `backend/tests/Feature/Import/BudgetInvestModuleParserTest.php`
- `backend/tests/Feature/Import/ImportRegionCommandBudgetInvestTest.php`
- `backend/tests/Feature/Import/AndijanBudgetInvestParityTest.php`

**Modified:**
- `backend/database/seeders/IndicatorSeeder.php` (budget_invest supported_periods)
- `backend/app/Services/Import/SheetResolver.php` (add 'budget_invest' SIGNATURE)
- `backend/app/Console/Commands/ImportRegionCommand.php` (register parser)

---

## Task 1: Update `IndicatorSeeder` budget_invest supported_periods

**Files:**
- Modify: `backend/database/seeders/IndicatorSeeder.php`
- Create: `backend/tests/Feature/Import/IndicatorSeederBudgetInvestPeriodsTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/IndicatorSeederBudgetInvestPeriodsTest.php`:

```php
<?php

use App\Models\Indicator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('budget_invest indicator has supported_periods = [q1, h1, year]', function () {
    $this->seed();
    $bi = Indicator::where('code', 'budget_invest')->firstOrFail();
    expect($bi->supported_periods)->toBe(['q1', 'h1', 'year']);
});

test('macro indicators still use the default 4-period set', function () {
    $this->seed();
    $grp = Indicator::where('code', 'grp')->firstOrFail();
    expect($grp->supported_periods)->toBe(['q1', 'h1', 'm9', 'year']);
});
```

- [ ] **Step 2: Run, confirm fails**

Run from `backend/`: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/IndicatorSeederBudgetInvestPeriodsTest.php`
Expected: FAIL — `budget_invest` currently has `["q1","h1","m9","year"]` (the default), not `["q1","h1","year"]`.

- [ ] **Step 3: Update the seeder**

Edit `backend/database/seeders/IndicatorSeeder.php`:

1. Near the top of `run()`, add a new period variable next to the existing `$allPeriods`, `$yearOnly`, `$h1Year`, `$h1q2Year`:

```php
$q1H1Year   = json_encode(['q1','h1','year']);
```

2. Find the `budget_invest` row in the `$rows` array. The row currently has `$allPeriods` as its 9th tuple element (the periods column). Change it to `$q1H1Year`. The row should change from:

```php
['budget_investment',    'Бюджет инвестициялари ўзлаштирилиши',             'Бюджет инвест',               'Бюджет инвестициялари','budget_invest',  'both',     'млн сўм',     false, $allPeriods, false, true,  false, 'Объектлар сони',                'Ишга туширилаётган объектлар', 'bank',      80],
```

To:

```php
['budget_investment',    'Бюджет инвестициялари ўзлаштирилиши',             'Бюджет инвест',               'Бюджет инвестициялари','budget_invest',  'both',     'млн сўм',     false, $q1H1Year,   false, true,  false, 'Объектлар сони',                'Ишга туширилаётган объектлар', 'bank',      80],
```

Note: the indicator's `code` is `budget_investment` (not `budget_invest`). The module_code (5th tuple element) is `budget_invest`. Don't confuse them. The test queries by `Indicator::where('code', 'budget_invest')` — wait, that needs verification.

**Verification step:** Look up the actual indicator code in the seeder. The Plan 1 seed list shows the row's first tuple element is `'budget_investment'` (the indicator code). The module_code is `'budget_invest'`. The test query above uses `code='budget_invest'` which would FAIL because no such indicator code exists.

**Correct the test before running it.** The test should be:

```php
test('budget_invest module indicator has supported_periods = [q1, h1, year]', function () {
    $this->seed();
    $bi = Indicator::where('code', 'budget_investment')->firstOrFail();
    expect($bi->supported_periods)->toBe(['q1', 'h1', 'year']);
});
```

- [ ] **Step 4: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/IndicatorSeederBudgetInvestPeriodsTest.php`
Expected: 2 tests pass.

- [ ] **Step 5: Run full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: 92 + 2 = 94 tests, all green.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/database/seeders/IndicatorSeeder.php \
    backend/tests/Feature/Import/IndicatorSeederBudgetInvestPeriodsTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): set budget_investment supported_periods to [q1,h1,year]

Budget investment data covers q1, h1, and year — not q2 or m9.
Updates IndicatorSeeder so the catalog matches actual coverage.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Add `budget_invest` signature to `SheetResolver`

**Files:**
- Modify: `backend/app/Services/Import/SheetResolver.php`
- Create: `backend/tests/Feature/Import/SheetResolverBudgetInvestTest.php`

The sheet name varies per region (`1.ҚР`, `2.Анд`, `3.Бух`, …). The hybrid resolver needs content-pattern signatures that survive across all 14 variants. Plan 4 lesson: the scorer silently skips RichText cells; pick plain-string substrings from header rows.

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/SheetResolverBudgetInvestTest.php`:

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

function loadAndijanBudgetInvest(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan budget_invest workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(false);
    return $reader->load($path);
}

function budgetInvestSheetCtx(): array
{
    $region = Region::where('code', 'andijan')->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'budget_invest')->value('id'),
        'file_name' => '4.1-жадвал (бюджет инвестка).xlsx',
        'file_path' => 'fixture',
        'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region, year: 2026, dataPath: base_path('../data'),
    );
    return ['ctx' => $ctx, 'rwb' => $rwb];
}

test('SheetResolver detects budget_invest sheet by content (2.Анд for Andijan)', function () {
    $this->seed();
    $book = loadAndijanBudgetInvest();
    ['ctx' => $ctx, 'rwb' => $rwb] = budgetInvestSheetCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'budget_invest', 'budget_invest');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('2.Анд');
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/SheetResolverBudgetInvestTest.php`
Expected: FAIL — no `budget_invest` logical_kind in SIGNATURES.

- [ ] **Step 3: Inspect the workbook to find plain-string signatures**

Before adding signatures, verify which strings appear as plain strings (not RichText) in rows 1-6:

```
php -d memory_limit=1G artisan tinker --execute="
\$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx');
\$reader->setReadDataOnly(false);
\$book = \$reader->load('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx');
\$sheet = \$book->getSheetByName('2.Анд');
for (\$row = 1; \$row <= 6; \$row++) {
    for (\$col = 1; \$col <= 12; \$col++) {
        \$cell = \$sheet->getCell([\$col, \$row]);
        \$val = \$cell->getValue();
        \$type = is_object(\$val) ? get_class(\$val) : gettype(\$val);
        if (is_string(\$val) || is_numeric(\$val)) {
            echo 'r' . \$row . 'c' . \$col . ' [' . \$type . ']: ' . \$val . PHP_EOL;
        } elseif (is_object(\$val)) {
            echo 'r' . \$row . 'c' . \$col . ' [' . \$type . ']: (RichText)' . PHP_EOL;
        }
    }
}
"
```

The output identifies which header cells are plain strings vs RichText. Pick 2-3 plain-string substrings that:
- Appear in row 1-6 of the budget_invest sheet
- Don't appear in any other sheet of any other workbook (to avoid scoring collisions)

- [ ] **Step 4: Add the signature**

Add the `budget_invest` entry to `SheetResolver::SIGNATURES`. The exact strings depend on what plain-string cells the inspection in Step 3 surfaced. Likely candidates (in priority order):

1. `'объект сони'` — appears in row 6 as a column header
2. `'лимит'` — appears in row 6 as a column header
3. `'ўзлаш-тириш'` — appears in row 6 as a column header

The full SIGNATURES becomes:

```php
private const SIGNATURES = [
    'rollup'                    => ['ЯҲМ', 'асосий иқтисодий кўрсаткич'],
    'district_industry'         => ['Саноат маҳсулотларини ишлаб чиқариш'],
    'district_agriculture'      => ['Қишлоқ хўжалиги маҳсулотларини'],
    'district_services'         => ['Бозор хизматлари'],
    'food_balance'              => ['Балансини асос', 'Маҳсулот номи', 'Ресурс'],
    'warehouses_district_table' => ['Захира омборлари', 'совутгичли омборлар'],
    'budget'                    => ['прогноз', 'кутилиш', 'Ҳудудлар'],
    'budget_invest'             => ['объект сони', 'лимит', 'ўзлаш-тириш'],
];
```

If the Step 3 inspection shows different plain-string content, replace the three signatures with substrings from cells the inspection surfaced as `[string]`.

- [ ] **Step 5: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/SheetResolverBudgetInvestTest.php`
Expected: 1 test passes.

- [ ] **Step 6: Run full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: 94 + 1 = 95 tests green.

- [ ] **Step 7: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/SheetResolver.php \
    backend/tests/Feature/Import/SheetResolverBudgetInvestTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add SheetResolver signature for budget_invest

Sheet name is region-coded (1.ҚР, 2.Анд, …, 14.Тош ш.). Plain-
string column-header signatures match all 14 variants by content.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Implement `BudgetInvestModuleParser`

**Files:**
- Create: `backend/app/Services/Import/Modules/BudgetInvestModuleParser.php`
- Create: `backend/tests/Feature/Import/BudgetInvestModuleParserTest.php`

The meat of Plan 5. Before writing the parser, verify all column positions including cols M-V. Plan 1's inspection only dumped 12 cols; year_absorption, year_pct, commissioning_year_count, commissioning_year_value all live in cols M-V somewhere.

- [ ] **Step 0 (BEFORE writing the parser): Inspect column layout**

Use tinker to dump rows 4-9 of the `2.Анд` sheet across cols A-V (1-22):

```
php -d memory_limit=1G artisan tinker --execute="
\$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx');
\$reader->setReadDataOnly(false);
\$book = \$reader->load('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx');
\$sheet = \$book->getSheetByName('2.Анд');
for (\$row = 4; \$row <= 12; \$row++) {
    \$rowData = [];
    for (\$col = 1; \$col <= 22; \$col++) {
        \$rowData[chr(64+\$col)] = \$sheet->getCell([\$col, \$row])->getCalculatedValue();
    }
    print_r([\$row => \$rowData]);
}
"
```

Look for the `Жами` rollup row's values matching DATA.regional.budget_investment:
- `objects = 100`, col **C**(3)
- `limit = 950279.86`, col **D**(4)
- `q1_absorption = 177548.80`, col **E**(5)
- `q1_pct = 18.68`, col **F**(6)
- `h1_absorption = 444137.69`, likely col **I**(9)
- `h1_pct = 46.74`, likely col **J**(10)
- `year_absorption = 1024641.5`, somewhere in M-V (verify)
- `year_pct = 107.8`, somewhere in M-V (verify)
- `commissioning_year_count = 96`, somewhere in M-V (verify)

Note the column letters/numbers for each value found. Use them in Step 2 below.

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/BudgetInvestModuleParserTest.php`:

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
use App\Services\Import\Modules\BudgetInvestModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function budgetInvestParserCtx(): array
{
    $path = base_path('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan budget_invest workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'budget_invest')->value('id'),
        'file_name' => '4.1-жадвал (бюджет инвестка).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('BudgetInvestModuleParser produces 51 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = budgetInvestParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new BudgetInvestModuleParser(
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

    expect(ImportStagingIndicatorFact::where('indicator_code', 'budget_invest')->count())->toBe(51);

    $rollup = ImportStagingIndicatorFact::where('indicator_code', 'budget_invest')->whereNull('district_code');
    expect($rollup->count())->toBe(3);
    $districtRows = ImportStagingIndicatorFact::where('indicator_code', 'budget_invest')->whereNotNull('district_code');
    expect($districtRows->count())->toBe(48);

    // Region rollup q1
    $q1 = ImportStagingIndicatorFact::where('indicator_code', 'budget_invest')
        ->whereNull('district_code')->where('period', 'q1')->first();
    expect($q1)->not->toBeNull();
    expect($q1->plan_value)->toBeNumericallyClose(950279.86, 0.5);    // limit
    expect($q1->actual_hokimyat)->toBeNumericallyClose(177548.80, 0.5);
    expect($q1->pct_of_plan)->toBeNumericallyClose(18.68, 0.05);
    expect($q1->count_extra)->toBe(100);
    expect($q1->count_extra_2)->toBeNull();

    // Region rollup year (with commissioning_year_count)
    $year = ImportStagingIndicatorFact::where('indicator_code', 'budget_invest')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($year->plan_value)->toBeNumericallyClose(950279.86, 0.5);
    expect($year->actual_hokimyat)->toBeNumericallyClose(1024641.5, 0.5);
    expect($year->pct_of_plan)->toBeNumericallyClose(107.8, 0.05);
    expect($year->count_extra)->toBe(100);
    expect($year->count_extra_2)->toBe(96);

    // Andijan city (d01) q1: limit = 128147.1, q1_absorption = 6566.9, objects = 13
    $cityQ1 = ImportStagingIndicatorFact::where('indicator_code', 'budget_invest')
        ->where('district_code', 'd01')->where('period', 'q1')->first();
    expect($cityQ1)->not->toBeNull();
    expect($cityQ1->plan_value)->toBeNumericallyClose(128147.1, 0.5);
    expect($cityQ1->actual_hokimyat)->toBeNumericallyClose(6566.9, 0.5);
    expect($cityQ1->count_extra)->toBe(13);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/BudgetInvestModuleParserTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create `backend/app/Services/Import/Modules/BudgetInvestModuleParser.php`**

Use the column constants you verified in Step 0. Replace any `??` placeholders below with the actual column numbers from your inspection.

```php
<?php

namespace App\Services\Import\Modules;

use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BudgetInvestModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'budget_invest'; }

    private const COL_OBJECTS              = 3;   // C — verified Plan 1
    private const COL_LIMIT                = 4;   // D — verified Plan 1
    private const COL_Q1_ABSORPTION        = 5;   // E — verified Plan 1
    private const COL_Q1_PCT               = 6;   // F — verified Plan 1
    // cols G/H = q1 молиялаштириш — SKIP (DATA blob only carries absorption)
    private const COL_H1_ABSORPTION        = 9;   // I — verified Plan 1
    private const COL_H1_PCT               = 10;  // J — verified Plan 1
    // cols K/L = h1 молиялаштириш — SKIP

    // Verify these in Step 0 — replace with actual values from inspection
    private const COL_YEAR_ABSORPTION      = 13;  // M — VERIFY
    private const COL_YEAR_PCT             = 14;  // N — VERIFY
    private const COL_COMMISSIONING_COUNT  = 21;  // U — VERIFY (commissioning_year_count = 96 for Andijan rollup)

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'budget_invest', 'budget_invest');
        if ($sheet === null) return 0;

        $rollupRow = $this->findRollupRow($sheet);
        if ($rollupRow === null) return 0;

        $count = 0;
        $count += $this->emitEntityRows($ctx, $sheet, $rollupRow, null, $filePath);

        // Districts come after the ownership-breakdown rows. Walk a generous range.
        for ($row = $rollupRow + 1; $row <= $rollupRow + 40; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            $kind = $this->classifyRow($colA, $colB);
            if ($kind === 'rollup' || $kind === 'skip') continue;
            // kind === 'district'
            $districtCode = $this->districtResolver->resolve(
                trim((string) $colB), $ctx,
                basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            if ($districtCode === null) continue;
            $count += $this->emitEntityRows($ctx, $sheet, $row, $districtCode, $filePath);
        }
        return $count;
    }

    /** Returns the row of "Жами" (col B), or null. */
    private function findRollupRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= 15; $row++) {
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
            if (is_string($colB) && trim($colB) === 'Жами') return $row;
        }
        return null;
    }

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

    /** Emits 3 IndicatorFactDtos (q1, h1, year) for one row. Returns count. */
    private function emitEntityRows(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): int {
        $objects = $this->intOrNull($sheet->getCell([self::COL_OBJECTS, $row])->getCalculatedValue());
        $limit = $this->numericOrNull($sheet->getCell([self::COL_LIMIT, $row])->getCalculatedValue());

        $absorptions = [
            'q1'   => $this->numericOrNull($sheet->getCell([self::COL_Q1_ABSORPTION,   $row])->getCalculatedValue()),
            'h1'   => $this->numericOrNull($sheet->getCell([self::COL_H1_ABSORPTION,   $row])->getCalculatedValue()),
            'year' => $this->numericOrNull($sheet->getCell([self::COL_YEAR_ABSORPTION, $row])->getCalculatedValue()),
        ];
        $pcts = [
            'q1'   => $this->numericOrNull($sheet->getCell([self::COL_Q1_PCT,   $row])->getCalculatedValue()),
            'h1'   => $this->numericOrNull($sheet->getCell([self::COL_H1_PCT,   $row])->getCalculatedValue()),
            'year' => $this->numericOrNull($sheet->getCell([self::COL_YEAR_PCT, $row])->getCalculatedValue()),
        ];
        $commissioning = $this->intOrNull($sheet->getCell([self::COL_COMMISSIONING_COUNT, $row])->getCalculatedValue());

        $count = 0;
        foreach (['q1', 'h1', 'year'] as $period) {
            $dto = new IndicatorFactDto(
                regionCode:      $ctx->regionCode(),
                districtCode:    $districtCode,
                year:            $ctx->year,
                indicatorCode:   'budget_invest',
                period:          $period,
                planValue:       $limit,
                actualHokimyat:  $absorptions[$period],
                pctOfPlan:       $pcts[$period],
                countExtra:      $objects,
                countExtra2:     $period === 'year' ? $commissioning : null,
                unit:            'млн сўм',
                sourceLabel:     basename($filePath) . " · {$sheet->getTitle()} · row $row",
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

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (! is_numeric($value)) return null;
        return (int) $value;
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/BudgetInvestModuleParserTest.php`
Expected: 1 test passes (~13 assertions).

If it fails:
- **Wrong row count** ≠ 51: dump `$writer->bufferedCount(...)` and check. The `classifyRow` may not be skipping ownership rows correctly. Inspect rows between rollup and first district.
- **Spot-check assertion fails by 10x**: column index is wrong. Re-do Step 0 inspection. Most likely culprits: `COL_YEAR_ABSORPTION`, `COL_YEAR_PCT`, `COL_COMMISSIONING_COUNT`.
- **Andijan city (d01) not found**: `DistrictResolver` raised `unknown_district`. Check what district name string the budget_invest sheet uses. If different from existing alt_labels, add to `regions_districts.json`. (Plan 4 found `Бўстон тумани ` had a trailing space — `trim()` already in place.)

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/Modules/BudgetInvestModuleParser.php \
    backend/tests/Feature/Import/BudgetInvestModuleParserTest.php
```

If you updated seed JSON for new alt_labels:
```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/database/seeders/data/regions_districts.json
```

Commit:
```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add BudgetInvestModuleParser

Parses Andijan's '2.Анд' sheet (region-coded suffix) — 1 region
rollup ('Жами') + 16 districts, each emitting 3 IndicatorFactDtos
(q1, h1, year periods) for a total of 51 staging rows under
indicator_code='budget_invest'. Skips ownership-breakdown rows
(шу жумладан:, *буюртмачилигида:). count_extra carries 'objects';
count_extra_2 carries 'commissioning_year_count' on year rows
only.

Column constants verified against actual workbook before commit:
[Note any column-position adjustments from Step 0 inspection.]

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Register `BudgetInvestModuleParser` in `ImportRegionCommand`

**Files:**
- Modify: `backend/app/Console/Commands/ImportRegionCommand.php`
- Create: `backend/tests/Feature/Import/ImportRegionCommandBudgetInvestTest.php`

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/ImportRegionCommandBudgetInvestTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 budget_invest creates a successful run with 51 rows', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx'))) {
        $this->markTestSkipped('Andijan budget_invest data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'budget_invest',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    expect(ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'budget_invest')->count())->toBe(51);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ImportRegionCommandBudgetInvestTest.php`
Expected: FAIL with output containing "no parser implemented yet, skipping" and 0 budget_invest rows.

- [ ] **Step 3: Register the parser**

Edit `backend/app/Console/Commands/ImportRegionCommand.php`:

Add the import next to the existing parser imports:
```php
use App\Services\Import\Modules\BudgetInvestModuleParser;
```

Add the entry to the `$parsers` array. The block becomes:

```php
$parsers = [
    'macro'         => new MacroModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'inflation'     => new InflationModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'budget'        => new BudgetModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'budget_invest' => new BudgetInvestModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

- [ ] **Step 4: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ImportRegionCommandBudgetInvestTest.php`
Expected: 1 test passes.

- [ ] **Step 5: Run full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: 95 + 1 (Task 3) + 1 (this Task) = ~97 tests green.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Console/Commands/ImportRegionCommand.php \
    backend/tests/Feature/Import/ImportRegionCommandBudgetInvestTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): register BudgetInvestModuleParser

php artisan import:region andijan 2026 --module=budget_invest
produces 51 staging rows. Omitting --module runs all 4 modules
in one ImportRun (212 + 28 + 51 + 51 = 342 rows).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: `AndijanBudgetInvestParityTest`

**Files:**
- Create: `backend/tests/Feature/Import/AndijanBudgetInvestParityTest.php`

- [ ] **Step 1: Write the parity test**

Create `backend/tests/Feature/Import/AndijanBudgetInvestParityTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanBudgetInvestDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 'andijan')
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

test('Andijan budget_invest import reproduces DATA.regional.budget_investment and DATA.districts[*].data.budget_investment within tolerance', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/4.1-жадвал (бюджет инвестка).xlsx'))) {
        $this->markTestSkipped('Andijan budget_invest data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'budget_invest',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'budget_invest')->get();

    expect($rows)->toHaveCount(51);

    // ----- Region rollup: 3 periods -----
    $regional = $expected['regional']['budget_investment'];
    foreach (['q1', 'h1', 'year'] as $period) {
        $actual = $rows->first(fn ($r) =>
            $r->district_code === null && $r->period->value === $period
        );
        expect($actual)->not->toBeNull("missing rollup row for period $period");
        expect($actual->plan_value)->toBeNumericallyClose($regional['limit'], 0.5);
        if (isset($regional["{$period}_absorption"])) {
            expect($actual->actual_hokimyat)->toBeNumericallyClose($regional["{$period}_absorption"], 0.5);
        }
        if (isset($regional["{$period}_pct"])) {
            expect($actual->pct_of_plan)->toBeNumericallyClose($regional["{$period}_pct"], 0.05);
        }
        expect($actual->count_extra)->toBe($regional['objects']);
        if ($period === 'year' && isset($regional['commissioning_year_count'])) {
            expect($actual->count_extra_2)->toBe($regional['commissioning_year_count']);
        } else {
            expect($actual->count_extra_2)->toBeNull();
        }
    }

    // ----- District rows -----
    $matched = 0;
    $unmatched = [];
    foreach ($expected['districts'] as $expectedDistrict) {
        $bi = $expectedDistrict['data']['budget_investment'] ?? null;
        if ($bi === null) continue;

        $districtCode = andijanBudgetInvestDistrictCode($expectedDistrict['name']);
        if ($districtCode === null) {
            $unmatched[] = "lookup-failed:{$expectedDistrict['name']}";
            continue;
        }

        foreach (['q1', 'h1', 'year'] as $period) {
            $actual = $rows->first(fn ($r) =>
                $r->district_code === $districtCode && $r->period->value === $period
            );
            if ($actual === null) {
                $unmatched[] = "no-row:{$districtCode}/{$period}";
                continue;
            }
            $matched++;

            expect($actual->plan_value)->toBeNumericallyClose($bi['limit'], 0.5);
            if (isset($bi["{$period}_absorption"])) {
                expect($actual->actual_hokimyat)->toBeNumericallyClose($bi["{$period}_absorption"], 0.5);
            }
            if (isset($bi["{$period}_pct"])) {
                expect($actual->pct_of_plan)->toBeNumericallyClose($bi["{$period}_pct"], 0.05);
            }
            expect($actual->count_extra)->toBe($bi['objects']);
            if ($period === 'year' && isset($bi['commissioning_year_count'])) {
                expect($actual->count_extra_2)->toBe($bi['commissioning_year_count']);
            }
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(45);

    if (! empty($unmatched)) {
        echo "\nUnmatched budget_invest entries: " . implode(', ', $unmatched) . "\n";
    }
});
```

- [ ] **Step 2: Run, verify it passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/AndijanBudgetInvestParityTest.php`
Expected: 1 test passes.

If it fails:
- **Counts mismatch (≠ 51)**: dump `$rows->pluck('district_code')->unique()` and compare to DATA blob's district list.
- **Numeric assertions fail by ~10x**: column index error from Task 3. Re-verify column positions.
- **Tolerance issue**: limit and absorption values are large (millions); 0.5 tolerance accommodates 1dp rounding (smaller than e.g. 0.0005% of typical values). If a tighter tolerance is needed, lower it. If a value diverges by more than 0.5, that's a real bug.
- **`count_extra_2` is set on q1/h1 rows**: parser logic is wrong. Should be NULL on q1/h1, only set on year rows.

- [ ] **Step 3: Run the full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: ~97 + 1 = ~98 tests green.

- [ ] **Step 4: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/tests/Feature/Import/AndijanBudgetInvestParityTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "test(import): add Andijan budget_invest parity test

51 staging rows asserted against DATA.regional.budget_investment
and DATA.districts[*].data.budget_investment. Plan 5 complete:
macro + inflation + budget + budget_invest all end-to-end with
parity validation.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Self-review

Verified against `docs/superpowers/specs/2026-05-06-budget-invest-module-design.md`:

- **Spec §3 architecture deltas:** Task 1 (IndicatorSeeder periods), Task 2 (SheetResolver signature), Task 3 (BudgetInvestModuleParser), Task 4 (command registration), Task 5 (parity). ✓
- **Spec §4 parser:** Task 3 includes column constants (with explicit Step 0 inspection for cols M+), `classifyRow` handling rollup/skip/district, per-period emission table with `count_extra_2` only on year. ✓
- **Spec §5 SheetResolver signature:** Task 2 with explicit Step 3 RichText-vs-plain-string inspection. ✓
- **Spec §6 CLI:** Task 4. ✓
- **Spec §7 parity test:** Task 5 with the documented period/facet structure. ✓
- **Spec §8 out-of-scope:** commissioning_year_value discarded, financing cols ignored, ownership rows skipped silently — all reflected in Task 3's parser code. ✓

**Placeholder scan:** Two `// VERIFY` markers in Task 3 column constants are intentional — Step 0 inspection resolves them. Not actual placeholders.

**Type consistency:** `IndicatorFactDto` field names (existing), parser column constants (Task 3), `andijanBudgetInvestDistrictCode` helper (Task 5) consistent across tasks.

---

## Out of scope (deferred)

- **Plan 6:** `foreign_invest` module.
- **Plan 7:** `export` module.
- **Plan 8:** `employment` module (first sentinel exposure).
- **Plan 9:** roll out to 12 non-Navoi regions.
- **Plan 10:** Filament admin UI.
- **Plan 11:** cross-region contamination detector (Navoi unblock).
- **commissioning_year_value:** workbook column exists, but no cube schema column. Future plan can add.
- **Ownership-breakdown rows** (`Вилоят ҳокимлиги буюртмачилигида:`, `Шаҳар-туман ҳокимликлари буюртмачилигида:`): currently skipped silently. Future plan can capture in a sibling table.
- **молиялаштириш (financing) columns:** workbook tracks both absorption and financing. DATA blob mirrors only absorption; importer matches.
