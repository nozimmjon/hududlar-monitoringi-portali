# Macro Sheet + Header Detection Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace iterator-based row scanning in `SheetResolver::scoreSheet` and `HeaderDetector::detect` with `Worksheet::toArray()` so merged title/unit rows in xlsx workbooks are no longer silently skipped. Resolves 4 blocker issues in macro import (1 sheet_missing + 3 header_not_found).

**Architecture:** Two file edits + two new tests. Both services pre-read the sheet into a 2D PHP array via `toArray(null, true, true, false)` and iterate that array instead of `getRowIterator + getCellIterator` (which has buggy behavior with merged cells under loop access).

**Tech Stack:** Laravel 11 + Pest 3 + PhpOffice/PhpSpreadsheet.

**Spec:** `docs/superpowers/specs/2026-05-13-macro-sheet-header-detection-design.md`

**Working directory:** `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali`. `php artisan` / `vendor/bin/pest` from `backend/`. `git` from project root.

---

## File Structure

| File | Action |
|---|---|
| `backend/app/Services/Import/SheetResolver.php` | modify `scoreSheet` |
| `backend/app/Services/Import/HeaderDetector.php` | modify `detect` |
| `backend/tests/Feature/Import/SheetResolverMergedCellsTest.php` | new — regression for merged-cell title rows |
| `backend/tests/Feature/Import/HeaderDetectorMergedCellsTest.php` | new — regression for merged-cell unit rows |

---

### Task 1: Rewrite `SheetResolver::scoreSheet` to use `toArray`

**Files:**
- Modify: `backend/app/Services/Import/SheetResolver.php`

- [ ] **Step 1: Read current implementation**

Open `backend/app/Services/Import/SheetResolver.php`. Find the private `scoreSheet` method at the bottom:

```php
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
```

- [ ] **Step 2: Replace the method body**

Use `Edit` to replace the existing `scoreSheet` method body with:

```php
private function scoreSheet(Worksheet $sheet, array $signatures): int
{
    $rows = $sheet->toArray(null, true, true, false);
    $score = 0;
    $limit = min(8, count($rows));

    for ($i = 0; $i < $limit; $i++) {
        $rowText = '';
        foreach ($rows[$i] as $cell) {
            if (is_string($cell)) {
                $rowText .= ' ' . $cell;
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
```

Differences vs the original:
- Pre-reads sheet via `toArray(null, true, true, false)` (nullValue=null, calculateFormulas=true, formatData=true, returnCellRef=false).
- Iterates 0-indexed PHP array.
- Scan range 5 → 8 rows (covers signature tokens like `ЯҲМ` that live on row 6 of `1.1. ЯҲМ`).

- [ ] **Step 3: Sanity check**

```bash
cd backend && php -l app/Services/Import/SheetResolver.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add backend/app/Services/Import/SheetResolver.php
git commit -m "fix(import): SheetResolver scoreSheet uses toArray to read merged cells"
```

---

### Task 2: Rewrite `HeaderDetector::detect` to use `toArray`

**Files:**
- Modify: `backend/app/Services/Import/HeaderDetector.php`

- [ ] **Step 1: Read current implementation**

Open `backend/app/Services/Import/HeaderDetector.php`. The existing `detect` method body is:

```php
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
```

- [ ] **Step 2: Replace the method body**

Use `Edit` to replace the body of `detect` with:

```php
public function detect(Worksheet $sheet, ImportContext $ctx, int $regionWorkbookSheetId): ?int
{
    $cached = RegionWorkbookSheet::find($regionWorkbookSheetId);
    if ($cached && $cached->header_row) {
        return $cached->header_row;
    }

    $allRows = $sheet->toArray(null, true, true, false);
    $hasUnitAbove = false;

    $limit = min(15, count($allRows));
    for ($i = 0; $i < $limit; $i++) {
        $rowText = '';
        foreach ($allRows[$i] as $cell) {
            if (is_string($cell)) {
                $rowText .= ' ' . $cell;
            }
        }

        if (mb_stripos($rowText, 'ҳажм') !== false || mb_stripos($rowText, 'млрд.сўм') !== false) {
            $hasUnitAbove = true;
        }

        $colA = $allRows[$i][0] ?? null;
        if ($hasUnitAbove && (is_int($colA) || (is_string($colA) && ctype_digit(trim($colA))))) {
            $rowNumber = $i + 1;
            if ($cached) {
                $cached->update(['header_row' => $rowNumber]);
            }
            return $rowNumber;
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
```

Differences vs original:
- Pre-reads via `toArray(null, true, true, false)`.
- `$colA` comes from `$allRows[$i][0]` (zero-indexed PHP array column 0 = spreadsheet column A).
- Returns `$i + 1` instead of `$row` so callers still get 1-indexed row numbers (consumers like `MacroModuleParser` do `$sheet->getCell([col, $headerRow])` which expects 1-indexed).
- Cache write also uses the 1-indexed `$rowNumber`.

- [ ] **Step 3: Sanity check**

```bash
cd backend && php -l app/Services/Import/HeaderDetector.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add backend/app/Services/Import/HeaderDetector.php
git commit -m "fix(import): HeaderDetector uses toArray to read merged unit rows"
```

---

### Task 3: Regression test for `SheetResolver` merged-row signatures

**Files:**
- Create: `backend/tests/Feature/Import/SheetResolverMergedCellsTest.php`

This test builds an in-memory `Spreadsheet` with a merged title row containing a signature substring, drives `SheetResolver::resolve`, and asserts the sheet is returned (signature match succeeds despite the merged-cell layout).

- [ ] **Step 1: Write the test**

Create `backend/tests/Feature/Import/SheetResolverMergedCellsTest.php`:

```php
<?php

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use App\Services\Import\SheetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'sort_order' => 2,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('reporting_years')->insert([
        'year' => 2026, 'is_current' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        'code' => 'macro', 'label' => 'Макро', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

test('scoreSheet finds rollup signature in merged title row', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('1.1. ЯҲМ');
    $sheet->setCellValue('J1', '1.1-жадвал');
    // Merged title row contains one of the rollup signatures.
    $sheet->setCellValue('A2', '2026 йил Андижон вилояти бўйича асосий иқтисодий кўрсаткичларнинг прогнози');
    $sheet->mergeCells('A2:J2');
    // Unrelated subsequent rows.
    $sheet->setCellValue('A4', '№');
    $sheet->setCellValue('B4', 'Кўрсаткичлар');
    $sheet->setCellValue('A6', 1);
    $sheet->setCellValue('B6', 'ЯҲМ');

    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => ImportRunStatus::Parsing,
        'started_at'   => now(),
    ]);
    $ctx = new ImportContext(run: $run, region: $region, year: 2026, dataPath: null);

    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'macro')->value('id'),
        'file_name'         => 'test.xlsx',
        'file_path'         => '/tmp/test.xlsx',
        'last_seen_at'      => now(),
    ]);

    $issues = new IssueCollector();
    $resolver = new SheetResolver($issues);
    $resolved = $resolver->resolve($ctx, $book, $rwb->id, 'macro', 'rollup');

    expect($resolved)->not->toBeNull();
    expect($resolved->getTitle())->toBe('1.1. ЯҲМ');
    expect($issues->bufferedCount())->toBe(0);
});
```

- [ ] **Step 2: Run, expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/SheetResolverMergedCellsTest.php
```

Expected: 1 test pass.

If the test fails because `RegionWorkbook` requires extra columns, inspect the migration `2026_05_05_000005_create_region_workbooks_table.php` (or similar) and add missing required fields to the `RegionWorkbook::create` call. Do not change the service code.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Import/SheetResolverMergedCellsTest.php
git commit -m "test(import): SheetResolver regression for merged-cell title rows"
```

---

### Task 4: Regression test for `HeaderDetector` merged-unit rows

**Files:**
- Create: `backend/tests/Feature/Import/HeaderDetectorMergedCellsTest.php`

- [ ] **Step 1: Write the test**

Create `backend/tests/Feature/Import/HeaderDetectorMergedCellsTest.php`:

```php
<?php

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Models\Region;
use App\Models\RegionWorkbook;
use App\Models\RegionWorkbookSheet;
use App\Services\Import\HeaderDetector;
use App\Services\Import\ImportContext;
use App\Services\Import\IssueCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'sort_order' => 2,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('reporting_years')->insert([
        'year' => 2026, 'is_current' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        'code' => 'macro', 'label' => 'Макро', 'sort_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

test('detect returns the row where col A is digit after merged unit row', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('1.2. Саноат');
    $sheet->setCellValue('J1', '1.2-жадвал');
    $sheet->setCellValue('A2', '2026 йил Андижон вилояти бўйича Саноат прогнози');
    $sheet->mergeCells('A2:J2');
    $sheet->setCellValue('A4', '№');
    $sheet->setCellValue('B4', 'Туман/шаҳар номи');
    $sheet->setCellValue('C4', 'Саноат маҳсулотларини ишлаб чиқариш');
    // Merged unit row.
    $sheet->setCellValue('C5', 'ҳажми (млрд.сўм)');
    $sheet->mergeCells('C5:J5');
    // Regional rollup row (no leading number — should be skipped).
    $sheet->setCellValue('B7', 'Андижон вилояти');
    // First district row with col A = '1' as string (Excel often stores ints as strings here).
    $sheet->setCellValue('A8', '1');
    $sheet->setCellValue('B8', 'Андижон шаҳри');

    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => ImportRunStatus::Parsing,
        'started_at'   => now(),
    ]);
    $ctx = new ImportContext(run: $run, region: $region, year: 2026, dataPath: null);

    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'macro')->value('id'),
        'file_name'         => 'test.xlsx',
        'file_path'         => '/tmp/test.xlsx',
        'last_seen_at'      => now(),
    ]);

    $rws = RegionWorkbookSheet::create([
        'region_workbook_id' => $rwb->id,
        'sheet_name'         => '1.2. Саноат',
        'logical_kind'       => 'district_industry',
    ]);

    $issues = new IssueCollector();
    $detector = new HeaderDetector($issues);
    $headerRow = $detector->detect($sheet, $ctx, $rws->id);

    expect($headerRow)->toBe(8);
    expect($issues->bufferedCount())->toBe(0);
});

test('detect emits issue when no digit row follows unit marker', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('empty');
    $sheet->setCellValue('A1', 'a sheet without unit markers');

    $region = Region::where('code', 1703)->first();
    $run = ImportRun::create([
        'region_code'  => 1703,
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => ImportRunStatus::Parsing,
        'started_at'   => now(),
    ]);
    $ctx = new ImportContext(run: $run, region: $region, year: 2026, dataPath: null);

    $rwb = RegionWorkbook::create([
        'region_id'         => $region->id,
        'reporting_year_id' => DB::table('reporting_years')->where('year', 2026)->value('id'),
        'module_id'         => DB::table('modules')->where('code', 'macro')->value('id'),
        'file_name'         => 'test.xlsx',
        'file_path'         => '/tmp/test.xlsx',
        'last_seen_at'      => now(),
    ]);

    $rws = RegionWorkbookSheet::create([
        'region_workbook_id' => $rwb->id,
        'sheet_name'         => 'empty',
        'logical_kind'       => 'district_industry',
    ]);

    $issues = new IssueCollector();
    $detector = new HeaderDetector($issues);
    $headerRow = $detector->detect($sheet, $ctx, $rws->id);

    expect($headerRow)->toBeNull();
    expect($issues->bufferedCount())->toBeGreaterThan(0);
});
```

- [ ] **Step 2: Run, expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/HeaderDetectorMergedCellsTest.php
```

Expected: 2 tests pass.

If `RegionWorkbookSheet::create` errors on missing columns, inspect the migration and supply additional fields. Do not change the service code.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Import/HeaderDetectorMergedCellsTest.php
git commit -m "test(import): HeaderDetector regression for merged-unit rows"
```

---

### Task 5: Operator smoke run

**Files:** none.

- [ ] **Step 1: Fresh DB**

```bash
cd backend && php artisan migrate:fresh --seed
```

Expected: `Seeded 14 regions and 208 districts.`

- [ ] **Step 2: Run Andijan import**

```bash
cd backend && php artisan import:region andijan 2026 2>&1 | tail -10
```

Expected:
- Macro module logs more rows than the prior 6 (rollup + 16 districts × 3 sub-sectors = ~50 rows).
- Final line: `Run #N: <rows> rows staged, <issues> issues. Status: awaiting_review.`
- No blocker issues should remain for macro module.

- [ ] **Step 3: Verify zero macro blocker issues**

```bash
cd backend && php artisan tinker --execute "
\$run = App\Models\ImportRun::latest('id')->first();
echo 'run status: ' . \$run->status->value . PHP_EOL;
foreach (DB::table('data_quality_issues')
    ->where('import_run_id', \$run->id)
    ->where('severity', 'blocker')
    ->get() as \$i) {
    echo '  ' . \$i->issue_kind . ' | ' . \$i->detail . PHP_EOL;
}
"
```

Expected: `run status: awaiting_review`. No `sheet_missing` or `header_not_found` lines for macro module.

- [ ] **Step 4: Promote and verify rows**

```bash
cd backend && php artisan tinker --execute "
\$run = App\Models\ImportRun::latest('id')->first();
echo \$run->id;
" | xargs -I {} php artisan import:promote {}
```

If `xargs` is unavailable on Windows shell, run directly:

```bash
cd backend && php artisan tinker --execute "echo App\Models\ImportRun::latest('id')->first()->id;"
# Note the printed run id (e.g. 1), then:
cd backend && php artisan import:promote 1
```

Expected: `Promoted N indicator_facts, M food_balance, K warehouses.` (or similar success message). `App\Models\IndicatorFact::where('region_code', 1703)->count()` > 0.

- [ ] **Step 5: Run full all-regions batch**

```bash
cd backend && php artisan import:all-regions 2026 2>&1 | tail -25
```

Expected: 14-row summary; most regions show `xlsx=promoted`, `tasks=ok`. Some regions may still have unrelated issues (district-name fuzzy match handles the common variants per the prior spec, but new region-specific quirks could surface). Report any new failures.

- [ ] **Step 6: Final commit if smoke surfaced any tweak**

If the smoke run revealed a regression that requires a small tweak in the same service files (e.g. a new sheet signature for a non-Andijan region), commit:

```bash
cd backend && git add -A
git commit -m "fix(import): smoke-run touch-ups"
```

If clean, skip.

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| §3 Strategy (toArray pre-read in both services) | Tasks 1, 2 |
| §4 SheetResolver scoreSheet rewrite | Task 1 |
| §5 HeaderDetector detect rewrite | Task 2 |
| §6.1 SheetResolver merged-cell regression | Task 3 |
| §6.2 HeaderDetector merged-cell regression | Task 4 |
| §7 Files touched | each task |
| §8 Operator smoke (migrate:fresh + import:region + promote + import:all-regions) | Task 5 |

**Placeholder scan:** no TBD/handwave. Every step has concrete code or commands.

**Type/name consistency:**

- Method `scoreSheet(Worksheet, array): int` — signature unchanged from current.
- Method `detect(Worksheet, ImportContext, int): ?int` — signature unchanged from current.
- `toArray(null, true, true, false)` arg order: `nullValue, calculateFormulas, formatData, returnCellRef` — consistent across both rewrites.
- Returned row numbers stay 1-indexed (downstream consumers in MacroModuleParser et al. call `$sheet->getCell([col, $headerRow])`, which uses 1-indexed rows).
- Test fixture column names match `RegionWorkbook` and `RegionWorkbookSheet` migration columns (`region_id`, `reporting_year_id`, `module_id`, `file_name`, `file_path`, `last_seen_at`, `sheet_name`, `logical_kind`).
