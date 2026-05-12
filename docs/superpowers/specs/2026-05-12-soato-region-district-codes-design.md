# SOATO numeric region/district codes design

**Date:** 2026-05-12
**Status:** Approved (pending user spec review)
**Scope:** Replace string-slug values in `regions.code` and `districts.code` (and every FK column that references them) with numeric SOATO codes sourced from `districts.xlsx`. Schema columns become `unsignedInteger`. All Eloquent models, Livewire components, importers, DTOs, and tests update to int.

---

## 1. Goal

Today the prototype uses hand-coined slug identifiers: `regions.code = 'andijan'`, `districts.code = 'andijan_city'`, etc. The Uzbek SOATO classifier provides canonical 4-digit (region) and 7-digit (district) numeric codes — e.g. Андижон = `1703`, Андижон шаҳри = `1703401`. The repo already has `districts.xlsx` at the root with all 14 regions × 208 districts mapped to SOATO codes.

This spec swaps the slug-based identifiers for SOATO numeric codes everywhere they appear: schema columns become `unsignedInteger`, seeded data uses ints, all FK joins remain valid (same column on both sides), and the codebase + tests update accordingly. The cleanup is single-pass: edit existing migrations + seeders, then `migrate:fresh --seed` and re-import.

## 2. Non-goals

- No new tables, no new indexes.
- No URL-friendly slug aliases. Anything that referenced `'andijan'` becomes `1703`. Routes that previously embedded slug (e.g. `/profile?districtCode=andijan_city`) embed the SOATO int.
- No backwards-compatible migration path. Dev DB is rebuilt from scratch via `migrate:fresh --seed`.
- No `Region` or `District` model API rewrite beyond casts + scope signature changes.
- No CSS, no Blade markup-structure changes.
- `task_districts` pivot stays unchanged (uses auto-PK `district_id`, not `code`).
- `indicators.code` and `modules.code` stay as string slugs — those are domain identifiers, not SOATO.

## 3. Strategy

Edit existing migrations in place. Replace the existing region+district seeders with a single `SoatoSeeder` containing two const arrays (14 regions, 208 districts) compiled from `districts.xlsx`. Update model `$casts` to int. Update Livewire constants + scope signatures + test fixtures + DTOs. Importer CLI accepts either SOATO int (`1703`) or slug (`andijan`) — slug resolves via `regions.name_latin`.

Single coherent change wave; cleanest schema afterward.

## 4. Schema changes

For each migration listed below, change `string('region_code', 32)` → `unsignedInteger('region_code')`, and `string('district_code', 64)->nullable()` → `unsignedInteger('district_code')->nullable()`. Same for the `code` column on `regions` (`varchar(32)` → `unsignedInteger`) and `districts` (`varchar(64)` → `unsignedInteger`).

| File | Columns to change |
|---|---|
| `2026_05_04_000002_create_regions_table.php` | `code` |
| `2026_05_04_000003_create_districts_table.php` | `code` |
| `2026_05_05_000001_add_region_code_to_districts.php` | `region_code` (the column added by this migration) |
| `2026_05_05_000003_create_region_indicator_availability_table.php` | `region_code` |
| `2026_05_05_000004_create_indicator_facts_table.php` | `region_code`, `district_code` |
| `2026_05_05_000005_create_food_balance_table.php` | `region_code` |
| `2026_05_05_000006_create_warehouses_table.php` | `region_code`, `district_code` |
| `2026_05_05_000007_create_guarantee_letters_table.php` | `region_code` |
| `2026_05_05_000008_create_promise_targets_table.php` | `region_code` |
| `2026_05_05_000009_create_import_runs_table.php` | `region_code` |
| `2026_05_05_000010_create_import_files_table.php` | `region_code` if present |
| `2026_05_05_000011_create_data_quality_issues_table.php` | `region_code` |
| `2026_05_05_000012_create_import_staging_indicator_facts_table.php` | `region_code`, `district_code` |
| `2026_05_05_000013_create_import_staging_food_balance_table.php` | `region_code` |
| `2026_05_05_000014_create_import_staging_warehouses_table.php` | `region_code`, `district_code` if present |
| `2026_05_08_000001_create_tasks_table.php` | `region_code` |

Indexes, uniques, and FKs keep the same column lists; only types change. Composite FK `(region_code, district_code) → districts(region_code, code)` works as before since both sides flip together.

`promise_targets.target_districts` is a JSONB column. Its array entries — previously slug strings — become integer SOATO codes. The Eloquent `array` cast handles both.

## 5. SoatoSeeder

**Create `backend/database/seeders/SoatoSeeder.php`.** Loads two embedded const arrays compiled from `districts.xlsx`. No runtime xlsx parsing; values are frozen at code time.

### 5.1 Region data

```php
public const REGIONS = [
    1703 => ['name_short' => 'Андижон',          'name_full' => 'Андижон вилояти',          'name_latin' => 'andijan',      'has_districts' => true,  'sort_order' => 2],
    1706 => ['name_short' => 'Бухоро',           'name_full' => 'Бухоро вилояти',           'name_latin' => 'bukhara',      'has_districts' => true,  'sort_order' => 3],
    1708 => ['name_short' => 'Жиззах',           'name_full' => 'Жиззах вилояти',           'name_latin' => 'jizzakh',      'has_districts' => true,  'sort_order' => 4],
    1710 => ['name_short' => 'Қашқадарё',        'name_full' => 'Қашқадарё вилояти',        'name_latin' => 'kashkadarya',  'has_districts' => true,  'sort_order' => 5],
    1712 => ['name_short' => 'Навоий',           'name_full' => 'Навоий вилояти',           'name_latin' => 'navoi',        'has_districts' => true,  'sort_order' => 6],
    1714 => ['name_short' => 'Наманган',         'name_full' => 'Наманган вилояти',         'name_latin' => 'namangan',     'has_districts' => true,  'sort_order' => 7],
    1718 => ['name_short' => 'Самарқанд',        'name_full' => 'Самарқанд вилояти',        'name_latin' => 'samarkand',    'has_districts' => true,  'sort_order' => 8],
    1722 => ['name_short' => 'Сурхондарё',       'name_full' => 'Сурхондарё вилояти',       'name_latin' => 'surkhandarya', 'has_districts' => true,  'sort_order' => 9],
    1724 => ['name_short' => 'Сирдарё',          'name_full' => 'Сирдарё вилояти',          'name_latin' => 'sirdarya',     'has_districts' => true,  'sort_order' => 10],
    1726 => ['name_short' => 'Тошкент шаҳри',    'name_full' => 'Тошкент шаҳри',            'name_latin' => 'tashkent_city','has_districts' => true,  'sort_order' => 11],
    1727 => ['name_short' => 'Тошкент',          'name_full' => 'Тошкент вилояти',          'name_latin' => 'tashkent',     'has_districts' => true,  'sort_order' => 12],
    1730 => ['name_short' => 'Фарғона',          'name_full' => 'Фарғона вилояти',          'name_latin' => 'fergana',      'has_districts' => true,  'sort_order' => 13],
    1733 => ['name_short' => 'Хоразм',           'name_full' => 'Хоразм вилояти',           'name_latin' => 'khorezm',      'has_districts' => true,  'sort_order' => 14],
    1735 => ['name_short' => 'Қорақалпоғистон',  'name_full' => 'Қорақалпоғистон Республикаси','name_latin' => 'karakalpak', 'has_districts' => true,  'sort_order' => 1],
];
```

`sort_order` chosen to match the existing seeder order (Andijan = 2, Karakalpak = 1 leads the list per workbook convention).

### 5.2 District data

Each region maps to a list of `[soato => row]` pairs. For each row from `districts.xlsx`:

- `name_full` is the xlsx `district_name` verbatim (e.g. `Олтинкўл тумани`, `Андижон`, `Хонобод`).
- `name_short` is derived: trim ` тумани` for districts; for cities (xlsx names without ` тумани` suffix), keep the city name as `name_short` and `name_full` becomes `<name> шаҳри`.
- `name_latin` is hand-curated: preserve the prior slugs where they exist (`andijan_city`, `asaka_district`, `boston_district`, etc.) so legacy `name_latin`-based lookups still resolve. New districts not previously seeded get a transliterated slug.
- `kind`: `'city'` when xlsx district_name has no ` тумани` suffix (Andijan, Khonobod, Кagan, etc.); else `'district'`.
- `sort_order`: ascending integer matching xlsx row order within a region.

Example (Andijan, region 1703, 16 entries):

```php
public const DISTRICTS = [
    1703 => [
        1703202 => ['name_short' => 'Олтинкўл т.',    'name_full' => 'Олтинкўл тумани',    'name_latin' => 'oltinkol_district', 'kind' => 'district', 'sort_order' => 1],
        1703203 => ['name_short' => 'Андижон т.',     'name_full' => 'Андижон тумани',     'name_latin' => 'andijan_district',  'kind' => 'district', 'sort_order' => 2],
        1703206 => ['name_short' => 'Балиқчи т.',     'name_full' => 'Балиқчи тумани',     'name_latin' => 'baliqchi_district', 'kind' => 'district', 'sort_order' => 3],
        1703209 => ['name_short' => 'Бўстон т.',      'name_full' => 'Бўстон тумани',      'name_latin' => 'boston_district',   'kind' => 'district', 'sort_order' => 4],
        1703210 => ['name_short' => 'Булоқбоши т.',   'name_full' => 'Булоқбоши тумани',   'name_latin' => 'buloqboshi_district','kind' => 'district', 'sort_order' => 5],
        1703211 => ['name_short' => 'Жалақудуқ т.',   'name_full' => 'Жалақудуқ тумани',   'name_latin' => 'jalaquduq_district','kind' => 'district', 'sort_order' => 6],
        1703214 => ['name_short' => 'Избоскан т.',    'name_full' => 'Избоскан тумани',    'name_latin' => 'izboskan_district', 'kind' => 'district', 'sort_order' => 7],
        1703217 => ['name_short' => 'Улуғнор т.',     'name_full' => 'Улуғнор тумани',     'name_latin' => 'ulugnor_district',  'kind' => 'district', 'sort_order' => 8],
        1703220 => ['name_short' => 'Қўрғонтепа т.',  'name_full' => 'Қўрғонтепа тумани',  'name_latin' => 'qorgontepa_district','kind' => 'district', 'sort_order' => 9],
        1703224 => ['name_short' => 'Асака т.',       'name_full' => 'Асака тумани',       'name_latin' => 'asaka_district',    'kind' => 'district', 'sort_order' => 10],
        1703227 => ['name_short' => 'Мархамат т.',    'name_full' => 'Мархамат тумани',    'name_latin' => 'markhamat_district','kind' => 'district', 'sort_order' => 11],
        1703230 => ['name_short' => 'Шахрихон т.',    'name_full' => 'Шахрихон тумани',    'name_latin' => 'shakhrikhan_district','kind' => 'district', 'sort_order' => 12],
        1703232 => ['name_short' => 'Пахтаобод т.',   'name_full' => 'Пахтаобод тумани',   'name_latin' => 'pakhtaobod_district','kind' => 'district', 'sort_order' => 13],
        1703236 => ['name_short' => 'Хўжаобод т.',    'name_full' => 'Хўжаобод тумани',    'name_latin' => 'xojaobod_district', 'kind' => 'district', 'sort_order' => 14],
        1703401 => ['name_short' => 'Андижон ш.',     'name_full' => 'Андижон шаҳри',      'name_latin' => 'andijan_city',      'kind' => 'city',     'sort_order' => 15],
        1703408 => ['name_short' => 'Хонобод ш.',     'name_full' => 'Хонобод шаҳри',      'name_latin' => 'khonobod_city',     'kind' => 'city',     'sort_order' => 16],
    ],
    1706 => [ /* 13 entries for Бухоро */ ],
    // ... 12 more regions
];
```

Spelling note: the xlsx uses `Мархамат` and `Шахрихон` (not `Марҳамат`/`Шаҳрихон`). Seed values take the xlsx spelling. `AndijanMapGeometry::CELLS` already uses the older spelling and must be updated to match (see §6).

### 5.3 Seed logic

```php
public function run(): void
{
    foreach (self::REGIONS as $code => $r) {
        Region::updateOrCreate(['code' => $code], $r + ['code' => $code]);
    }

    foreach (self::DISTRICTS as $regionCode => $rows) {
        $regionId = Region::where('code', $regionCode)->value('id');
        foreach ($rows as $code => $row) {
            District::updateOrCreate(
                ['region_id' => $regionId, 'code' => $code],
                $row + ['code' => $code, 'region_id' => $regionId, 'region_code' => $regionCode],
            );
        }
    }
}
```

`DatabaseSeeder::run()` calls `$this->call(SoatoSeeder::class)`. Existing region/district seeders (if any) are deleted or their content replaced.

## 6. AndijanMapGeometry spelling fix

The `App\Support\AndijanMapGeometry::CELLS` array has Cyrillic `name` strings that must match `districts.name_full` exactly for the Blade view to attach status colors to cells.

Two spellings differ from xlsx:

| Geometry name (current) | xlsx spelling (new) |
|---|---|
| `Марҳамат тумани` | `Мархамат тумани` |
| `Шаҳрихон тумани` | `Шахрихон тумани` |

City cells (`Андижон шаҳри`, `Хонобод шаҳри`) already match the seeder's `name_full = "<short> шаҳри"` construction.

The unit test in `tests/Unit/AndijanMapGeometryTest.php` asserts cell count + city names — no spelling assertion currently, but the cross-check against seeded districts (`array_filter ending in 'тумани'`) keeps working.

## 7. Model + scope updates

Each affected model gets a `$casts` entry for any int FK column.

| Model | Casts to add |
|---|---|
| `App\Models\Region` | `'code' => 'integer'` |
| `App\Models\District` | `'code' => 'integer', 'region_code' => 'integer', 'alt_labels' => 'array'` (last one likely already present) |
| `App\Models\IndicatorFact` | `'region_code' => 'integer', 'district_code' => 'integer'` |
| `App\Models\Warehouse` | `'region_code' => 'integer', 'district_code' => 'integer'` |
| `App\Models\FoodBalance` | `'region_code' => 'integer'` |
| `App\Models\GuaranteeLetter` | `'region_code' => 'integer'` |
| `App\Models\PromiseTarget` | `'region_code' => 'integer'`; existing `'target_districts' => 'array'` cast unchanged |
| `App\Models\Task` | `'region_code' => 'integer'` |
| `App\Models\RegionIndicatorAvailability` | `'region_code' => 'integer'` |
| `App\Models\ImportRun` | `'region_code' => 'integer'` |
| `App\Models\DataQualityIssue` | `'region_code' => 'integer'` |
| `App\Models\ImportStagingIndicatorFact` | `'region_code' => 'integer', 'district_code' => 'integer'` |
| `App\Models\ImportStagingFoodBalance` | `'region_code' => 'integer'` |
| `App\Models\ImportStagingWarehouse` | `'region_code' => 'integer', 'district_code' => 'integer'` |

Scope signatures change where applicable:

- `Task::scopeForRegion(Builder $q, int $code)` (was `string $code`)
- All other model scopes accepting region/district code change `string` → `int` in the type hint.

## 8. Code constant + literal updates

| Literal | New value | Sample files |
|---|---|---|
| `'andijan'` | `1703` | `App\Livewire\DistrictsPage::REGION_CODE`, `App\Livewire\TasksBoard::$regionCode`, every test seed fixture |
| `'andijan_city'` | `1703401` | tests, Blade fixtures |
| `'asaka_district'`, `'boston_district'`, `'ulugnor_district'`, `'pakhtaobod_district'`, etc. | corresponding SOATO ints (1703224, 1703209, 1703217, 1703232, …) | tests, importer seeds |

`TasksTaxonomy::REGION_FILENAMES` keeps slug keys (used for CLI argument lookup). The slug → SOATO resolution happens inside `ImportTasks::handle()` via `Region::where('name_latin', $arg)->value('code')`.

## 9. Importer CLI

`ImportRegionCommand::handle()` and `ImportTasks::handle()` start with:

```php
$arg = (string) $this->argument('region');
$regionCode = ctype_digit($arg)
    ? (int) $arg
    : Region::where('name_latin', $arg)->value('code');

if ($regionCode === null) {
    $this->error("Unknown region: {$arg}");
    return self::FAILURE;
}
```

Subsequent code uses `$regionCode` (int) for all queries. CLI invocations both work:

```bash
php artisan import:region andijan   # resolves to 1703
php artisan import:region 1703      # uses directly
```

`DistrictResolver::loadFor(int $regionCode)` — signature change. Returns district codes as integers from inside `$d->code`.

DTOs:
- `App\Support\Import\IndicatorFactDto`: `region_code` and `district_code` properties become `int` and `?int`.
- `App\Support\Import\WarehouseDto`: same.
- `App\Support\Import\FoodBalanceDto`: `region_code` becomes `int`.

`StagingWriter` and the promote pipeline carry the ints through; staging tables already aligned via §4.

## 10. Test fixture updates

All tests under `backend/tests/` that seed `'code' => 'andijan'` (regions) or hardcode slug values for districts must update. Rough count: ~30 tests across Feature/Schema, Feature/Http, Feature/Import, Unit.

Pattern — before:
```php
DB::table('regions')->insert(['code' => 'andijan', 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти', 'sort_order' => 2, ...]);
DB::table('districts')->insert(['code' => 'andijan_city', 'region_code' => 'andijan', ...]);
```
After:
```php
DB::table('regions')->insert(['code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти', 'sort_order' => 2, ...]);
DB::table('districts')->insert(['code' => 1703401, 'region_code' => 1703, ...]);
```

The geometry test's cell-name spellings update for `Мархамат`/`Шахрихон` if it now cross-checks against seeded districts. Currently it doesn't — but the §6 cell-name fix happens regardless.

## 11. Files touched (high level)

| Area | Files | Type of change |
|---|---|---|
| Migrations | 14 | column type: `string → unsignedInteger` |
| Seeders | 1 new (`SoatoSeeder`) + delete old region/district seeders | content |
| Models | 14 | `$casts` additions |
| Livewire | `DistrictsPage`, `TasksBoard`, `Dashboard\*` (3-4), `RegionProfile`, `ExecutionPage` | `REGION_CODE` const + scope signature use |
| Importers | `ImportRegionCommand`, `ImportTasks`, `DistrictResolver`, `PromoteImportRunCommand` | slug→int resolver |
| DTOs | `IndicatorFactDto`, `WarehouseDto`, `FoodBalanceDto` | property type |
| Support | `AndijanMapGeometry` | 2 cell-name spelling fixes |
| Tests | ~30 across Schema/Http/Import/Unit | fixture values |

No CSS, no Blade markup structure changes.

## 12. Rollout

```bash
cd backend
php artisan migrate:fresh --seed
php artisan import:region andijan
php artisan import:tasks andijan
vendor/bin/pest
php artisan serve   # smoke
```

Expected: all tests green; `/dashboard`, `/districts`, `/tasks` render with SOATO-keyed data; `Task` count = 86, district pivot rows = 6, indicator facts intact.

## 13. Risks

- **Risk:** test fixture cascade — many tests need value swaps. *Mitigation:* run full Pest suite after migrations land; failing tests pinpoint missed slugs.
- **Risk:** Postgres composite FK `(region_code, district_code) → districts(region_code, code)` requires same types on both sides. *Mitigation:* all four columns flip in the same migration edit pass; types stay aligned.
- **Risk:** `AndijanMapGeometry::CELLS` Cyrillic names depend on exact `name_full` match. *Mitigation:* §6 lists the two spelling differences and the seeder uses xlsx spelling verbatim.
- **Risk:** `name_latin` previously held free-form slugs (some `_district`, some bare). *Mitigation:* §5.2 documents the convention; legacy slugs preserved where present, new ones transliterated.
- **Risk:** `task_districts` pivot pulls `district_id` (auto-PK), not code — safe. Verified.
- **Risk:** Old `region_code` indexes/uniques carry name strings; renaming the column type shouldn't drop them but Postgres ALTER COLUMN TYPE can fail with FK constraints. *Mitigation:* since we edit migrations in place (`migrate:fresh`), Postgres builds tables fresh — no ALTER needed.
