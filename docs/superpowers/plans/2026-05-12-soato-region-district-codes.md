# SOATO Region/District Codes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace all string-slug values in `regions.code` / `districts.code` and every FK referencing them with numeric SOATO codes (region 4-digit, district 7-digit) loaded from `districts.xlsx`. Columns become `unsignedInteger`. Single migration wave: edit existing migrations + seeders + models + Livewire + tests, then `migrate:fresh --seed` and re-import.

**Architecture:** Edit existing migrations in place (column type swap). Replace existing region+district seeders with one new `SoatoSeeder` that reads `districts.xlsx` via PhpSpreadsheet (already in composer). Update model casts, Livewire constants, importer CLI resolver, DTOs, and ~30 test fixtures. Importer CLI continues to accept slugs (`andijan`) by resolving via `regions.name_latin`.

**Tech Stack:** Laravel 11 + Livewire 3 + Pest 3 + PostgreSQL + PhpOffice/PhpSpreadsheet.

**Spec:** `docs/superpowers/specs/2026-05-12-soato-region-district-codes-design.md`

**Working directory:** `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali`. All `php artisan` / `vendor/bin/pest` commands run from inside `backend/`. All `git` commands from project root.

---

## File Structure

| File | Action |
|---|---|
| 14 migrations under `backend/database/migrations/` | edit column types in place |
| `backend/database/seeders/SoatoSeeder.php` | new — reads `districts.xlsx` and seeds regions + districts |
| `backend/database/seeders/DatabaseSeeder.php` | modify — replace `RegionSeeder`/`DistrictSeeder` with `SoatoSeeder` |
| `backend/database/seeders/RegionSeeder.php`, `DistrictSeeder.php` | delete (or leave unreferenced) |
| 14 model files under `backend/app/Models/` | add `$casts` for int FKs |
| `backend/app/Support/AndijanMapGeometry.php` | fix 2 cell-name spellings |
| `backend/app/Console/Commands/ImportRegionCommand.php` | slug→int resolver at entry |
| `backend/app/Console/Commands/ImportTasks.php` | same resolver |
| `backend/app/Services/Import/DistrictResolver.php` | signature `string` → `int` |
| 3 DTOs under `backend/app/Support/Import/` | property types `string` → `int` |
| 7-ish Livewire components under `backend/app/Livewire/` | `REGION_CODE` const + scope use |
| `backend/app/Models/Task.php` | scope signatures `string` → `int` |
| ~30 test files under `backend/tests/` | fixture values |

---

### Task 1: Edit migrations — schema column types

**Files:** 14 migration files in `backend/database/migrations/`. Each gets `string` → `unsignedInteger` swap on `code` / `region_code` / `district_code` columns. No FK or index name changes.

- [ ] **Step 1: Edit `2026_05_04_000002_create_regions_table.php`**

Find: `$table->string('code', 32)->unique();`
Replace with: `$table->unsignedInteger('code')->unique();`

- [ ] **Step 2: Edit `2026_05_04_000003_create_districts_table.php`**

Find: `$table->string('code', 64);`
Replace with: `$table->unsignedInteger('code');`

- [ ] **Step 3: Edit `2026_05_05_000001_add_region_code_to_districts.php`**

Three edits:

Find: `$table->string('region_code', 32)->nullable()->after('region_id');`
Replace with: `$table->unsignedInteger('region_code')->nullable()->after('region_id');`

Find: `$table->string('region_code', 32)->nullable(false)->change();`
Replace with: `$table->unsignedInteger('region_code')->nullable(false)->change();`

The inline `UPDATE … SET region_code = r.code` SQL works unchanged.

- [ ] **Step 4: Edit `2026_05_05_000003_create_region_indicator_availability_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

- [ ] **Step 5: Edit `2026_05_05_000004_create_indicator_facts_table.php`**

Two edits:

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

Find: `$table->string('district_code', 64)->nullable();`
Replace with: `$table->unsignedInteger('district_code')->nullable();`

- [ ] **Step 6: Edit `2026_05_05_000005_create_food_balance_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

- [ ] **Step 7: Edit `2026_05_05_000006_create_warehouses_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

Find: `$table->string('district_code', 64)->nullable();`
Replace with: `$table->unsignedInteger('district_code')->nullable();`

- [ ] **Step 8: Edit `2026_05_05_000007_create_guarantee_letters_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

- [ ] **Step 9: Edit `2026_05_05_000008_create_promise_targets_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

- [ ] **Step 10: Edit `2026_05_05_000009_create_import_runs_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

- [ ] **Step 11: Edit `2026_05_05_000010_create_import_files_table.php`**

If the file has `$table->string('region_code', 32);` line — replace with `$table->unsignedInteger('region_code');`. If not present, leave file alone.

- [ ] **Step 12: Edit `2026_05_05_000011_create_data_quality_issues_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

- [ ] **Step 13: Edit `2026_05_05_000012_create_import_staging_indicator_facts_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

If the file has `$table->string('district_code', 64)->nullable();` — replace with `$table->unsignedInteger('district_code')->nullable();`.

- [ ] **Step 14: Edit `2026_05_05_000013_create_import_staging_food_balance_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

- [ ] **Step 15: Edit `2026_05_05_000014_create_import_staging_warehouses_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

If the file has `$table->string('district_code', 64)->nullable();` — replace with `$table->unsignedInteger('district_code')->nullable();`.

- [ ] **Step 16: Edit `2026_05_08_000001_create_tasks_table.php`**

Find: `$table->string('region_code', 32);`
Replace with: `$table->unsignedInteger('region_code');`

- [ ] **Step 17: Sanity check**

```bash
grep -rn "string('region_code'\|string('district_code'" backend/database/migrations/
```

Expected: no matches. Every `region_code`/`district_code` column is now `unsignedInteger`.

```bash
grep -rn "string('code'" backend/database/migrations/2026_05_04_00000{2,3}*.php
```

Expected: no matches.

- [ ] **Step 18: Commit**

```bash
git add backend/database/migrations/
git commit -m "feat(soato): switch region/district FK columns to unsignedInteger"
```

---

### Task 2: SoatoSeeder + DatabaseSeeder wiring

**Files:**
- Create: `backend/database/seeders/SoatoSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create SoatoSeeder**

Create `backend/database/seeders/SoatoSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SoatoSeeder extends Seeder
{
    /**
     * SOATO region code => human-readable Latin slug (`name_latin`).
     * Slugs are stable English transliterations used by CLI and legacy code paths.
     */
    public const REGION_LATIN = [
        1703 => 'andijan',
        1706 => 'bukhara',
        1708 => 'jizzakh',
        1710 => 'kashkadarya',
        1712 => 'navoi',
        1714 => 'namangan',
        1718 => 'samarkand',
        1722 => 'surkhandarya',
        1724 => 'sirdarya',
        1726 => 'tashkent_city',
        1727 => 'tashkent',
        1730 => 'fergana',
        1733 => 'khorezm',
        1735 => 'karakalpak',
    ];

    /**
     * SOATO district code => Latin slug (`name_latin`).
     * Preserves legacy Andijan slugs to keep prior code paths working.
     * Other districts get a transliteration; legacy code only referenced Andijan
     * districts, so the rest can be generic.
     */
    public const DISTRICT_LATIN = [
        1703202 => 'oltinkol_district',
        1703203 => 'andijan_district',
        1703206 => 'baliqchi_district',
        1703209 => 'boston_district',
        1703210 => 'buloqboshi_district',
        1703211 => 'jalaquduq_district',
        1703214 => 'izboskan_district',
        1703217 => 'ulugnor_district',
        1703220 => 'qorgontepa_district',
        1703224 => 'asaka_district',
        1703227 => 'markhamat_district',
        1703230 => 'shakhrikhan_district',
        1703232 => 'pakhtaobod_district',
        1703236 => 'xojaobod_district',
        1703401 => 'andijan_city',
        1703408 => 'khonobod_city',
    ];

    /** Region sort_order (1-based, leads with Karakalpakstan per convention). */
    public const REGION_SORT = [
        1735 => 1,  // Қорақалпоғистон
        1703 => 2,  // Андижон
        1706 => 3,  // Бухоро
        1708 => 4,  // Жиззах
        1710 => 5,  // Қашқадарё
        1712 => 6,  // Навоий
        1714 => 7,  // Наманган
        1718 => 8,  // Самарқанд
        1722 => 9,  // Сурхондарё
        1724 => 10, // Сирдарё
        1726 => 11, // Тошкент шаҳри
        1727 => 12, // Тошкент вилояти
        1730 => 13, // Фарғона
        1733 => 14, // Хоразм
    ];

    public function run(): void
    {
        $path = base_path('../districts.xlsx');
        if (! is_file($path)) {
            $path = base_path('districts.xlsx');
        }
        if (! is_file($path)) {
            $this->command->error("districts.xlsx not found at repo root.");
            return;
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $now = now();

        $regions   = [];
        $districts = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }
            [$regionName, $regionCode, $districtName, $districtCode] = array_pad($cells, 4, null);
            if ($regionCode === null) continue;

            $rc = (int) $regionCode;
            $dc = (int) $districtCode;

            if (! isset($regions[$rc])) {
                $regions[$rc] = $this->makeRegionRow($rc, (string) $regionName, $now);
            }

            $districts[] = $this->makeDistrictRow($rc, $dc, (string) $districtName, $now);
        }

        foreach (array_values($regions) as $r) {
            DB::table('regions')->updateOrInsert(['code' => $r['code']], $r);
        }

        // Reload region IDs for FK
        $regionIds = DB::table('regions')->pluck('id', 'code');

        $sortByRegion = [];
        foreach ($districts as $d) {
            $rc = $d['region_code'];
            $sortByRegion[$rc] = ($sortByRegion[$rc] ?? 0) + 1;
            $d['sort_order'] = $sortByRegion[$rc];
            $d['region_id']  = $regionIds[$rc] ?? null;
            if ($d['region_id'] === null) continue;

            DB::table('districts')->updateOrInsert(
                ['region_id' => $d['region_id'], 'code' => $d['code']],
                $d,
            );
        }

        $this->command->info('Seeded ' . count($regions) . ' regions and ' . count($districts) . ' districts.');
    }

    private function makeRegionRow(int $code, string $name, \DateTimeInterface $now): array
    {
        $nameFull = $name;
        $nameShort = $nameFull;
        if (str_ends_with($nameShort, ' вилояти')) {
            $nameShort = mb_substr($nameShort, 0, -mb_strlen(' вилояти'));
        } elseif (str_ends_with($nameShort, ' шаҳри')) {
            $nameShort = mb_substr($nameShort, 0, -mb_strlen(' шаҳри'));
        } elseif (str_ends_with($nameShort, ' Республикаси')) {
            $nameShort = mb_substr($nameShort, 0, -mb_strlen(' Республикаси'));
        }

        return [
            'code'          => $code,
            'name_short'    => $nameShort,
            'name_full'     => $nameFull,
            'name_latin'    => self::REGION_LATIN[$code] ?? null,
            'folder_name'   => null,
            'sort_order'    => self::REGION_SORT[$code] ?? 99,
            'has_districts' => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];
    }

    private function makeDistrictRow(int $regionCode, int $code, string $name, \DateTimeInterface $now): array
    {
        if (str_ends_with($name, ' тумани')) {
            $base = mb_substr($name, 0, -mb_strlen(' тумани'));
            $nameFull  = $name;
            $nameShort = $base . ' т.';
            $kind = 'district';
        } else {
            $nameFull  = $name . ' шаҳри';
            $nameShort = $name . ' ш.';
            $kind = 'city';
        }

        return [
            'code'         => $code,
            'region_code'  => $regionCode,
            'name_short'   => $nameShort,
            'name_full'    => $nameFull,
            'name_latin'   => self::DISTRICT_LATIN[$code] ?? null,
            'kind'         => $kind,
            'alt_labels'   => null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }
}
```

- [ ] **Step 2: Wire SoatoSeeder into DatabaseSeeder**

Open `backend/database/seeders/DatabaseSeeder.php`. Replace its `run()` body. The current body has:

```php
        $this->call([
            ReportingYearSeeder::class,
            ModuleSeeder::class,
            RegionSeeder::class,
            DistrictSeeder::class,
            IndicatorSeeder::class,
            RegionIndicatorAvailabilitySeeder::class,
        ]);
```

Replace with:

```php
        $this->call([
            ReportingYearSeeder::class,
            ModuleSeeder::class,
            SoatoSeeder::class,
            IndicatorSeeder::class,
            RegionIndicatorAvailabilitySeeder::class,
        ]);
```

`RegionSeeder` and `DistrictSeeder` files stay in place (deletion out of scope) but are no longer called.

- [ ] **Step 3: Smoke check seeder loads xlsx**

```bash
cd backend && php artisan migrate:fresh --seed 2>&1 | tail -10
```

Expected lines (among others):
- `2026_05_04_000002_create_regions_table … DONE`
- `2026_05_08_000001_create_tasks_table … DONE`
- `Seeded 14 regions and 208 districts.`

If the seeder errors with "districts.xlsx not found at repo root", verify the file exists at `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali\districts.xlsx`.

- [ ] **Step 4: Commit**

```bash
git add backend/database/seeders/SoatoSeeder.php backend/database/seeders/DatabaseSeeder.php
git commit -m "feat(soato): SoatoSeeder reads districts.xlsx for 14 regions + 208 districts"
```

---

### Task 3: Model `$casts` updates

**Files:** 14 models under `backend/app/Models/`.

For each model below, add or extend its `$casts` array to coerce `region_code` / `district_code` to integers.

- [ ] **Step 1: Edit `Region.php`**

Add `protected $casts = ['code' => 'integer'];` (or extend existing).

- [ ] **Step 2: Edit `District.php`**

Extend casts. Resulting block must include:

```php
    protected $casts = [
        'alt_labels'   => 'array',
        'code'         => 'integer',
        'region_code'  => 'integer',
    ];
```

- [ ] **Step 3: Edit `IndicatorFact.php`**

Add to existing casts (or create the array):

```php
    protected $casts = [
        'region_code'    => 'integer',
        'district_code'  => 'integer',
        'year'           => 'integer',
        'plan_value'     => 'float',
        'actual_hokimyat'=> 'float',
        'actual_statkom' => 'float',
        'growth_pct'     => 'float',
        'pct_of_plan'    => 'float',
    ];
```

If existing casts already declare `year`, `plan_value`, etc., merge — keep one entry per key.

- [ ] **Step 4: Edit `Warehouse.php`**

Add to casts:

```php
        'region_code'   => 'integer',
        'district_code' => 'integer',
```

- [ ] **Step 5: Edit `FoodBalance.php`**

Add to casts: `'region_code' => 'integer',`.

- [ ] **Step 6: Edit `GuaranteeLetter.php`**

Add to casts: `'region_code' => 'integer',`.

- [ ] **Step 7: Edit `PromiseTarget.php`**

Add to casts (keep existing `'target_districts' => 'array'`):

```php
        'region_code'      => 'integer',
        'target_districts' => 'array',
```

- [ ] **Step 8: Edit `Task.php`**

Add to casts: `'region_code' => 'integer',`. Update scope signatures:

```php
    public function scopeForRegion(Builder $q, int $code): Builder
    {
        return $q->where('region_code', $code);
    }
```

`scopeForDistrict(Builder $q, int $districtId)` was already int — leave unchanged.

- [ ] **Step 9: Edit `RegionIndicatorAvailability.php`**

Add to casts: `'region_code' => 'integer',`.

- [ ] **Step 10: Edit `ImportRun.php`**

Add to casts: `'region_code' => 'integer',`.

- [ ] **Step 11: Edit `DataQualityIssue.php`**

Add to casts: `'region_code' => 'integer',`.

- [ ] **Step 12: Edit `ImportStagingIndicatorFact.php`**

Add to casts:

```php
        'region_code'   => 'integer',
        'district_code' => 'integer',
```

- [ ] **Step 13: Edit `ImportStagingFoodBalance.php`**

Add to casts: `'region_code' => 'integer',`.

- [ ] **Step 14: Edit `ImportStagingWarehouse.php`**

Add to casts:

```php
        'region_code'   => 'integer',
        'district_code' => 'integer',
```

- [ ] **Step 15: Commit**

```bash
git add backend/app/Models/
git commit -m "feat(soato): integer casts on region_code/district_code across models"
```

---

### Task 4: AndijanMapGeometry spelling fix

**Files:** `backend/app/Support/AndijanMapGeometry.php`.

The `name` fields for two cells use Уzbek orthography (Марҳамат, Шаҳрихон) that differs from `districts.xlsx` (Мархамат, Шахрихон). The seeder writes the xlsx spelling, so the geometry must match exactly for cell-status mapping to work.

- [ ] **Step 1: Replace the two cell names**

In `backend/app/Support/AndijanMapGeometry.php`, find the line containing `'name' => 'Марҳамат тумани'` and replace `Марҳамат` with `Мархамат` (single character: `ҳ` → `х`).

Find the line containing `'name' => 'Шаҳрихон тумани'` and replace `Шаҳрихон` with `Шахрихон` (single character: `ҳ` → `х`).

- [ ] **Step 2: Verify by grep**

```bash
grep -n "Мархамат\|Шахрихон" backend/app/Support/AndijanMapGeometry.php
```

Expected: 2 lines with the new spellings.

```bash
grep -n "Марҳамат\|Шаҳрихон" backend/app/Support/AndijanMapGeometry.php
```

Expected: no matches.

- [ ] **Step 3: Commit**

```bash
git add backend/app/Support/AndijanMapGeometry.php
git commit -m "fix(districts): align AndijanMapGeometry names to xlsx orthography"
```

---

### Task 5: Importer CLI slug→int resolver

**Files:**
- Modify: `backend/app/Console/Commands/ImportRegionCommand.php`
- Modify: `backend/app/Console/Commands/ImportTasks.php`
- Modify: `backend/app/Services/Import/DistrictResolver.php`

- [ ] **Step 1: Edit `ImportRegionCommand.php`**

Find the line at the top of `handle()` that reads the region argument (likely `$regionCode = $this->argument('region');` or similar). Replace it with:

```php
        $arg = (string) $this->argument('region');
        $regionCode = ctype_digit($arg)
            ? (int) $arg
            : \App\Models\Region::where('name_latin', $arg)->value('code');

        if ($regionCode === null) {
            $this->error("Unknown region: {$arg}");
            return self::FAILURE;
        }
```

Replace any subsequent use of the slug-string `$regionCode` so it now holds an `int`. Most downstream queries already use `where('region_code', $regionCode)` which works with either type, but make sure no string concatenation expects a slug.

- [ ] **Step 2: Edit `ImportTasks.php`**

Apply the same resolver at the start of `handle()`. Find the existing `$regionCode = (string) $this->argument('region');` line and replace with:

```php
        $arg = (string) $this->argument('region');
        $regionCode = ctype_digit($arg)
            ? (int) $arg
            : \App\Models\Region::where('name_latin', $arg)->value('code');

        if ($regionCode === null) {
            $this->error("Unknown region: {$arg}");
            return self::FAILURE;
        }
```

The `resolveFile($regionCode)` and `TasksTaxonomy::REGION_FILENAMES[...]` lookups remain slug-keyed; replace those lookup keys with the original `$arg` (slug) value. Pass `$arg` to `resolveFile`. Pass `$regionCode` (int) when persisting Task rows.

Concretely, change `resolveFile` signature from `private function resolveFile(string $regionCode)` to `private function resolveFile(string $regionSlug)` and adjust the body to look up `TasksTaxonomy::REGION_FILENAMES[$regionSlug]`. At call sites pass `$arg`.

If the slug arg was numeric (e.g. `1703`), `resolveFile` needs the slug to look up the filename — convert int back to slug via `array_search($regionCode, SoatoSeeder::REGION_LATIN)` OR require `--file=` when arg is numeric. Simpler: when numeric arg, error out unless `--file=` is supplied:

```php
$slug = ctype_digit($arg)
    ? array_search($regionCode, \Database\Seeders\SoatoSeeder::REGION_LATIN, true)
    : $arg;

$file = $this->option('file') ?: $this->resolveFile($slug);
```

- [ ] **Step 3: Edit `DistrictResolver.php`**

Find `public function loadFor(string $regionCode): void` and replace `string` with `int`:

```php
    public function loadFor(int $regionCode): void
```

The downstream `District::where('region_code', $regionCode)` works with either type but PHP type system is now correct.

- [ ] **Step 4: Run all import-related tests**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/ 2>&1 | tail -5
```

Expected: tests pass (some will likely fail because of fixture types; that's Task 8's job).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/ImportRegionCommand.php backend/app/Console/Commands/ImportTasks.php backend/app/Services/Import/DistrictResolver.php
git commit -m "feat(soato): importer CLI accepts slug or numeric SOATO for region"
```

---

### Task 6: DTO property types

**Files:**
- Modify: `backend/app/Support/Import/IndicatorFactDto.php`
- Modify: `backend/app/Support/Import/WarehouseDto.php`
- Modify: `backend/app/Support/Import/FoodBalanceDto.php`

- [ ] **Step 1: Edit `IndicatorFactDto.php`**

Change property types `string $regionCode` → `int $regionCode`. Change `?string $districtCode` → `?int $districtCode`. Update any constructor / static factory signatures correspondingly.

- [ ] **Step 2: Edit `WarehouseDto.php`**

Same `string` → `int` on `regionCode` / `districtCode` properties.

- [ ] **Step 3: Edit `FoodBalanceDto.php`**

Change `string $regionCode` → `int $regionCode`.

- [ ] **Step 4: Run import-related tests**

```bash
cd backend && vendor/bin/pest tests/Feature/Import/IndicatorFactDtoTest.php tests/Feature/Import/WarehouseDtoTest.php tests/Feature/Import/FoodBalanceDtoTest.php 2>&1 | tail -5
```

If failing because tests pass `string` values, leave them failing — Task 8 fixes test fixtures.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Support/Import/IndicatorFactDto.php backend/app/Support/Import/WarehouseDto.php backend/app/Support/Import/FoodBalanceDto.php
git commit -m "feat(soato): DTOs use int region_code / district_code"
```

---

### Task 7: Livewire constants + scope use

**Files:**
- Modify: `backend/app/Livewire/DistrictsPage.php`
- Modify: `backend/app/Livewire/TasksBoard.php`
- Modify: `backend/app/Livewire/Dashboard/KpiFrontCards.php`
- Modify: `backend/app/Livewire/Dashboard/KpiWorkspaceCard.php`
- Modify: `backend/app/Livewire/Dashboard/MacroComposition.php`
- Modify: `backend/app/Livewire/RegionProfile.php`
- Modify: `backend/app/Livewire/ExecutionPage.php`

- [ ] **Step 1: Edit `DistrictsPage.php`**

Find: `public const REGION_CODE = 'andijan';`
Replace with: `public const REGION_CODE = 1703;`

`$district` URL prop stays `string` (Livewire URL serialization works with both; `(int) $this->district` cast on use). All `District::where('region_code', self::REGION_CODE)` queries remain valid.

- [ ] **Step 2: Edit `TasksBoard.php`**

Find: `public string $regionCode = 'andijan';`
Replace with: `public int $regionCode = 1703;`

Update `Task::forRegion($this->regionCode)` calls — no change needed since `forRegion` signature is now `int`.

- [ ] **Step 3: Edit `Dashboard/KpiFrontCards.php`, `KpiWorkspaceCard.php`, `MacroComposition.php`**

Search each for `'andijan'` and replace with `1703`. If they use a constant `REGION_CODE`, change that. Most dashboard components likely have something like `where('region_code', 'andijan')` — change the value.

- [ ] **Step 4: Edit `RegionProfile.php` and `ExecutionPage.php`**

Same: replace `'andijan'` literal with `1703` where it's used as a region code.

- [ ] **Step 5: Grep verification**

```bash
grep -rn "'andijan'" backend/app/Livewire/
```

Expected: no matches (the slug appears nowhere as a region code). If any matches remain, check whether they're region codes (replace) vs slugs used elsewhere (leave).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Livewire/
git commit -m "feat(soato): Livewire components use int 1703 instead of 'andijan'"
```

---

### Task 8: Test fixture updates

**Files:** ~30 test files under `backend/tests/`. Each fixture seeding `regions.code` or `districts.code` with slug strings is updated to SOATO ints.

- [ ] **Step 1: Identify affected tests**

```bash
grep -rln "'code' => 'andijan'\|'region_code' => 'andijan'\|'code' => 'asaka_district'\|'code' => 'andijan_city'\|'code' => 'boston_district'\|'code' => 'ulugnor_district'\|'code' => 'pakhtaobod_district'\|'code' => 'shakhrikhan_district'\|'code' => 'khonobod_city'" backend/tests/
```

This lists every test file that hardcodes a slug as a region or district code. For each file in the output, perform Step 2.

- [ ] **Step 2: Map slug → SOATO int**

Use this lookup table for every replacement:

| Slug | SOATO int |
|---|---|
| `'andijan'` (region) | `1703` |
| `'bukhara'` (region) | `1706` |
| `'jizzakh'` (region) | `1708` |
| `'kashkadarya'` (region) | `1710` |
| `'navoi'` (region) | `1712` |
| `'namangan'` (region) | `1714` |
| `'samarkand'` (region) | `1718` |
| `'surkhandarya'` (region) | `1722` |
| `'sirdarya'` (region) | `1724` |
| `'tashkent_city'` (region) | `1726` |
| `'tashkent'` (region) | `1727` |
| `'fergana'` (region) | `1730` |
| `'khorezm'` (region) | `1733` |
| `'karakalpak'` (region) | `1735` |
| `'andijan_city'` | `1703401` |
| `'khonobod_city'` | `1703408` |
| `'andijan_district'` | `1703203` |
| `'oltinkol_district'` | `1703202` |
| `'baliqchi_district'` | `1703206` |
| `'boston_district'` | `1703209` |
| `'buloqboshi_district'` | `1703210` |
| `'jalaquduq_district'` | `1703211` |
| `'izboskan_district'` | `1703214` |
| `'ulugnor_district'` | `1703217` |
| `'qorgontepa_district'` | `1703220` |
| `'asaka_district'` | `1703224` |
| `'markhamat_district'` | `1703227` |
| `'shakhrikhan_district'` | `1703230` |
| `'pakhtaobod_district'` | `1703232` |
| `'xojaobod_district'` | `1703236` |

For non-Andijan districts not listed, query via `District::where('name_latin', $slug)->value('code')` after the seeder runs, OR use the `SoatoSeeder::DISTRICT_LATIN` const reverse lookup.

- [ ] **Step 3: For each test file in Step 1 output, perform the replacements**

In each file, find every fixture that seeds:
- `'code' => '<slug>'` for regions → `'code' => <SOATO_INT>`
- `'region_code' => '<slug>'` → `'region_code' => <SOATO_INT>`
- `'code' => '<slug>'` for districts → `'code' => <SOATO_INT>`

Also update any assertion that compares against slug values. Example: `expect($task->region_code)->toBe('andijan');` → `expect($task->region_code)->toBe(1703);`.

Also update any `District::create` / `IndicatorFact::create` / `Task::create` calls that hardcode slug values.

Run the test file after each edit to confirm. Examples:

```bash
cd backend && vendor/bin/pest tests/Feature/Schema/TasksTableTest.php
cd backend && vendor/bin/pest tests/Feature/Schema/TaskDistrictsTableTest.php
cd backend && vendor/bin/pest tests/Unit/TaskScopeTest.php
cd backend && vendor/bin/pest tests/Feature/Import/ImportTasksCommandTest.php
cd backend && vendor/bin/pest tests/Feature/Http/TasksPageTest.php
cd backend && vendor/bin/pest tests/Feature/Http/DistrictsPageTest.php
```

- [ ] **Step 4: Run full Pest suite**

```bash
cd backend && vendor/bin/pest 2>&1 | tail -8
```

Expected: tests pass. If a parity test (e.g. `AndijanMacroParityTest.php`, `AndijanBudgetParityTest.php`) fails because it asserts region_code as string, fix it. The parity tests use real fact data — they should work post-import since `region_code` is now an int matching DB rows.

If any other Andijan-prefixed parity test fails for the same reason, apply the same fix pattern.

- [ ] **Step 5: Commit**

```bash
git add backend/tests/
git commit -m "test(soato): update fixtures and assertions to SOATO int codes"
```

---

### Task 9: End-to-end smoke

**Files:** none.

- [ ] **Step 1: Fresh migration + seed**

```bash
cd backend && php artisan migrate:fresh --seed
```

Expected output (key lines):
- All migrations land green.
- `Seeded 14 regions and 208 districts.`

- [ ] **Step 2: Import Andijan macro/etc.**

```bash
cd backend && php artisan import:region andijan
```

Expected: import completes without errors. The CLI accepts the slug; resolver looks up `regions.name_latin='andijan'` → `1703`.

- [ ] **Step 3: Import Andijan tasks**

```bash
cd backend && php artisan import:tasks andijan
```

Expected: `Imported 86 tasks for region '1703'.` (or similar — the message format depends on whether the implementer kept `$arg` as the print value or replaced with `$regionCode`).

- [ ] **Step 4: Verify counts via Tinker**

```bash
cd backend && php artisan tinker --execute "
echo 'regions: ' . App\Models\Region::count() . PHP_EOL;
echo 'districts: ' . App\Models\District::count() . PHP_EOL;
echo 'Andijan districts: ' . App\Models\District::where('region_code', 1703)->count() . PHP_EOL;
echo 'tasks: ' . App\Models\Task::where('region_code', 1703)->count() . PHP_EOL;
echo 'IndicatorFact andijan rows: ' . App\Models\IndicatorFact::where('region_code', 1703)->count() . PHP_EOL;
"
```

Expected:
- regions: 14
- districts: 208
- Andijan districts: 16
- tasks: 86 (matches prior import)
- IndicatorFact andijan rows: > 100 (varies; should be non-zero)

- [ ] **Step 5: Browser smoke**

```bash
cd backend && php artisan serve
```

Open `http://127.0.0.1:8000/dashboard`, `/districts`, `/tasks`, `/districts?kpi=industry`. Confirm:
- Dashboard renders KPIs with Andijan facts.
- Districts page hex map renders 16 colored cells; clicking selects a district; URL becomes `?district=<SOATO_int>`.
- Tasks page lists 86 tasks; T-чорак/D-мақсад chips render.

If a regression appears (e.g. district selection doesn't highlight on map), inspect with browser DevTools and confirm the hex map `wire:click="selectDistrict('<code>')"` argument is a numeric string (Livewire serializes it). If the `District::find` lookup fails because `selectDistrict` expects string-keyed, recall that scope methods cast it via PHP loose comparison.

- [ ] **Step 6: Final commit if any fixes**

```bash
cd backend && git status
```

If any touch-ups were needed during smoke:

```bash
cd backend && git add -A
git commit -m "fix(soato): smoke-test touch-ups"
```

If clean, skip.

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| §4 Schema column type changes (14 migrations) | Task 1 |
| §5 SoatoSeeder + DatabaseSeeder | Task 2 |
| §6 AndijanMapGeometry spelling fix | Task 4 |
| §7 Model casts (14 models) | Task 3 |
| §8 Code constant updates | Task 7 |
| §9 Importer CLI resolver | Task 5 |
| §9 DTO property types | Task 6 |
| §10 Test fixture updates | Task 8 |
| §12 Rollout (migrate:fresh + imports + smoke) | Task 9 |

**Placeholder scan:** no TBD/handwave. Every step has a concrete command, expected output, or exact code edit.

**Type consistency:**

- `region_code` and `district_code` flip to `int` everywhere: migrations (`unsignedInteger`), model casts (`'integer'`), DTOs (`int $regionCode`), Livewire (`int $regionCode = 1703`).
- Scope signatures: `Task::scopeForRegion(Builder $q, int $code)` in Task 3 matches Task 5 (importer calls `Task::forRegion($regionCode)` where `$regionCode` is int).
- `SoatoSeeder::REGION_LATIN` const referenced in `ImportTasks` (Task 5 Step 2) via `\Database\Seeders\SoatoSeeder::REGION_LATIN`.
- Andijan slug `'andijan'` mapped to int `1703` everywhere (Task 7 const, Task 8 fixture map).

**Gaps:** none. Plan covers spec end-to-end.
