# Bug B: Region Folder Mapping Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Populate `Region->folder_name` for all 14 regions via a `REGION_FOLDER` constant in `SoatoSeeder` so `WorkbookLocator` finds the correct data folder for every region.

**Architecture:** Single seeder change. Add an immutable `REGION_FOLDER` map (SOATO int → folder string), wire it into `makeRegionRow`, and guard with a one-line consistency self-check inside `run`. `WorkbookLocator` already prefers `Region->folder_name` — no service changes.

**Tech Stack:** PHP 8.3 · Laravel 11 · Pest 3 · PostgreSQL. All commands run from `backend/`.

---

## File structure

| File | Responsibility |
|---|---|
| `backend/database/seeders/SoatoSeeder.php` | add `REGION_FOLDER` constant, use it in `makeRegionRow`, add consistency check in `run`. |
| `backend/tests/Feature/Database/SoatoSeederFolderMappingTest.php` | Pest feature test: verifies all 14 regions get the right `folder_name` and that the three region maps cover the same codes. |

---

### Task 1: Add `REGION_FOLDER` constant + wire into `makeRegionRow`

**Files:**
- Modify: `backend/database/seeders/SoatoSeeder.php`
- Create: `backend/tests/Feature/Database/SoatoSeederFolderMappingTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Database/SoatoSeederFolderMappingTest.php`:

```php
<?php

use App\Models\Region;
use Database\Seeders\SoatoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeded regions have correct folder_name for all 14 regions', function () {
    expect(SoatoSeeder::REGION_FOLDER)->toHaveCount(14);

    foreach (SoatoSeeder::REGION_FOLDER as $code => $expected) {
        expect(Region::where('code', $code)->value('folder_name'))
            ->toBe($expected, "Region {$code} folder_name");
    }
});
```

Verify the test's DB seeding bootstrap by reading `backend/tests/Pest.php` and `backend/phpunit.xml` to confirm `RefreshDatabase` runs the seeders (Laravel runs them when `--seed` is on, controlled by `DatabaseSeeder` or `TestCase::setUp`). If they do not auto-run seeders, add `protected $seed = true;` to the test class via `uses(RefreshDatabase::class, fn() => $this->seed())->in(__DIR__)` in `Pest.php`, OR insert `(new SoatoSeeder())->run();` at the top of the test. Verify the existing pest tests pass after either change.

- [ ] **Step 2: Run test to verify it fails**

```bash
cd backend && vendor/bin/pest tests/Feature/Database/SoatoSeederFolderMappingTest.php
```

Expected: FAIL with `Undefined constant SoatoSeeder::REGION_FOLDER` (or, if the constant is added but folder_name is still null, with `Expected 'X', got null`).

- [ ] **Step 3: Add the constant**

In `backend/database/seeders/SoatoSeeder.php`, insert after the existing `REGION_SORT` constant (around line 71):

```php
/**
 * SOATO region code => exact folder name on disk under data/.
 * Decoupled from sort_order because operator's folder numbering
 * differs from UI display order. Values must match disk byte-for-byte.
 */
public const REGION_FOLDER = [
    1735 => '1. Қорақалпоғистон Республикаси',
    1703 => '2. Андижон',
    1706 => '3. Бухоро',
    1708 => '4. Жиззах',
    1710 => '5. Қашқадарё',
    1712 => '6. Навоий',
    1714 => '7. Наманган',
    1718 => '8. Самарқанд',
    1722 => '9. Сурхондарё',
    1724 => '10. Сирдарё',
    1727 => '11. Тошкент вил',
    1730 => '12. Фарғона',
    1733 => '13. Хоразм',
    1726 => '14. Тошкент ш',
];
```

- [ ] **Step 4: Wire it into `makeRegionRow`**

In the same file, change line 148 from:

```php
'folder_name'   => null,
```

to:

```php
'folder_name'   => self::REGION_FOLDER[$code] ?? null,
```

- [ ] **Step 5: Run test to verify it passes**

```bash
cd backend && vendor/bin/pest tests/Feature/Database/SoatoSeederFolderMappingTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/database/seeders/SoatoSeeder.php backend/tests/Feature/Database/SoatoSeederFolderMappingTest.php
git commit -m "feat(import): seed Region->folder_name from REGION_FOLDER constant"
```

---

### Task 2: Map-consistency self-check

**Files:**
- Modify: `backend/database/seeders/SoatoSeeder.php`
- Modify: `backend/tests/Feature/Database/SoatoSeederFolderMappingTest.php`

- [ ] **Step 1: Write the failing test**

Append to `SoatoSeederFolderMappingTest.php`:

```php
test('REGION_LATIN, REGION_SORT, REGION_FOLDER all cover the same region codes', function () {
    $latin  = array_keys(SoatoSeeder::REGION_LATIN);
    $sort   = array_keys(SoatoSeeder::REGION_SORT);
    $folder = array_keys(SoatoSeeder::REGION_FOLDER);

    sort($latin); sort($sort); sort($folder);

    expect($latin)->toBe($sort);
    expect($latin)->toBe($folder);
});
```

This test already passes if all three maps are correct (which Task 1 ensures). Verify it passes:

```bash
cd backend && vendor/bin/pest tests/Feature/Database/SoatoSeederFolderMappingTest.php --filter="cover the same region codes"
```

Expected: PASS.

- [ ] **Step 2: Add a deliberate failure to confirm the test bites**

Temporarily edit `SoatoSeeder.php` to remove one entry from `REGION_FOLDER` (e.g. delete the `1733 => '13. Хоразм',` line). Run the test:

```bash
cd backend && vendor/bin/pest tests/Feature/Database/SoatoSeederFolderMappingTest.php --filter="cover the same region codes"
```

Expected: FAIL with `Expected: [...]; Got: [...]` (the arrays differ by one element).

Restore the deleted line. Re-run the test. Expected: PASS.

- [ ] **Step 3: Add a runtime self-check in `SoatoSeeder::run`**

Insert at the start of `SoatoSeeder::run()`, right after the existing file-not-found guard (around line 82), BEFORE the spreadsheet is loaded:

```php
$expectedCodes = array_keys(self::REGION_LATIN);
foreach (['REGION_SORT' => self::REGION_SORT, 'REGION_FOLDER' => self::REGION_FOLDER] as $label => $map) {
    if (array_diff_key($map, array_flip($expectedCodes)) || array_diff_key(array_flip($expectedCodes), $map)) {
        throw new \RuntimeException("SoatoSeeder::{$label} disagrees with REGION_LATIN on region codes.");
    }
}
```

- [ ] **Step 4: Verify both tests still pass**

```bash
cd backend && vendor/bin/pest tests/Feature/Database/SoatoSeederFolderMappingTest.php
```

Expected: 2/2 PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/seeders/SoatoSeeder.php backend/tests/Feature/Database/SoatoSeederFolderMappingTest.php
git commit -m "feat(import): seeder asserts REGION_LATIN/SORT/FOLDER cover same codes"
```

---

### Task 3: Disk-existence assertion (gated)

**Files:**
- Modify: `backend/tests/Feature/Database/SoatoSeederFolderMappingTest.php`

- [ ] **Step 1: Add the gated test**

Append to the test file:

```php
test('every region folder_name exists on disk when data dir is present', function () {
    $dataPath = base_path('../data');
    if (! is_dir($dataPath)) {
        $this->markTestSkipped('data/ folder not present in this environment.');
    }

    foreach (SoatoSeeder::REGION_FOLDER as $code => $folder) {
        $full = $dataPath . DIRECTORY_SEPARATOR . $folder;
        expect(is_dir($full))->toBeTrue("Missing data folder for region {$code}: {$full}");
    }
});
```

- [ ] **Step 2: Run the test**

```bash
cd backend && vendor/bin/pest tests/Feature/Database/SoatoSeederFolderMappingTest.php
```

Expected: 3/3 PASS locally (where `../data/` exists). In CI: third test skipped, 2 pass.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Database/SoatoSeederFolderMappingTest.php
git commit -m "test(import): disk-existence check for region folder_name (gated)"
```

---

### Task 4: End-to-end smoke

**Files:** none (operator verification).

- [ ] **Step 1: Fresh DB + full import**

```bash
cd backend && php artisan migrate:fresh --seed
php artisan import:all-regions 2026
```

- [ ] **Step 2: Verify the summary table**

Expected: 14/14 xlsx rows with `rows_staged > 0`. Specifically, all of:

- `karakalpak` — was 0, expect > 200
- `tashkent_city` — was 0, expect > 200
- `tashkent` — was 0, expect > 200
- `fergana` — was 0, expect > 200
- `khorezm` — was 0, expect > 200

Previously-green regions (andijan, bukhara, jizzakh, navoi, namangan, samarkand, surkhandarya, sirdarya, kashkadarya) must still promote.

- [ ] **Step 3: Spot-check one freshly-importing region**

```bash
php artisan tinker --execute="echo json_encode(DB::table('indicator_facts')->where('region_code', 1733)->select('district_code','indicator_code','period')->limit(5)->get(), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);"
```

Expected: 5 rows of khorezm indicator facts with non-null `district_code` and indicator codes like `gdp`, `industry`, etc.

- [ ] **Step 4: Empty commit to record smoke success**

```bash
git commit --allow-empty -m "test(import): bug B smoke — all 14 regions stage data"
```

---

## Self-review

**Spec coverage:**
- §3 Strategy → Task 1
- §4 Constant → Task 1 step 3
- §5 Seeder change → Task 1 step 4
- §6 Map consistency self-check → Task 2 step 3
- §8.1 Unit test → Task 1 step 1 + Task 2 step 1
- §8.2 Disk-existence assertion → Task 3
- §8.3 Smoke → Task 4

**No placeholders.** All code blocks are concrete.

**Type consistency:** `REGION_FOLDER` is `array<int, string>` everywhere. `Region->folder_name` is `?string`. `Region->code` is `int`. Method names: `run`, `makeRegionRow`, `setCommand` — all match Laravel 11 `Seeder` API.

**Naming:** Constant `REGION_FOLDER` (singular) matches existing `REGION_LATIN` and `REGION_SORT` style.
