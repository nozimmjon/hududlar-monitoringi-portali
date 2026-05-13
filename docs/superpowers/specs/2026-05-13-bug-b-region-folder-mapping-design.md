# Bug B: region folder mapping

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Populate `Region->folder_name` for all 14 regions via a `REGION_FOLDER` constant in `SoatoSeeder`, so `WorkbookLocator` finds the correct data folder for every region. Eliminates silent 0-rows-staged false success for karakalpak, tashkent_city, tashkent, fergana, khorezm.

---

## 1. Goal

`php artisan import:all-regions 2026` reports `xlsx=promoted, rows_staged=0` for 5 regions because `WorkbookLocator::resolveRegionFolder` constructs the wrong path. The fallback formula `sprintf('%d. %s', $sort_order, $name_short)` produces:

| Region        | Constructed                  | Actual folder on disk           |
|---------------|------------------------------|----------------------------------|
| karakalpak    | `1. Қорақалпоғистон`         | `1. Қорақалпоғистон Республикаси` |
| tashkent_city | `11. Тошкент ш.`             | `14. Тошкент ш`                  |
| tashkent      | `12. Тошкент вил.`           | `11. Тошкент вил`                |
| fergana       | `13. Фарғона`                | `12. Фарғона`                    |
| khorezm       | `14. Хоразм`                 | `13. Хоразм`                     |

The seeder leaves `Region->folder_name = null` for every region, so the fallback always runs. `WorkbookLocator` already prefers `Region->folder_name` when set (see `backend/app/Services/Import/WorkbookLocator.php:47-54`). Setting it explicitly fixes the lookup.

After this change, all 14 regions stage their workbooks correctly.

## 2. Non-goals

- No change to `WorkbookLocator` source. The `folder_name`-first branch already exists.
- No change to xlsx parsers, schema, migrations, models, Livewire pages, or views.
- No disk folder renames.
- No change to `Region->sort_order` (UI display order) — `sort_order` and folder numbering are intentionally decoupled.
- No fix for the kashkadarya tasks-side unique violation (separate Bug C).

## 3. Strategy

Add a `REGION_FOLDER` constant to `SoatoSeeder` keyed by SOATO region code (int) → folder name (string, exact match to disk). Use it in `makeRegionRow` to populate `folder_name`. All 14 regions get an explicit entry; the existing fallback in `WorkbookLocator` becomes a safety net for any future region missing from the constant (which would also be missing from `REGION_LATIN` and `REGION_SORT` — failures would be loud).

## 4. Constant

```php
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

The values must match disk exactly — copied character-for-character from the output of `ls ../data/`. No leading/trailing whitespace. Tashkent city folder has no trailing dot on `ш` (one-letter folder name, operator chose it that way).

## 5. Seeder change

In `SoatoSeeder::makeRegionRow`, replace:

```php
'folder_name' => null,
```

with:

```php
'folder_name' => self::REGION_FOLDER[$code] ?? null,
```

Place `REGION_FOLDER` next to `REGION_LATIN` and `REGION_SORT` at the top of the class for readability.

## 6. Map consistency self-check

Append to `SoatoSeeder::run()` after the foreach loop that processes the xlsx:

```php
$expectedCodes = array_keys(self::REGION_LATIN);
foreach ([self::REGION_SORT, self::REGION_FOLDER] as $map) {
    if (array_diff_key($map, array_flip($expectedCodes))) {
        throw new \RuntimeException('SoatoSeeder constant maps disagree on region codes.');
    }
    if (array_diff_key(array_flip($expectedCodes), $map)) {
        throw new \RuntimeException('SoatoSeeder constant map missing a region code.');
    }
}
```

This guards against a future contributor adding a region to one map and forgetting the others.

## 7. Files

| File | Action |
|---|---|
| `backend/database/seeders/SoatoSeeder.php` | modify: add `REGION_FOLDER` constant, use it in `makeRegionRow`, add self-check in `run` |
| `backend/tests/Feature/Database/SoatoSeederFolderMappingTest.php` | new |

No new migration, no new service, no new model.

## 8. Tests

### 8.1 Unit / feature

`tests/Feature/Database/SoatoSeederFolderMappingTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Region;
use Database\Seeders\SoatoSeeder;

uses(RefreshDatabase::class);

test('seeder populates folder_name from REGION_FOLDER constant for all 14 regions', function () {
    (new SoatoSeeder())->setCommand($this->app['Illuminate\Console\Command'] ?? new \Illuminate\Console\Command())->run();

    foreach (SoatoSeeder::REGION_FOLDER as $code => $expected) {
        $folderName = Region::where('code', $code)->value('folder_name');
        expect($folderName)->toBe($expected, "Region {$code} folder_name");
    }
});

test('REGION_FOLDER, REGION_LATIN, REGION_SORT all cover the same region codes', function () {
    $latinCodes = array_keys(SoatoSeeder::REGION_LATIN);
    $sortCodes  = array_keys(SoatoSeeder::REGION_SORT);
    $folderCodes = array_keys(SoatoSeeder::REGION_FOLDER);

    sort($latinCodes); sort($sortCodes); sort($folderCodes);

    expect($latinCodes)->toBe($sortCodes);
    expect($latinCodes)->toBe($folderCodes);
});
```

The first test re-runs the seeder against the test DB (which already runs SoatoSeeder in `RefreshDatabase::class`); rather than re-running, simpler form: just query `Region` directly after `RefreshDatabase` runs the seeder.

Simpler version (preferred):

```php
test('seeded regions have correct folder_name for all 14 regions', function () {
    foreach (SoatoSeeder::REGION_FOLDER as $code => $expected) {
        expect(Region::where('code', $code)->value('folder_name'))->toBe($expected);
    }
});
```

This works if the test bootstrapper seeds via `RefreshDatabase` + `SoatoSeeder`. Verify the existing test setup does this; if not, dispatch `Artisan::call('db:seed', ['--class' => 'SoatoSeeder'])` once at the top.

### 8.2 Disk-existence assertion (optional, gated)

```php
test('every region folder_name exists on disk when data dir is present', function () {
    $dataPath = base_path('../data');
    if (! is_dir($dataPath)) {
        $this->markTestSkipped('data/ folder not present in this environment.');
    }
    foreach (SoatoSeeder::REGION_FOLDER as $code => $folder) {
        expect(is_dir($dataPath . DIRECTORY_SEPARATOR . $folder))->toBeTrue("Missing data folder for region {$code}: {$folder}");
    }
});
```

Skipped in CI; runs locally where `data/` exists.

### 8.3 Smoke (operator-run)

```
cd backend && php artisan migrate:fresh --seed
php artisan import:all-regions 2026
```

Expected: 14/14 xlsx rows show `rows_staged > 0`. karakalpak / tashkent_city / tashkent / fergana / khorezm previously reported 0; now they report 200-600.

## 9. Risks

- **Risk:** Disk folder renamed later (e.g. operator moves files). *Mitigation:* import fails with the existing `data folder not found, skipping` log line. Operator updates `REGION_FOLDER` constant.
- **Risk:** Constant copy contains hidden trailing whitespace from copy-paste. *Mitigation:* the disk-existence test (§8.2) catches it locally before commit.
- **Risk:** Fixtures that insert `Region` rows directly with `folder_name = null` keep doing so. *Mitigation:* fine — those tests bypass the seeder by design. No behavior change.
- **Risk:** Region values include Cyrillic + look-alike Latin chars in the source folder names. *Mitigation:* `ls` already revealed pure Cyrillic. Constants use the same Cyrillic. No transliteration needed.
- **Risk:** The 5 currently-broken regions might surface NEW bugs once they stage data (new district name variants, new sentinels). *Mitigation:* known acceptable. The fix is to STAGE the data; new bugs uncovered are progress, not regressions.
