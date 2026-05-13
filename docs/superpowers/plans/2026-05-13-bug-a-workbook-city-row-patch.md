# Bug A: Workbook City-Row Patch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add ` ш.` suffix to ambiguous bare-city rows in all 14 regions' xlsx workbooks via a re-runnable artisan command so `DistrictResolver` no longer collapses city + district to the same SOATO code.

**Architecture:** A single artisan command, `data:patch-city-rows`, scans each region's `data/` folder. For each district-listing sheet, it overwrites the topmost bare-city row with the canonical `'{Name} ш.'` form from the `districts` table. `--dry-run` reports without saving; `--region=` whitelists regions.

**Tech Stack:** PHP 8.3 · Laravel 11 · PhpOffice/PhpSpreadsheet · Pest 3 · PostgreSQL (for `districts` table lookups). All commands run from `backend/`.

---

## File structure

| File | Responsibility |
|---|---|
| `backend/app/Console/Commands/PatchWorkbookCityRows.php` | Artisan command. Iterates regions, classifies district sheets, patches col B. |
| `backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php` | Pest feature test. In-memory spreadsheet + tmp xlsx for the dry-run path. |

---

### Task 1: Command skeleton + dependency injection

**Files:**
- Create: `backend/app/Console/Commands/PatchWorkbookCityRows.php`
- Create: `backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('command is registered as data:patch-city-rows', function () {
    $exitCode = Artisan::call('data:patch-city-rows', ['--dry-run' => true, '--region' => ['nonexistent_slug']]);
    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Patched 0 row(s)');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php
```

Expected: FAIL with `Command "data:patch-city-rows" is not defined`.

- [ ] **Step 3: Implement minimal command**

```php
<?php
// backend/app/Console/Commands/PatchWorkbookCityRows.php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PatchWorkbookCityRows extends Command
{
    protected $signature = 'data:patch-city-rows
                            {--region=* : Restrict to listed region slugs (e.g. kashkadarya). Default = all 14.}
                            {--dry-run : Print report without saving.}';

    protected $description = 'Append " ш." to ambiguous bare-city rows in region xlsx workbooks.';

    public function handle(): int
    {
        $this->info('Patched 0 row(s) across 0 xlsx file(s) in 0 region(s).');
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/PatchWorkbookCityRows.php backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php
git commit -m "feat(import): scaffold data:patch-city-rows artisan command"
```

---

### Task 2: District-sheet classification + city map

**Files:**
- Modify: `backend/app/Console/Commands/PatchWorkbookCityRows.php`
- Modify: `backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php`

- [ ] **Step 1: Write the failing test**

Append this test to `PatchWorkbookCityRowsTest.php`:

```php
use App\Console\Commands\PatchWorkbookCityRows;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

test('isDistrictSheet recognizes Туман col B in first 6 rows', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('1.5');
    $sheet->setCellValue('A4', '№');
    $sheet->setCellValue('B4', 'Туман/шаҳар номи');

    $rows = $sheet->toArray(null, true, true, false);
    $cmd = new PatchWorkbookCityRows();
    expect(invade($cmd)->isDistrictSheet($rows))->toBeTrue();
});

test('isDistrictSheet returns false for header-only sheets', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('other');
    $sheet->setCellValue('A1', 'unrelated');

    $rows = $sheet->toArray(null, true, true, false);
    $cmd = new PatchWorkbookCityRows();
    expect(invade($cmd)->isDistrictSheet($rows))->toBeFalse();
});
```

This uses `spatie/invade` to call the private method. Install via `composer require --dev spatie/invade` if not already present (check `composer.json` first).

- [ ] **Step 2: Verify the test fails**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php
```

Expected: FAIL (`Method isDistrictSheet does not exist` or undefined function `invade`).

If `invade` is missing, first run:

```bash
cd backend && composer require --dev spatie/invade
```

- [ ] **Step 3: Implement the method**

Add to `PatchWorkbookCityRows.php`:

```php
private function isDistrictSheet(array $rows): bool
{
    $limit = min(6, count($rows));
    for ($i = 0; $i < $limit; $i++) {
        $b = $rows[$i][1] ?? null;
        if (is_string($b) && mb_stripos($b, 'туман') !== false) {
            return true;
        }
    }
    return false;
}
```

- [ ] **Step 4: Run tests**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php
```

Expected: 3/3 PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/PatchWorkbookCityRows.php backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php backend/composer.json backend/composer.lock
git commit -m "feat(import): isDistrictSheet heuristic for col B Туман match"
```

---

### Task 3: City-form derivation from DB

**Files:**
- Modify: `backend/app/Console/Commands/PatchWorkbookCityRows.php`
- Modify: `backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php`

- [ ] **Step 1: Write the failing test**

Append:

```php
use App\Models\District;
use Illuminate\Support\Facades\DB;

test('cityFormsForRegion returns bare + full + normalized variants', function () {
    DB::table('regions')->insert([
        'code' => 1710, 'name_short' => 'Қашқадарё', 'name_full' => 'Қашқадарё вилояти',
        'name_latin' => 'kashkadarya', 'sort_order' => 5,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('districts')->insert([
        ['code' => 1710401, 'region_id' => 1, 'region_code' => 1710, 'name_short' => 'Қарши ш.', 'name_full' => 'Қарши шаҳри', 'kind' => 'city', 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 1710405, 'region_id' => 1, 'region_code' => 1710, 'name_short' => 'Шаҳрисабз ш.', 'name_full' => 'Шаҳрисабз шаҳри', 'kind' => 'city', 'sort_order' => 16, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 1710224, 'region_id' => 1, 'region_code' => 1710, 'name_short' => 'Қарши т.', 'name_full' => 'Қарши тумани', 'kind' => 'district', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $cmd = new PatchWorkbookCityRows();
    $forms = invade($cmd)->cityFormsForRegion(1710);

    expect($forms)->toHaveCount(2);
    expect($forms[0]['full'])->toBe('Қарши ш.');
    expect($forms[0]['bare'])->toBe('Қарши');
    expect($forms[0]['bareNorm'])->toBe('қарши');
    expect($forms[0]['fullNorm'])->toBe('қарши ш.');
});
```

- [ ] **Step 2: Verify test fails**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php --filter="cityFormsForRegion"
```

Expected: FAIL — method missing.

- [ ] **Step 3: Implement method**

Add to `PatchWorkbookCityRows.php`:

```php
use App\Models\District;
use App\Support\Import\DistrictNameNormalizer;

private function cityFormsForRegion(int $regionCode): array
{
    return District::query()
        ->where('region_code', $regionCode)
        ->where('kind', 'city')
        ->orderBy('sort_order')
        ->get(['name_short'])
        ->map(function ($city) {
            $full = trim($city->name_short);
            $bare = preg_replace('/ ш\.$/u', '', $full);
            return [
                'bare'     => $bare,
                'full'     => $full,
                'bareNorm' => DistrictNameNormalizer::normalize($bare),
                'fullNorm' => DistrictNameNormalizer::normalize($full),
            ];
        })
        ->values()
        ->all();
}
```

- [ ] **Step 4: Run test**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php --filter="cityFormsForRegion"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/PatchWorkbookCityRows.php backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php
git commit -m "feat(import): cityFormsForRegion derives bare/full/norm tuples"
```

---

### Task 4: Sheet patcher (topmost-bare rule)

**Files:**
- Modify: `backend/app/Console/Commands/PatchWorkbookCityRows.php`
- Modify: `backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php`

- [ ] **Step 1: Write the failing test**

Append:

```php
test('patchSheet rewrites topmost bare-city row to canonical city full form', function () {
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('1.5');
    $sheet->setCellValue('A4', '№');
    $sheet->setCellValue('B4', 'Туман/шаҳар номи');
    $sheet->setCellValue('B7', 'Қашқадарё вилояти');
    $sheet->setCellValue('A8', '1');
    $sheet->setCellValue('B8', 'Қарши ');           // bare, will be patched
    $sheet->setCellValue('A9', '2');
    $sheet->setCellValue('B9', 'Шахрисабз ш.');    // city already marked (orthography variant — leave alone)
    $sheet->setCellValue('A13', '6');
    $sheet->setCellValue('B13', 'Қарши ');         // bare again — district row, leave alone

    $cityForms = [
        ['bare' => 'Қарши', 'full' => 'Қарши ш.', 'bareNorm' => 'қарши', 'fullNorm' => 'қарши ш.'],
        ['bare' => 'Шаҳрисабз', 'full' => 'Шаҳрисабз ш.', 'bareNorm' => 'шаҳрисабз', 'fullNorm' => 'шаҳрисабз ш.'],
    ];

    $cmd = new PatchWorkbookCityRows();
    $patches = invade($cmd)->patchSheet($sheet, $cityForms);

    expect($patches)->toHaveCount(1);
    expect($patches[0]['row'])->toBe(8);
    expect($patches[0]['old'])->toBe('Қарши ');
    expect($patches[0]['new'])->toBe('Қарши ш.');
    expect($sheet->getCell('B8')->getValue())->toBe('Қарши ш.');
    expect($sheet->getCell('B9')->getValue())->toBe('Шахрисабз ш.');
    expect($sheet->getCell('B13')->getValue())->toBe('Қарши ');
});
```

- [ ] **Step 2: Verify test fails**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php --filter="patchSheet"
```

Expected: FAIL — method missing.

- [ ] **Step 3: Implement method**

Add to `PatchWorkbookCityRows.php`:

```php
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

private function patchSheet(Worksheet $sheet, array $cityForms): array
{
    $rows = $sheet->toArray(null, true, true, false);

    $colBNorm = [];
    for ($i = 6; $i < count($rows); $i++) {
        $val = $rows[$i][1] ?? null;
        if (! is_string($val)) continue;
        $trimmed = trim($val);
        if ($trimmed === '') continue;
        $colBNorm[$i + 1] = DistrictNameNormalizer::normalize($trimmed);
    }

    $patches = [];
    foreach ($cityForms as $cf) {
        $bareRows = array_keys(array_filter($colBNorm, fn($v) => $v === $cf['bareNorm']));
        $fullRows = array_keys(array_filter($colBNorm, fn($v) => $v === $cf['fullNorm']));

        if (count($fullRows) > 0) continue;
        if (count($bareRows) === 0) continue;

        $patchRow = min($bareRows);
        $oldValue = $sheet->getCell([2, $patchRow])->getValue();
        $sheet->setCellValue([2, $patchRow], $cf['full']);
        $colBNorm[$patchRow] = $cf['fullNorm'];

        $patches[] = ['row' => $patchRow, 'old' => $oldValue, 'new' => $cf['full']];
    }
    return $patches;
}
```

- [ ] **Step 4: Run test**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php --filter="patchSheet"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/PatchWorkbookCityRows.php backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php
git commit -m "feat(import): patchSheet rewrites topmost bare-city row"
```

---

### Task 5: Region folder resolver

**Files:**
- Modify: `backend/app/Console/Commands/PatchWorkbookCityRows.php`
- Modify: `backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php`

- [ ] **Step 1: Write the failing test**

Append:

```php
use App\Models\Region;

test('resolveRegionFolder uses Region->folder_name when set, falls back to sort_order + name_short', function () {
    DB::table('regions')->insert([
        ['code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти', 'name_latin' => 'andijan', 'sort_order' => 2, 'has_districts' => true, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 1735, 'name_short' => 'Қорақалпоғистон', 'name_full' => 'Қорақалпоғистон Республикаси', 'name_latin' => 'karakalpak', 'sort_order' => 1, 'folder_name' => '1. Қорақалпоғистон Республикаси', 'has_districts' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $cmd = new PatchWorkbookCityRows();

    $andijan = Region::where('code', 1703)->first();
    expect(invade($cmd)->regionFolderName($andijan))->toBe('2. Андижон');

    $kkalp = Region::where('code', 1735)->first();
    expect(invade($cmd)->regionFolderName($kkalp))->toBe('1. Қорақалпоғистон Республикаси');
});
```

- [ ] **Step 2: Verify failure**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php --filter="resolveRegionFolder"
```

Expected: FAIL.

- [ ] **Step 3: Implement**

Add to `PatchWorkbookCityRows.php`:

```php
use App\Models\Region;

private function regionFolderName(Region $region): string
{
    if (! empty($region->folder_name)) {
        return $region->folder_name;
    }
    return sprintf('%d. %s', $region->sort_order, $region->name_short);
}
```

- [ ] **Step 4: Run test**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php --filter="resolveRegionFolder"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/PatchWorkbookCityRows.php backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php
git commit -m "feat(import): regionFolderName mirrors WorkbookLocator fallback"
```

---

### Task 6: `handle` wires everything + reporting

**Files:**
- Modify: `backend/app/Console/Commands/PatchWorkbookCityRows.php`
- Modify: `backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php`

- [ ] **Step 1: Write the failing test**

Append:

```php
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Illuminate\Support\Facades\File;

test('handle patches a tmp xlsx end-to-end and reports correctly', function () {
    // Seed one region + cities
    DB::table('regions')->insert([
        'code' => 1710, 'name_short' => 'Қашқадарё', 'name_full' => 'Қашқадарё вилояти',
        'name_latin' => 'kashkadarya', 'sort_order' => 5, 'has_districts' => true,
        'folder_name' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('districts')->insert([
        ['code' => 1710401, 'region_id' => 1, 'region_code' => 1710, 'name_short' => 'Қарши ш.', 'name_full' => 'Қарши шаҳри', 'kind' => 'city', 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 1710224, 'region_id' => 1, 'region_code' => 1710, 'name_short' => 'Қарши т.', 'name_full' => 'Қарши тумани', 'kind' => 'district', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Build a tmp data dir + xlsx
    $tmpDataDir = sys_get_temp_dir() . '/patch_city_rows_' . uniqid();
    $regionDir = $tmpDataDir . DIRECTORY_SEPARATOR . '5. Қашқадарё';
    File::makeDirectory($regionDir, 0777, true);

    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('1.5');
    $sheet->setCellValue('B4', 'Туман/шаҳар номи');
    $sheet->setCellValue('B8', 'Қарши ');
    $xlsxPath = $regionDir . DIRECTORY_SEPARATOR . 'macro.xlsx';
    (new XlsxWriter($book))->save($xlsxPath);
    $book->disconnectWorksheets();
    unset($book);

    config(['import.data_path' => $tmpDataDir]);

    Artisan::call('data:patch-city-rows', ['--region' => ['kashkadarya']]);
    $output = Artisan::output();

    expect($output)->toContain("row 8 | 'Қарши ' → 'Қарши ш.'");
    expect($output)->toContain('Patched 1 row(s) across 1 xlsx file(s) in 1 region(s).');

    $reloaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
    expect($reloaded->getActiveSheet()->getCell('B8')->getValue())->toBe('Қарши ш.');

    File::deleteDirectory($tmpDataDir);
});
```

- [ ] **Step 2: Verify failure**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php --filter="handle patches a tmp xlsx"
```

Expected: FAIL.

- [ ] **Step 3: Implement `handle`**

Replace the stub `handle` in `PatchWorkbookCityRows.php` with:

```php
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

public function handle(): int
{
    $dataPath = config('import.data_path');
    if (! is_string($dataPath) || ! is_dir($dataPath)) {
        $this->error("data_path '{$dataPath}' not found or not a directory.");
        return self::FAILURE;
    }

    $regionSlugs = (array) $this->option('region');
    $dryRun = (bool) $this->option('dry-run');

    $query = Region::query()->orderBy('sort_order');
    if (! empty($regionSlugs)) {
        $query->whereIn('name_latin', $regionSlugs);
    }
    $regions = $query->get();

    $totalPatched = 0;
    $totalFiles = 0;
    $totalRegions = 0;

    foreach ($regions as $region) {
        $regionDir = $dataPath . DIRECTORY_SEPARATOR . $this->regionFolderName($region);
        if (! is_dir($regionDir)) {
            $this->line("{$region->code} {$region->name_latin}: data folder not found, skipping");
            continue;
        }

        $cityForms = $this->cityFormsForRegion($region->code);
        if (empty($cityForms)) continue;

        $regionPatchedAny = false;
        foreach (glob($regionDir . DIRECTORY_SEPARATOR . '*.xlsx') as $file) {
            $book = IOFactory::load($file);
            $dirty = false;

            foreach ($book->getAllSheets() as $sheet) {
                $rows = $sheet->toArray(null, true, true, false);
                if (! $this->isDistrictSheet($rows)) continue;

                $patches = $this->patchSheet($sheet, $cityForms);
                foreach ($patches as $p) {
                    $this->line(sprintf(
                        "%d %s | %s | %s | row %d | '%s' → '%s'",
                        $region->code,
                        $region->name_latin,
                        basename($file),
                        $sheet->getTitle(),
                        $p['row'],
                        $p['old'],
                        $p['new'],
                    ));
                    $totalPatched++;
                    $dirty = true;
                }
            }

            if ($dirty) {
                $totalFiles++;
                $regionPatchedAny = true;
                if (! $dryRun) {
                    try {
                        (new XlsxWriter($book))->save($file);
                    } catch (\Throwable $e) {
                        $this->error("Failed to save {$file}: {$e->getMessage()} (close it in Excel and re-run)");
                    }
                }
            }
            $book->disconnectWorksheets();
            unset($book);
        }

        if ($regionPatchedAny) $totalRegions++;
    }

    $this->info("Patched {$totalPatched} row(s) across {$totalFiles} xlsx file(s) in {$totalRegions} region(s).");
    return self::SUCCESS;
}
```

Also add this to `backend/config/import.php` if `data_path` is not already set:

```php
'data_path' => env('IMPORT_DATA_PATH', base_path('../data')),
```

Check the file first — if `data_path` is already defined, skip the config edit.

- [ ] **Step 4: Run all command tests**

```bash
cd backend && vendor/bin/pest tests/Feature/Console/PatchWorkbookCityRowsTest.php
```

Expected: all PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/PatchWorkbookCityRows.php backend/tests/Feature/Console/PatchWorkbookCityRowsTest.php backend/config/import.php
git commit -m "feat(import): handle iterates regions/sheets and writes patches"
```

---

### Task 7: Dry-run smoke against real kashkadarya workbook

**Files:**
- No file changes — operator-run verification.

- [ ] **Step 1: Verify dry-run reports the known-bad row**

```bash
cd backend && php artisan migrate:fresh --seed
php artisan data:patch-city-rows --region=kashkadarya --dry-run
```

Expected output includes (one line):

```
1710 kashkadarya | 1.1-1.5-жадваллар (макро).xlsx | 1.5. Бозор хизматлари | row 8 | 'Қарши ' → 'Қарши ш.'
```

And a summary:

```
Patched 1 row(s) across 1 xlsx file(s) in 1 region(s).
```

Verify the actual xlsx file mtime is unchanged:

```bash
stat -c '%Y' "../data/5. Қашқадарё/1.1-1.5-жадваллар (макро).xlsx"
# Note timestamp, re-run dry-run, confirm timestamp unchanged.
```

- [ ] **Step 2: If output diverges, debug before proceeding.**

Inspect the failing sheet:

```bash
php artisan tinker --execute='
$f = "../data/5. Қашқадарё/1.1-1.5-жадваллар (макро).xlsx";
$book = \PhpOffice\PhpSpreadsheet\IOFactory::load(realpath($f));
foreach ($book->getSheetNames() as $n) if (str_contains($n, "1.5")) { $sheet = $book->getSheetByName($n); break; }
$rows = $sheet->toArray(null, true, true, false);
for ($i=6; $i<min(25,count($rows)); $i++) echo ($i+1)."| B=".var_export($rows[$i][1] ?? null, true)."\n";
'
```

- [ ] **Step 3: No commit needed for this smoke task.**

---

### Task 8: Full patch + import smoke

**Files:**
- No file changes — operator-run verification.

- [ ] **Step 1: Apply patches (no dry-run)**

```bash
cd backend && php artisan data:patch-city-rows
```

Expected: report lists at least kashkadarya + sirdarya rows. Save succeeds for all touched xlsx.

- [ ] **Step 2: Fresh DB + re-import all regions**

```bash
php artisan migrate:fresh --seed
php artisan import:all-regions 2026
```

Expected summary table:

- `kashkadarya` row: `xlsx=promoted`, `rows_promoted > 0` (no SQLSTATE 21000 error).
- `sirdarya` row: `xlsx=promoted`, `rows_promoted > 0`.
- All previously-ok regions remain ok.
- Last line: `Run complete. ≥14/14 xlsx ok…` (numbers may improve for the 5 zero-row regions too, but those are Bug B scope).

- [ ] **Step 3: Verify no duplicate tuples remain in staging for run #1 (kashkadarya)**

```bash
php artisan tinker --execute="
echo json_encode(DB::select(\"SELECT region_code, district_code, indicator_code, period, COUNT(*) AS n FROM import_staging_indicator_facts WHERE region_code IN (1710, 1724) GROUP BY 1,2,3,4 HAVING COUNT(*) > 1 LIMIT 10\"), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
"
```

Expected: `[]` (empty array).

- [ ] **Step 4: Commit a note recording smoke success**

```bash
git commit --allow-empty -m "test(import): bug A patch smoke — kashkadarya + sirdarya promote ok"
```

---

## Self-review

**Spec coverage:**
- §3 Strategy → Tasks 1–6
- §4 Command signature → Task 1 (signature) + Task 6 (handle wiring)
- §5 Algorithm (cityForms / patchSheet / isDistrictSheet / regionFolderName) → Tasks 2–6
- §7.1 Unit test → Task 4 + Task 6
- §7.2 Dry-run smoke → Task 7
- §7.3 End-to-end smoke → Task 8
- §8 Risk (Windows file lock) → Task 6 step 3 (try/catch around `save`)
- §8 Risk (region folder missing) → Task 6 step 3 (`is_dir` check + skip)

**No placeholders.** All code blocks are concrete.

**Type consistency:** `cityForms` is `array<int, array{bare:string, full:string, bareNorm:string, fullNorm:string}>` everywhere. `patchSheet` returns `array<int, array{row:int, old:string, new:string}>`. `regionFolderName` returns `string`. `isDistrictSheet` returns `bool`.

**Dependency note:** `spatie/invade` is installed in Task 2 step 2 if not already present. Task 6 stops needing `invade` because it tests via the artisan CLI, but earlier tasks rely on it.
