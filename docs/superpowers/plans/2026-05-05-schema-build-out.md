# Schema Build-Out Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the full Postgres schema described in spec `2026-05-05-fact-tables-and-import-schema-design.md` — 14 new migrations, 2 new seeders, Eloquent models, and a Postgres-backed test setup. End state: `php artisan migrate:fresh --seed` populates a working database that downstream importer + Filament work can build against.

**Architecture:** Three layers added on top of the existing reference tables: indicator catalog (`indicators` + availability), fact data (`indicator_facts` cube + tabular extras), and import infrastructure (runs + staging twins). Natural keys (`region_code`, `district_code`, `indicator_code`, `year`) for cross-table references; surrogate `id`s for lifecycle records. Postgres `jsonb` for label aliases and validation errors. Composite FK `(region_code, district_code) → districts(region_code, code)` requires a denormalization migration on the existing `districts` table.

**Tech Stack:** Laravel 12.58 · PHP 8.2 · PostgreSQL 14 · PHPUnit (default Laravel test framework) · `phpoffice/phpspreadsheet` (added later in Plan 2 for the importer).

**Working directory:** All paths in this plan are relative to `backend/` unless prefixed with `../`. Run all `php artisan`, `composer`, and `phpunit` commands from `backend/`.

---

## File map

**Created:**
- `backend/database/migrations/2026_05_05_000001_add_region_code_to_districts.php`
- `backend/database/migrations/2026_05_05_000002_create_indicators_table.php`
- `backend/database/migrations/2026_05_05_000003_create_region_indicator_availability_table.php`
- `backend/database/migrations/2026_05_05_000004_create_indicator_facts_table.php`
- `backend/database/migrations/2026_05_05_000005_create_food_balance_table.php`
- `backend/database/migrations/2026_05_05_000006_create_warehouses_table.php`
- `backend/database/migrations/2026_05_05_000007_create_guarantee_letters_table.php`
- `backend/database/migrations/2026_05_05_000008_create_promise_targets_table.php`
- `backend/database/migrations/2026_05_05_000009_create_import_runs_table.php`
- `backend/database/migrations/2026_05_05_000010_create_import_files_table.php`
- `backend/database/migrations/2026_05_05_000011_create_data_quality_issues_table.php`
- `backend/database/migrations/2026_05_05_000012_create_import_staging_indicator_facts_table.php`
- `backend/database/migrations/2026_05_05_000013_create_import_staging_food_balance_table.php`
- `backend/database/migrations/2026_05_05_000014_create_import_staging_warehouses_table.php`
- `backend/database/seeders/IndicatorSeeder.php`
- `backend/database/seeders/RegionIndicatorAvailabilitySeeder.php`
- `backend/app/Models/Indicator.php`
- `backend/app/Models/RegionIndicatorAvailability.php`
- `backend/app/Models/IndicatorFact.php`
- `backend/app/Models/FoodBalance.php`
- `backend/app/Models/Warehouse.php`
- `backend/app/Models/GuaranteeLetter.php`
- `backend/app/Models/PromiseTarget.php`
- `backend/app/Models/ImportRun.php`
- `backend/app/Models/ImportFile.php`
- `backend/app/Models/DataQualityIssue.php`
- `backend/app/Models/ImportStagingIndicatorFact.php`
- `backend/app/Models/ImportStagingFoodBalance.php`
- `backend/app/Models/ImportStagingWarehouse.php`
- `backend/app/Enums/Period.php`
- `backend/app/Enums/IndicatorScope.php`
- `backend/app/Enums/AvailabilityStatus.php`
- `backend/app/Enums/PromiseKind.php`
- `backend/app/Enums/ImportRunStatus.php`
- `backend/app/Enums/StagingStatus.php`
- `backend/app/Enums/IssueSeverity.php`
- `backend/tests/Feature/Schema/DistrictsRegionCodeTest.php`
- `backend/tests/Feature/Schema/IndicatorsTableTest.php`
- `backend/tests/Feature/Schema/RegionIndicatorAvailabilityTest.php`
- `backend/tests/Feature/Schema/IndicatorFactsTableTest.php`
- `backend/tests/Feature/Schema/FoodBalanceTableTest.php`
- `backend/tests/Feature/Schema/WarehousesTableTest.php`
- `backend/tests/Feature/Schema/GuaranteeLettersTableTest.php`
- `backend/tests/Feature/Schema/PromiseTargetsTableTest.php`
- `backend/tests/Feature/Schema/ImportRunsTableTest.php`
- `backend/tests/Feature/Schema/ImportFilesTableTest.php`
- `backend/tests/Feature/Schema/DataQualityIssuesTableTest.php`
- `backend/tests/Feature/Schema/ImportStagingTablesTest.php`
- `backend/tests/Feature/Schema/SchemaIntegrityTest.php`

**Modified:**
- `backend/phpunit.xml` (switch test connection to a Postgres test DB)
- `backend/database/seeders/DistrictSeeder.php` (populate new `region_code` column)
- `backend/database/seeders/DatabaseSeeder.php` (call new seeders)

---

## Task 0: Switch test database to Postgres

**Files:**
- Create test DB in Postgres
- Modify: `backend/phpunit.xml`

- [ ] **Step 1: Create the test database**

```powershell
$env:PGPASSWORD = "<your-postgres-password>"
psql -U postgres -h 127.0.0.1 -c "CREATE DATABASE hududlar_monitoringi_test;"
```

Expected: `CREATE DATABASE`. If it already exists, drop and recreate, or skip.

- [ ] **Step 2: Update `backend/phpunit.xml` to use Postgres for tests**

Replace lines 26-28 (`<env name="DB_CONNECTION" value="sqlite"/>` etc.) with:

```xml
            <env name="DB_CONNECTION" value="pgsql"/>
            <env name="DB_HOST" value="127.0.0.1"/>
            <env name="DB_PORT" value="5432"/>
            <env name="DB_DATABASE" value="hududlar_monitoringi_test"/>
            <env name="DB_USERNAME" value="postgres"/>
            <env name="DB_PASSWORD" value="postgres"/>
```

(Use whatever password matches your local Postgres install.)

- [ ] **Step 3: Verify by running the existing test suite against Postgres**

Run: `php artisan test`
Expected: `ExampleTest` passes against Postgres test DB.

- [ ] **Step 4: Commit**

```bash
git add backend/phpunit.xml
git commit -m "test: run PHPUnit suite against Postgres test database

SQLite in-memory does not support the jsonb columns and composite
foreign keys used by the upcoming fact tables."
```

---

## Task 1: Denormalize `region_code` on districts

**Files:**
- Create: `backend/database/migrations/2026_05_05_000001_add_region_code_to_districts.php`
- Create: `backend/tests/Feature/Schema/DistrictsRegionCodeTest.php`
- Modify: `backend/database/seeders/DistrictSeeder.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/DistrictsRegionCodeTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DistrictsRegionCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_districts_table_has_region_code_column(): void
    {
        $this->assertTrue(Schema::hasColumn('districts', 'region_code'));
    }

    public function test_region_code_is_not_null_after_seed(): void
    {
        $this->seed();
        $nullCount = DB::table('districts')->whereNull('region_code')->count();
        $this->assertSame(0, $nullCount, 'all districts must have a region_code');
    }

    public function test_region_code_matches_parent_region(): void
    {
        $this->seed();
        $mismatched = DB::table('districts as d')
            ->join('regions as r', 'd.region_id', '=', 'r.id')
            ->whereColumn('d.region_code', '!=', 'r.code')
            ->count();
        $this->assertSame(0, $mismatched);
    }

    public function test_unique_region_code_district_code(): void
    {
        $this->seed();
        $duplicates = DB::table('districts')
            ->select('region_code', 'code', DB::raw('COUNT(*) as c'))
            ->groupBy('region_code', 'code')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        $this->assertSame(0, $duplicates);
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=DistrictsRegionCodeTest`
Expected: FAIL with "column region_code does not exist".

- [ ] **Step 3: Write the migration**

Create `backend/database/migrations/2026_05_05_000001_add_region_code_to_districts.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->string('region_code', 32)->nullable()->after('region_id');
        });

        DB::statement(<<<'SQL'
            UPDATE districts d
               SET region_code = r.code
              FROM regions r
             WHERE d.region_id = r.id
        SQL);

        Schema::table('districts', function (Blueprint $table) {
            $table->string('region_code', 32)->nullable(false)->change();
            $table->unique(['region_code', 'code'], 'uq_districts_region_code_code');
            $table->index('region_code', 'idx_districts_region_code');
        });
    }

    public function down(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->dropUnique('uq_districts_region_code_code');
            $table->dropIndex('idx_districts_region_code');
            $table->dropColumn('region_code');
        });
    }
};
```

- [ ] **Step 4: Update `DistrictSeeder` to populate `region_code`**

In `backend/database/seeders/DistrictSeeder.php`, inside the `foreach ($region['districts'] as $district)` loop, add `'region_code' => $regionCode` to the row array. Compute `$regionCode` once per region using the same `RegionSeeder::CODE_MAP` lookup. Final loop body:

```php
foreach ($regions as $region) {
    $regionId = $region['id'];
    $folderShort = trim(explode('. ', $region['folder_name'], 2)[1] ?? '');
    $regionCode = \Database\Seeders\RegionSeeder::CODE_MAP[$folderShort] ?? null;
    if (! $regionCode) {
        continue;
    }

    foreach ($region['districts'] as $district) {
        $code = $this->makeCode($region['id'], $district);
        $altLabels = array_filter([
            $district['name_short'] ?? null,
            $district['name_full'] ?? null,
            $district['name_latin'] ?? null,
        ]);

        $rows[] = [
            'region_id'   => $regionId,
            'region_code' => $regionCode,
            'code'        => $code,
            'name_short'  => $district['name_short'],
            'name_full'   => $district['name_full'],
            'name_latin'  => $district['name_latin'],
            'alt_labels'  => json_encode(array_values(array_unique($altLabels)), JSON_UNESCAPED_UNICODE),
            'kind'        => $district['kind'],
            'sort_order'  => $district['sort_order'],
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
    }
}
```

Also add `'region_code'` to the upsert column list:

```php
DB::table('districts')->upsert(
    $chunk,
    ['region_code', 'code'],
    ['name_short', 'name_full', 'name_latin', 'alt_labels', 'kind', 'sort_order', 'updated_at']
);
```

Make `RegionSeeder::CODE_MAP` `public` so `DistrictSeeder` can read it. Edit `RegionSeeder.php` line `private const CODE_MAP` → `public const CODE_MAP`.

- [ ] **Step 5: Run the test, confirm it passes**

Run: `php artisan test --filter=DistrictsRegionCodeTest`
Expected: PASS, all 4 assertions green.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_05_05_000001_add_region_code_to_districts.php \
        backend/database/seeders/DistrictSeeder.php \
        backend/database/seeders/RegionSeeder.php \
        backend/tests/Feature/Schema/DistrictsRegionCodeTest.php
git commit -m "feat(schema): denormalize region_code onto districts

Adds region_code column with backfill and (region_code, code) unique
index. Enables fact tables to FK on natural keys via composite FK to
districts(region_code, code)."
```

---

## Task 2: `indicators` table + seeder + model + enums

**Files:**
- Create: `backend/database/migrations/2026_05_05_000002_create_indicators_table.php`
- Create: `backend/app/Models/Indicator.php`
- Create: `backend/app/Enums/IndicatorScope.php`
- Create: `backend/database/seeders/IndicatorSeeder.php`
- Create: `backend/tests/Feature/Schema/IndicatorsTableTest.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/IndicatorsTableTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\Indicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndicatorsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['id','code','label_full','label_short','sector','module_code','scope',
                 'default_unit','lower_is_better','supported_periods','has_growth_pct',
                 'has_pct_of_plan','has_sentinel','count_extra_label','count_extra_2_label',
                 'icon','sort_order','notes','created_at','updated_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('indicators', $c), "missing column $c");
        }
    }

    public function test_seed_inserts_twenty_indicators(): void
    {
        $this->seed();
        $this->assertSame(20, Indicator::count());
    }

    public function test_seed_includes_required_codes(): void
    {
        $this->seed();
        $required = ['grp','industry','agriculture','construction','services','inflation',
                     'budget','budget_investment','investment','export','unemployment',
                     'poverty','small_business_share','localization','energy_electricity',
                     'energy_gas','jobs','legalization','mfy_clear','microprojects'];
        foreach ($required as $code) {
            $this->assertNotNull(Indicator::where('code', $code)->first(),
                "missing indicator $code");
        }
    }

    public function test_poverty_has_sentinel_and_lower_is_better(): void
    {
        $this->seed();
        $poverty = Indicator::where('code', 'poverty')->firstOrFail();
        $this->assertTrue($poverty->lower_is_better);
        $this->assertTrue($poverty->has_sentinel);
    }

    public function test_construction_is_region_scope(): void
    {
        $this->seed();
        $this->assertSame('region', Indicator::where('code', 'construction')->value('scope'));
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=IndicatorsTableTest`
Expected: FAIL with "table indicators does not exist".

- [ ] **Step 3: Write the enum**

Create `backend/app/Enums/IndicatorScope.php`:

```php
<?php

namespace App\Enums;

enum IndicatorScope: string
{
    case Region = 'region';
    case District = 'district';
    case Both = 'both';
}
```

- [ ] **Step 4: Write the migration**

Create `backend/database/migrations/2026_05_05_000002_create_indicators_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 48)->unique();
            $table->string('label_full', 192);
            $table->string('label_short', 96);
            $table->string('sector', 96)->nullable();
            $table->string('module_code', 32)->nullable();
            $table->string('scope', 16);                       // 'region' | 'district' | 'both'
            $table->string('default_unit', 48);
            $table->boolean('lower_is_better')->default(false);
            $table->jsonb('supported_periods')->default(json_encode(['q1','h1','m9','year']));
            $table->boolean('has_growth_pct')->default(false);
            $table->boolean('has_pct_of_plan')->default(false);
            $table->boolean('has_sentinel')->default(false);
            $table->string('count_extra_label', 64)->nullable();
            $table->string('count_extra_2_label', 64)->nullable();
            $table->string('icon', 32)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('module_code')->references('code')->on('modules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};
```

- [ ] **Step 5: Write the model**

Create `backend/app/Models/Indicator.php`:

```php
<?php

namespace App\Models;

use App\Enums\IndicatorScope;
use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    protected $primaryKey = 'id';

    protected $fillable = [
        'code', 'label_full', 'label_short', 'sector', 'module_code', 'scope',
        'default_unit', 'lower_is_better', 'supported_periods',
        'has_growth_pct', 'has_pct_of_plan', 'has_sentinel',
        'count_extra_label', 'count_extra_2_label', 'icon', 'sort_order', 'notes',
    ];

    protected $casts = [
        'supported_periods' => 'array',
        'lower_is_better'   => 'boolean',
        'has_growth_pct'    => 'boolean',
        'has_pct_of_plan'   => 'boolean',
        'has_sentinel'      => 'boolean',
        'scope'             => IndicatorScope::class,
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
```

- [ ] **Step 6: Write the seeder**

Create `backend/database/seeders/IndicatorSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndicatorSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $allPeriods = json_encode(['q1','h1','m9','year']);
        $yearOnly   = json_encode(['year']);
        $h1Year     = json_encode(['h1','year']);

        $rows = [
            // code, label_full, label_short, sector, module, scope, unit, lower, periods, growth, pct_plan, sentinel, ce, ce2, icon, sort
            ['grp',                  'ЯҲМ',                                            'ЯҲМ',                          'Макро иқтисодиёт',     'macro',          'region',   'млрд сўм',    false, $allPeriods, true,  false, false, null,                           null,           'trend',     10],
            ['industry',             'Саноат маҳсулотлари',                             'Саноат',                      'Макро иқтисодиёт',     'macro',          'both',     'млрд сўм',    false, $allPeriods, true,  false, false, null,                           null,           'factory',   20],
            ['agriculture',          'Қишлоқ хўжалиги маҳсулотлари',                    'Қишлоқ хўжалиги',             'Макро иқтисодиёт',     'macro',          'both',     'млрд сўм',    false, $allPeriods, true,  false, false, null,                           null,           'trend',     30],
            ['construction',         'Қурилиш ишлари',                                  'Қурилиш',                     'Макро иқтисодиёт',     'macro',          'region',   'млрд сўм',    false, $allPeriods, true,  false, false, null,                           null,           'bank',      40],
            ['services',             'Бозор хизматлари',                                'Хизматлар',                   'Макро иқтисодиёт',     'macro',          'both',     'млрд сўм',    false, $allPeriods, true,  false, false, null,                           null,           'globe',     50],
            ['inflation',            'Инфляция ва асосий озиқ-овқат нархлари',           'Инфляция',                    'Инфляция',             'inflation',      'region',   '%',           true,  $allPeriods, false, false, false, null,                           null,           'price',     60],
            ['budget',               'Бюджет тушумлари',                                'Бюджет',                      'Бюджет',               'budget',         'both',     'млрд сўм',    false, $allPeriods, false, true,  false, null,                           null,           'bank',      70],
            ['budget_investment',    'Бюджет инвестициялари ўзлаштирилиши',             'Бюджет инвест',               'Бюджет инвестициялари','budget_invest',  'both',     'млн сўм',     false, $allPeriods, false, true,  false, 'Объектлар сони',                'Ишга туширилаётган объектлар', 'bank',      80],
            ['investment',           'Хорижий инвестициялар',                          'Инвестиция',                  'Хорижий инвестиция',   'foreign_invest', 'both',     'млн доллар',  false, $allPeriods, false, true,  false, 'Лойиҳалар сони',                'Иш ўринлари', 'rocket',    90],
            ['export',               'Экспорт ҳажми',                                  'Экспорт',                     'Экспорт',              'export',         'both',     'минг доллар', false, $allPeriods, true,  false, false, 'Экспортчи корхоналар сони',     null,           'globe',    100],
            ['unemployment',         'Ишсизлик даражаси',                              'Ишсизлик',                    'Бандлик ва камбағаллик','employment',     'both',     '%',           true,  $h1Year,     false, false, false, null,                           null,           'users',    110],
            ['poverty',              'Камбағаллик даражаси',                           'Камбағаллик',                 'Бандлик ва камбағаллик','employment',     'both',     '%',           true,  $h1Year,     false, false, true,  null,                           null,           'users',    120],
            ['small_business_share', 'Кичик тадбиркорликнинг ЯҲМдаги улуши',           'Кичик бизнес улуши',          'Бандлик ва камбағаллик','macro',          'region',   '%',           false, $yearOnly,   false, false, false, null,                           null,           'briefcase', 130],
            ['localization',         'Маҳаллийлаштириш дастури',                       'Маҳаллийлаштириш',            'Саноат',               'macro',          'district', 'млн сўм',     false, $h1Year,     false, false, false, 'Лойиҳалар сони',                null,           'factory',  140],
            ['energy_electricity',   'Электр энергиясини тежаш',                       'Электр тежаш',                'Саноат',               'macro',          'district', 'млн кВт·с',   false, $h1Year,     false, false, false, null,                           null,           'trend',    150],
            ['energy_gas',           'Табиий газни тежаш',                             'Газ тежаш',                   'Саноат',               'macro',          'district', 'млн м³',      false, $h1Year,     false, false, false, null,                           null,           'trend',    160],
            ['jobs',                 'Доимий ишга жойлаштириш',                        'Ишга жойлаштириш',            'Бандлик ва камбағаллик','employment',     'district', 'минг нафар',  false, $h1Year,     false, false, false, null,                           null,           'users',    170],
            ['legalization',         'Норасмий бандларни легаллаштириш',               'Легаллаштириш',               'Бандлик ва камбағаллик','employment',     'district', 'минг нафар',  false, $h1Year,     false, false, false, null,                           null,           'users',    180],
            ['mfy_clear',            'Камбағаллик ва ишсизликдан холи МФЙлар',         'Камбағалликдан холи МФЙлар',  'Бандлик ва камбағаллик','employment',     'district', 'count',       false, $h1Year,     false, false, false, null,                           null,           'users',    190],
            ['microprojects',        'Микролойиҳалар',                                 'Микролойиҳа',                 'Бандлик ва камбағаллик','employment',     'district', 'count',       false, $h1Year,     false, false, false, null,                           null,           'users',    200],
        ];

        $records = [];
        foreach ($rows as $r) {
            $records[] = [
                'code'                => $r[0],
                'label_full'          => $r[1],
                'label_short'         => $r[2],
                'sector'              => $r[3],
                'module_code'         => $r[4],
                'scope'               => $r[5],
                'default_unit'        => $r[6],
                'lower_is_better'     => $r[7],
                'supported_periods'   => $r[8],
                'has_growth_pct'      => $r[9],
                'has_pct_of_plan'     => $r[10],
                'has_sentinel'        => $r[11],
                'count_extra_label'   => $r[12],
                'count_extra_2_label' => $r[13],
                'icon'                => $r[14],
                'sort_order'          => $r[15],
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        DB::table('indicators')->upsert(
            $records,
            ['code'],
            ['label_full','label_short','sector','module_code','scope','default_unit',
             'lower_is_better','supported_periods','has_growth_pct','has_pct_of_plan',
             'has_sentinel','count_extra_label','count_extra_2_label','icon','sort_order','updated_at']
        );

        $this->command->info('Seeded ' . count($records) . ' indicators.');
    }
}
```

- [ ] **Step 7: Wire `IndicatorSeeder` into `DatabaseSeeder`**

Edit `backend/database/seeders/DatabaseSeeder.php`:

```php
public function run(): void
{
    $this->call([
        ReportingYearSeeder::class,
        ModuleSeeder::class,
        RegionSeeder::class,
        DistrictSeeder::class,
        IndicatorSeeder::class,
    ]);
}
```

- [ ] **Step 8: Run the test, confirm it passes**

Run: `php artisan test --filter=IndicatorsTableTest`
Expected: PASS (5 assertions).

- [ ] **Step 9: Commit**

```bash
git add backend/database/migrations/2026_05_05_000002_create_indicators_table.php \
        backend/app/Models/Indicator.php \
        backend/app/Enums/IndicatorScope.php \
        backend/database/seeders/IndicatorSeeder.php \
        backend/database/seeders/DatabaseSeeder.php \
        backend/tests/Feature/Schema/IndicatorsTableTest.php
git commit -m "feat(schema): add indicators catalog with 20 seeded KPIs"
```

---

## Task 3: `region_indicator_availability` table + seeder + model + enum

**Files:**
- Create: `backend/database/migrations/2026_05_05_000003_create_region_indicator_availability_table.php`
- Create: `backend/app/Models/RegionIndicatorAvailability.php`
- Create: `backend/app/Enums/AvailabilityStatus.php`
- Create: `backend/database/seeders/RegionIndicatorAvailabilitySeeder.php`
- Create: `backend/tests/Feature/Schema/RegionIndicatorAvailabilityTest.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/RegionIndicatorAvailabilityTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Enums\AvailabilityStatus;
use App\Models\RegionIndicatorAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionIndicatorAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_row_per_region_indicator_pair(): void
    {
        $this->seed();
        $regions = \DB::table('regions')->count();
        $indicators = \DB::table('indicators')->count();
        $this->assertSame($regions * $indicators, RegionIndicatorAvailability::count());
    }

    public function test_default_status_is_available(): void
    {
        $this->seed();
        $available = RegionIndicatorAvailability::where('status', AvailabilityStatus::Available)->count();
        $total = RegionIndicatorAvailability::count();
        $this->assertGreaterThan($total - 20, $available, 'most rows should default to available');
    }

    public function test_tashkent_city_agriculture_is_not_applicable(): void
    {
        $this->seed();
        $row = RegionIndicatorAvailability::where('region_code', 'tashkent_city')
            ->where('indicator_code', 'agriculture')->firstOrFail();
        $this->assertSame(AvailabilityStatus::NotApplicable, $row->status);
    }

    public function test_navoiy_macro_indicators_are_blocked(): void
    {
        $this->seed();
        $blockedCodes = ['grp','industry','agriculture','construction','services'];
        foreach ($blockedCodes as $code) {
            $row = RegionIndicatorAvailability::where('region_code', 'navoiy')
                ->where('indicator_code', $code)->firstOrFail();
            $this->assertSame(AvailabilityStatus::Blocked, $row->status,
                "navoiy × $code should be blocked");
            $this->assertNotNull($row->note);
        }
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=RegionIndicatorAvailabilityTest`
Expected: FAIL with "table region_indicator_availability does not exist".

- [ ] **Step 3: Write the enum**

Create `backend/app/Enums/AvailabilityStatus.php`:

```php
<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Available     = 'available';
    case NotApplicable = 'not_applicable';
    case Blocked       = 'blocked';
    case Pending       = 'pending';
}
```

- [ ] **Step 4: Write the migration**

Create `backend/database/migrations/2026_05_05_000003_create_region_indicator_availability_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('region_indicator_availability', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->string('indicator_code', 48);
            $table->string('status', 16)->default('available');
            $table->text('note')->nullable();
            $table->date('blocked_until')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['region_code', 'indicator_code'], 'uq_ria_region_indicator');
            $table->foreign('region_code')->references('code')->on('regions')->cascadeOnDelete();
            $table->foreign('indicator_code')->references('code')->on('indicators')->cascadeOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_indicator_availability');
    }
};
```

- [ ] **Step 5: Write the model**

Create `backend/app/Models/RegionIndicatorAvailability.php`:

```php
<?php

namespace App\Models;

use App\Enums\AvailabilityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionIndicatorAvailability extends Model
{
    protected $table = 'region_indicator_availability';

    protected $fillable = [
        'region_code', 'indicator_code', 'status', 'note',
        'blocked_until', 'updated_by_user_id',
    ];

    protected $casts = [
        'status'        => AvailabilityStatus::class,
        'blocked_until' => 'date',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_code', 'code');
    }
}
```

Note: `Region` model doesn't exist yet — create a minimal one:

Create `backend/app/Models/Region.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['code','name_short','name_full','name_latin','folder_name','sort_order','has_districts'];

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
```

- [ ] **Step 6: Write the seeder**

Create `backend/database/seeders/RegionIndicatorAvailabilitySeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionIndicatorAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $regions    = DB::table('regions')->pluck('code');
        $indicators = DB::table('indicators')->pluck('code');

        $rows = [];
        foreach ($regions as $regionCode) {
            foreach ($indicators as $indicatorCode) {
                $rows[] = [
                    'region_code'    => $regionCode,
                    'indicator_code' => $indicatorCode,
                    'status'         => 'available',
                    'note'           => null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('region_indicator_availability')->upsert(
                $chunk,
                ['region_code', 'indicator_code'],
                ['status', 'note', 'updated_at']
            );
        }

        // Apply known exceptions

        DB::table('region_indicator_availability')
            ->where('region_code', 'tashkent_city')
            ->where('indicator_code', 'agriculture')
            ->update([
                'status'     => 'not_applicable',
                'note'       => 'Тошкент шаҳри учун қишлоқ хўжалиги кесими йўқ.',
                'updated_at' => $now,
            ]);

        DB::table('region_indicator_availability')
            ->where('region_code', 'navoiy')
            ->whereIn('indicator_code', ['grp','industry','agriculture','construction','services'])
            ->update([
                'status'     => 'blocked',
                'note'       => 'Манба макро 1.2 саҳифасида Сурхондарё маълумоти жойлаштирилган. Юқори манбадан тузатиш кутилмоқда.',
                'updated_at' => $now,
            ]);

        $this->command->info('Seeded ' . count($rows) . ' region_indicator_availability rows.');
    }
}
```

- [ ] **Step 7: Wire into `DatabaseSeeder`**

Edit `backend/database/seeders/DatabaseSeeder.php`, add to the `call` array (after `IndicatorSeeder::class`):

```php
RegionIndicatorAvailabilitySeeder::class,
```

- [ ] **Step 8: Run the test, confirm it passes**

Run: `php artisan test --filter=RegionIndicatorAvailabilityTest`
Expected: PASS (4 assertions).

- [ ] **Step 9: Commit**

```bash
git add backend/database/migrations/2026_05_05_000003_create_region_indicator_availability_table.php \
        backend/app/Models/RegionIndicatorAvailability.php \
        backend/app/Models/Region.php \
        backend/app/Enums/AvailabilityStatus.php \
        backend/database/seeders/RegionIndicatorAvailabilitySeeder.php \
        backend/database/seeders/DatabaseSeeder.php \
        backend/tests/Feature/Schema/RegionIndicatorAvailabilityTest.php
git commit -m "feat(schema): add region_indicator_availability matrix"
```

---

## Task 4: `indicator_facts` table + model + Period enum

**Files:**
- Create: `backend/database/migrations/2026_05_05_000004_create_indicator_facts_table.php`
- Create: `backend/app/Models/IndicatorFact.php`
- Create: `backend/app/Enums/Period.php`
- Create: `backend/tests/Feature/Schema/IndicatorFactsTableTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/IndicatorFactsTableTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\IndicatorFact;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndicatorFactsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['region_code','district_code','year','indicator_code','period',
                 'plan_value','expected_value','actual_hokimyat','actual_statkom',
                 'growth_pct','pct_of_plan','count_extra','count_extra_2',
                 'is_sentinel','sentinel_label','unit','source_label',
                 'hokimyat_reported_at','statkom_published_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('indicator_facts', $c), "missing column $c");
        }
    }

    public function test_unique_constraint_blocks_duplicates(): void
    {
        $this->seed();
        IndicatorFact::create([
            'region_code' => 'andijan', 'district_code' => null, 'year' => 2026,
            'indicator_code' => 'grp', 'period' => 'h1',
            'plan_value' => 52100.8, 'unit' => 'млрд сўм', 'source_label' => 'test',
        ]);
        $this->expectException(QueryException::class);
        IndicatorFact::create([
            'region_code' => 'andijan', 'district_code' => null, 'year' => 2026,
            'indicator_code' => 'grp', 'period' => 'h1',
            'plan_value' => 99.0, 'unit' => 'млрд сўм', 'source_label' => 'dup',
        ]);
    }

    public function test_district_rollup_is_allowed_via_null_district_code(): void
    {
        $this->seed();
        $row = IndicatorFact::create([
            'region_code' => 'andijan', 'district_code' => null, 'year' => 2026,
            'indicator_code' => 'industry', 'period' => 'q1',
            'plan_value' => 25945.4, 'growth_pct' => 108.4,
            'unit' => 'млрд сўм', 'source_label' => 'test',
        ]);
        $this->assertNotNull($row->id);
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=IndicatorFactsTableTest`
Expected: FAIL with "table indicator_facts does not exist".

- [ ] **Step 3: Write the Period enum**

Create `backend/app/Enums/Period.php`:

```php
<?php

namespace App\Enums;

enum Period: string
{
    case Q1   = 'q1';
    case H1   = 'h1';
    case M9   = 'm9';
    case Year = 'year';
}
```

- [ ] **Step 4: Write the migration**

Create `backend/database/migrations/2026_05_05_000004_create_indicator_facts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('indicator_facts', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->string('district_code', 64)->nullable();         // NULL = region rollup
            $table->smallInteger('year');
            $table->string('indicator_code', 48);
            $table->string('period', 8);                              // q1 | h1 | m9 | year

            $table->decimal('plan_value', 20, 6)->nullable();
            $table->decimal('expected_value', 20, 6)->nullable();
            $table->decimal('actual_hokimyat', 20, 6)->nullable();
            $table->decimal('actual_statkom', 20, 6)->nullable();
            $table->decimal('growth_pct', 10, 4)->nullable();
            $table->decimal('pct_of_plan', 10, 4)->nullable();
            $table->integer('count_extra')->nullable();
            $table->integer('count_extra_2')->nullable();

            $table->boolean('is_sentinel')->default(false);
            $table->string('sentinel_label', 64)->nullable();

            $table->string('unit', 48);
            $table->string('source_label', 255);
            $table->timestamp('hokimyat_reported_at')->nullable();
            $table->timestamp('statkom_published_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['region_code', 'district_code', 'year', 'indicator_code', 'period'],
                'uq_indicator_facts'
            );
            $table->index(['region_code', 'year', 'indicator_code'], 'idx_facts_rgn_yr_ind');
            $table->index(['region_code', 'district_code', 'year'], 'idx_facts_rgn_dist_yr');
            $table->index(['year', 'indicator_code', 'period'], 'idx_facts_yr_ind_per');

            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign(['region_code', 'district_code'])
                  ->references(['region_code', 'code'])->on('districts');
            $table->foreign('year')->references('year')->on('reporting_years');
            $table->foreign('indicator_code')->references('code')->on('indicators');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_facts');
    }
};
```

- [ ] **Step 5: Write the model**

Create `backend/app/Models/IndicatorFact.php`:

```php
<?php

namespace App\Models;

use App\Enums\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorFact extends Model
{
    protected $fillable = [
        'region_code','district_code','year','indicator_code','period',
        'plan_value','expected_value','actual_hokimyat','actual_statkom',
        'growth_pct','pct_of_plan','count_extra','count_extra_2',
        'is_sentinel','sentinel_label','unit','source_label',
        'hokimyat_reported_at','statkom_published_at',
    ];

    protected $casts = [
        'period'               => Period::class,
        'plan_value'           => 'decimal:6',
        'expected_value'       => 'decimal:6',
        'actual_hokimyat'      => 'decimal:6',
        'actual_statkom'       => 'decimal:6',
        'growth_pct'           => 'decimal:4',
        'pct_of_plan'          => 'decimal:4',
        'is_sentinel'          => 'boolean',
        'hokimyat_reported_at' => 'datetime',
        'statkom_published_at' => 'datetime',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_code', 'code');
    }
}
```

- [ ] **Step 6: Run the test, confirm it passes**

Run: `php artisan test --filter=IndicatorFactsTableTest`
Expected: PASS (3 assertions).

- [ ] **Step 7: Commit**

```bash
git add backend/database/migrations/2026_05_05_000004_create_indicator_facts_table.php \
        backend/app/Models/IndicatorFact.php \
        backend/app/Enums/Period.php \
        backend/tests/Feature/Schema/IndicatorFactsTableTest.php
git commit -m "feat(schema): add indicator_facts cube table"
```

---

## Task 5: `food_balance` table + model

**Files:**
- Create: `backend/database/migrations/2026_05_05_000005_create_food_balance_table.php`
- Create: `backend/app/Models/FoodBalance.php`
- Create: `backend/tests/Feature/Schema/FoodBalanceTableTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/FoodBalanceTableTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\FoodBalance;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoodBalanceTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['region_code','year','product','product_sort_order',
                 'resource_total','year_start_stock','production','import_volume',
                 'use_total','use_household','use_processing','use_other',
                 'per_capita_norm','per_capita_balance','local_supply_ratio','year_end_stock',
                 'source_label'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('food_balance', $c), "missing column $c");
        }
    }

    public function test_unique_region_year_product(): void
    {
        $this->seed();
        FoodBalance::create([
            'region_code' => 'andijan', 'year' => 2026, 'product' => 'Ун',
            'production' => 368.3, 'source_label' => 'test',
        ]);
        $this->expectException(QueryException::class);
        FoodBalance::create([
            'region_code' => 'andijan', 'year' => 2026, 'product' => 'Ун',
            'production' => 999.0, 'source_label' => 'dup',
        ]);
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=FoodBalanceTableTest`
Expected: FAIL "table food_balance does not exist".

- [ ] **Step 3: Write the migration**

Create `backend/database/migrations/2026_05_05_000005_create_food_balance_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('food_balance', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->smallInteger('year');
            $table->string('product', 96);
            $table->smallInteger('product_sort_order')->default(0);

            $table->decimal('resource_total', 20, 6)->nullable();
            $table->decimal('year_start_stock', 20, 6)->nullable();
            $table->decimal('production', 20, 6)->nullable();
            $table->decimal('import_volume', 20, 6)->nullable();
            $table->decimal('use_total', 20, 6)->nullable();
            $table->decimal('use_household', 20, 6)->nullable();
            $table->decimal('use_processing', 20, 6)->nullable();
            $table->decimal('use_other', 20, 6)->nullable();
            $table->decimal('per_capita_norm', 20, 6)->nullable();
            $table->decimal('per_capita_balance', 20, 6)->nullable();
            $table->decimal('local_supply_ratio', 20, 6)->nullable();
            $table->decimal('year_end_stock', 20, 6)->nullable();

            $table->string('source_label', 255);
            $table->timestamps();

            $table->unique(['region_code', 'year', 'product'], 'uq_food_balance');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('year')->references('year')->on('reporting_years');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_balance');
    }
};
```

- [ ] **Step 4: Write the model**

Create `backend/app/Models/FoodBalance.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodBalance extends Model
{
    protected $table = 'food_balance';

    protected $fillable = [
        'region_code','year','product','product_sort_order',
        'resource_total','year_start_stock','production','import_volume',
        'use_total','use_household','use_processing','use_other',
        'per_capita_norm','per_capita_balance','local_supply_ratio','year_end_stock',
        'source_label',
    ];

    protected $casts = [
        'resource_total'    => 'decimal:6',
        'year_start_stock'  => 'decimal:6',
        'production'        => 'decimal:6',
        'import_volume'     => 'decimal:6',
        'use_total'         => 'decimal:6',
        'use_household'     => 'decimal:6',
        'use_processing'    => 'decimal:6',
        'use_other'         => 'decimal:6',
        'per_capita_norm'   => 'decimal:6',
        'per_capita_balance'=> 'decimal:6',
        'local_supply_ratio'=> 'decimal:6',
        'year_end_stock'    => 'decimal:6',
    ];
}
```

- [ ] **Step 5: Run the test, confirm it passes**

Run: `php artisan test --filter=FoodBalanceTableTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_05_05_000005_create_food_balance_table.php \
        backend/app/Models/FoodBalance.php \
        backend/tests/Feature/Schema/FoodBalanceTableTest.php
git commit -m "feat(schema): add food_balance table"
```

---

## Task 6: `warehouses` table + model

**Files:**
- Create: `backend/database/migrations/2026_05_05_000006_create_warehouses_table.php`
- Create: `backend/app/Models/Warehouse.php`
- Create: `backend/tests/Feature/Schema/WarehousesTableTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/WarehousesTableTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarehousesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['region_code','district_code','year',
                 'reserve_warehouses','reserve_capacity_t',
                 'cold_storage_count','cold_storage_capacity_t',
                 'new_small_cold_count','new_small_cold_capacity_t','new_small_cold_mfys',
                 'new_large_cold_count','new_large_cold_capacity_t','source_label'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('warehouses', $c), "missing column $c");
        }
    }

    public function test_district_rollup_via_null_district_code(): void
    {
        $this->seed();
        $row = Warehouse::create([
            'region_code' => 'andijan', 'district_code' => null, 'year' => 2026,
            'reserve_warehouses' => 89, 'cold_storage_count' => 320,
            'source_label' => 'test',
        ]);
        $this->assertNotNull($row->id);
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=WarehousesTableTest`
Expected: FAIL.

- [ ] **Step 3: Write the migration**

Create `backend/database/migrations/2026_05_05_000006_create_warehouses_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->string('district_code', 64)->nullable();
            $table->smallInteger('year');

            $table->integer('reserve_warehouses')->nullable();
            $table->integer('reserve_capacity_t')->nullable();
            $table->integer('cold_storage_count')->nullable();
            $table->integer('cold_storage_capacity_t')->nullable();
            $table->integer('new_small_cold_count')->nullable();
            $table->integer('new_small_cold_capacity_t')->nullable();
            $table->integer('new_small_cold_mfys')->nullable();
            $table->integer('new_large_cold_count')->nullable();
            $table->integer('new_large_cold_capacity_t')->nullable();

            $table->string('source_label', 255);
            $table->timestamps();

            $table->unique(['region_code', 'district_code', 'year'], 'uq_warehouses');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign(['region_code', 'district_code'])
                  ->references(['region_code', 'code'])->on('districts');
            $table->foreign('year')->references('year')->on('reporting_years');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
```

- [ ] **Step 4: Write the model**

Create `backend/app/Models/Warehouse.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = [
        'region_code','district_code','year',
        'reserve_warehouses','reserve_capacity_t',
        'cold_storage_count','cold_storage_capacity_t',
        'new_small_cold_count','new_small_cold_capacity_t','new_small_cold_mfys',
        'new_large_cold_count','new_large_cold_capacity_t','source_label',
    ];
}
```

- [ ] **Step 5: Run the test, confirm it passes**

Run: `php artisan test --filter=WarehousesTableTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_05_05_000006_create_warehouses_table.php \
        backend/app/Models/Warehouse.php \
        backend/tests/Feature/Schema/WarehousesTableTest.php
git commit -m "feat(schema): add warehouses table"
```

---

## Task 7: `guarantee_letters` table + model

**Files:**
- Create: `backend/database/migrations/2026_05_05_000007_create_guarantee_letters_table.php`
- Create: `backend/app/Models/GuaranteeLetter.php`
- Create: `backend/tests/Feature/Schema/GuaranteeLettersTableTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/GuaranteeLettersTableTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\GuaranteeLetter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GuaranteeLettersTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['region_code','year','source_path','sha256','paragraph_count',
                 'raw_text','signed_at','status','imported_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('guarantee_letters', $c), "missing column $c");
        }
    }

    public function test_unique_region_year(): void
    {
        $this->seed();
        GuaranteeLetter::create([
            'region_code' => 'andijan', 'year' => 2026,
            'paragraph_count' => 110, 'raw_text' => 'lorem',
            'status' => 'imported',
        ]);
        $this->expectException(QueryException::class);
        GuaranteeLetter::create([
            'region_code' => 'andijan', 'year' => 2026,
            'paragraph_count' => 9, 'raw_text' => 'dup',
            'status' => 'imported',
        ]);
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=GuaranteeLettersTableTest`
Expected: FAIL.

- [ ] **Step 3: Write the migration**

Create `backend/database/migrations/2026_05_05_000007_create_guarantee_letters_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guarantee_letters', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->smallInteger('year');
            $table->string('source_path', 512)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->integer('paragraph_count')->nullable();
            $table->text('raw_text')->nullable();
            $table->date('signed_at')->nullable();
            $table->string('status', 16)->default('pending');     // 'pending' | 'imported' | 'archived'
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['region_code', 'year'], 'uq_guarantee_letter');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('year')->references('year')->on('reporting_years');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guarantee_letters');
    }
};
```

- [ ] **Step 4: Write the model**

Create `backend/app/Models/GuaranteeLetter.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuaranteeLetter extends Model
{
    protected $fillable = [
        'region_code','year','source_path','sha256','paragraph_count',
        'raw_text','signed_at','status','imported_at',
    ];

    protected $casts = [
        'signed_at'    => 'date',
        'imported_at'  => 'datetime',
    ];

    public function promiseTargets(): HasMany
    {
        return $this->hasMany(PromiseTarget::class);
    }
}
```

- [ ] **Step 5: Run the test, confirm it passes**

Run: `php artisan test --filter=GuaranteeLettersTableTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_05_05_000007_create_guarantee_letters_table.php \
        backend/app/Models/GuaranteeLetter.php \
        backend/tests/Feature/Schema/GuaranteeLettersTableTest.php
git commit -m "feat(schema): add guarantee_letters table"
```

---

## Task 8: `promise_targets` table + model + PromiseKind enum

**Files:**
- Create: `backend/database/migrations/2026_05_05_000008_create_promise_targets_table.php`
- Create: `backend/app/Models/PromiseTarget.php`
- Create: `backend/app/Enums/PromiseKind.php`
- Create: `backend/tests/Feature/Schema/PromiseTargetsTableTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/PromiseTargetsTableTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Enums\PromiseKind;
use App\Models\GuaranteeLetter;
use App\Models\PromiseTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromiseTargetsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['guarantee_letter_id','region_code','year','kind','title','body',
                 'sector','indicator_code','period','target_value','target_text','direction',
                 'target_districts','source_paragraph_index'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('promise_targets', $c), "missing column $c");
        }
    }

    public function test_creates_numeric_promise_with_indicator_link(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::create([
            'region_code' => 'andijan', 'year' => 2026, 'paragraph_count' => 110,
            'raw_text' => 'lorem', 'status' => 'imported',
        ]);
        $promise = PromiseTarget::create([
            'guarantee_letter_id' => $letter->id,
            'region_code' => 'andijan', 'year' => 2026,
            'kind' => PromiseKind::Numeric, 'title' => 'GRP H1 = 52,100.8',
            'body' => 'Биринчи ярим йилликда…',
            'sector' => 'Макро иқтисодиёт',
            'indicator_code' => 'grp', 'period' => 'h1',
            'target_value' => 52100.8, 'direction' => 'higher',
            'source_paragraph_index' => 3,
        ]);
        $this->assertNotNull($promise->id);
        $this->assertSame('grp', $promise->indicator_code);
    }

    public function test_target_districts_jsonb_round_trip(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::create([
            'region_code' => 'andijan', 'year' => 2026, 'paragraph_count' => 1,
            'raw_text' => 'x', 'status' => 'imported',
        ]);
        $promise = PromiseTarget::create([
            'guarantee_letter_id' => $letter->id,
            'region_code' => 'andijan', 'year' => 2026,
            'kind' => PromiseKind::Narrative, 'title' => 'Reopen factories',
            'body' => 'Хонобод шаҳри ва Шаҳрихон тумани…',
            'target_districts' => ['city', 'shahrikhan_district'],
            'source_paragraph_index' => 7,
        ]);
        $promise->refresh();
        $this->assertSame(['city','shahrikhan_district'], $promise->target_districts);
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=PromiseTargetsTableTest`
Expected: FAIL.

- [ ] **Step 3: Write the enum**

Create `backend/app/Enums/PromiseKind.php`:

```php
<?php

namespace App\Enums;

enum PromiseKind: string
{
    case Numeric   = 'numeric';
    case Narrative = 'narrative';
}
```

- [ ] **Step 4: Write the migration**

Create `backend/database/migrations/2026_05_05_000008_create_promise_targets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promise_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guarantee_letter_id')->constrained('guarantee_letters')->cascadeOnDelete();
            $table->string('region_code', 32);
            $table->smallInteger('year');
            $table->string('kind', 16);                                  // 'numeric' | 'narrative'
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('sector', 96)->nullable();
            $table->string('indicator_code', 48)->nullable();
            $table->string('period', 8)->nullable();
            $table->decimal('target_value', 20, 6)->nullable();
            $table->string('target_text', 128)->nullable();
            $table->string('direction', 16)->nullable();                  // 'higher' | 'lower' | 'unspecified'
            $table->jsonb('target_districts')->nullable();                // ['city', 'shahrikhan_district']
            $table->integer('source_paragraph_index')->nullable();
            $table->timestamps();

            $table->index(['region_code', 'year'], 'idx_pt_region_year');
            $table->index(['indicator_code', 'period'], 'idx_pt_indicator_period');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('indicator_code')->references('code')->on('indicators')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promise_targets');
    }
};
```

- [ ] **Step 5: Write the model**

Create `backend/app/Models/PromiseTarget.php`:

```php
<?php

namespace App\Models;

use App\Enums\PromiseKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromiseTarget extends Model
{
    protected $fillable = [
        'guarantee_letter_id','region_code','year','kind','title','body',
        'sector','indicator_code','period','target_value','target_text','direction',
        'target_districts','source_paragraph_index',
    ];

    protected $casts = [
        'kind'             => PromiseKind::class,
        'target_value'     => 'decimal:6',
        'target_districts' => 'array',
    ];

    public function guaranteeLetter(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLetter::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_code', 'code');
    }
}
```

- [ ] **Step 6: Run the test, confirm it passes**

Run: `php artisan test --filter=PromiseTargetsTableTest`
Expected: PASS (3 assertions).

- [ ] **Step 7: Commit**

```bash
git add backend/database/migrations/2026_05_05_000008_create_promise_targets_table.php \
        backend/app/Models/PromiseTarget.php \
        backend/app/Enums/PromiseKind.php \
        backend/tests/Feature/Schema/PromiseTargetsTableTest.php
git commit -m "feat(schema): add promise_targets table"
```

---

## Task 9: `import_runs` table + model + ImportRunStatus enum

**Files:**
- Create: `backend/database/migrations/2026_05_05_000009_create_import_runs_table.php`
- Create: `backend/app/Models/ImportRun.php`
- Create: `backend/app/Enums/ImportRunStatus.php`
- Create: `backend/tests/Feature/Schema/ImportRunsTableTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/ImportRunsTableTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportRunsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['region_code','year','triggered_by_user_id','trigger_kind','status',
                 'started_at','parsed_at','promoted_at','rejected_at','failed_at',
                 'files_processed','rows_staged','rows_promoted',
                 'issues_open_count','issues_blocker_count','notes'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('import_runs', $c), "missing column $c");
        }
    }

    public function test_creates_run(): void
    {
        $this->seed();
        $run = ImportRun::create([
            'region_code' => 'andijan', 'year' => 2026,
            'trigger_kind' => 'cli', 'status' => ImportRunStatus::Parsing,
            'started_at' => now(),
        ]);
        $this->assertNotNull($run->id);
        $this->assertSame(0, $run->files_processed);
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=ImportRunsTableTest`
Expected: FAIL.

- [ ] **Step 3: Write the enum**

Create `backend/app/Enums/ImportRunStatus.php`:

```php
<?php

namespace App\Enums;

enum ImportRunStatus: string
{
    case Parsing        = 'parsing';
    case AwaitingReview = 'awaiting_review';
    case Promoting      = 'promoting';
    case Promoted       = 'promoted';
    case Rejected       = 'rejected';
    case Failed         = 'failed';
}
```

- [ ] **Step 4: Write the migration**

Create `backend/database/migrations/2026_05_05_000009_create_import_runs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 32);
            $table->smallInteger('year');
            $table->foreignId('triggered_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('trigger_kind', 16);                          // 'cli' | 'filament' | 'scheduled'
            $table->string('status', 16);
            $table->timestamp('started_at');
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('files_processed')->default(0);
            $table->integer('rows_staged')->default(0);
            $table->integer('rows_promoted')->default(0);
            $table->integer('issues_open_count')->default(0);
            $table->integer('issues_blocker_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['region_code', 'year', 'status'], 'idx_runs_rgn_yr_status');
            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('year')->references('year')->on('reporting_years');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
```

- [ ] **Step 5: Write the model**

Create `backend/app/Models/ImportRun.php`:

```php
<?php

namespace App\Models;

use App\Enums\ImportRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRun extends Model
{
    protected $fillable = [
        'region_code','year','triggered_by_user_id','trigger_kind','status',
        'started_at','parsed_at','promoted_at','rejected_at','failed_at',
        'files_processed','rows_staged','rows_promoted',
        'issues_open_count','issues_blocker_count','notes',
    ];

    protected $casts = [
        'status'       => ImportRunStatus::class,
        'started_at'   => 'datetime',
        'parsed_at'    => 'datetime',
        'promoted_at'  => 'datetime',
        'rejected_at'  => 'datetime',
        'failed_at'    => 'datetime',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(ImportFile::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(DataQualityIssue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'triggered_by_user_id');
    }
}
```

- [ ] **Step 6: Run the test, confirm it passes**

Run: `php artisan test --filter=ImportRunsTableTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/database/migrations/2026_05_05_000009_create_import_runs_table.php \
        backend/app/Models/ImportRun.php \
        backend/app/Enums/ImportRunStatus.php \
        backend/tests/Feature/Schema/ImportRunsTableTest.php
git commit -m "feat(schema): add import_runs table"
```

---

## Task 10: `import_files` table + model

**Files:**
- Create: `backend/database/migrations/2026_05_05_000010_create_import_files_table.php`
- Create: `backend/app/Models/ImportFile.php`
- Create: `backend/tests/Feature/Schema/ImportFilesTableTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/ImportFilesTableTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\ImportFile;
use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportFilesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['import_run_id','module_code','file_name','file_path','sha256',
                 'size_bytes','sheet_count','parsed_ok','error_text','parsed_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('import_files', $c), "missing column $c");
        }
    }

    public function test_cascade_delete_on_run_removal(): void
    {
        $this->seed();
        $run = ImportRun::create([
            'region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli',
            'status' => 'parsing', 'started_at' => now(),
        ]);
        $file = ImportFile::create([
            'import_run_id' => $run->id, 'module_code' => 'macro',
            'file_name' => '1.1-1.5.xlsx', 'sha256' => str_repeat('a', 64),
            'size_bytes' => 1024, 'sheet_count' => 5, 'parsed_ok' => true,
        ]);
        $run->delete();
        $this->assertNull(ImportFile::find($file->id));
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=ImportFilesTableTest`
Expected: FAIL.

- [ ] **Step 3: Write the migration**

Create `backend/database/migrations/2026_05_05_000010_create_import_files_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();
            $table->string('module_code', 32)->nullable();
            $table->string('file_name', 255);
            $table->string('file_path', 512)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->smallInteger('sheet_count')->nullable();
            $table->boolean('parsed_ok')->default(false);
            $table->text('error_text')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();

            $table->index(['import_run_id', 'module_code'], 'idx_imp_files_run_module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_files');
    }
};
```

- [ ] **Step 4: Write the model**

Create `backend/app/Models/ImportFile.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportFile extends Model
{
    protected $fillable = [
        'import_run_id','module_code','file_name','file_path','sha256',
        'size_bytes','sheet_count','parsed_ok','error_text','parsed_at',
    ];

    protected $casts = [
        'parsed_ok' => 'boolean',
        'parsed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }
}
```

- [ ] **Step 5: Run the test, confirm it passes**

Run: `php artisan test --filter=ImportFilesTableTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_05_05_000010_create_import_files_table.php \
        backend/app/Models/ImportFile.php \
        backend/tests/Feature/Schema/ImportFilesTableTest.php
git commit -m "feat(schema): add import_files table"
```

---

## Task 11: `data_quality_issues` table + model + IssueSeverity enum

**Files:**
- Create: `backend/database/migrations/2026_05_05_000011_create_data_quality_issues_table.php`
- Create: `backend/app/Models/DataQualityIssue.php`
- Create: `backend/app/Enums/IssueSeverity.php`
- Create: `backend/tests/Feature/Schema/DataQualityIssuesTableTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/DataQualityIssuesTableTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Enums\IssueSeverity;
use App\Models\DataQualityIssue;
use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataQualityIssuesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['import_run_id','region_code','district_code','indicator_code','year','period',
                 'issue_kind','severity','detail','detected_value','expected_value',
                 'source_label','detected_at','resolved_at','resolved_by_user_id',
                 'resolution_kind','resolution_note'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('data_quality_issues', $c), "missing column $c");
        }
    }

    public function test_blocker_issue_creation(): void
    {
        $this->seed();
        $run = ImportRun::create([
            'region_code' => 'navoiy', 'year' => 2026, 'trigger_kind' => 'cli',
            'status' => 'parsing', 'started_at' => now(),
        ]);
        $issue = DataQualityIssue::create([
            'import_run_id' => $run->id,
            'region_code' => 'navoiy', 'indicator_code' => 'industry',
            'issue_kind' => 'cross_region_data', 'severity' => IssueSeverity::Blocker,
            'detail' => 'Sheet contains Surxondaryo districts under Navoi',
            'detected_value' => 'Termiz shahri',
            'detected_at' => now(),
        ]);
        $this->assertNotNull($issue->id);
        $this->assertSame(IssueSeverity::Blocker, $issue->severity);
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=DataQualityIssuesTableTest`
Expected: FAIL.

- [ ] **Step 3: Write the enum**

Create `backend/app/Enums/IssueSeverity.php`:

```php
<?php

namespace App\Enums;

enum IssueSeverity: string
{
    case Low     = 'low';
    case Medium  = 'medium';
    case High    = 'high';
    case Blocker = 'blocker';
}
```

- [ ] **Step 4: Write the migration**

Create `backend/database/migrations/2026_05_05_000011_create_data_quality_issues_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('data_quality_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->nullable()
                  ->constrained('import_runs')->nullOnDelete();
            $table->string('region_code', 32);
            $table->string('district_code', 64)->nullable();
            $table->string('indicator_code', 48)->nullable();
            $table->smallInteger('year')->nullable();
            $table->string('period', 8)->nullable();
            $table->string('issue_kind', 48);
            $table->string('severity', 16);
            $table->text('detail');
            $table->text('detected_value')->nullable();
            $table->text('expected_value')->nullable();
            $table->string('source_label', 255)->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('resolution_kind', 32)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['region_code', 'year', 'severity', 'resolved_at'], 'idx_dqi_rgn_yr_sev');
            $table->index(['issue_kind', 'severity'], 'idx_dqi_kind_severity');
            $table->index('import_run_id', 'idx_dqi_run');

            $table->foreign('region_code')->references('code')->on('regions');
            $table->foreign('indicator_code')->references('code')->on('indicators')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_issues');
    }
};
```

- [ ] **Step 5: Write the model**

Create `backend/app/Models/DataQualityIssue.php`:

```php
<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataQualityIssue extends Model
{
    protected $fillable = [
        'import_run_id','region_code','district_code','indicator_code','year','period',
        'issue_kind','severity','detail','detected_value','expected_value',
        'source_label','detected_at','resolved_at','resolved_by_user_id',
        'resolution_kind','resolution_note',
    ];

    protected $casts = [
        'severity'    => IssueSeverity::class,
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }
}
```

- [ ] **Step 6: Run the test, confirm it passes**

Run: `php artisan test --filter=DataQualityIssuesTableTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/database/migrations/2026_05_05_000011_create_data_quality_issues_table.php \
        backend/app/Models/DataQualityIssue.php \
        backend/app/Enums/IssueSeverity.php \
        backend/tests/Feature/Schema/DataQualityIssuesTableTest.php
git commit -m "feat(schema): add data_quality_issues table"
```

---

## Task 12: Staging tables + StagingStatus enum

**Files:**
- Create: `backend/database/migrations/2026_05_05_000012_create_import_staging_indicator_facts_table.php`
- Create: `backend/database/migrations/2026_05_05_000013_create_import_staging_food_balance_table.php`
- Create: `backend/database/migrations/2026_05_05_000014_create_import_staging_warehouses_table.php`
- Create: `backend/app/Models/ImportStagingIndicatorFact.php`
- Create: `backend/app/Models/ImportStagingFoodBalance.php`
- Create: `backend/app/Models/ImportStagingWarehouse.php`
- Create: `backend/app/Enums/StagingStatus.php`
- Create: `backend/tests/Feature/Schema/ImportStagingTablesTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Schema/ImportStagingTablesTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Enums\StagingStatus;
use App\Models\ImportRun;
use App\Models\ImportStagingFoodBalance;
use App\Models\ImportStagingIndicatorFact;
use App\Models\ImportStagingWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportStagingTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('import_staging_indicator_facts'));
        $this->assertTrue(Schema::hasTable('import_staging_food_balance'));
        $this->assertTrue(Schema::hasTable('import_staging_warehouses'));
    }

    public function test_staging_indicator_facts_mirrors_production_columns(): void
    {
        // Same column set as indicator_facts plus import_run_id, staging_status, validation_errors
        $cols = ['region_code','district_code','year','indicator_code','period',
                 'plan_value','actual_hokimyat','growth_pct','unit','source_label',
                 'import_run_id','staging_status','validation_errors'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('import_staging_indicator_facts', $c),
                "missing column $c");
        }
    }

    public function test_staging_allows_duplicates_no_unique_key(): void
    {
        $this->seed();
        $run = ImportRun::create([
            'region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli',
            'status' => 'parsing', 'started_at' => now(),
        ]);
        for ($i = 0; $i < 2; $i++) {
            ImportStagingIndicatorFact::create([
                'import_run_id' => $run->id,
                'region_code' => 'andijan', 'district_code' => null, 'year' => 2026,
                'indicator_code' => 'grp', 'period' => 'h1',
                'plan_value' => 52100.8, 'unit' => 'млрд сўм', 'source_label' => 'test',
                'staging_status' => StagingStatus::Pending,
            ]);
        }
        $this->assertSame(2, ImportStagingIndicatorFact::count(),
            'staging tables must allow multiple rows for the same logical key');
    }
}
```

- [ ] **Step 2: Run the test, confirm it fails**

Run: `php artisan test --filter=ImportStagingTablesTest`
Expected: FAIL.

- [ ] **Step 3: Write the StagingStatus enum**

Create `backend/app/Enums/StagingStatus.php`:

```php
<?php

namespace App\Enums;

enum StagingStatus: string
{
    case Pending   = 'pending';
    case Validated = 'validated';
    case Rejected  = 'rejected';
    case Promoted  = 'promoted';
}
```

- [ ] **Step 4: Write the staging migration for `indicator_facts`**

Create `backend/database/migrations/2026_05_05_000012_create_import_staging_indicator_facts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_staging_indicator_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();

            $table->string('region_code', 32);
            $table->string('district_code', 64)->nullable();
            $table->smallInteger('year');
            $table->string('indicator_code', 48);
            $table->string('period', 8);

            $table->decimal('plan_value', 20, 6)->nullable();
            $table->decimal('expected_value', 20, 6)->nullable();
            $table->decimal('actual_hokimyat', 20, 6)->nullable();
            $table->decimal('actual_statkom', 20, 6)->nullable();
            $table->decimal('growth_pct', 10, 4)->nullable();
            $table->decimal('pct_of_plan', 10, 4)->nullable();
            $table->integer('count_extra')->nullable();
            $table->integer('count_extra_2')->nullable();

            $table->boolean('is_sentinel')->default(false);
            $table->string('sentinel_label', 64)->nullable();

            $table->string('unit', 48);
            $table->string('source_label', 255);
            $table->timestamp('hokimyat_reported_at')->nullable();
            $table->timestamp('statkom_published_at')->nullable();

            $table->string('staging_status', 16)->default('pending');
            $table->jsonb('validation_errors')->nullable();
            $table->timestamps();

            $table->index(['import_run_id', 'staging_status'], 'idx_stg_facts_run_status');
            $table->index(['region_code', 'district_code', 'year', 'indicator_code'],
                          'idx_stg_facts_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staging_indicator_facts');
    }
};
```

- [ ] **Step 5: Write the staging migration for `food_balance`**

Create `backend/database/migrations/2026_05_05_000013_create_import_staging_food_balance_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_staging_food_balance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();

            $table->string('region_code', 32);
            $table->smallInteger('year');
            $table->string('product', 96);
            $table->smallInteger('product_sort_order')->default(0);

            $table->decimal('resource_total', 20, 6)->nullable();
            $table->decimal('year_start_stock', 20, 6)->nullable();
            $table->decimal('production', 20, 6)->nullable();
            $table->decimal('import_volume', 20, 6)->nullable();
            $table->decimal('use_total', 20, 6)->nullable();
            $table->decimal('use_household', 20, 6)->nullable();
            $table->decimal('use_processing', 20, 6)->nullable();
            $table->decimal('use_other', 20, 6)->nullable();
            $table->decimal('per_capita_norm', 20, 6)->nullable();
            $table->decimal('per_capita_balance', 20, 6)->nullable();
            $table->decimal('local_supply_ratio', 20, 6)->nullable();
            $table->decimal('year_end_stock', 20, 6)->nullable();

            $table->string('source_label', 255);
            $table->string('staging_status', 16)->default('pending');
            $table->jsonb('validation_errors')->nullable();
            $table->timestamps();

            $table->index(['import_run_id', 'staging_status'], 'idx_stg_food_run_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staging_food_balance');
    }
};
```

- [ ] **Step 6: Write the staging migration for `warehouses`**

Create `backend/database/migrations/2026_05_05_000014_create_import_staging_warehouses_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_staging_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();

            $table->string('region_code', 32);
            $table->string('district_code', 64)->nullable();
            $table->smallInteger('year');

            $table->integer('reserve_warehouses')->nullable();
            $table->integer('reserve_capacity_t')->nullable();
            $table->integer('cold_storage_count')->nullable();
            $table->integer('cold_storage_capacity_t')->nullable();
            $table->integer('new_small_cold_count')->nullable();
            $table->integer('new_small_cold_capacity_t')->nullable();
            $table->integer('new_small_cold_mfys')->nullable();
            $table->integer('new_large_cold_count')->nullable();
            $table->integer('new_large_cold_capacity_t')->nullable();

            $table->string('source_label', 255);
            $table->string('staging_status', 16)->default('pending');
            $table->jsonb('validation_errors')->nullable();
            $table->timestamps();

            $table->index(['import_run_id', 'staging_status'], 'idx_stg_warehouses_run_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staging_warehouses');
    }
};
```

- [ ] **Step 7: Write the three models**

Create `backend/app/Models/ImportStagingIndicatorFact.php`:

```php
<?php

namespace App\Models;

use App\Enums\Period;
use App\Enums\StagingStatus;
use Illuminate\Database\Eloquent\Model;

class ImportStagingIndicatorFact extends Model
{
    protected $table = 'import_staging_indicator_facts';

    protected $fillable = [
        'import_run_id','region_code','district_code','year','indicator_code','period',
        'plan_value','expected_value','actual_hokimyat','actual_statkom',
        'growth_pct','pct_of_plan','count_extra','count_extra_2',
        'is_sentinel','sentinel_label','unit','source_label',
        'hokimyat_reported_at','statkom_published_at',
        'staging_status','validation_errors',
    ];

    protected $casts = [
        'period'             => Period::class,
        'staging_status'     => StagingStatus::class,
        'validation_errors'  => 'array',
        'is_sentinel'        => 'boolean',
        'plan_value'         => 'decimal:6',
        'actual_hokimyat'    => 'decimal:6',
        'growth_pct'         => 'decimal:4',
    ];
}
```

Create `backend/app/Models/ImportStagingFoodBalance.php`:

```php
<?php

namespace App\Models;

use App\Enums\StagingStatus;
use Illuminate\Database\Eloquent\Model;

class ImportStagingFoodBalance extends Model
{
    protected $table = 'import_staging_food_balance';

    protected $fillable = [
        'import_run_id','region_code','year','product','product_sort_order',
        'resource_total','year_start_stock','production','import_volume',
        'use_total','use_household','use_processing','use_other',
        'per_capita_norm','per_capita_balance','local_supply_ratio','year_end_stock',
        'source_label','staging_status','validation_errors',
    ];

    protected $casts = [
        'staging_status'     => StagingStatus::class,
        'validation_errors'  => 'array',
    ];
}
```

Create `backend/app/Models/ImportStagingWarehouse.php`:

```php
<?php

namespace App\Models;

use App\Enums\StagingStatus;
use Illuminate\Database\Eloquent\Model;

class ImportStagingWarehouse extends Model
{
    protected $table = 'import_staging_warehouses';

    protected $fillable = [
        'import_run_id','region_code','district_code','year',
        'reserve_warehouses','reserve_capacity_t',
        'cold_storage_count','cold_storage_capacity_t',
        'new_small_cold_count','new_small_cold_capacity_t','new_small_cold_mfys',
        'new_large_cold_count','new_large_cold_capacity_t',
        'source_label','staging_status','validation_errors',
    ];

    protected $casts = [
        'staging_status'     => StagingStatus::class,
        'validation_errors'  => 'array',
    ];
}
```

- [ ] **Step 8: Run the test, confirm it passes**

Run: `php artisan test --filter=ImportStagingTablesTest`
Expected: PASS (3 assertions).

- [ ] **Step 9: Commit**

```bash
git add backend/database/migrations/2026_05_05_000012_create_import_staging_indicator_facts_table.php \
        backend/database/migrations/2026_05_05_000013_create_import_staging_food_balance_table.php \
        backend/database/migrations/2026_05_05_000014_create_import_staging_warehouses_table.php \
        backend/app/Models/ImportStagingIndicatorFact.php \
        backend/app/Models/ImportStagingFoodBalance.php \
        backend/app/Models/ImportStagingWarehouse.php \
        backend/app/Enums/StagingStatus.php \
        backend/tests/Feature/Schema/ImportStagingTablesTest.php
git commit -m "feat(schema): add staging tables for indicator_facts, food_balance, warehouses"
```

---

## Task 13: End-to-end schema integrity test

**Files:**
- Create: `backend/tests/Feature/Schema/SchemaIntegrityTest.php`

This test verifies the full migrate+seed cycle is clean and that all expected tables/seeds are in place. It runs against a freshly migrated database.

- [ ] **Step 1: Write the integrity test**

Create `backend/tests/Feature/Schema/SchemaIntegrityTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_TABLES = [
        // Reference (from earlier migrations)
        'reporting_years', 'regions', 'districts', 'modules',
        'region_workbooks', 'region_workbook_sheets',
        // Indicator catalog
        'indicators', 'region_indicator_availability',
        // Fact data
        'indicator_facts', 'food_balance', 'warehouses',
        // Narrative
        'guarantee_letters', 'promise_targets',
        // Import infra
        'import_runs', 'import_files', 'data_quality_issues',
        'import_staging_indicator_facts', 'import_staging_food_balance', 'import_staging_warehouses',
    ];

    public function test_all_expected_tables_exist(): void
    {
        foreach (self::EXPECTED_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing table $table");
        }
    }

    public function test_seed_populates_reference_data(): void
    {
        $this->seed();

        $this->assertSame(1,   DB::table('reporting_years')->count(), 'reporting_years');
        $this->assertSame(7,   DB::table('modules')->count(),         'modules');
        $this->assertSame(14,  DB::table('regions')->count(),         'regions');
        $this->assertSame(208, DB::table('districts')->count(),       'districts');
        $this->assertSame(20,  DB::table('indicators')->count(),      'indicators');

        // 14 × 20 availability rows
        $this->assertSame(14 * 20, DB::table('region_indicator_availability')->count());
    }

    public function test_no_orphan_districts(): void
    {
        $this->seed();
        $orphans = DB::table('districts as d')
            ->leftJoin('regions as r', 'd.region_code', '=', 'r.code')
            ->whereNull('r.code')
            ->count();
        $this->assertSame(0, $orphans);
    }

    public function test_seeded_blocked_indicators_for_navoi(): void
    {
        $this->seed();
        $blocked = DB::table('region_indicator_availability')
            ->where('region_code', 'navoiy')
            ->where('status', 'blocked')
            ->count();
        $this->assertSame(5, $blocked, 'navoi macro indicators should be blocked');
    }

    public function test_indicator_facts_fk_works(): void
    {
        $this->seed();
        // Insert one row to verify the composite FK works end-to-end
        DB::table('indicator_facts')->insert([
            'region_code' => 'andijan', 'district_code' => null, 'year' => 2026,
            'indicator_code' => 'grp', 'period' => 'h1',
            'plan_value' => 52100.8, 'unit' => 'млрд сўм',
            'source_label' => 'integrity-test', 'is_sentinel' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(1, DB::table('indicator_facts')->count());
    }
}
```

- [ ] **Step 2: Run the integrity test**

Run: `php artisan test --filter=SchemaIntegrityTest`
Expected: PASS (5 assertions). If anything fails, fix the affected migration / seeder before continuing.

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test`
Expected: all schema tests green; total ≥ 30 assertions across the schema suite.

- [ ] **Step 4: Commit**

```bash
git add backend/tests/Feature/Schema/SchemaIntegrityTest.php
git commit -m "test: end-to-end schema integrity check"
```

---

## Task 14: Wire dev database (final smoke test)

**Files:**
- No new files; this task verifies migrations run cleanly against the dev Postgres database.

- [ ] **Step 1: Ensure dev DB exists**

```powershell
$env:PGPASSWORD = "<your-postgres-password>"
psql -U postgres -h 127.0.0.1 -c "CREATE DATABASE hududlar_monitoringi;"
```

(If it already exists, skip.)

- [ ] **Step 2: Migrate fresh + seed against dev**

Run: `php artisan migrate:fresh --seed`
Expected output: every migration logs "DONE", each seeder reports its row count, no errors. Final state: 14 regions, 208 districts, 20 indicators, 280 availability rows.

- [ ] **Step 3: Verify with a couple of psql checks**

```powershell
psql -U postgres -h 127.0.0.1 -d hududlar_monitoringi -c "SELECT region_code, COUNT(*) FROM districts GROUP BY region_code ORDER BY region_code;"
```

Expected: 14 rows, district counts matching the regions_districts.json (16, 17, 13, 13, …).

```powershell
psql -U postgres -h 127.0.0.1 -d hududlar_monitoringi -c "SELECT region_code, indicator_code FROM region_indicator_availability WHERE status = 'blocked';"
```

Expected: 5 rows, all `navoiy` × {grp, industry, agriculture, construction, services}.

```powershell
psql -U postgres -h 127.0.0.1 -d hududlar_monitoringi -c "SELECT region_code, indicator_code, status, note FROM region_indicator_availability WHERE region_code = 'tashkent_city' AND status != 'available';"
```

Expected: 1 row, `agriculture` → `not_applicable`.

- [ ] **Step 4: Commit a final "schema complete" marker (optional, signals end of plan)**

```bash
git commit --allow-empty -m "chore(schema): plan 1 complete — fact tables and import infrastructure ready"
```

---

## Self-review

Verified against `docs/superpowers/specs/2026-05-05-fact-tables-and-import-schema-design.md`:

- **Spec §2** constraints: Tashkent city no agriculture (Task 3 seeder exception), Navoi blocked (Task 3 seeder exception), sentinel handling (Task 4 columns + Task 2 indicator metadata), workbook variance (out of scope here — handled by importer in Plan 2).
- **Spec §5** indicator catalog: 20 indicators seeded in Task 2 with metadata matching the seed table in spec §5.
- **Spec §6** indicator_facts cube: Task 4 creates the table with all columns including `actual_hokimyat`/`actual_statkom` separation, `is_sentinel` + `sentinel_label`, composite FK.
- **Spec §7** availability matrix: Task 3.
- **Spec §8** food_balance + warehouses: Tasks 5 + 6.
- **Spec §9** guarantee_letters + promise_targets: Tasks 7 + 8.
- **Spec §10** import infrastructure: Tasks 9 + 10 + 11 + 12.
- **Spec §13** districts adjustment: Task 1.
- **Spec §14** testing approach: each task has its own table/integrity test; the Andijan parity test belongs to Plan 2 (importer) since it requires the importer to populate facts.
- **Spec §16** migration plan summary: 14 migrations created in matching order.

No placeholders. Type names consistent across tasks (`Indicator`, `IndicatorFact`, `ImportRun`, `Period`, `AvailabilityStatus`, `IssueSeverity`, `StagingStatus`, `PromiseKind`, `ImportRunStatus`, `IndicatorScope`).

---

## What's NOT in this plan

These belong to follow-up plans (separate specs/plans):

- **Plan 2 — Artisan importer (`php artisan import:region <code> <year>`)**: PhpSpreadsheet integration, sheet resolution by content-pattern, header-row detection, district name resolution against `alt_labels`, staging fill, data-quality-issue raising, Andijan parity test against the inlined `DATA` blob.
- **Plan 3 — Filament 3 admin UI**: resources for each table, RBAC via Spatie Permission, the import-run review page (staging vs production diff + Promote/Reject actions), users + region-scoped access.
- **Tasks tables** (`tasks`, `task_districts`, `task_status_history`): deferred until you provide the source data structure.
