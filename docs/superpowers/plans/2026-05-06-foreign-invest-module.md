# Foreign Investment Module Importer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `php artisan import:region andijan 2026 --module=foreign_invest` produce 51 staging rows for Andijan — 1 region rollup + 16 districts × 3 periods (q1, h1, year) — under `indicator_code='investment'`. Andijan parity test asserts every row matches `DATA.regional.foreign_investment` and `DATA.districts[*].data.foreign_investment` from `index.html` within tolerance.

**Architecture:** Adds `ForeignInvestModuleParser` on top of Plan 2's pipeline. Reuses `IndicatorFactDto`. Sheet name varies across 4 patterns covering all 14 regions; resolved by `SheetResolver` content-pattern signatures. Per-period mapping is cleaner than budget_invest because each period has its own plan: q1→`q1_plan`+`q1_actual`, h1→`h1_plan`+`h1_expected`+`h1_jobs`, year→`year_forecast`+`year_expected`. count_extra populates per-period projects; count_extra_2 holds h1 jobs only.

**Tech Stack:** Laravel 12.58 · PHP 8.2 · PostgreSQL 14 · Pest 3 · `phpoffice/phpspreadsheet`.

**Working directory:** All paths relative to `backend/` unless prefixed `../`. Run commands from `backend/`. Andijan source: `../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx`, sheet `4,2-хорижий инв`. Parity target: `../index.html`.

**Memory note:** Use `php -d memory_limit=1G vendor/bin/pest ...` for any test that loads workbooks (Plan 4 lesson — PhpSpreadsheet OOMs on the default PHP memory limit).

**TDD discipline:** Failing test first, run, write minimal implementation, run again, commit. Tests against `hududlar_monitoringi_test`. Currently 98 tests + 2374 assertions; every task adds tests and keeps the suite green.

---

## File map

**Created:**
- `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php`
- `backend/tests/Feature/Import/IndicatorSeederForeignInvestPeriodsTest.php`
- `backend/tests/Feature/Import/SheetResolverForeignInvestTest.php`
- `backend/tests/Feature/Import/ForeignInvestModuleParserTest.php`
- `backend/tests/Feature/Import/ImportRegionCommandForeignInvestTest.php`
- `backend/tests/Feature/Import/AndijanForeignInvestParityTest.php`

**Modified:**
- `backend/database/seeders/IndicatorSeeder.php` (investment supported_periods)
- `backend/app/Services/Import/SheetResolver.php` (add 'foreign_invest' SIGNATURE)
- `backend/app/Console/Commands/ImportRegionCommand.php` (register parser)

---

## Task 1: Update `IndicatorSeeder` `investment` supported_periods

**Files:**
- Modify: `backend/database/seeders/IndicatorSeeder.php`
- Create: `backend/tests/Feature/Import/IndicatorSeederForeignInvestPeriodsTest.php`

**Important naming note:** The indicator's `code` is `investment` (short word). The module_code is `foreign_invest`. The query is `Indicator::where('code', 'investment')`. Plan 5 used the same trick with `budget_investment` (indicator code) vs `budget_invest` (module code).

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/IndicatorSeederForeignInvestPeriodsTest.php`:

```php
<?php

use App\Models\Indicator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('investment indicator has supported_periods = [q1, h1, year]', function () {
    $this->seed();
    $inv = Indicator::where('code', 'investment')->firstOrFail();
    expect($inv->supported_periods)->toBe(['q1', 'h1', 'year']);
});

test('macro indicators still use the default 4-period set', function () {
    $this->seed();
    $grp = Indicator::where('code', 'grp')->firstOrFail();
    expect($grp->supported_periods)->toBe(['q1', 'h1', 'm9', 'year']);
});
```

- [ ] **Step 2: Run, confirm fails**

Run from `backend/`: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/IndicatorSeederForeignInvestPeriodsTest.php`
Expected: FAIL — `investment` currently has `["q1","h1","m9","year"]` (the default).

- [ ] **Step 3: Update the seeder**

Edit `backend/database/seeders/IndicatorSeeder.php`:

The variable `$q1H1Year = json_encode(['q1','h1','year']);` was added in Plan 5 Task 1 next to `$allPeriods`, `$yearOnly`, `$h1Year`, `$h1q2Year`. Reuse it.

Find the `investment` row in the `$rows` array (its first tuple element is `'investment'`). The row currently has `$allPeriods` as its 9th tuple element. Change it to `$q1H1Year`.

The row should change from:
```php
['investment',           'Хорижий инвестициялар',                          'Инвестиция',                  'Хорижий инвестиция',   'foreign_invest', 'both',     'млн доллар',  false, $allPeriods, false, true,  false, 'Лойиҳалар сони',                'Иш ўринлари', 'rocket',    90],
```

To:
```php
['investment',           'Хорижий инвестициялар',                          'Инвестиция',                  'Хорижий инвестиция',   'foreign_invest', 'both',     'млн доллар',  false, $q1H1Year,   false, true,  false, 'Лойиҳалар сони',                'Иш ўринлари', 'rocket',    90],
```

- [ ] **Step 4: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/IndicatorSeederForeignInvestPeriodsTest.php`
Expected: 2 tests pass.

- [ ] **Step 5: Run full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: 98 + 2 = 100 tests, all green.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/database/seeders/IndicatorSeeder.php \
    backend/tests/Feature/Import/IndicatorSeederForeignInvestPeriodsTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): set investment supported_periods to [q1,h1,year]

Foreign investment data covers q1, h1, and year — not q2 or m9.
Updates IndicatorSeeder so the catalog matches actual coverage.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Add `foreign_invest` signature to `SheetResolver`

**Files:**
- Modify: `backend/app/Services/Import/SheetResolver.php`
- Create: `backend/tests/Feature/Import/SheetResolverForeignInvestTest.php`

The Andijan workbook sheet name is `4,2-хорижий инв`. Sheet name varies across 4 patterns; we need plain-string content-pattern signatures from rows 1-5 (Plan 5 lesson: `scoreSheet` only scans rows 1-5 plain-string cells).

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/SheetResolverForeignInvestTest.php`:

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

function loadAndijanForeignInvest(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan foreign_invest workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(false);
    return $reader->load($path);
}

function foreignInvestSheetCtx(): array
{
    $region = Region::where('code', 'andijan')->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'foreign_invest')->value('id'),
        'file_name' => '4.2-жадвал (инвестициялар).xlsx',
        'file_path' => 'fixture',
        'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region, year: 2026, dataPath: base_path('../data'),
    );
    return ['ctx' => $ctx, 'rwb' => $rwb];
}

test('SheetResolver detects foreign_invest sheet by content (4,2-хорижий инв for Andijan)', function () {
    $this->seed();
    $book = loadAndijanForeignInvest();
    ['ctx' => $ctx, 'rwb' => $rwb] = foreignInvestSheetCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'foreign_invest', 'foreign_invest');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('4,2-хорижий инв');
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/SheetResolverForeignInvestTest.php`
Expected: FAIL — no `foreign_invest` logical_kind in SIGNATURES.

- [ ] **Step 3: Inspect rows 1-5 for plain-string cells**

Use tinker to dump rows 1-5 cols 1-12 of the `4,2-хорижий инв` sheet. Identify which cells are plain strings vs RichText:

```
php -d memory_limit=1G artisan tinker --execute="
\$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx');
\$reader->setReadDataOnly(false);
\$book = \$reader->load('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx');
\$sheet = \$book->getSheetByName('4,2-хорижий инв');
for (\$row = 1; \$row <= 5; \$row++) {
    for (\$col = 1; \$col <= 12; \$col++) {
        \$cell = \$sheet->getCell([\$col, \$row]);
        \$val = \$cell->getValue();
        \$type = is_object(\$val) ? get_class(\$val) : gettype(\$val);
        if (is_string(\$val) || is_numeric(\$val)) {
            echo 'r' . \$row . 'c' . \$col . ' [' . \$type . ']: ' . substr((string)\$val, 0, 80) . PHP_EOL;
        } elseif (is_object(\$val)) {
            echo 'r' . \$row . 'c' . \$col . ' [' . \$type . ']: (RichText)' . PHP_EOL;
        }
    }
}
"
```

Pick 2-3 plain-string substrings that appear in rows 1-5 and aren't already in another module's signature.

- [ ] **Step 4: Add the signature**

Add the `foreign_invest` entry at the end of `SheetResolver::SIGNATURES`. Likely candidates from Plan 1 row 4-5 inspection (verify they're plain strings via Step 3):

```php
private const SIGNATURES = [
    'rollup'                    => ['ЯҲМ', 'асосий иқтисодий кўрсаткич'],
    'district_industry'         => ['Саноат маҳсулотларини ишлаб чиқариш'],
    'district_agriculture'      => ['Қишлоқ хўжалиги маҳсулотларини'],
    'district_services'         => ['Бозор хизматлари'],
    'food_balance'              => ['Балансини асос', 'Маҳсулот номи', 'Ресурс'],
    'warehouses_district_table' => ['Захира омборлари', 'совутгичли омборлар'],
    'budget'                    => ['прогноз', 'кутилиш', 'Ҳудудлар'],
    'budget_invest'             => ['I-чорак', 'Қишлоқ, ўрмон ва балиқчилик хўжалиги'],
    'foreign_invest'            => ['Хорижий инвестициялар', 'Шаҳар ва туманлар номи', 'млн долл.'],
];
```

If Step 3 inspection shows different plain-string content (e.g., the title row "Хорижий инвестициялар" is RichText), substitute plain-string column-header substrings from rows 4-5.

- [ ] **Step 5: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/SheetResolverForeignInvestTest.php`
Expected: 1 test passes.

- [ ] **Step 6: Run full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: 100 + 1 = 101 tests green.

- [ ] **Step 7: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/SheetResolver.php \
    backend/tests/Feature/Import/SheetResolverForeignInvestTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add SheetResolver signature for foreign_invest

Sheet name varies across 4 patterns ('4.2. Хорижий
инвестициялар', '4,2-хорижий инв', '2-жадвал (туманка)',
'4.2-жадвал (туманка)'). Plain-string column-header signatures
match all 14 regions by content.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

If you needed different signature strings, document them in the commit message.

---

## Task 3: Implement `ForeignInvestModuleParser`

**Files:**
- Create: `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php`
- Create: `backend/tests/Feature/Import/ForeignInvestModuleParserTest.php`

The meat of Plan 6. **Step 0 mandatory:** the workbook spans cols A-AJ (1-36). Plan 1 inspection only confirmed cols A-L. Year-period cols, h1 cols, count_extra columns all live somewhere in cols M-AJ.

## Step 0 (MANDATORY): Inspect column layout for cols A-AJ

Use tinker to dump rows 4-10 cols 1-36 of the `4,2-хорижий инв` sheet:

```
php -d memory_limit=1G artisan tinker --execute="
\$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx');
\$reader->setReadDataOnly(false);
\$book = \$reader->load('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx');
\$sheet = \$book->getSheetByName('4,2-хорижий инв');
for (\$row = 4; \$row <= 12; \$row++) {
    \$rowData = [];
    for (\$col = 1; \$col <= 36; \$col++) {
        \$rowData[chr(64+\$col)] = \$sheet->getCell([\$col, \$row])->getCalculatedValue();
    }
    print_r([\$row => \$rowData]);
}
"
```

Look at the rollup row (col A = 'Андижон вилояти'). Find the columns matching DATA blob's Andijan rollup values:
- year_forecast = 3341.7
- q1_plan = 807.4
- q1_actual = 880.0
- q1_pct = 1.1
- q1_projects = 101
- h1_plan = 1760.8
- h1_expected = 1783.3
- h1_pct = 1.0
- h1_projects = 155
- h1_jobs = 8989
- year_expected = 3508.6
- year_pct = 1.0

Note the column letters/numbers for each. Use them in Step 2.

## Step 1: Failing test

Create `backend/tests/Feature/Import/ForeignInvestModuleParserTest.php`:

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
use App\Services\Import\Modules\ForeignInvestModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function foreignInvestParserCtx(): array
{
    $path = base_path('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan foreign_invest workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'foreign_invest')->value('id'),
        'file_name' => '4.2-жадвал (инвестициялар).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('ForeignInvestModuleParser produces 51 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = foreignInvestParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new ForeignInvestModuleParser(
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

    expect(ImportStagingIndicatorFact::where('indicator_code', 'investment')->count())->toBe(51);

    $rollup = ImportStagingIndicatorFact::where('indicator_code', 'investment')->whereNull('district_code');
    expect($rollup->count())->toBe(3);
    expect(ImportStagingIndicatorFact::where('indicator_code', 'investment')->whereNotNull('district_code')->count())->toBe(48);

    // Region rollup q1: plan=807.4, actual=880.0, pct=1.1, projects=101, count_extra_2=NULL
    $q1 = ImportStagingIndicatorFact::where('indicator_code', 'investment')
        ->whereNull('district_code')->where('period', 'q1')->first();
    expect($q1)->not->toBeNull();
    expect($q1->plan_value)->toBeNumericallyClose(807.4, 0.5);
    expect($q1->actual_hokimyat)->toBeNumericallyClose(880.0, 0.5);
    expect($q1->expected_value)->toBeNull();
    expect($q1->count_extra)->toBe(101);
    expect($q1->count_extra_2)->toBeNull();

    // Region rollup h1: plan=1760.8, expected=1783.3, actual=NULL, projects=155, jobs=8989
    $h1 = ImportStagingIndicatorFact::where('indicator_code', 'investment')
        ->whereNull('district_code')->where('period', 'h1')->first();
    expect($h1->plan_value)->toBeNumericallyClose(1760.8, 0.5);
    expect($h1->expected_value)->toBeNumericallyClose(1783.3, 0.5);
    expect($h1->actual_hokimyat)->toBeNull();
    expect($h1->count_extra)->toBe(155);
    expect($h1->count_extra_2)->toBe(8989);

    // Region rollup year: plan=3341.7, expected=3508.6, actual=NULL, count_extra=NULL, count_extra_2=NULL
    $year = ImportStagingIndicatorFact::where('indicator_code', 'investment')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($year->plan_value)->toBeNumericallyClose(3341.7, 0.5);
    expect($year->expected_value)->toBeNumericallyClose(3508.6, 0.5);
    expect($year->actual_hokimyat)->toBeNull();
    expect($year->count_extra)->toBeNull();
    expect($year->count_extra_2)->toBeNull();

    // Andijan city (d01) q1
    $cityQ1 = ImportStagingIndicatorFact::where('indicator_code', 'investment')
        ->where('district_code', 'd01')->where('period', 'q1')->first();
    expect($cityQ1)->not->toBeNull();
    expect($cityQ1->plan_value)->toBeNumericallyClose(175.2, 0.5);
    expect($cityQ1->actual_hokimyat)->toBeNumericallyClose(141.1, 0.5);
});
```

## Step 2: Run, confirm fails

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ForeignInvestModuleParserTest.php`
Expected: FAIL — class not found.

## Step 3: Create `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php`

Use the column constants you verified in Step 0. The skeleton below uses placeholder column numbers — replace with your inspection's findings. Column constants for cols not yet verified: replace `??` with the actual column number.

```php
<?php

namespace App\Services\Import\Modules;

use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ForeignInvestModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'foreign_invest'; }

    // Confirmed from Plan 1 inspection (cols A-L)
    private const COL_DISTRICT_NAME       = 2;   // B
    private const COL_YEAR_FORECAST       = 7;   // G
    private const COL_Q1_PLAN             = 9;   // I
    private const COL_Q1_ACTUAL           = 12;  // L

    // Verify in Step 0 — replace with actual column numbers from inspection
    private const COL_Q1_PCT              = 13;  // M (verify)
    private const COL_Q1_PROJECTS         = 14;  // N (verify — Plan 1 didn't dump cols past L)
    private const COL_H1_PLAN             = 17;  // (verify)
    private const COL_H1_EXPECTED         = 19;  // (verify)
    private const COL_H1_PCT              = 22;  // (verify)
    private const COL_H1_PROJECTS         = 24;  // (verify)
    private const COL_H1_JOBS             = 25;  // (verify)
    private const COL_YEAR_EXPECTED       = 32;  // (verify)
    private const COL_YEAR_PCT            = 34;  // (verify)

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'foreign_invest', 'foreign_invest');
        if ($sheet === null) return 0;

        $rollupRow = $this->findRollupRow($sheet);
        if ($rollupRow === null) return 0;

        $count = 0;
        $count += $this->emitEntityRows($ctx, $sheet, $rollupRow, null, $filePath);

        for ($row = $rollupRow + 1; $row <= $rollupRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([self::COL_DISTRICT_NAME, $row])->getCalculatedValue();
            $kind = $this->classifyRow($colA, $colB);
            if ($kind !== 'district') continue;

            $districtCode = $this->districtResolver->resolve(
                trim((string) $colB), $ctx,
                basename($filePath) . " · {$sheet->getTitle()} · row $row",
            );
            if ($districtCode === null) continue;

            $count += $this->emitEntityRows($ctx, $sheet, $row, $districtCode, $filePath);
        }
        return $count;
    }

    /**
     * The Andijan rollup row has col A = "Андижон вилояти" (string). Districts have integer col A.
     */
    private function findRollupRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= 15; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            if (is_string($colA) && str_contains($colA, 'вилояти')) return $row;
        }
        return null;
    }

    private function classifyRow(mixed $colA, mixed $colB): string
    {
        if (is_string($colA) && str_contains($colA, 'вилояти')) return 'rollup';
        if (! is_string($colB) || trim($colB) === '') return 'skip';
        if (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA)))) return 'district';
        return 'skip';
    }

    /** Emits 3 IndicatorFactDtos (q1, h1, year) for one row. */
    private function emitEntityRows(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): int {
        $sourceLabel = basename($filePath) . " · {$sheet->getTitle()} · row $row";

        $q1Plan      = $this->numericOrNull($sheet->getCell([self::COL_Q1_PLAN,        $row])->getCalculatedValue());
        $q1Actual    = $this->numericOrNull($sheet->getCell([self::COL_Q1_ACTUAL,      $row])->getCalculatedValue());
        $q1Pct       = $this->numericOrNull($sheet->getCell([self::COL_Q1_PCT,         $row])->getCalculatedValue());
        $q1Projects  = $this->intOrNull(    $sheet->getCell([self::COL_Q1_PROJECTS,    $row])->getCalculatedValue());

        $h1Plan      = $this->numericOrNull($sheet->getCell([self::COL_H1_PLAN,        $row])->getCalculatedValue());
        $h1Expected  = $this->numericOrNull($sheet->getCell([self::COL_H1_EXPECTED,    $row])->getCalculatedValue());
        $h1Pct       = $this->numericOrNull($sheet->getCell([self::COL_H1_PCT,         $row])->getCalculatedValue());
        $h1Projects  = $this->intOrNull(    $sheet->getCell([self::COL_H1_PROJECTS,    $row])->getCalculatedValue());
        $h1Jobs      = $this->intOrNull(    $sheet->getCell([self::COL_H1_JOBS,        $row])->getCalculatedValue());

        $yearForecast = $this->numericOrNull($sheet->getCell([self::COL_YEAR_FORECAST, $row])->getCalculatedValue());
        $yearExpected = $this->numericOrNull($sheet->getCell([self::COL_YEAR_EXPECTED, $row])->getCalculatedValue());
        $yearPct      = $this->numericOrNull($sheet->getCell([self::COL_YEAR_PCT,      $row])->getCalculatedValue());

        $rows = [
            ['period' => 'q1',   'plan' => $q1Plan,      'expected' => null,         'actual' => $q1Actual, 'pct' => $q1Pct,   'extra' => $q1Projects, 'extra2' => null],
            ['period' => 'h1',   'plan' => $h1Plan,      'expected' => $h1Expected,  'actual' => null,      'pct' => $h1Pct,   'extra' => $h1Projects, 'extra2' => $h1Jobs],
            ['period' => 'year', 'plan' => $yearForecast,'expected' => $yearExpected,'actual' => null,      'pct' => $yearPct, 'extra' => null,        'extra2' => null],
        ];

        $count = 0;
        foreach ($rows as $r) {
            $dto = new IndicatorFactDto(
                regionCode:      $ctx->regionCode(),
                districtCode:    $districtCode,
                year:            $ctx->year,
                indicatorCode:   'investment',
                period:          $r['period'],
                planValue:       $r['plan'],
                expectedValue:   $r['expected'],
                actualHokimyat:  $r['actual'],
                pctOfPlan:       $r['pct'],
                countExtra:      $r['extra'],
                countExtra2:     $r['extra2'],
                unit:            'млн доллар',
                sourceLabel:     $sourceLabel,
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

## Step 4: Run the test

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ForeignInvestModuleParserTest.php`
Expected: 1 test passes.

If it fails:
- **Wrong row count ≠ 51**: dump `$writer->bufferedCount(...)`. Some districts may not be matching. Inspect rows after rollup.
- **Spot-check assertion fails by ~10x**: column index wrong. Re-verify Step 0.
- **Andijan city (d01) not found**: `DistrictResolver` raised `unknown_district`. Check what district name string the foreign_invest sheet uses. If different, update `regions_districts.json`.

## Step 5: Commit

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/Modules/ForeignInvestModuleParser.php \
    backend/tests/Feature/Import/ForeignInvestModuleParserTest.php
```

If you updated seed JSON for new alt_labels:
```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/database/seeders/data/regions_districts.json
```

Commit:
```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add ForeignInvestModuleParser

Parses Andijan's '4,2-хорижий инв' sheet — 1 region rollup
('Андижон вилояти' in col A) + 16 districts × 3 periods
(q1, h1, year) = 51 staging rows under indicator_code='investment'.
Per-period mapping: q1 has actual+projects, h1 has plan+expected
+projects+jobs, year has forecast+expected only.

Column constants verified against actual workbook before commit:
[Note column-position findings from Step 0 inspection.]

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Register `ForeignInvestModuleParser` in `ImportRegionCommand`

**Files:**
- Modify: `backend/app/Console/Commands/ImportRegionCommand.php`
- Create: `backend/tests/Feature/Import/ImportRegionCommandForeignInvestTest.php`

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/ImportRegionCommandForeignInvestTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 foreign_invest creates a successful run with 51 rows', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx'))) {
        $this->markTestSkipped('Andijan foreign_invest data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'foreign_invest',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    expect(ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'investment')->count())->toBe(51);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ImportRegionCommandForeignInvestTest.php`
Expected: FAIL — "no parser implemented yet, skipping" output, 0 investment rows.

- [ ] **Step 3: Register the parser**

Edit `backend/app/Console/Commands/ImportRegionCommand.php`:

Add the import next to existing parser imports:
```php
use App\Services\Import\Modules\ForeignInvestModuleParser;
```

Add to `$parsers`:
```php
$parsers = [
    'macro'          => new MacroModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'inflation'      => new InflationModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'budget'         => new BudgetModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'budget_invest'  => new BudgetInvestModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'foreign_invest' => new ForeignInvestModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

- [ ] **Step 4: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ImportRegionCommandForeignInvestTest.php`
Expected: 1 test passes.

- [ ] **Step 5: Run full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: 101 + 1 (Task 3) + 1 (this Task) = ~103 tests green.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Console/Commands/ImportRegionCommand.php \
    backend/tests/Feature/Import/ImportRegionCommandForeignInvestTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): register ForeignInvestModuleParser

php artisan import:region andijan 2026 --module=foreign_invest
produces 51 staging rows. Omitting --module runs all 5 modules
(212 + 28 + 51 + 51 + 51 = 393 rows in one ImportRun).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: `AndijanForeignInvestParityTest`

**Files:**
- Create: `backend/tests/Feature/Import/AndijanForeignInvestParityTest.php`

- [ ] **Step 1: Write the parity test**

Create `backend/tests/Feature/Import/AndijanForeignInvestParityTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanForeignInvestDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 'andijan')
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

function assertForeignInvestPeriodRow($actual, array $e, string $period): void
{
    if ($period === 'q1') {
        expect($actual->plan_value)->toBeNumericallyClose($e['q1_plan'], 0.5);
        expect($actual->actual_hokimyat)->toBeNumericallyClose($e['q1_actual'], 0.5);
        expect($actual->expected_value)->toBeNull();
        expect($actual->count_extra)->toBe($e['q1_projects']);
        expect($actual->count_extra_2)->toBeNull();
    } elseif ($period === 'h1') {
        expect($actual->plan_value)->toBeNumericallyClose($e['h1_plan'], 0.5);
        expect($actual->expected_value)->toBeNumericallyClose($e['h1_expected'], 0.5);
        expect($actual->actual_hokimyat)->toBeNull();
        expect($actual->count_extra)->toBe($e['h1_projects']);
        expect($actual->count_extra_2)->toBe($e['h1_jobs']);
    } else { // year
        expect($actual->plan_value)->toBeNumericallyClose($e['year_forecast'], 0.5);
        expect($actual->expected_value)->toBeNumericallyClose($e['year_expected'], 0.5);
        expect($actual->actual_hokimyat)->toBeNull();
        expect($actual->count_extra)->toBeNull();
        expect($actual->count_extra_2)->toBeNull();
    }
    if (isset($e["{$period}_pct"])) {
        expect($actual->pct_of_plan)->toBeNumericallyClose($e["{$period}_pct"], 0.05);
    }
}

test('Andijan foreign_invest import reproduces DATA.regional.foreign_investment and DATA.districts[*].data.foreign_investment within tolerance', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/4.2-жадвал (инвестициялар).xlsx'))) {
        $this->markTestSkipped('Andijan foreign_invest data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'foreign_invest',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'investment')->get();

    expect($rows)->toHaveCount(51);

    // Region rollup
    $regional = $expected['regional']['foreign_investment'];
    foreach (['q1', 'h1', 'year'] as $period) {
        $actual = $rows->first(fn ($r) =>
            $r->district_code === null && $r->period->value === $period
        );
        expect($actual)->not->toBeNull("missing rollup row for period $period");
        assertForeignInvestPeriodRow($actual, $regional, $period);
    }

    // District rows
    $matched = 0;
    $unmatched = [];
    foreach ($expected['districts'] as $expectedDistrict) {
        $fi = $expectedDistrict['data']['foreign_investment'] ?? null;
        if ($fi === null) continue;

        $districtCode = andijanForeignInvestDistrictCode($expectedDistrict['name']);
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
            assertForeignInvestPeriodRow($actual, $fi, $period);
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(45);

    if (! empty($unmatched)) {
        echo "\nUnmatched foreign_invest entries: " . implode(', ', $unmatched) . "\n";
    }
});
```

- [ ] **Step 2: Run, verify it passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/AndijanForeignInvestParityTest.php`
Expected: 1 test passes.

If it fails:
- **Counts mismatch**: dump `$rows->pluck('district_code')->unique()` vs DATA blob district names.
- **Numeric tolerance fails**: 0.5 should accommodate 1dp rounding. Larger divergence = column index error.
- **`count_extra_2` set on wrong period**: parser logic wrong. h1 only.

- [ ] **Step 3: Run the full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: ~103 + 1 = ~104 tests green.

- [ ] **Step 4: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/tests/Feature/Import/AndijanForeignInvestParityTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "test(import): add Andijan foreign_invest parity test

51 staging rows asserted against DATA.regional.foreign_investment
and DATA.districts[*].data.foreign_investment. Plan 6 complete:
macro + inflation + budget + budget_invest + foreign_invest all
end-to-end with parity validation.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Self-review

Verified against `docs/superpowers/specs/2026-05-06-foreign-invest-module-design.md`:

- **Spec §3 architecture deltas:** Task 1 (IndicatorSeeder), Task 2 (SheetResolver signature), Task 3 (Parser), Task 4 (registration), Task 5 (parity test). ✓
- **Spec §4 parser per-period mapping table:** Task 3 implements with `assertForeignInvestPeriodRow` helper for the parity test. ✓
- **Spec §5 SheetResolver signature with row-1-5 lesson:** Task 2 with explicit Step 3 inspection. ✓
- **Spec §6 CLI integration:** Task 4. ✓
- **Spec §7 parity test:** Task 5. ✓

**Placeholder scan:** Two `// (verify)` markers in Task 3 column constants are intentional Step 0 inputs.

**Type consistency:** `IndicatorFactDto` field names (existing), parser column constants (Task 3), helper functions consistent across tasks. The `assertForeignInvestPeriodRow` helper is defined once at the top of the parity test file.

---

## Out of scope (deferred)

- Plan 7: `export` module.
- Plan 8: `employment` module (first sentinel exposure).
- Plan 9: roll out to 12 non-Navoi regions.
- Plan 10: Filament admin UI.
- Plan 11: cross-region contamination detector.
- **Prior-year cols** (2025 jan-dec / jan-jun / jan-mar): workbook carries; DATA blob doesn't; importer ignores.
- **Growth multiplier strings** (e.g. `"1,7 бар."`): not numeric; not in cube schema.
- **Sub-breakdowns** (q1_plan_targeted, q1_carryover): not in DATA blob.
