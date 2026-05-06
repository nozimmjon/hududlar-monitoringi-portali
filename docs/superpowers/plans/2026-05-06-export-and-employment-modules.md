# Export + Employment Modules Importer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `php artisan import:region andijan 2026 --module=export` produce 51 staging rows and `--module=employment` produce 204 staging rows (with sentinel handling for poverty `холи ҳудуд` values). After this plan, all 7 source modules ship: full-suite import = 212 + 28 + 51 + 51 + 51 + 51 + 204 = **648 staging rows in one ImportRun**.

**Architecture:** Adds two `ModuleParser` subclasses on top of Plan 2's pipeline. Reuses `IndicatorFactDto`. Combined into one plan because both modules touch the same three infrastructure files (SheetResolver, IndicatorSeeder, ImportRegionCommand). First sentinel-handling implementation: parser detects `'холи ҳудуд'` string in poverty cells, emits `is_sentinel=true` DTOs, raises `IssueKind::Sentinel` issues for review.

**Tech Stack:** Laravel 12.58 · PHP 8.2 · PostgreSQL 14 · Pest 3 · `phpoffice/phpspreadsheet`.

**Working directory:** All paths relative to `backend/` unless prefixed `../`. Run commands from `backend/`. Andijan sources: `../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx` (sheet `5-жадвал`) and `../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx` (sheet `6. Камбағаллик`). Parity target: `../index.html`.

**Memory note:** Use `php -d memory_limit=1G vendor/bin/pest ...` for any test that loads workbooks (Plan 4 lesson).

**TDD discipline:** Failing test first, run, write minimal implementation, run again, commit. Tests against `hududlar_monitoringi_test`. Currently 104 tests + 2874 assertions; every task adds tests and keeps the suite green.

---

## File map

**Created:**
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

**Modified:**
- `backend/database/seeders/IndicatorSeeder.php` (export supported_periods only)
- `backend/app/Services/Import/SheetResolver.php` (add 2 SIGNATURES)
- `backend/app/Console/Commands/ImportRegionCommand.php` (register 2 parsers)

---

# PART A: EXPORT MODULE (Tasks 1-5)

## Task 1: Update `IndicatorSeeder` `export` supported_periods

**Files:**
- Modify: `backend/database/seeders/IndicatorSeeder.php`
- Create: `backend/tests/Feature/Import/IndicatorSeederExportPeriodsTest.php`

The variable `$q1H1Year = json_encode(['q1','h1','year']);` was added in Plan 5. Reuse it.

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/IndicatorSeederExportPeriodsTest.php`:

```php
<?php

use App\Models\Indicator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('export indicator has supported_periods = [q1, h1, year]', function () {
    $this->seed();
    $exp = Indicator::where('code', 'export')->firstOrFail();
    expect($exp->supported_periods)->toBe(['q1', 'h1', 'year']);
});

test('macro indicators still use the default 4-period set', function () {
    $this->seed();
    $grp = Indicator::where('code', 'grp')->firstOrFail();
    expect($grp->supported_periods)->toBe(['q1', 'h1', 'm9', 'year']);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/IndicatorSeederExportPeriodsTest.php`
Expected: FAIL — `export` currently has `["q1","h1","m9","year"]`.

- [ ] **Step 3: Update the seeder**

In `backend/database/seeders/IndicatorSeeder.php`, find the `export` row in the `$rows` array (its first tuple element is `'export'`). Change the 9th element from `$allPeriods` to `$q1H1Year`. Row should change from:

```php
['export',               'Экспорт ҳажми',                                  'Экспорт',                     'Экспорт',              'export',         'both',     'минг доллар', false, $allPeriods, true,  false, false, 'Экспортчи корхоналар сони',     null,           'globe',    100],
```

To:

```php
['export',               'Экспорт ҳажми',                                  'Экспорт',                     'Экспорт',              'export',         'both',     'минг доллар', false, $q1H1Year,   true,  false, false, 'Экспортчи корхоналар сони',     null,           'globe',    100],
```

- [ ] **Step 4: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/IndicatorSeederExportPeriodsTest.php`
Expected: 2 tests pass.

- [ ] **Step 5: Run full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: 104 + 2 = 106 tests green.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/database/seeders/IndicatorSeeder.php \
    backend/tests/Feature/Import/IndicatorSeederExportPeriodsTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): set export supported_periods to [q1,h1,year]

Export data covers q1, h1, and year — not m9.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Add `export` signature to `SheetResolver`

**Files:**
- Modify: `backend/app/Services/Import/SheetResolver.php`
- Create: `backend/tests/Feature/Import/SheetResolverExportTest.php`

**Lessons from Plan 4-6:** `scoreSheet` only scans rows 1-5 plain-string cells; RichText silently skipped. Pick signatures from confirmed plain-string cells.

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/SheetResolverExportTest.php`:

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

function loadAndijanExport(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan export workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(false);
    return $reader->load($path);
}

function exportSheetCtx(): array
{
    $region = Region::where('code', 'andijan')->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'export')->value('id'),
        'file_name' => '5.1-5.2-жадваллар (экспорт).xlsx',
        'file_path' => 'fixture',
        'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region, year: 2026, dataPath: base_path('../data'),
    );
    return ['ctx' => $ctx, 'rwb' => $rwb];
}

test('SheetResolver detects export sheet by content (5-жадвал for Andijan)', function () {
    $this->seed();
    $book = loadAndijanExport();
    ['ctx' => $ctx, 'rwb' => $rwb] = exportSheetCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'export', 'export');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('5-жадвал');
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/SheetResolverExportTest.php`
Expected: FAIL — no `export` logical_kind in SIGNATURES.

- [ ] **Step 3: Inspect rows 1-5 plain-string cells**

```
php -d memory_limit=1G artisan tinker --execute="
\$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx');
\$reader->setReadDataOnly(false);
\$book = \$reader->load('../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx');
\$sheet = \$book->getSheetByName('5-жадвал');
for (\$row = 1; \$row <= 5; \$row++) {
    for (\$col = 1; \$col <= 14; \$col++) {
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

Identify plain-string cells unique to export.

- [ ] **Step 4: Add the signature**

Likely candidates from Plan 1 row 4-5 inspection (`'Туман/шаҳарлар'`, `'Январь-март амалда'`, `'Экспорт ҳажмининг ўсиши'`, `'Январь-июнь кутилиш'`, `'Т/р'`). Pick 2-3 plain-string substrings that aren't already in another module's signature. The full SIGNATURES becomes:

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
    'foreign_invest'            => ['Шаҳар ва туманлар номи', 'I чорак'],
    'export'                    => ['Экспорт ҳажмининг', 'Январь-март', 'Январь-июнь'],
];
```

If those don't match (test still fails), use whichever plain-string content Step 3 surfaced.

- [ ] **Step 5: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/SheetResolverExportTest.php`
Expected: 1 test passes.

- [ ] **Step 6: Run full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: 106 + 1 = 107 tests green.

- [ ] **Step 7: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/SheetResolver.php \
    backend/tests/Feature/Import/SheetResolverExportTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add SheetResolver signature for export

Sheet name varies: '5-жадвал' (most), '5-жадвал (2)' (Jizzakh
typo), '5.1-жадвал' (Qashqadaryo, Surkhandarya). Plain-string
column-header signatures match all by content.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Implement `ExportModuleParser`

**Files:**
- Create: `backend/app/Services/Import/Modules/ExportModuleParser.php`
- Create: `backend/tests/Feature/Import/ExportModuleParserTest.php`

### Step 0 (MANDATORY): Inspect column layout

Plan 1 only confirmed cols A-L. The year_expected and year_growth columns live in cols M+ (sheet `dim=A1:P22`).

```
php -d memory_limit=1G artisan tinker --execute="
\$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx');
\$reader->setReadDataOnly(false);
\$book = \$reader->load('../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx');
\$sheet = \$book->getSheetByName('5-жадвал');
for (\$row = 4; \$row <= 12; \$row++) {
    \$rowData = [];
    for (\$col = 1; \$col <= 16; \$col++) {
        \$rowData[chr(64+\$col)] = \$sheet->getCell([\$col, \$row])->getCalculatedValue();
    }
    print_r([\$row => \$rowData]);
}
"
```

Find columns matching DATA blob's Andijan rollup:
- year_forecast=967178.43, q1_exporters=260, q1_value=196620.25, q1_growth=173.71
- h1_exporters=275, h1_expected=361620.88, h1_growth=121
- year_exporters=400, year_expected=976839.0, year_growth=131.5

Note column number for each. Use them in Step 2.

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/ExportModuleParserTest.php`:

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
use App\Services\Import\Modules\ExportModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function exportParserCtx(): array
{
    $path = base_path('../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan export workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'export')->value('id'),
        'file_name' => '5.1-5.2-жадваллар (экспорт).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('ExportModuleParser produces 51 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = exportParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new ExportModuleParser(
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

    expect(ImportStagingIndicatorFact::where('indicator_code', 'export')->count())->toBe(51);
    expect(ImportStagingIndicatorFact::where('indicator_code', 'export')->whereNull('district_code')->count())->toBe(3);
    expect(ImportStagingIndicatorFact::where('indicator_code', 'export')->whereNotNull('district_code')->count())->toBe(48);

    // Region rollup q1: actual=q1_value=196620.25, growth=173.71, count_extra=q1_exporters=260
    $q1 = ImportStagingIndicatorFact::where('indicator_code', 'export')
        ->whereNull('district_code')->where('period', 'q1')->first();
    expect($q1)->not->toBeNull();
    expect($q1->plan_value)->toBeNull();
    expect($q1->actual_hokimyat)->toBeNumericallyClose(196620.25, 0.5);
    expect($q1->growth_pct)->toBeNumericallyClose(173.71, 0.05);
    expect($q1->count_extra)->toBe(260);

    // Region rollup h1: expected=h1_expected=361620.88, growth=121, count_extra=275
    $h1 = ImportStagingIndicatorFact::where('indicator_code', 'export')
        ->whereNull('district_code')->where('period', 'h1')->first();
    expect($h1->plan_value)->toBeNull();
    expect($h1->expected_value)->toBeNumericallyClose(361620.88, 0.5);
    expect($h1->actual_hokimyat)->toBeNull();
    expect($h1->growth_pct)->toBeNumericallyClose(121, 0.05);
    expect($h1->count_extra)->toBe(275);

    // Region rollup year: plan=year_forecast=967178.43, expected=year_expected=976839.0, count_extra=400
    $year = ImportStagingIndicatorFact::where('indicator_code', 'export')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($year->plan_value)->toBeNumericallyClose(967178.43, 0.5);
    expect($year->expected_value)->toBeNumericallyClose(976839.0, 0.5);
    expect($year->actual_hokimyat)->toBeNull();
    expect($year->growth_pct)->toBeNumericallyClose(131.5, 0.05);
    expect($year->count_extra)->toBe(400);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ExportModuleParserTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create `backend/app/Services/Import/Modules/ExportModuleParser.php`**

Replace `?` placeholders with column numbers verified in Step 0:

```php
<?php

namespace App\Services\Import\Modules;

use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'export'; }

    // Confirmed from Plan 1 inspection (cols A-L)
    private const COL_DISTRICT_NAME       = 2;   // B
    private const COL_YEAR_FORECAST       = 3;   // C
    private const COL_Q1_EXPORTERS        = 4;   // D
    private const COL_Q1_VALUE            = 5;   // E
    private const COL_Q1_GROWTH           = 6;   // F
    // col 7 (G) = q1 difference — IGNORE
    private const COL_H1_EXPORTERS        = 8;   // H
    private const COL_H1_EXPECTED         = 9;   // I
    private const COL_H1_GROWTH           = 10;  // J
    // col 11 (K) = h1 difference — IGNORE
    private const COL_YEAR_EXPORTERS      = 12;  // L

    // VERIFY in Step 0
    private const COL_YEAR_EXPECTED       = 13;  // M (verify)
    private const COL_YEAR_GROWTH         = 14;  // N (verify)

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'export', 'export');
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
     * Strict rollup detection (Plan 6 lesson): short string ending in 'вилояти'.
     * Avoids matching long title rows that contain 'вилоятининг' mid-sentence.
     */
    private function findRollupRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= 15; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            if (is_string($colA)) {
                $t = trim($colA);
                if (strlen($t) <= 40 && str_ends_with($t, 'вилояти')) return $row;
            }
        }
        return null;
    }

    private function classifyRow(mixed $colA, mixed $colB): string
    {
        if (is_string($colA)) {
            $t = trim($colA);
            if (strlen($t) <= 40 && str_ends_with($t, 'вилояти')) return 'rollup';
        }
        if (! is_string($colB) || trim($colB) === '') return 'skip';
        if (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA)))) return 'district';
        return 'skip';
    }

    private function emitEntityRows(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): int {
        $sourceLabel = basename($filePath) . " · {$sheet->getTitle()} · row $row";

        $yearForecast  = $this->numericOrNull($sheet->getCell([self::COL_YEAR_FORECAST,  $row])->getCalculatedValue());
        $q1Exporters   = $this->intOrNull(    $sheet->getCell([self::COL_Q1_EXPORTERS,   $row])->getCalculatedValue());
        $q1Value       = $this->numericOrNull($sheet->getCell([self::COL_Q1_VALUE,       $row])->getCalculatedValue());
        $q1Growth      = $this->numericOrNull($sheet->getCell([self::COL_Q1_GROWTH,      $row])->getCalculatedValue());
        $h1Exporters   = $this->intOrNull(    $sheet->getCell([self::COL_H1_EXPORTERS,   $row])->getCalculatedValue());
        $h1Expected    = $this->numericOrNull($sheet->getCell([self::COL_H1_EXPECTED,    $row])->getCalculatedValue());
        $h1Growth      = $this->numericOrNull($sheet->getCell([self::COL_H1_GROWTH,      $row])->getCalculatedValue());
        $yearExporters = $this->intOrNull(    $sheet->getCell([self::COL_YEAR_EXPORTERS, $row])->getCalculatedValue());
        $yearExpected  = $this->numericOrNull($sheet->getCell([self::COL_YEAR_EXPECTED,  $row])->getCalculatedValue());
        $yearGrowth    = $this->numericOrNull($sheet->getCell([self::COL_YEAR_GROWTH,    $row])->getCalculatedValue());

        $rows = [
            ['period' => 'q1',   'plan' => null,         'expected' => null,         'actual' => $q1Value, 'growth' => $q1Growth,   'extra' => $q1Exporters],
            ['period' => 'h1',   'plan' => null,         'expected' => $h1Expected,  'actual' => null,     'growth' => $h1Growth,   'extra' => $h1Exporters],
            ['period' => 'year', 'plan' => $yearForecast,'expected' => $yearExpected,'actual' => null,     'growth' => $yearGrowth, 'extra' => $yearExporters],
        ];

        $count = 0;
        foreach ($rows as $r) {
            $dto = new IndicatorFactDto(
                regionCode:      $ctx->regionCode(),
                districtCode:    $districtCode,
                year:            $ctx->year,
                indicatorCode:   'export',
                period:          $r['period'],
                planValue:       $r['plan'],
                expectedValue:   $r['expected'],
                actualHokimyat:  $r['actual'],
                growthPct:       $r['growth'],
                countExtra:      $r['extra'],
                unit:            'минг доллар',
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

- [ ] **Step 4: Run the test**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ExportModuleParserTest.php`
Expected: 1 test passes.

If it fails:
- **Wrong row count ≠ 51**: dump rollup-row detection.
- **Spot-check fails by ~10x**: column index wrong. Re-verify Step 0.
- **Districts not found**: check workbook district name strings vs alt_labels.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/Modules/ExportModuleParser.php \
    backend/tests/Feature/Import/ExportModuleParserTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add ExportModuleParser

Parses Andijan's '5-жадвал' sheet — 1 region rollup + 16 districts
× 3 periods (q1, h1, year) = 51 staging rows under
indicator_code='export', unit='минг доллар'. Per-period mapping:
q1 has actual+growth+exporters, h1 has expected+growth+exporters,
year has plan+expected+growth+exporters.

[Note column-position findings from Step 0 inspection.]

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Register `ExportModuleParser` in `ImportRegionCommand`

**Files:**
- Modify: `backend/app/Console/Commands/ImportRegionCommand.php`
- Create: `backend/tests/Feature/Import/ImportRegionCommandExportTest.php`

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/ImportRegionCommandExportTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 export creates a successful run with 51 rows', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx'))) {
        $this->markTestSkipped('Andijan export data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'export',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    expect(ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'export')->count())->toBe(51);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ImportRegionCommandExportTest.php`
Expected: FAIL — "no parser implemented yet, skipping".

- [ ] **Step 3: Register the parser**

Edit `backend/app/Console/Commands/ImportRegionCommand.php`:

Add to imports:
```php
use App\Services\Import\Modules\ExportModuleParser;
```

Add to the `$parsers` array (after `'foreign_invest'`):
```php
$parsers = [
    'macro'          => new MacroModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'inflation'      => new InflationModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'budget'         => new BudgetModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'budget_invest'  => new BudgetInvestModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'foreign_invest' => new ForeignInvestModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'export'         => new ExportModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

- [ ] **Step 4: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ImportRegionCommandExportTest.php`
Expected: 1 test passes.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Console/Commands/ImportRegionCommand.php \
    backend/tests/Feature/Import/ImportRegionCommandExportTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): register ExportModuleParser

php artisan import:region andijan 2026 --module=export produces
51 staging rows.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: `AndijanExportParityTest`

**Files:**
- Create: `backend/tests/Feature/Import/AndijanExportParityTest.php`

- [ ] **Step 1: Write the parity test**

Create `backend/tests/Feature/Import/AndijanExportParityTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanExportDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 'andijan')
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

function assertExportPeriodRow($actual, array $e, string $period): void
{
    if ($period === 'q1') {
        expect($actual->plan_value)->toBeNull();
        expect($actual->expected_value)->toBeNull();
        expect($actual->actual_hokimyat)->toBeNumericallyClose($e['q1_value'], 0.5);
        expect($actual->count_extra)->toBe($e['q1_exporters']);
    } elseif ($period === 'h1') {
        expect($actual->plan_value)->toBeNull();
        expect($actual->expected_value)->toBeNumericallyClose($e['h1_expected'], 0.5);
        expect($actual->actual_hokimyat)->toBeNull();
        expect($actual->count_extra)->toBe($e['h1_exporters']);
    } else { // year
        expect($actual->plan_value)->toBeNumericallyClose($e['year_forecast'], 0.5);
        expect($actual->expected_value)->toBeNumericallyClose($e['year_expected'], 0.5);
        expect($actual->actual_hokimyat)->toBeNull();
        expect($actual->count_extra)->toBe($e['year_exporters']);
    }
    if (isset($e["{$period}_growth"]) && is_numeric($e["{$period}_growth"])) {
        expect($actual->growth_pct)->toBeNumericallyClose($e["{$period}_growth"], 0.05);
    }
}

test('Andijan export import reproduces DATA.regional.export and DATA.districts[*].data.export within tolerance', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/5.1-5.2-жадваллар (экспорт).xlsx'))) {
        $this->markTestSkipped('Andijan export data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'export',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->where('indicator_code', 'export')->get();

    expect($rows)->toHaveCount(51);

    $regional = $expected['regional']['export'];
    foreach (['q1', 'h1', 'year'] as $period) {
        $actual = $rows->first(fn ($r) =>
            $r->district_code === null && $r->period->value === $period
        );
        expect($actual)->not->toBeNull("missing rollup row for period $period");
        assertExportPeriodRow($actual, $regional, $period);
    }

    $matched = 0;
    $unmatched = [];
    foreach ($expected['districts'] as $expectedDistrict) {
        $ex = $expectedDistrict['data']['export'] ?? null;
        if ($ex === null) continue;

        $districtCode = andijanExportDistrictCode($expectedDistrict['name']);
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
            assertExportPeriodRow($actual, $ex, $period);
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(45);

    if (! empty($unmatched)) {
        echo "\nUnmatched export entries: " . implode(', ', $unmatched) . "\n";
    }
});
```

- [ ] **Step 2: Run, verify it passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/AndijanExportParityTest.php`
Expected: 1 test passes.

- [ ] **Step 3: Run the full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: ~110 tests green.

- [ ] **Step 4: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/tests/Feature/Import/AndijanExportParityTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "test(import): add Andijan export parity test

51 staging rows asserted against DATA.regional.export and
DATA.districts[*].data.export. Plan 7 (export) complete.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

# PART B: EMPLOYMENT MODULE (Tasks 6-10)

## Task 6: Add `employment` signature to `SheetResolver`

**Files:**
- Modify: `backend/app/Services/Import/SheetResolver.php`
- Create: `backend/tests/Feature/Import/SheetResolverEmploymentTest.php`

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/SheetResolverEmploymentTest.php`:

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

function loadAndijanEmployment(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $path = base_path('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan employment workbook not present');
    }
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(false);
    return $reader->load($path);
}

function employmentSheetCtx(): array
{
    $region = Region::where('code', 'andijan')->first();
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'employment')->value('id'),
        'file_name' => '6-жадвал (бандлик ва камбағаллик даражаси).xlsx',
        'file_path' => 'fixture',
        'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(
        run: ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]),
        region: $region, year: 2026, dataPath: base_path('../data'),
    );
    return ['ctx' => $ctx, 'rwb' => $rwb];
}

test('SheetResolver detects employment sheet by content (6. Камбағаллик for Andijan)', function () {
    $this->seed();
    $book = loadAndijanEmployment();
    ['ctx' => $ctx, 'rwb' => $rwb] = employmentSheetCtx();
    $resolver = new SheetResolver(new IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'employment', 'employment');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('6. Камбағаллик');
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/SheetResolverEmploymentTest.php`
Expected: FAIL — no `employment` logical_kind.

- [ ] **Step 3: Inspect rows 1-5**

```
php -d memory_limit=1G artisan tinker --execute="
\$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx');
\$reader->setReadDataOnly(false);
\$book = \$reader->load('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx');
\$sheet = \$book->getSheetByName('6. Камбағаллик');
for (\$row = 1; \$row <= 6; \$row++) {
    for (\$col = 1; \$col <= 14; \$col++) {
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

Identify plain-string cells unique to employment.

- [ ] **Step 4: Add the signature**

Append to `SheetResolver::SIGNATURES`:
```php
'employment' => ['Ишсизлик', 'Камбағаллик', 'Туман (шаҳар)номи'],
```

(The `'Туман (шаҳар)номи'` substring may have extra whitespace in the workbook — verify in Step 3 and adjust.)

If those don't match, use whichever Step 3 surfaced.

- [ ] **Step 5: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/SheetResolverEmploymentTest.php`
Expected: 1 test passes.

- [ ] **Step 6: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/SheetResolver.php \
    backend/tests/Feature/Import/SheetResolverEmploymentTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add SheetResolver signature for employment

Sheet name varies: '6. Камбағаллик', '7. Камбағаллик',
'Камбағаллик'. Plain-string column-header signatures match all.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: `EmploymentSentinelTest` (focused sentinel handling)

**Files:**
- Create: `backend/tests/Feature/Import/EmploymentSentinelTest.php`

This task writes the failing tests for sentinel handling **before** the parser exists. Task 8 makes them pass.

- [ ] **Step 1: Write the focused sentinel test**

Create `backend/tests/Feature/Import/EmploymentSentinelTest.php`:

```php
<?php

use App\Models\DataQualityIssue;
use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\EmploymentModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function employmentSentinelCtx(): array
{
    $path = base_path('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan employment workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'employment')->value('id'),
        'file_name' => '6-жадвал (бандлик ва камбағаллик даражаси).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('Andijan city poverty_year is parsed as a sentinel row', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = employmentSentinelCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new EmploymentModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        $districts,
        $writer,
        $issues,
    );

    $parser->parse($ctx, $path, $rwb->id);
    DB::transaction(fn() => $writer->flush());
    $issues->flush();

    // Андижон шаҳри (d01) poverty_year is 'холи ҳудуд' per the DATA blob.
    $row = ImportStagingIndicatorFact::where('region_code', 'andijan')
        ->where('district_code', 'd01')
        ->where('indicator_code', 'poverty')
        ->where('period', 'year')
        ->first();
    expect($row)->not->toBeNull();
    expect($row->is_sentinel)->toBeTrue();
    expect($row->sentinel_label)->toContain('холи ҳудуд');
    expect($row->plan_value)->toBeNull();
});

test('At least one sentinel issue is raised in data_quality_issues', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = employmentSentinelCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new EmploymentModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        $districts,
        $writer,
        $issues,
    );

    $parser->parse($ctx, $path, $rwb->id);
    $writer->flush();
    $issues->flush();

    $sentinelIssues = DataQualityIssue::where('issue_kind', 'sentinel')->count();
    expect($sentinelIssues)->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/EmploymentSentinelTest.php`
Expected: FAIL — `EmploymentModuleParser` class not found.

- [ ] **Step 3: Commit the failing test (Tasks 8 makes it pass)**

This test is a TDD anchor for sentinel handling. Don't commit yet — leave it failing until Task 8 is implemented. The implementer in Task 8 verifies all three EmploymentModuleParser tests (this + Task 8's own + Task 10's parity) pass together.

(No commit at this point; the test file remains uncommitted until Task 8 completes.)

---

## Task 8: Implement `EmploymentModuleParser`

**Files:**
- Create: `backend/app/Services/Import/Modules/EmploymentModuleParser.php`
- Create: `backend/tests/Feature/Import/EmploymentModuleParserTest.php`

### Step 0 (MANDATORY): Inspect column layout

Plan 1 inspection only confirmed cols A-L. Microprojects columns (h1 = 3834, year = 8790) live in cols M-N. Verify.

```
php -d memory_limit=1G artisan tinker --execute="
\$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx');
\$reader->setReadDataOnly(false);
\$book = \$reader->load('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx');
\$sheet = \$book->getSheetByName('6. Камбағаллик');
for (\$row = 4; \$row <= 12; \$row++) {
    \$rowData = [];
    for (\$col = 1; \$col <= 16; \$col++) {
        \$rowData[chr(64+\$col)] = \$sheet->getCell([\$col, \$row])->getCalculatedValue();
    }
    print_r([\$row => \$rowData]);
}
"
```

Look for ЖАМИ rollup row. Confirm 6 indicator pairs (h1, year). Microprojects in M-N.

- [ ] **Step 1: Write the failing parser test**

Create `backend/tests/Feature/Import/EmploymentModuleParserTest.php`:

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
use App\Services\Import\Modules\EmploymentModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function employmentParserCtx(): array
{
    $path = base_path('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx');
    if (! file_exists($path)) {
        test()->markTestSkipped('Andijan employment workbook not present');
    }
    $region = Region::where('code', 'andijan')->first();
    $run = ImportRun::create(['region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'employment')->value('id'),
        'file_name' => '6-жадвал (бандлик ва камбағаллик даражаси).xlsx',
        'file_path' => $path,
        'last_seen_at' => now(),
    ]);
    return [
        'ctx' => new ImportContext(run: $run, region: $region, year: 2026, dataPath: base_path('../data')),
        'path' => $path,
        'rwb' => $rwb,
    ];
}

test('EmploymentModuleParser produces 204 staging rows for Andijan', function () {
    $this->seed();
    ['ctx' => $ctx, 'path' => $path, 'rwb' => $rwb] = employmentParserCtx();

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $districts = new DistrictResolver($issues);
    $parser = new EmploymentModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        $districts,
        $writer,
        $issues,
    );

    $parser->parse($ctx, $path, $rwb->id);

    expect($writer->bufferedCount('import_staging_indicator_facts'))->toBe(204);

    DB::transaction(fn() => $writer->flush());
    $issues->flush();

    // 6 indicators × 2 periods × (1 rollup + 16 districts = 17 entities) = 204
    expect(ImportStagingIndicatorFact::whereIn('indicator_code', ['unemployment','poverty','jobs','legalization','mfy_clear','microprojects'])->count())->toBe(204);

    // Region rollup unemployment_h1 = 3.84
    $unempH1 = ImportStagingIndicatorFact::where('indicator_code', 'unemployment')
        ->whereNull('district_code')->where('period', 'h1')->first();
    expect($unempH1)->not->toBeNull();
    expect($unempH1->plan_value)->toBeNumericallyClose(3.84, 0.05);
    expect($unempH1->unit)->toBe('%');
    expect($unempH1->is_sentinel)->toBeFalse();

    // Region rollup poverty_year = 2.7 (NOT a sentinel for the rollup)
    $povYear = ImportStagingIndicatorFact::where('indicator_code', 'poverty')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($povYear)->not->toBeNull();
    expect($povYear->plan_value)->toBeNumericallyClose(2.7, 0.05);
    expect($povYear->is_sentinel)->toBeFalse();

    // Region rollup jobs_year = 86.674
    $jobsYear = ImportStagingIndicatorFact::where('indicator_code', 'jobs')
        ->whereNull('district_code')->where('period', 'year')->first();
    expect($jobsYear->plan_value)->toBeNumericallyClose(86.674, 0.05);

    // Андижон шаҳри poverty_year IS a sentinel
    $cityPovYear = ImportStagingIndicatorFact::where('indicator_code', 'poverty')
        ->where('district_code', 'd01')->where('period', 'year')->first();
    expect($cityPovYear->is_sentinel)->toBeTrue();
    expect($cityPovYear->plan_value)->toBeNull();
    expect($cityPovYear->sentinel_label)->toContain('холи ҳудуд');
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/EmploymentModuleParserTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create `backend/app/Services/Import/Modules/EmploymentModuleParser.php`**

```php
<?php

namespace App\Services\Import\Modules;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\Indicator;
use App\Services\Import\ImportContext;
use App\Support\Import\IndicatorFactDto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmploymentModuleParser extends ModuleParser
{
    public function moduleCode(): string { return 'employment'; }

    /**
     * Indicator → (h1_col, year_col) mapping. Verify col positions in Step 0.
     */
    private const INDICATOR_COLUMNS = [
        'unemployment'  => [3,  4],   // C, D
        'poverty'       => [5,  6],   // E, F
        'mfy_clear'     => [7,  8],   // G, H
        'jobs'          => [9,  10],  // I, J
        'legalization'  => [11, 12],  // K, L
        'microprojects' => [13, 14],  // M, N — verify in Step 0
    ];

    private array $unitByIndicator = [];

    public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);
        $book = $reader->load($filePath);

        $this->districtResolver->loadFor($ctx->regionCode());
        $this->loadUnits();

        $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'employment', 'employment');
        if ($sheet === null) return 0;

        $rollupRow = $this->findRollupRow($sheet);
        if ($rollupRow === null) return 0;

        $count = 0;
        $count += $this->emitEntityRows($ctx, $sheet, $rollupRow, null, $filePath);

        for ($row = $rollupRow + 1; $row <= $rollupRow + 30; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            $colB = $sheet->getCell([2, $row])->getCalculatedValue();
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

    private function loadUnits(): void
    {
        if (! empty($this->unitByIndicator)) return;
        foreach (array_keys(self::INDICATOR_COLUMNS) as $code) {
            $unit = Indicator::where('code', $code)->value('default_unit') ?? '';
            $this->unitByIndicator[$code] = $unit;
        }
    }

    private function findRollupRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= 15; $row++) {
            $colA = $sheet->getCell([1, $row])->getCalculatedValue();
            if (is_string($colA) && trim($colA) === 'ЖАМИ') return $row;
        }
        return null;
    }

    private function classifyRow(mixed $colA, mixed $colB): string
    {
        if (is_string($colA) && trim($colA) === 'ЖАМИ') return 'rollup';
        if (! is_string($colB) || trim($colB) === '') return 'skip';
        if (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA)))) return 'district';
        return 'skip';
    }

    /** Emits 12 IndicatorFactDtos (6 indicators × 2 periods) for one row. */
    private function emitEntityRows(
        ImportContext $ctx,
        Worksheet $sheet,
        int $row,
        ?string $districtCode,
        string $filePath,
    ): int {
        $sourceLabel = basename($filePath) . " · {$sheet->getTitle()} · row $row";

        $count = 0;
        foreach (self::INDICATOR_COLUMNS as $indicatorCode => [$h1Col, $yearCol]) {
            foreach (['h1' => $h1Col, 'year' => $yearCol] as $period => $col) {
                $rawValue = $sheet->getCell([$col, $row])->getCalculatedValue();
                $isSentinel = $this->isSentinel($rawValue);

                if ($isSentinel) {
                    // Raise issue for operator review
                    $this->issueCollector->add(
                        kind: IssueKind::Sentinel,
                        severity: IssueSeverity::Medium,
                        detail: "Sentinel value '{$rawValue}' in {$indicatorCode}/{$period}",
                        regionCode: $ctx->regionCode(),
                        districtCode: $districtCode,
                        indicatorCode: $indicatorCode,
                        year: $ctx->year,
                        period: $period,
                        detectedValue: (string) $rawValue,
                        sourceLabel: $sourceLabel,
                        importRunId: $ctx->run->id,
                    );
                }

                $dto = new IndicatorFactDto(
                    regionCode:      $ctx->regionCode(),
                    districtCode:    $districtCode,
                    year:            $ctx->year,
                    indicatorCode:   $indicatorCode,
                    period:          $period,
                    planValue:       $isSentinel ? null : $this->numericOrNull($rawValue),
                    isSentinel:      $isSentinel,
                    sentinelLabel:   $isSentinel ? 'холи ҳудуд' : null,
                    unit:            $this->unitByIndicator[$indicatorCode] ?? '',
                    sourceLabel:     $sourceLabel,
                );
                $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
                $count++;
            }
        }
        return $count;
    }

    private function isSentinel(mixed $value): bool
    {
        return is_string($value) && str_contains($value, 'холи ҳудуд');
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if (! is_numeric($value)) return null;
        return (float) $value;
    }
}
```

- [ ] **Step 4: Run all employment tests**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/EmploymentSentinelTest.php tests/Feature/Import/EmploymentModuleParserTest.php`
Expected: 3 tests pass (2 from sentinel test + 1 from parser test).

If the row count is not 204, it likely means the microprojects columns are at different positions. Adjust `INDICATOR_COLUMNS` per Step 0 findings.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Services/Import/Modules/EmploymentModuleParser.php \
    backend/tests/Feature/Import/EmploymentSentinelTest.php \
    backend/tests/Feature/Import/EmploymentModuleParserTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): add EmploymentModuleParser with sentinel handling

Parses Andijan's '6. Камбағаллик' sheet — 1 region rollup
('ЖАМИ' in col A) + 16 districts × 6 indicators × 2 periods
(h1, year) = 204 staging rows under indicator_codes
{unemployment, poverty, jobs, legalization, mfy_clear,
microprojects}.

First sentinel handling: when a poverty cell value is the string
'холи ҳудуд' (poverty-free zone), parser emits is_sentinel=true
DTO with plan_value=NULL and raises IssueKind::Sentinel
(severity=medium) so operators see during review.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Register `EmploymentModuleParser` in `ImportRegionCommand`

**Files:**
- Modify: `backend/app/Console/Commands/ImportRegionCommand.php`
- Create: `backend/tests/Feature/Import/ImportRegionCommandEmploymentTest.php`

- [ ] **Step 1: Failing test**

Create `backend/tests/Feature/Import/ImportRegionCommandEmploymentTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('import:region andijan 2026 employment creates a successful run with 204 rows', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx'))) {
        $this->markTestSkipped('Andijan employment data not present');
    }

    $exitCode = Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'employment',
    ]);

    expect($exitCode)->toBe(0);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $count = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->whereIn('indicator_code', ['unemployment','poverty','jobs','legalization','mfy_clear','microprojects'])
        ->count();
    expect($count)->toBe(204);
});
```

- [ ] **Step 2: Run, confirm fails**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ImportRegionCommandEmploymentTest.php`
Expected: FAIL — "no parser implemented yet, skipping".

- [ ] **Step 3: Register the parser**

Edit `backend/app/Console/Commands/ImportRegionCommand.php`:

Add to imports:
```php
use App\Services\Import\Modules\EmploymentModuleParser;
```

Add to `$parsers`:
```php
$parsers = [
    'macro'          => new MacroModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'inflation'      => new InflationModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'budget'         => new BudgetModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'budget_invest'  => new BudgetInvestModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'foreign_invest' => new ForeignInvestModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'export'         => new ExportModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
    'employment'     => new EmploymentModuleParser($sheetResolver, $headerDetector, $districtResolver, $writer, $issues),
];
```

- [ ] **Step 4: Run, confirm passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/ImportRegionCommandEmploymentTest.php`
Expected: 1 test passes.

- [ ] **Step 5: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/app/Console/Commands/ImportRegionCommand.php \
    backend/tests/Feature/Import/ImportRegionCommandEmploymentTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "feat(import): register EmploymentModuleParser

php artisan import:region andijan 2026 --module=employment
produces 204 staging rows. Omitting --module runs all 7 modules
(212 + 28 + 51 + 51 + 51 + 51 + 204 = 648 rows in one ImportRun).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: `AndijanEmploymentParityTest`

**Files:**
- Create: `backend/tests/Feature/Import/AndijanEmploymentParityTest.php`

- [ ] **Step 1: Write the parity test**

Create `backend/tests/Feature/Import/AndijanEmploymentParityTest.php`:

```php
<?php

use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\IndexHtmlDataExtractor;

uses(RefreshDatabase::class);

function andijanEmploymentDistrictCode(string $nameFull): ?string
{
    $sortOrder = DB::table('districts')
        ->where('region_code', 'andijan')
        ->where('name_full', $nameFull)
        ->value('sort_order');
    if ($sortOrder === null) return null;
    return 'd' . str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT);
}

/** Maps DATA blob employment keys to (indicator_code, period). */
function employmentKeyMapping(): array
{
    return [
        'unemployment_h1'   => ['unemployment',  'h1'],
        'unemployment_year' => ['unemployment',  'year'],
        'poverty_h1'        => ['poverty',       'h1'],
        'poverty_year'      => ['poverty',       'year'],
        'mfy_h1'            => ['mfy_clear',     'h1'],
        'mfy_year'          => ['mfy_clear',     'year'],
        'jobs_h1'           => ['jobs',          'h1'],
        'jobs_year'         => ['jobs',          'year'],
        'legalization_h1'   => ['legalization',  'h1'],
        'legalization_year' => ['legalization',  'year'],
        'microprojects_h1'  => ['microprojects', 'h1'],
        'microprojects_year'=> ['microprojects', 'year'],
    ];
}

function assertEmploymentCellMatches($actual, mixed $expectedValue): void
{
    if (is_string($expectedValue) && str_contains($expectedValue, 'холи ҳудуд')) {
        expect($actual->is_sentinel)->toBeTrue();
        expect($actual->sentinel_label)->toContain('холи ҳудуд');
        expect($actual->plan_value)->toBeNull();
    } else {
        expect($actual->is_sentinel)->toBeFalse();
        if (is_numeric($expectedValue)) {
            expect($actual->plan_value)->toBeNumericallyClose($expectedValue, 0.05);
        }
    }
}

test('Andijan employment import reproduces DATA.regional.employment and DATA.districts[*].data.employment with sentinel support', function () {
    $this->seed();
    if (! file_exists(base_path('../data/2. Андижон/6-жадвал (бандлик ва камбағаллик даражаси).xlsx'))) {
        $this->markTestSkipped('Andijan employment data not present');
    }
    if (! file_exists(base_path('../index.html'))) {
        $this->markTestSkipped('index.html not present');
    }

    Artisan::call('import:region', [
        'region_code' => 'andijan', 'year' => 2026, '--module' => 'employment',
    ]);

    $run = ImportRun::latest()->first();
    expect($run->status->value)->toBe('awaiting_review');
    expect($run->issues_blocker_count)->toBe(0);

    $expected = (new IndexHtmlDataExtractor())->extract(base_path('../index.html'));
    $rows = ImportStagingIndicatorFact::where('import_run_id', $run->id)
        ->whereIn('indicator_code', ['unemployment','poverty','jobs','legalization','mfy_clear','microprojects'])
        ->get();

    expect($rows)->toHaveCount(204);

    $mapping = employmentKeyMapping();

    // Region rollup
    $regional = $expected['regional']['employment'];
    foreach ($mapping as $dataKey => [$indicatorCode, $period]) {
        if (! isset($regional[$dataKey])) continue;
        $actual = $rows->first(fn ($r) =>
            $r->district_code === null
            && $r->indicator_code === $indicatorCode
            && $r->period->value === $period
        );
        expect($actual)->not->toBeNull("missing rollup row for $indicatorCode/$period");
        assertEmploymentCellMatches($actual, $regional[$dataKey]);
    }

    // District rows
    $matched = 0;
    foreach ($expected['districts'] as $expectedDistrict) {
        $emp = $expectedDistrict['data']['employment'] ?? null;
        if ($emp === null) continue;

        $districtCode = andijanEmploymentDistrictCode($expectedDistrict['name']);
        if ($districtCode === null) continue;

        foreach ($mapping as $dataKey => [$indicatorCode, $period]) {
            if (! isset($emp[$dataKey])) continue;
            $actual = $rows->first(fn ($r) =>
                $r->district_code === $districtCode
                && $r->indicator_code === $indicatorCode
                && $r->period->value === $period
            );
            if ($actual === null) continue;
            $matched++;
            assertEmploymentCellMatches($actual, $emp[$dataKey]);
        }
    }

    expect($matched)->toBeGreaterThanOrEqual(150);   // 16 districts × 12 cells, allow some misses

    // Sentinel issues should exist (Андижон шаҳри poverty_year)
    $sentinels = DB::table('data_quality_issues')
        ->where('import_run_id', $run->id)
        ->where('issue_kind', 'sentinel')->count();
    expect($sentinels)->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Run, verify it passes**

Run: `php -d memory_limit=1G vendor/bin/pest tests/Feature/Import/AndijanEmploymentParityTest.php`
Expected: 1 test passes.

- [ ] **Step 3: Run the full suite**

Run: `php -d memory_limit=1G vendor/bin/pest --no-coverage`
Expected: ~115+ tests green. Plans 7 + 8 complete.

- [ ] **Step 4: Commit**

```
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' add \
    backend/tests/Feature/Import/AndijanEmploymentParityTest.php
git -C 'C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali' commit -m "test(import): add Andijan employment parity test

204 staging rows asserted against DATA.regional.employment and
DATA.districts[*].data.employment with sentinel-aware matching.
Plans 7 + 8 complete: all 7 source modules (macro, inflation,
budget, budget_invest, foreign_invest, export, employment) ship
end-to-end with parity validation. Full-suite import:region
andijan 2026 produces 648 staging rows in one ImportRun.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Self-review

Verified against `docs/superpowers/specs/2026-05-06-export-and-employment-modules-design.md`:

- **Spec §3 architecture deltas:** Tasks 1-2 (export periods + sheet), Task 3 (export parser), Task 4 (export registration), Task 5 (export parity); Task 6 (employment sheet), Task 7 (sentinel test anchors), Task 8 (employment parser + sentinel impl), Task 9 (employment registration), Task 10 (employment parity). ✓
- **Spec §4 export per-period mapping:** Task 3 implements with explicit per-period asymmetry (q1 has actual, h1 has expected, year has plan+expected). ✓
- **Spec §5 employment 6-indicator fan-out + sentinel handling:** Tasks 7+8 implement with `INDICATOR_COLUMNS` map, `isSentinel()` check, IssueKind::Sentinel raised. ✓
- **Spec §6 CLI:** Tasks 4 + 9. ✓
- **Spec §7 parity tests:** Tasks 5 + 10. ✓

**Placeholder scan:** Two `// (verify)` markers in Task 3's column constants and Task 8's microprojects column are intentional — Step 0 inspections resolve them. No real placeholders.

**Type consistency:** `IndicatorFactDto` field names (existing), parser column constants, helper functions (`andijanExportDistrictCode`, `andijanEmploymentDistrictCode`, `assertExportPeriodRow`, `assertEmploymentCellMatches`) all consistent across tasks.

---

## Out of scope (deferred)

- Plan 9: roll out to 12 non-Navoi regions.
- Plan 10: Filament admin UI / promote-reject flow.
- Plan 11: Cross-region contamination detector + Statkom actual_statkom integration.
- 2025 prior-year columns (export workbook), difference columns (export), microprojects unit refinement.
