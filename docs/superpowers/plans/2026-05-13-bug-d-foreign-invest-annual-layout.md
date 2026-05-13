# Bug D: foreign_invest annual-only layout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Teach `SheetResolver` to recognize karakalpak's `4.2-жадвал (инвестициялар).xlsx` and teach `ForeignInvestModuleParser` to fall back to a single-column annual-only parser branch when no `'I чорак'` header is present.

**Architecture:** Two additive changes. (1) Extend `SheetResolver::SIGNATURES['foreign_invest']` with `'Ҳудудлар'` + `'ВМҚ-86'` so karakalpak's sheet matches. (2) In `ForeignInvestModuleParser::parse`, detect annual-only layout by absence of `'I чорак'` in first 10 rows; route to new `parseAnnualOnly` that emits one `year`-period DTO per district using col B. Standard regions stay on the existing quarterly path.

**Tech Stack:** PHP 8.3 · Laravel 11 · Pest 3 · PhpOffice/PhpSpreadsheet · PostgreSQL. All commands run from `backend/`.

---

## File structure

| File | Responsibility |
|---|---|
| `backend/app/Services/Import/SheetResolver.php` | extend `SIGNATURES['foreign_invest']` |
| `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php` | add layout detection + annual-only parse branch + extend rollup detection |
| `backend/tests/Feature/Import/SheetResolverForeignInvestTest.php` | extend with karakalpak-signature test |
| `backend/tests/Feature/Import/ForeignInvestAnnualOnlyTest.php` | new — covers parser annual-only branch end-to-end |

---

### Task 1: Extend SheetResolver signatures for foreign_invest

**Files:**
- Modify: `backend/app/Services/Import/SheetResolver.php`
- Modify: `backend/tests/Feature/Import/SheetResolverForeignInvestTest.php`

- [ ] **Step 1: Write the failing test**

Append to `backend/tests/Feature/Import/SheetResolverForeignInvestTest.php` (after the existing test):

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;

test('SheetResolver detects karakalpak-style annual-only foreign_invest sheet', function () {
    $this->seed();

    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('4.2. Хорижий инвестициялар');
    $sheet->setCellValue('A2', 'Қорақалпоғистон Республикасининг 2026 йил учун инвестиция прогноз кўрсаткичлари');
    $sheet->setCellValue('A4', 'Ҳудудлар');
    $sheet->setCellValue('B4', 'Хорижий инвестициялар прогнози (ВМҚ-86)');
    $sheet->setCellValue('B6', 'млн долл.');
    $sheet->setCellValue('A7', 'Жами');
    $sheet->setCellValue('B7', 633);

    DB::table('regions')->where('code', 1703)->update(['code' => 1735, 'name_latin' => 'karakalpak']);

    ['ctx' => $ctx, 'rwb' => $rwb] = foreignInvestSheetCtx();
    $ctx = new \App\Services\Import\ImportContext(
        run: $ctx->run, region: \App\Models\Region::where('code', 1735)->first(), year: 2026, dataPath: $ctx->dataPath,
    );
    $resolver = new \App\Services\Import\SheetResolver(new \App\Services\Import\IssueCollector());

    $sheet = $resolver->resolve($ctx, $book, $rwb->id, 'foreign_invest', 'foreign_invest');

    expect($sheet)->not->toBeNull();
    expect($sheet->getTitle())->toBe('4.2. Хорижий инвестициялар');
});
```

- [ ] **Step 2: Verify test fails**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/SheetResolverForeignInvestTest.php --filter="karakalpak-style"
```

Expected: FAIL — the karakalpak sheet matches zero existing signatures, `resolve()` returns null and emits a `sheet_missing` issue.

- [ ] **Step 3: Extend signatures**

In `backend/app/Services/Import/SheetResolver.php`, change the `foreign_invest` entry (around line 22):

```php
// before
'foreign_invest'            => ['Шаҳар ва туманлар номи', 'I чорак'],

// after
'foreign_invest'            => ['Шаҳар ва туманлар номи', 'I чорак', 'Ҳудудлар', 'ВМҚ-86'],
```

- [ ] **Step 4: Run test → expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/SheetResolverForeignInvestTest.php
```

Expected: 2/2 PASS (existing Andijan test still green, new karakalpak test now green).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Import/SheetResolver.php backend/tests/Feature/Import/SheetResolverForeignInvestTest.php
git commit -m "feat(import): SheetResolver matches karakalpak foreign_invest signature"
```

---

### Task 2: Add isAnnualOnlyLayout helper

**Files:**
- Modify: `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php`
- Create: `backend/tests/Feature/Import/ForeignInvestAnnualOnlyTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Import/ForeignInvestAnnualOnlyTest.php`:

```php
<?php

use App\Services\Import\DistrictResolver;
use App\Services\Import\HeaderDetector;
use App\Services\Import\IssueCollector;
use App\Services\Import\Modules\ForeignInvestModuleParser;
use App\Services\Import\SheetResolver;
use App\Services\Import\StagingWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

function makeForeignInvestParser(): ForeignInvestModuleParser
{
    $issues = new IssueCollector();
    return new ForeignInvestModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        new DistrictResolver($issues),
        new StagingWriter(),
        $issues,
    );
}

test('isAnnualOnlyLayout returns true when no "I чорак" appears in first 10 rows', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A4', 'Ҳудудлар');
    $sheet->setCellValue('B4', 'Хорижий инвестициялар прогнози (ВМҚ-86)');

    expect(invade(makeForeignInvestParser())->isAnnualOnlyLayout($sheet))->toBeTrue();
});

test('isAnnualOnlyLayout returns false when "I чорак" appears in row range', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A4', 'Шаҳар ва туманлар номи');
    $sheet->setCellValue('I4', 'I чорак прогноз');

    expect(invade(makeForeignInvestParser())->isAnnualOnlyLayout($sheet))->toBeFalse();
});
```

- [ ] **Step 2: Verify test fails**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/ForeignInvestAnnualOnlyTest.php
```

Expected: FAIL — `isAnnualOnlyLayout` method does not exist.

- [ ] **Step 3: Add helper to parser**

In `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php`, add the helper at the bottom of the class (after `ratioToPercent`, before the closing `}`):

```php
private function isAnnualOnlyLayout(Worksheet $sheet): bool
{
    $rows = $sheet->toArray(null, true, true, false);
    $limit = min(10, count($rows));
    for ($i = 0; $i < $limit; $i++) {
        foreach ($rows[$i] as $cell) {
            if (is_string($cell) && mb_stripos($cell, 'I чорак') !== false) {
                return false;
            }
        }
    }
    return true;
}
```

- [ ] **Step 4: Run tests → expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/ForeignInvestAnnualOnlyTest.php
```

Expected: 2/2 PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Import/Modules/ForeignInvestModuleParser.php backend/tests/Feature/Import/ForeignInvestAnnualOnlyTest.php
git commit -m "feat(import): isAnnualOnlyLayout detects absence of I чорак header"
```

---

### Task 3: Extend isRollupCell to accept `Жами` and `Республикаси`

**Files:**
- Modify: `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php`
- Modify: `backend/tests/Feature/Import/ForeignInvestAnnualOnlyTest.php`

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/Import/ForeignInvestAnnualOnlyTest.php`:

```php
test('isRollupCell accepts standard region with "вилояти" suffix', function () {
    expect(invade(makeForeignInvestParser())->isRollupCell('Андижон вилояти'))->toBeTrue();
});

test('isRollupCell accepts "Жами" (used in karakalpak)', function () {
    expect(invade(makeForeignInvestParser())->isRollupCell('Жами'))->toBeTrue();
});

test('isRollupCell accepts "Қорақалпоғистон Республикаси"', function () {
    expect(invade(makeForeignInvestParser())->isRollupCell('Қорақалпоғистон Республикаси'))->toBeTrue();
});

test('isRollupCell rejects multi-sentence title cells', function () {
    $longTitle = 'Қорақалпоғистон Республикасининг 2026 йил учун инвестиция прогноз кўрсаткичлари тўғрисида МАЪЛУМОТ';
    expect(invade(makeForeignInvestParser())->isRollupCell($longTitle))->toBeFalse();
});

test('isRollupCell rejects non-strings', function () {
    expect(invade(makeForeignInvestParser())->isRollupCell(42))->toBeFalse();
    expect(invade(makeForeignInvestParser())->isRollupCell(null))->toBeFalse();
});
```

- [ ] **Step 2: Verify some new tests fail**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/ForeignInvestAnnualOnlyTest.php --filter="Жами|Республикаси"
```

Expected: FAIL — `'Жами'` and `'Қорақалпоғистон Республикаси'` cases return false under the current predicate (only `'вилояти'` suffix accepted).

- [ ] **Step 3: Extend the helper**

In `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php`, replace the existing `isRollupCell` method (around lines 96-101):

```php
private function isRollupCell(mixed $value): bool
{
    if (! is_string($value)) return false;
    $trimmed = trim($value);
    if (strlen($trimmed) > 40) return false;
    return str_ends_with($trimmed, 'вилояти')
        || str_ends_with($trimmed, 'Республикаси')
        || $trimmed === 'Жами';
}
```

- [ ] **Step 4: Run tests → expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/ForeignInvestAnnualOnlyTest.php
```

Expected: all PASS (7 tests now in this file).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Import/Modules/ForeignInvestModuleParser.php backend/tests/Feature/Import/ForeignInvestAnnualOnlyTest.php
git commit -m "feat(import): isRollupCell accepts Жами and Республикаси rollup labels"
```

---

### Task 4: Add parseAnnualOnly + emitAnnualRow

**Files:**
- Modify: `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php`
- Modify: `backend/tests/Feature/Import/ForeignInvestAnnualOnlyTest.php`

- [ ] **Step 1: Write the failing test (end-to-end annual parse)**

Append to `tests/Feature/Import/ForeignInvestAnnualOnlyTest.php`:

```php
use App\Models\ImportRun;
use App\Models\ImportStagingIndicatorFact;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\ImportContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

test('parseAnnualOnly emits one year-period row per district + rollup with plan_value from col B', function () {
    $this->seed();

    // Build karakalpak-shaped xlsx in tmp.
    $tmpDir = sys_get_temp_dir() . '/fi_annual_' . uniqid();
    File::makeDirectory($tmpDir, 0777, true);
    $path = $tmpDir . DIRECTORY_SEPARATOR . 'karakalpak_4.2.xlsx';

    $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('4.2. Хорижий инвестициялар');
    $sheet->setCellValue('A2', 'Қорақалпоғистон Республикасининг 2026 йил учун инвестиция прогноз кўрсаткичлари');
    $sheet->setCellValue('A4', 'Ҳудудлар');
    $sheet->setCellValue('B4', 'Хорижий инвестициялар прогнози (ВМҚ-86)');
    $sheet->setCellValue('B6', 'млн долл.');
    $sheet->setCellValue('A7', 'Жами');         $sheet->setCellValue('B7', 633);
    $sheet->setCellValue('A8', 'Нукус шаҳри');   $sheet->setCellValue('B8', 129);
    $sheet->setCellValue('A9', 'Амударё тумани');$sheet->setCellValue('B9', 18);
    (new XlsxWriter($book))->save($path);
    $book->disconnectWorksheets();
    unset($book);

    $region = Region::where('code', 1735)->first();
    if (! $region) {
        DB::table('regions')->insert([
            'code' => 1735, 'name_short' => 'Қорақалпоғистон', 'name_full' => 'Қорақалпоғистон Республикаси',
            'name_latin' => 'karakalpak', 'sort_order' => 1, 'has_districts' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $region = Region::where('code', 1735)->first();
    }
    DB::table('districts')->insert([
        ['code' => 1735401, 'region_id' => $region->id, 'region_code' => 1735, 'name_short' => 'Нукус ш.', 'name_full' => 'Нукус шаҳри', 'kind' => 'city', 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 1735207, 'region_id' => $region->id, 'region_code' => 1735, 'name_short' => 'Амударё т.', 'name_full' => 'Амударё тумани', 'kind' => 'district', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $run = ImportRun::create(['region_code' => 1735, 'year' => 2026, 'trigger_kind' => 'cli', 'status' => 'parsing', 'started_at' => now()]);
    $rwb = RegionWorkbook::create([
        'region_id' => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id' => DB::table('modules')->where('code', 'foreign_invest')->value('id'),
        'file_name' => basename($path), 'file_path' => $path, 'last_seen_at' => now(),
    ]);
    $ctx = new ImportContext(run: $run, region: $region, year: 2026, dataPath: $tmpDir);

    $issues = new IssueCollector();
    $writer = new StagingWriter();
    $parser = new ForeignInvestModuleParser(
        new SheetResolver($issues),
        new HeaderDetector($issues),
        new DistrictResolver($issues),
        $writer,
        $issues,
    );

    $count = $parser->parse($ctx, $path, $rwb->id);

    expect($count)->toBe(3);
    DB::transaction(fn() => $writer->flush());

    $facts = ImportStagingIndicatorFact::where('import_run_id', $run->id)->orderBy('id')->get();
    expect($facts)->toHaveCount(3);

    $rollup = $facts->firstWhere('district_code', null);
    expect($rollup)->not->toBeNull();
    expect($rollup->period)->toBe('year');
    expect((float) $rollup->plan_value)->toBe(633.0);
    expect($rollup->expected_value)->toBeNull();
    expect($rollup->actual_hokimyat)->toBeNull();

    $nukus = $facts->firstWhere('district_code', 1735401);
    expect($nukus)->not->toBeNull();
    expect($nukus->period)->toBe('year');
    expect((float) $nukus->plan_value)->toBe(129.0);

    $amudaryo = $facts->firstWhere('district_code', 1735207);
    expect($amudaryo)->not->toBeNull();
    expect((float) $amudaryo->plan_value)->toBe(18.0);

    File::deleteDirectory($tmpDir);
});
```

- [ ] **Step 2: Verify test fails**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/ForeignInvestAnnualOnlyTest.php --filter="parseAnnualOnly emits"
```

Expected: FAIL — `parse()` currently routes the annual-only sheet to the standard-layout code path, which fails on missing quarterly columns. Exact error will be a null col-A or zero-row count depending on layout.

- [ ] **Step 3: Add parseAnnualOnly and emitAnnualRow**

In `backend/app/Services/Import/Modules/ForeignInvestModuleParser.php`, add both methods just before the helper-methods block at the bottom of the class:

```php
private function parseAnnualOnly(ImportContext $ctx, Worksheet $sheet, string $filePath): int
{
    $rollupRow = $this->findRollupRow($sheet);
    if ($rollupRow === null) return 0;

    $count = 0;
    $count += $this->emitAnnualRow($ctx, $sheet, $rollupRow, null, $filePath);

    for ($row = $rollupRow + 1; $row <= $rollupRow + 30; $row++) {
        $colA = $sheet->getCell([1, $row])->getCalculatedValue();
        $colB = $sheet->getCell([2, $row])->getCalculatedValue();
        if (! is_string($colA) || trim($colA) === '') continue;
        if (! is_numeric($colB)) continue;

        $districtCode = $this->districtResolver->resolve(
            trim($colA), $ctx,
            basename($filePath) . " · {$sheet->getTitle()} · row $row",
        );
        if ($districtCode === null) continue;

        $count += $this->emitAnnualRow($ctx, $sheet, $row, $districtCode, $filePath);
    }
    return $count;
}

private function emitAnnualRow(
    ImportContext $ctx,
    Worksheet $sheet,
    int $row,
    ?int $districtCode,
    string $filePath,
): int {
    $value = $this->numericOrNull($sheet->getCell([2, $row])->getCalculatedValue());
    if ($value === null) return 0;

    $dto = new IndicatorFactDto(
        regionCode:     $ctx->regionCode(),
        districtCode:   $districtCode,
        year:           $ctx->year,
        indicatorCode:  'investment',
        period:         'year',
        planValue:      $value,
        expectedValue:  null,
        actualHokimyat: null,
        pctOfPlan:      null,
        countExtra:     null,
        countExtra2:    null,
        unit:           'млн доллар',
        sourceLabel:    basename($filePath) . " · {$sheet->getTitle()} · row $row",
    );
    $this->stagingWriter->buffer('import_staging_indicator_facts', $dto->toStagingRow($ctx->run->id));
    return 1;
}
```

Verify the `districtCode` type on `IndicatorFactDto::__construct`. In current code (`backend/app/Support/Import/IndicatorFactDto.php`), `districtCode` is typed as `?int` after the SOATO migration. If you see `?string`, stop and report this gap before proceeding.

- [ ] **Step 4: Wire the branch into parse()**

Replace the body of `parse()` in the same file. Insert the layout check after `$sheet` is resolved:

```php
public function parse(ImportContext $ctx, string $filePath, int $regionWorkbookId): int
{
    $reader = IOFactory::createReaderForFile($filePath);
    $reader->setReadDataOnly(false);
    $book = $reader->load($filePath);

    $this->districtResolver->loadFor($ctx->regionCode());

    $sheet = $this->sheetResolver->resolve($ctx, $book, $regionWorkbookId, 'foreign_invest', 'foreign_invest');
    if ($sheet === null) return 0;

    if ($this->isAnnualOnlyLayout($sheet)) {
        return $this->parseAnnualOnly($ctx, $sheet, $filePath);
    }

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
```

The body matches the existing standard-path logic exactly except for the early `isAnnualOnlyLayout` branch — do not change behavior for non-annual workbooks.

- [ ] **Step 5: Run all foreign_invest tests → expect ALL PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/ForeignInvestAnnualOnlyTest.php tests/Feature/Import/ForeignInvestModuleParserTest.php tests/Feature/Import/SheetResolverForeignInvestTest.php
```

Expected: all GREEN. The Andijan parser-parity test must NOT regress (51 rows from the standard layout).

If `ForeignInvestModuleParserTest` is RED due to pre-existing fixture decay (e.g. `'d01'` string district codes after SOATO migration), record it as `SKIP for pre-existing fixture decay (out of scope)`. Verify the failure does NOT mention annual-layout or new methods.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/Import/Modules/ForeignInvestModuleParser.php backend/tests/Feature/Import/ForeignInvestAnnualOnlyTest.php
git commit -m "feat(import): parseAnnualOnly branch handles karakalpak foreign_invest"
```

---

### Task 5: End-to-end smoke against real karakalpak workbook

**Files:** none (operator verification).

- [ ] **Step 1: Run full karakalpak import**

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php artisan import:region karakalpak 2026
```

Expected output:

```
Importing region '1735' year 2026 (modules: macro, inflation, budget, budget_invest, foreign_invest, export, employment)…
  · macro: 198 rows buffered
  · inflation: 25 rows buffered
  · budget: 0 rows buffered
  · budget_invest: 48 rows buffered
  · foreign_invest: 15 rows buffered     ← was 0; should now be 15 (1 rollup + 14 districts)
  · export: 0 rows buffered
  · employment: 0 rows buffered
Run #N: <total> rows staged, <issues> issues. Status: awaiting_review.
```

The exact `foreign_invest` row count is the number of non-empty col-B district lines plus the rollup. Run the following query to verify:

```bash
php artisan tinker --execute="echo DB::table('import_staging_indicator_facts')->where('region_code',1735)->where('indicator_code','investment')->count();"
```

Expected: ≥ 13 (rollup + at least 12 districts).

- [ ] **Step 2: Promote**

```bash
php artisan import:promote {run_id}
```

Expected: no SQLSTATE error. `Promoted run #N: <N> indicator facts, ...`.

- [ ] **Step 3: Run import:all-regions**

```bash
php -d memory_limit=2G artisan migrate:fresh --seed
php -d memory_limit=2G artisan import:all-regions 2026
```

Expected: 14/14 `xlsx=promoted`. karakalpak `rows_promoted ≥ 220` (was 0). All previously-green regions still green.

- [ ] **Step 4: Empty commit to record smoke**

```bash
git commit --allow-empty -m "test(import): bug D smoke — karakalpak foreign_invest stages via annual-only branch"
```

---

## Self-review

**Spec coverage:**
- §3 Strategy → Tasks 1, 2, 4 (signatures + helper + branch)
- §4 SheetResolver change → Task 1
- §5.1 Layout-detection helper → Task 2
- §5.2 Rollup detection extension → Task 3
- §5.3 parseAnnualOnly method → Task 4 step 3
- §5.4 parse() entry-point → Task 4 step 4
- §7.1 Layout-detection unit → Task 2 step 1
- §7.2 End-to-end annual-only parse → Task 4 step 1
- §7.3 SheetResolver signature test → Task 1 step 1
- §7.4 Operator smoke → Task 5

**No placeholders.** All code blocks concrete. Test fixtures specify seeded rows and assertions.

**Type consistency:**
- `Worksheet $sheet`, `Spreadsheet $book` — PhpOffice classes used consistently
- `ImportContext $ctx`, `int $regionWorkbookId`, `string $filePath` — match existing parser
- `?int $districtCode` (post-SOATO migration). If `IndicatorFactDto` constructor uses `?string`, halt and report.
- `bool` return for `isAnnualOnlyLayout` and `isRollupCell`; `int` return for `parseAnnualOnly` and `emitAnnualRow` (rows-staged count)
- `Жами` is `string`; `42` is `int` (`isRollupCell` rejects via `is_string` guard)

**Naming:**
- `isAnnualOnlyLayout`, `parseAnnualOnly`, `emitAnnualRow` — verb-camelCase, matches existing `findRollupRow` / `emitEntityRows`
- Test names follow Pest "subject does X under Y" convention
