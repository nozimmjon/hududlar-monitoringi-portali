# Plan 9 — Blade + Livewire Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hardcoded `index.html` DATA blob with a Laravel Blade + Livewire dashboard that reads from the `indicator_facts`, `food_balance`, and `warehouses` production tables.

**Architecture:** An `import:promote` Artisan command moves staging rows to production tables. Five Blade pages, each with one Livewire component, serve the five dashboard sections via real Laravel routes. CSS is extracted from `index.html` into `public/css/portal.css` and served statically — no Vite, no npm.

**Tech Stack:** Laravel 12.58, Livewire 3 (composer require livewire/livewire), Pest 3, PostgreSQL 14, PHP 8.2.

---

## File Map

**New files:**
| File | Responsibility |
|---|---|
| `backend/app/Console/Commands/PromoteImportRunCommand.php` | Artisan `import:promote {run_id}` — upserts staging → production |
| `backend/tests/Feature/PromoteImportRunCommandTest.php` | 4 tests covering happy path, status guard, idempotency, all 3 tables |
| `backend/public/css/portal.css` | CSS extracted from `index.html` lines 11–5603 |
| `backend/resources/views/layouts/app.blade.php` | Shared shell: sidebar nav + page-head + content slot |
| `backend/resources/views/pages/dashboard.blade.php` | Extends layout, embeds `<livewire:kpi-dashboard />` |
| `backend/resources/views/pages/districts.blade.php` | Extends layout, embeds `<livewire:districts-page />` |
| `backend/resources/views/pages/tasks.blade.php` | Extends layout, embeds `<livewire:tasks-board />` |
| `backend/resources/views/pages/profile.blade.php` | Extends layout, embeds `<livewire:region-profile />` |
| `backend/resources/views/pages/execution.blade.php` | Extends layout, embeds `<livewire:execution-page />` |
| `backend/app/Livewire/KpiDashboard.php` | Module tabs + KPI tiles + period switcher from `indicator_facts` |
| `backend/app/Livewire/DistrictsPage.php` | District table with metric/period switcher |
| `backend/app/Livewire/TasksBoard.php` | Stub — placeholder text, no DB query |
| `backend/app/Livewire/RegionProfile.php` | Per-district indicator facts, districtCode from URL param |
| `backend/app/Livewire/ExecutionPage.php` | Budget/investment/export execution view |
| `backend/resources/views/livewire/kpi-dashboard.blade.php` | Template for KpiDashboard |
| `backend/resources/views/livewire/districts-page.blade.php` | Template for DistrictsPage |
| `backend/resources/views/livewire/tasks-board.blade.php` | Template for TasksBoard |
| `backend/resources/views/livewire/region-profile.blade.php` | Template for RegionProfile |
| `backend/resources/views/livewire/execution-page.blade.php` | Template for ExecutionPage |
| `backend/tests/Feature/Http/DashboardRoutesTest.php` | 5 HTTP smoke tests: each route returns 200 |

**Modified files:**
| File | Change |
|---|---|
| `backend/routes/web.php` | Replace welcome view with 5 named view routes + root redirect |

---

## Task 1: PromoteImportRunCommand

**Files:**
- Create: `backend/app/Console/Commands/PromoteImportRunCommand.php`
- Test: `backend/tests/Feature/PromoteImportRunCommandTest.php`

---

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/PromoteImportRunCommandTest.php`:

```php
<?php

use App\Enums\ImportRunStatus;
use App\Models\FoodBalance;
use App\Models\IndicatorFact;
use App\Models\ImportRun;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('import:promote moves indicator facts to production table', function () {
    $this->seed();

    $run = ImportRun::create([
        'region_code'  => 'andijan',
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => 'awaiting_review',
        'started_at'   => now(),
        'rows_staged'  => 1,
    ]);

    DB::table('import_staging_indicator_facts')->insert([
        'import_run_id'  => $run->id,
        'region_code'    => 'andijan',
        'district_code'  => null,
        'year'           => 2026,
        'indicator_code' => 'export',
        'period'         => 'year',
        'plan_value'     => 3341.74,
        'unit'           => 'минг доллар',
        'source_label'   => 'test',
        'staging_status' => 'pending',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $exitCode = Artisan::call('import:promote', ['run_id' => $run->id]);

    expect($exitCode)->toBe(0);
    expect(IndicatorFact::count())->toBe(1);

    $fact = IndicatorFact::first();
    expect($fact->indicator_code)->toBe('export');
    expect($fact->plan_value)->toBeNumericallyClose(3341.74, 0.01);

    $run->refresh();
    expect($run->status->value)->toBe('promoted');
    expect($run->rows_promoted)->toBeGreaterThanOrEqual(1);
    expect($run->promoted_at)->not->toBeNull();
});

test('import:promote fails if run is not awaiting_review', function () {
    $this->seed();

    $run = ImportRun::create([
        'region_code'  => 'andijan',
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => 'promoted',
        'started_at'   => now(),
    ]);

    $exitCode = Artisan::call('import:promote', ['run_id' => $run->id]);

    expect($exitCode)->toBe(1);
    expect(IndicatorFact::count())->toBe(0);

    $run->refresh();
    expect($run->status->value)->toBe('promoted');
});

test('import:promote is idempotent — second run with same key updates value', function () {
    $this->seed();

    $run1 = ImportRun::create([
        'region_code'  => 'andijan',
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => 'awaiting_review',
        'started_at'   => now(),
        'rows_staged'  => 1,
    ]);

    DB::table('import_staging_indicator_facts')->insert([
        'import_run_id'  => $run1->id,
        'region_code'    => 'andijan',
        'district_code'  => null,
        'year'           => 2026,
        'indicator_code' => 'export',
        'period'         => 'year',
        'plan_value'     => 3341.74,
        'unit'           => 'минг доллар',
        'source_label'   => 'test',
        'staging_status' => 'pending',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    Artisan::call('import:promote', ['run_id' => $run1->id]);

    $run2 = ImportRun::create([
        'region_code'  => 'andijan',
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => 'awaiting_review',
        'started_at'   => now(),
        'rows_staged'  => 1,
    ]);

    DB::table('import_staging_indicator_facts')->insert([
        'import_run_id'  => $run2->id,
        'region_code'    => 'andijan',
        'district_code'  => null,
        'year'           => 2026,
        'indicator_code' => 'export',
        'period'         => 'year',
        'plan_value'     => 4000.00,  // updated value
        'unit'           => 'минг доллар',
        'source_label'   => 'test2',
        'staging_status' => 'pending',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $exitCode = Artisan::call('import:promote', ['run_id' => $run2->id]);

    expect($exitCode)->toBe(0);
    expect(IndicatorFact::count())->toBe(1);  // still 1, not 2

    $fact = IndicatorFact::first();
    expect($fact->plan_value)->toBeNumericallyClose(4000.00, 0.01);  // updated
});

test('import:promote also promotes food_balance and warehouses', function () {
    $this->seed();

    $run = ImportRun::create([
        'region_code'  => 'andijan',
        'year'         => 2026,
        'trigger_kind' => 'cli',
        'status'       => 'awaiting_review',
        'started_at'   => now(),
        'rows_staged'  => 2,
    ]);

    DB::table('import_staging_food_balance')->insert([
        'import_run_id'  => $run->id,
        'region_code'    => 'andijan',
        'year'           => 2026,
        'product'        => 'Буғдой',
        'source_label'   => 'test',
        'staging_status' => 'pending',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    DB::table('import_staging_warehouses')->insert([
        'import_run_id'     => $run->id,
        'region_code'       => 'andijan',
        'district_code'     => null,
        'year'              => 2026,
        'reserve_warehouses'=> 5,
        'source_label'      => 'test',
        'staging_status'    => 'pending',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    Artisan::call('import:promote', ['run_id' => $run->id]);

    expect(FoodBalance::count())->toBe(1);
    expect(Warehouse::count())->toBe(1);
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```powershell
cd backend
php -d memory_limit=1G vendor/bin/pest tests/Feature/PromoteImportRunCommandTest.php -v
```

Expected: 4 FAILs — `import:promote` command does not exist yet.

- [ ] **Step 3: Implement PromoteImportRunCommand**

Create `backend/app/Console/Commands/PromoteImportRunCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PromoteImportRunCommand extends Command
{
    protected $signature = 'import:promote {run_id : The ID of the ImportRun to promote}';
    protected $description = 'Promote staged rows from an import run into the production fact tables.';

    public function handle(): int
    {
        $runId = (int) $this->argument('run_id');

        $run = ImportRun::find($runId);
        if (! $run) {
            $this->error("ImportRun #{$runId} not found.");
            return 1;
        }

        if ($run->status !== ImportRunStatus::AwaitingReview) {
            $this->error("Run #{$runId} is '{$run->status->value}', not 'awaiting_review'. Aborting.");
            return 1;
        }

        $run->update(['status' => ImportRunStatus::Promoting]);

        $factCount      = $this->promoteIndicatorFacts($runId);
        $foodCount      = $this->promoteFoodBalance($runId);
        $warehouseCount = $this->promoteWarehouses($runId);

        $totalPromoted = $factCount + $foodCount + $warehouseCount;

        $run->update([
            'status'        => ImportRunStatus::Promoted,
            'promoted_at'   => now(),
            'rows_promoted' => $totalPromoted,
        ]);

        $this->info("Promoted run #{$runId}: {$factCount} indicator facts, {$foodCount} food balance rows, {$warehouseCount} warehouse rows.");

        return 0;
    }

    private function promoteIndicatorFacts(int $runId): int
    {
        $cols = 'region_code, district_code, year, indicator_code, period,
                 plan_value, expected_value, actual_hokimyat, actual_statkom,
                 growth_pct, pct_of_plan, count_extra, count_extra_2,
                 is_sentinel, sentinel_label, unit, source_label,
                 hokimyat_reported_at, statkom_published_at';

        $updateSet = 'plan_value = EXCLUDED.plan_value,
                      expected_value = EXCLUDED.expected_value,
                      actual_hokimyat = EXCLUDED.actual_hokimyat,
                      actual_statkom = EXCLUDED.actual_statkom,
                      growth_pct = EXCLUDED.growth_pct,
                      pct_of_plan = EXCLUDED.pct_of_plan,
                      count_extra = EXCLUDED.count_extra,
                      count_extra_2 = EXCLUDED.count_extra_2,
                      is_sentinel = EXCLUDED.is_sentinel,
                      sentinel_label = EXCLUDED.sentinel_label,
                      unit = EXCLUDED.unit,
                      source_label = EXCLUDED.source_label,
                      hokimyat_reported_at = EXCLUDED.hokimyat_reported_at,
                      statkom_published_at = EXCLUDED.statkom_published_at,
                      updated_at = now()';

        // District rows — partial index uq_indicator_facts_district
        DB::statement("
            INSERT INTO indicator_facts ({$cols}, created_at, updated_at)
            SELECT {$cols}, now(), now()
            FROM import_staging_indicator_facts
            WHERE import_run_id = ? AND district_code IS NOT NULL
            ON CONFLICT (region_code, district_code, year, indicator_code, period)
            WHERE district_code IS NOT NULL
            DO UPDATE SET {$updateSet}
        ", [$runId]);

        // Rollup rows — partial index uq_indicator_facts_rollup
        DB::statement("
            INSERT INTO indicator_facts ({$cols}, created_at, updated_at)
            SELECT {$cols}, now(), now()
            FROM import_staging_indicator_facts
            WHERE import_run_id = ? AND district_code IS NULL
            ON CONFLICT (region_code, year, indicator_code, period)
            WHERE district_code IS NULL
            DO UPDATE SET {$updateSet}
        ", [$runId]);

        return DB::table('import_staging_indicator_facts')
            ->where('import_run_id', $runId)
            ->count();
    }

    private function promoteFoodBalance(int $runId): int
    {
        $cols = 'region_code, year, product, product_sort_order,
                 resource_total, year_start_stock, production, import_volume,
                 use_total, use_household, use_processing, use_other,
                 per_capita_norm, per_capita_balance, local_supply_ratio, year_end_stock,
                 source_label';

        DB::statement("
            INSERT INTO food_balance ({$cols}, created_at, updated_at)
            SELECT {$cols}, now(), now()
            FROM import_staging_food_balance
            WHERE import_run_id = ?
            ON CONFLICT (region_code, year, product)
            DO UPDATE SET
                product_sort_order = EXCLUDED.product_sort_order,
                resource_total     = EXCLUDED.resource_total,
                year_start_stock   = EXCLUDED.year_start_stock,
                production         = EXCLUDED.production,
                import_volume      = EXCLUDED.import_volume,
                use_total          = EXCLUDED.use_total,
                use_household      = EXCLUDED.use_household,
                use_processing     = EXCLUDED.use_processing,
                use_other          = EXCLUDED.use_other,
                per_capita_norm    = EXCLUDED.per_capita_norm,
                per_capita_balance = EXCLUDED.per_capita_balance,
                local_supply_ratio = EXCLUDED.local_supply_ratio,
                year_end_stock     = EXCLUDED.year_end_stock,
                source_label       = EXCLUDED.source_label,
                updated_at         = now()
        ", [$runId]);

        return DB::table('import_staging_food_balance')
            ->where('import_run_id', $runId)
            ->count();
    }

    private function promoteWarehouses(int $runId): int
    {
        $cols = 'region_code, district_code, year,
                 reserve_warehouses, reserve_capacity_t,
                 cold_storage_count, cold_storage_capacity_t,
                 new_small_cold_count, new_small_cold_capacity_t, new_small_cold_mfys,
                 new_large_cold_count, new_large_cold_capacity_t,
                 source_label';

        $updateSet = 'reserve_warehouses       = EXCLUDED.reserve_warehouses,
                      reserve_capacity_t       = EXCLUDED.reserve_capacity_t,
                      cold_storage_count       = EXCLUDED.cold_storage_count,
                      cold_storage_capacity_t  = EXCLUDED.cold_storage_capacity_t,
                      new_small_cold_count     = EXCLUDED.new_small_cold_count,
                      new_small_cold_capacity_t= EXCLUDED.new_small_cold_capacity_t,
                      new_small_cold_mfys      = EXCLUDED.new_small_cold_mfys,
                      new_large_cold_count     = EXCLUDED.new_large_cold_count,
                      new_large_cold_capacity_t= EXCLUDED.new_large_cold_capacity_t,
                      source_label             = EXCLUDED.source_label,
                      updated_at               = now()';

        // District rows — partial index uq_warehouses_district
        DB::statement("
            INSERT INTO warehouses ({$cols}, created_at, updated_at)
            SELECT {$cols}, now(), now()
            FROM import_staging_warehouses
            WHERE import_run_id = ? AND district_code IS NOT NULL
            ON CONFLICT (region_code, district_code, year)
            WHERE district_code IS NOT NULL
            DO UPDATE SET {$updateSet}
        ", [$runId]);

        // Rollup rows — partial index uq_warehouses_rollup
        DB::statement("
            INSERT INTO warehouses ({$cols}, created_at, updated_at)
            SELECT {$cols}, now(), now()
            FROM import_staging_warehouses
            WHERE import_run_id = ? AND district_code IS NULL
            ON CONFLICT (region_code, year)
            WHERE district_code IS NULL
            DO UPDATE SET {$updateSet}
        ", [$runId]);

        return DB::table('import_staging_warehouses')
            ->where('import_run_id', $runId)
            ->count();
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```powershell
php -d memory_limit=1G vendor/bin/pest tests/Feature/PromoteImportRunCommandTest.php -v
```

Expected: 4 PASS. Total test suite should remain green:
```powershell
php -d memory_limit=1G vendor/bin/pest --stop-on-failure
```

- [ ] **Step 5: Commit**

```powershell
git add backend/app/Console/Commands/PromoteImportRunCommand.php `
        backend/tests/Feature/PromoteImportRunCommandTest.php
git commit -m "feat: add import:promote command — upserts staging rows into production tables"
```

---

## Task 2: CSS Extraction + Livewire Install

**Files:**
- Create: `backend/public/css/portal.css`
- `backend/composer.json` modified by composer

---

- [ ] **Step 1: Install Livewire 3**

```powershell
cd backend
composer require livewire/livewire
```

Expected: `livewire/livewire` added to `composer.json`. No errors. Livewire auto-discovers its service provider via Laravel's package discovery.

- [ ] **Step 2: Extract CSS from index.html**

Run from the **repo root** (one directory above `backend/`):

```powershell
# Run from C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali
$lines = Get-Content .\index.html
# Lines 11–5603 (1-indexed) = indices 10–5602 (0-indexed)
# This is the CSS content between <style> (line 10) and </style> (line 5604)
$lines[10..5602] | Out-File -FilePath .\backend\public\css\portal.css -Encoding utf8
```

Verify the file was created and has content:

```powershell
(Get-Content .\backend\public\css\portal.css | Measure-Object -Line).Lines
```

Expected: ~5593 lines. The file should start with `:root {`.

- [ ] **Step 3: Commit**

```powershell
git add backend/public/css/portal.css backend/composer.json backend/composer.lock
git commit -m "feat: extract portal CSS to public/css/portal.css, install Livewire 3"
```

---

## Task 3: Blade Layout + Routes

**Files:**
- Create: `backend/resources/views/layouts/app.blade.php`
- Create: `backend/resources/views/pages/dashboard.blade.php`
- Create: `backend/resources/views/pages/districts.blade.php`
- Create: `backend/resources/views/pages/tasks.blade.php`
- Create: `backend/resources/views/pages/profile.blade.php`
- Create: `backend/resources/views/pages/execution.blade.php`
- Modify: `backend/routes/web.php`

---

- [ ] **Step 1: Update routes**

Replace the entire content of `backend/routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::view('/dashboard', 'pages.dashboard')->name('dashboard');
Route::view('/districts', 'pages.districts')->name('districts');
Route::view('/tasks', 'pages.tasks')->name('tasks');
Route::view('/profile', 'pages.profile')->name('profile');
Route::view('/execution', 'pages.execution')->name('execution');
```

- [ ] **Step 2: Create the shared layout**

Create `backend/resources/views/layouts/app.blade.php`:

```blade
<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Андижон вилояти мониторинг платформаси · v7</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css">
  <style>
    a.nav-btn { text-decoration: none; }
  </style>
  @livewireStyles
</head>
<body>
  <div class="shell">
    <aside class="sidebar">
      <div class="side-title">
        <strong>Бошқарув маркази</strong>
      </div>
      <a class="nav-btn {{ Route::is('dashboard') ? 'active' : '' }}"
         href="{{ route('dashboard') }}" title="KPI">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M4 13h6V4H4v9Zm10 7h6V4h-6v16ZM4 20h6v-4H4v4Z"/>
        </svg>
        <span>KPI</span>
      </a>
      <a class="nav-btn {{ Route::is('tasks') ? 'active' : '' }}"
         href="{{ route('tasks') }}" title="Топшириқлар">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M9 11l2 2 4-5M5 4h14v16H5z"/>
        </svg>
        <span>Топшириқлар</span>
      </a>
      <a class="nav-btn {{ Route::is('districts') ? 'active' : '' }}"
         href="{{ route('districts') }}" title="Туманлар">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-7h6v7"/>
        </svg>
        <span>Туманлар</span>
      </a>
      <a class="nav-btn {{ Route::is('execution') ? 'active' : '' }}"
         href="{{ route('execution') }}" title="Ижро мониторинги">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M4 5h16M4 12h16M4 19h10M8 9l2 2 4-4"/>
        </svg>
        <span>Ижро</span>
      </a>
    </aside>

    <main class="main">
      <section class="page-head">
        <div>
          <div class="eyebrow">Андижон вилояти</div>
          <h2>@yield('page-title', 'KPI')</h2>
          <p>@yield('page-subtitle', '')</p>
        </div>
        <div class="toolbar">
          @yield('toolbar')
        </div>
      </section>

      <div style="padding: 0 24px 32px;">
        @yield('content')
      </div>
    </main>
  </div>

  @livewireScripts
</body>
</html>
```

- [ ] **Step 3: Create the five page scaffold views**

Create `backend/resources/views/pages/dashboard.blade.php`:

```blade
@extends('layouts.app')

@section('page-title', 'KPI')
@section('page-subtitle', 'Йиллик мақсад, чораклар кесими ва ижро ҳолати.')

@section('content')
  <livewire:kpi-dashboard />
@endsection
```

Create `backend/resources/views/pages/districts.blade.php`:

```blade
@extends('layouts.app')

@section('page-title', 'Туманлар')
@section('page-subtitle', 'KPI кўрсаткичлари туманлар кесимида.')

@section('content')
  <livewire:districts-page />
@endsection
```

Create `backend/resources/views/pages/tasks.blade.php`:

```blade
@extends('layouts.app')

@section('page-title', 'Топшириқлар')
@section('page-subtitle', 'Кафолат хати топшириқлари ва ижро ҳолати.')

@section('content')
  <livewire:tasks-board />
@endsection
```

Create `backend/resources/views/pages/profile.blade.php`:

```blade
@extends('layouts.app')

@section('page-title', 'Профиль')
@section('page-subtitle', 'Туман кесимидаги кўрсаткичлар.')

@section('content')
  <livewire:region-profile />
@endsection
```

Create `backend/resources/views/pages/execution.blade.php`:

```blade
@extends('layouts.app')

@section('page-title', 'Ижро мониторинги')
@section('page-subtitle', 'Бюджет, инвестиция ва экспорт бўйича ижро ҳолати.')

@section('content')
  <livewire:execution-page />
@endsection
```

- [ ] **Step 4: Verify routes are registered**

```powershell
php artisan route:list
```

Expected output includes these 5 routes:
```
GET  /          → Closure (redirects)
GET  /dashboard → pages.dashboard
GET  /districts → pages.districts
GET  /tasks     → pages.tasks
GET  /profile   → pages.profile
GET  /execution → pages.execution
```

- [ ] **Step 5: Commit**

```powershell
git add backend/routes/web.php `
        backend/resources/views/layouts/app.blade.php `
        backend/resources/views/pages/
git commit -m "feat: add Blade layout, 5 page views, and web routes"
```

---

## Task 4: KpiDashboard Livewire Component

**Files:**
- Create: `backend/app/Livewire/KpiDashboard.php`
- Create: `backend/resources/views/livewire/kpi-dashboard.blade.php`

---

- [ ] **Step 1: Create the KpiDashboard component class**

Create `backend/app/Livewire/KpiDashboard.php`:

```php
<?php

namespace App\Livewire;

use App\Models\IndicatorFact;
use App\Models\Indicator;
use Illuminate\Support\Collection;
use Livewire\Component;

class KpiDashboard extends Component
{
    public string $period = 'year';
    public string $module = 'macro';

    public array $moduleMap = [
        'macro'         => ['grp', 'industry', 'agriculture', 'construction', 'services'],
        'inflation'     => ['inflation'],
        'budget'        => ['budget'],
        'budget_invest' => ['budget_investment'],
        'investment'    => ['investment'],
        'export'        => ['export'],
        'employment'    => ['unemployment', 'poverty', 'jobs', 'legalization', 'mfy_clear', 'microprojects'],
    ];

    public array $moduleLabels = [
        'macro'         => '1. Макроиқтисодиёт',
        'inflation'     => '2. Инфляция',
        'budget'        => '3. Бюджет',
        'budget_invest' => '4. Бюджет инвестициялари',
        'investment'    => '5. Хорижий инвестициялар',
        'export'        => '6. Экспорт',
        'employment'    => '7. Бандлик ва камбағаллик',
    ];

    public array $periodLabels = [
        'year' => 'Йиллик',
        'h1'   => 'I ярим йил',
        'q1'   => 'I чорак',
    ];

    public function render()
    {
        $indicatorCodes = $this->moduleMap[$this->module] ?? [];

        $facts = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNull('district_code')
            ->where('period', $this->period)
            ->whereIn('indicator_code', $indicatorCodes)
            ->get()
            ->keyBy('indicator_code');

        $indicators = Indicator::whereIn('code', $indicatorCodes)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        return view('livewire.kpi-dashboard', [
            'facts'      => $facts,
            'indicators' => $indicators,
        ]);
    }
}
```

- [ ] **Step 2: Create the kpi-dashboard Blade template**

Create `backend/resources/views/livewire/kpi-dashboard.blade.php`:

```blade
<div>
    {{-- Module tabs --}}
    <div class="dashboard-module-tabs">
        @foreach($moduleLabels as $mod => $label)
            <button class="module-tab {{ $module === $mod ? 'active' : '' }}"
                    wire:click="$set('module', '{{ $mod }}')"
                    type="button">
                <span class="module-dot" aria-hidden="true"></span>
                <strong>{{ preg_replace('/^\d+\.\s*/', '', $label) }}</strong>
            </button>
        @endforeach
    </div>

    {{-- Module heading --}}
    <div class="module-heading" style="margin: 16px 0 12px;">
        <div>
            <h2>{{ $moduleLabels[$module] ?? '' }}</h2>
        </div>
    </div>

    {{-- Period switcher --}}
    <div class="segmented" style="margin-bottom: 20px;">
        @foreach($periodLabels as $p => $label)
            <button class="seg-btn {{ $period === $p ? 'active' : '' }}"
                    wire:click="$set('period', '{{ $p }}')"
                    type="button">{{ $label }}</button>
        @endforeach
    </div>

    {{-- KPI tiles --}}
    @php $codes = $moduleMap[$module] ?? []; @endphp

    @if($facts->isEmpty())
        <div style="padding: 32px; text-align: center; color: var(--muted);">
            <p>Маълумот топилмади. Аввал <code>import:region andijan 2026</code> ва <code>import:promote</code> буйруқларини ишга туширинг.</p>
        </div>
    @else
        <div class="front-kpis module-kpis">
            @foreach($codes as $code)
                @php
                    $fact = $facts->get($code);
                    $ind  = $indicators->get($code);
                    if (! $ind) continue;

                    if ($fact && $fact->growth_pct !== null) {
                        $main = number_format((float) $fact->growth_pct, 1) . '%';
                    } elseif ($fact && $fact->plan_value !== null) {
                        $main = number_format((float) $fact->plan_value, 1) . ' ' . $fact->unit;
                    } else {
                        $main = '—';
                    }

                    $hasData = $fact !== null;
                @endphp
                <div role="button" tabindex="0" class="front-kpi {{ ! $hasData ? 'muted' : '' }}">
                    <div class="front-kpi-copy">
                        <h3>{{ $ind->label_short }}</h3>
                        <p>{{ $ind->label_full }}</p>
                        <span class="front-kpi-meta">
                            <i class="front-kpi-dot" aria-hidden="true"></i>
                            {{ $main }}
                        </span>
                        @if($fact && $fact->plan_value !== null && $fact->growth_pct !== null)
                            <small style="display:block; margin-top:4px; color:var(--muted);">
                                Режа: {{ number_format((float) $fact->plan_value, 1) }} {{ $fact->unit }}
                            </small>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
```

- [ ] **Step 3: Verify dashboard page renders**

Start dev server in one terminal and open in browser:

```powershell
php artisan serve
```

Open `http://localhost:8000/dashboard`. Expected:
- Sidebar visible with 4 nav links (KPI active)
- Module tabs row showing 7 modules
- If DB has no data: "Маълумот топилмади" message
- If DB has promoted data: KPI tiles with values

If indicator_facts is empty, run in a separate terminal (workbook files must be present):
```powershell
php artisan import:region andijan 2026
# note the run_id from the output, e.g. run_id=1
php artisan import:promote 1
```

Then refresh the browser — tiles should appear with real values.

- [ ] **Step 4: Commit**

```powershell
git add backend/app/Livewire/KpiDashboard.php `
        backend/resources/views/livewire/kpi-dashboard.blade.php
git commit -m "feat: add KpiDashboard Livewire component with module tabs and period switcher"
```

---

## Task 5: DistrictsPage Livewire Component

**Files:**
- Create: `backend/app/Livewire/DistrictsPage.php`
- Create: `backend/resources/views/livewire/districts-page.blade.php`

---

- [ ] **Step 1: Create the DistrictsPage component class**

Create `backend/app/Livewire/DistrictsPage.php`:

```php
<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\IndicatorFact;
use Illuminate\Support\Collection;
use Livewire\Component;

class DistrictsPage extends Component
{
    public string $period = 'year';
    public string $indicatorCode = 'export';

    public array $availableIndicators = [
        'export'           => 'Экспорт',
        'investment'       => 'Инвестициялар',
        'budget'           => 'Бюджет',
        'budget_investment'=> 'Бюджет инвест',
        'industry'         => 'Саноат',
        'unemployment'     => 'Ишсизлик',
        'poverty'          => 'Камбағаллик',
    ];

    public array $periodLabels = [
        'year' => 'Йиллик',
        'h1'   => 'I ярим йил',
        'q1'   => 'I чорак',
    ];

    public function render()
    {
        $districts = District::where('region_code', 'andijan')
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        $facts = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->where('period', $this->period)
            ->where('indicator_code', $this->indicatorCode)
            ->whereNotNull('district_code')
            ->get()
            ->keyBy('district_code');

        $rollup = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->where('period', $this->period)
            ->where('indicator_code', $this->indicatorCode)
            ->whereNull('district_code')
            ->first();

        return view('livewire.districts-page', [
            'districts' => $districts,
            'facts'     => $facts,
            'rollup'    => $rollup,
        ]);
    }
}
```

- [ ] **Step 2: Create the districts-page Blade template**

Create `backend/resources/views/livewire/districts-page.blade.php`:

```blade
<div>
    {{-- Controls --}}
    <div style="display:flex; gap:12px; align-items:center; margin-bottom:20px; flex-wrap:wrap;">
        <div class="segmented">
            @foreach($periodLabels as $p => $label)
                <button class="seg-btn {{ $period === $p ? 'active' : '' }}"
                        wire:click="$set('period', '{{ $p }}')"
                        type="button">{{ $label }}</button>
            @endforeach
        </div>

        <select wire:model.live="indicatorCode"
                style="padding:6px 10px; border:1px solid var(--line); border-radius:8px; font-size:13px;">
            @foreach($availableIndicators as $code => $label)
                <option value="{{ $code }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @if($facts->isEmpty())
        <div style="padding: 32px; text-align: center; color: var(--muted);">
            <p>Маълумот топилмади. Базани тўлдириш учун <code>import:promote</code> буйруғини ишга туширинг.</p>
        </div>
    @else
        {{-- Rollup row --}}
        @if($rollup)
            <div class="kpi-tile" style="margin-bottom:12px; padding:12px 16px; background:var(--paper); border-radius:10px; border:1px solid var(--line);">
                <strong>Андижон вилояти (жами)</strong>
                <span style="margin-left:16px; color:var(--muted);">
                    @if($rollup->growth_pct !== null)
                        Ўсиш: {{ number_format((float)$rollup->growth_pct, 1) }}%
                    @endif
                    @if($rollup->plan_value !== null)
                        · Режа: {{ number_format((float)$rollup->plan_value, 1) }} {{ $rollup->unit }}
                    @endif
                    @if($rollup->actual_hokimyat !== null)
                        · Амалда: {{ number_format((float)$rollup->actual_hokimyat, 1) }} {{ $rollup->unit }}
                    @endif
                </span>
            </div>
        @endif

        {{-- District table --}}
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid var(--line); text-align:left;">
                    <th style="padding:8px 12px; color:var(--muted); font-weight:500;">Туман</th>
                    <th style="padding:8px 12px; color:var(--muted); font-weight:500;">Режа</th>
                    <th style="padding:8px 12px; color:var(--muted); font-weight:500;">Амалда</th>
                    <th style="padding:8px 12px; color:var(--muted); font-weight:500;">Ўсиш %</th>
                    <th style="padding:8px 12px; color:var(--muted); font-weight:500;">Ижро %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($districts as $districtCode => $district)
                    @php $fact = $facts->get($districtCode); @endphp
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:8px 12px;">
                            <a href="{{ route('profile') }}?districtCode={{ $districtCode }}"
                               style="color:var(--ink); text-decoration:none; font-weight:500;">
                                {{ $district->name_short }}
                            </a>
                        </td>
                        <td style="padding:8px 12px; color:var(--muted);">
                            {{ $fact && $fact->plan_value !== null ? number_format((float)$fact->plan_value, 1) : '—' }}
                        </td>
                        <td style="padding:8px 12px; color:var(--muted);">
                            {{ $fact && $fact->actual_hokimyat !== null ? number_format((float)$fact->actual_hokimyat, 1) : '—' }}
                        </td>
                        <td style="padding:8px 12px; color:var(--muted);">
                            {{ $fact && $fact->growth_pct !== null ? number_format((float)$fact->growth_pct, 1).'%' : '—' }}
                        </td>
                        <td style="padding:8px 12px; color:var(--muted);">
                            {{ $fact && $fact->pct_of_plan !== null ? number_format((float)$fact->pct_of_plan, 2) : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
```

- [ ] **Step 3: Open `/districts` in browser, verify table renders with district data**

```
http://localhost:8000/districts
```

Expected: metric selector, period tabs, table with 16 Andijan districts and their values.

- [ ] **Step 4: Commit**

```powershell
git add backend/app/Livewire/DistrictsPage.php `
        backend/resources/views/livewire/districts-page.blade.php
git commit -m "feat: add DistrictsPage Livewire component with district table and metric switcher"
```

---

## Task 6: RegionProfile Livewire Component

**Files:**
- Create: `backend/app/Livewire/RegionProfile.php`
- Create: `backend/resources/views/livewire/region-profile.blade.php`

---

- [ ] **Step 1: Create the RegionProfile component class**

Create `backend/app/Livewire/RegionProfile.php`:

```php
<?php

namespace App\Livewire;

use App\Models\District;
use App\Models\Indicator;
use App\Models\IndicatorFact;
use Livewire\Attributes\Url;
use Livewire\Component;

class RegionProfile extends Component
{
    #[Url]
    public string $districtCode = '';

    public function render()
    {
        $district = null;
        $facts     = collect();
        $indicators = collect();

        if ($this->districtCode !== '') {
            $district = District::where('code', $this->districtCode)
                ->where('region_code', 'andijan')
                ->first();

            $facts = IndicatorFact::where('region_code', 'andijan')
                ->where('year', 2026)
                ->where('district_code', $this->districtCode)
                ->where('period', 'year')
                ->get()
                ->keyBy('indicator_code');

            $indicatorCodes = $facts->keys()->toArray();
            $indicators = Indicator::whereIn('code', $indicatorCodes)
                ->orderBy('sort_order')
                ->get()
                ->keyBy('code');
        }

        return view('livewire.region-profile', [
            'district'   => $district,
            'facts'      => $facts,
            'indicators' => $indicators,
        ]);
    }
}
```

- [ ] **Step 2: Create the region-profile Blade template**

Create `backend/resources/views/livewire/region-profile.blade.php`:

```blade
<div>
    @if(! $districtCode)
        <div style="padding: 32px; text-align: center; color: var(--muted);">
            <p>Туман танланмаган. <a href="{{ route('districts') }}">Туманлар</a> саҳифасидан туманни танланг.</p>
        </div>
    @elseif(! $district)
        <div style="padding: 32px; text-align: center; color: var(--muted);">
            <p>Туман топилмади: <code>{{ $districtCode }}</code></p>
        </div>
    @else
        <div style="margin-bottom: 24px;">
            <h3 style="margin: 0 0 4px; font-size: 20px;">{{ $district->name_full ?? $district->name_short }}</h3>
            <p style="color: var(--muted); margin: 0;">Андижон вилояти · 2026 йил · Йиллик кўрсаткичлар</p>
        </div>

        @if($facts->isEmpty())
            <div style="padding: 24px; color: var(--muted);">
                <p>Мазкур туман учун маълумот топилмади.</p>
            </div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px;">
                @foreach($indicators as $code => $ind)
                    @php $fact = $facts->get($code); if (! $fact) continue; @endphp
                    <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px;">
                        <p style="margin: 0 0 6px; font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em;">{{ $ind->label_short }}</p>
                        <div style="font-size: 18px; font-weight: 700; color: var(--ink);">
                            @if($fact->growth_pct !== null)
                                {{ number_format((float) $fact->growth_pct, 1) }}%
                            @elseif($fact->plan_value !== null)
                                {{ number_format((float) $fact->plan_value, 1) }}
                                <span style="font-size: 12px; font-weight: 400; color: var(--muted);">{{ $fact->unit }}</span>
                            @else
                                —
                            @endif
                        </div>
                        @if($fact->is_sentinel)
                            <p style="margin: 4px 0 0; font-size: 11px; color: var(--muted);">{{ $fact->sentinel_label }}</p>
                        @endif
                        @if($fact->plan_value !== null && $fact->actual_hokimyat !== null)
                            <p style="margin: 4px 0 0; font-size: 11px; color: var(--muted);">
                                Режа: {{ number_format((float) $fact->plan_value, 1) }} · Амалда: {{ number_format((float) $fact->actual_hokimyat, 1) }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div style="margin-top: 20px;">
            <a href="{{ route('districts') }}" style="color: var(--muted); font-size: 13px;">← Туманлар рўйхатига қайтиш</a>
        </div>
    @endif
</div>
```

- [ ] **Step 3: Verify profile page works**

Navigate to a district from the districts page. Click a district name link — it should open `/profile?districtCode=...` with that district's indicator tiles.

Also test the empty state at `http://localhost:8000/profile` (no districtCode).

- [ ] **Step 4: Commit**

```powershell
git add backend/app/Livewire/RegionProfile.php `
        backend/resources/views/livewire/region-profile.blade.php
git commit -m "feat: add RegionProfile Livewire component with per-district indicator tiles"
```

---

## Task 7: ExecutionPage Livewire Component

**Files:**
- Create: `backend/app/Livewire/ExecutionPage.php`
- Create: `backend/resources/views/livewire/execution-page.blade.php`

---

- [ ] **Step 1: Create the ExecutionPage component class**

Create `backend/app/Livewire/ExecutionPage.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Indicator;
use App\Models\IndicatorFact;
use Livewire\Component;

class ExecutionPage extends Component
{
    public string $period = 'year';

    public array $periodLabels = [
        'year' => 'Йиллик',
        'h1'   => 'I ярим йил',
        'q1'   => 'I чорак',
    ];

    private array $executionIndicators = [
        'budget', 'budget_investment', 'investment', 'export',
    ];

    public function render()
    {
        $facts = IndicatorFact::where('region_code', 'andijan')
            ->where('year', 2026)
            ->whereNull('district_code')
            ->where('period', $this->period)
            ->whereIn('indicator_code', $this->executionIndicators)
            ->get()
            ->keyBy('indicator_code');

        $indicators = Indicator::whereIn('code', $this->executionIndicators)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        return view('livewire.execution-page', [
            'facts'      => $facts,
            'indicators' => $indicators,
        ]);
    }
}
```

- [ ] **Step 2: Create the execution-page Blade template**

Create `backend/resources/views/livewire/execution-page.blade.php`:

```blade
<div>
    {{-- Period tabs --}}
    <div class="segmented" style="margin-bottom: 24px;">
        @foreach($periodLabels as $p => $label)
            <button class="seg-btn {{ $period === $p ? 'active' : '' }}"
                    wire:click="$set('period', '{{ $p }}')"
                    type="button">{{ $label }}</button>
        @endforeach
    </div>

    @if($facts->isEmpty())
        <div style="padding: 32px; text-align: center; color: var(--muted);">
            <p>Маълумот топилмади. Базани тўлдириш учун <code>import:promote</code> буйруғини ишга туширинг.</p>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
            @foreach(['budget', 'budget_investment', 'investment', 'export'] as $code)
                @php
                    $fact = $facts->get($code);
                    $ind  = $indicators->get($code);
                    if (! $ind) continue;
                @endphp
                <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 20px;">
                    <p style="margin: 0 0 8px; font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">
                        {{ $ind->label_short }}
                    </p>
                    <p style="margin: 0 0 4px; font-size: 13px; color: var(--muted);">{{ $ind->label_full }}</p>

                    @if(! $fact)
                        <p style="margin: 12px 0 0; color: var(--muted); font-size: 13px;">Маълумот йўқ</p>
                    @else
                        <div style="margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            @if($fact->plan_value !== null)
                                <div style="padding: 8px; background: var(--surface); border-radius: 6px;">
                                    <p style="margin: 0; font-size: 11px; color: var(--muted);">Режа</p>
                                    <p style="margin: 2px 0 0; font-size: 15px; font-weight: 700;">
                                        {{ number_format((float) $fact->plan_value, 1) }}
                                        <span style="font-size: 11px; font-weight: 400;">{{ $fact->unit }}</span>
                                    </p>
                                </div>
                            @endif
                            @if($fact->expected_value !== null)
                                <div style="padding: 8px; background: var(--surface); border-radius: 6px;">
                                    <p style="margin: 0; font-size: 11px; color: var(--muted);">Кутилаётган</p>
                                    <p style="margin: 2px 0 0; font-size: 15px; font-weight: 700;">
                                        {{ number_format((float) $fact->expected_value, 1) }}
                                        <span style="font-size: 11px; font-weight: 400;">{{ $fact->unit }}</span>
                                    </p>
                                </div>
                            @endif
                            @if($fact->actual_hokimyat !== null)
                                <div style="padding: 8px; background: var(--surface); border-radius: 6px;">
                                    <p style="margin: 0; font-size: 11px; color: var(--muted);">Амалда</p>
                                    <p style="margin: 2px 0 0; font-size: 15px; font-weight: 700;">
                                        {{ number_format((float) $fact->actual_hokimyat, 1) }}
                                        <span style="font-size: 11px; font-weight: 400;">{{ $fact->unit }}</span>
                                    </p>
                                </div>
                            @endif
                            @if($fact->pct_of_plan !== null)
                                <div style="padding: 8px; background: var(--surface); border-radius: 6px;">
                                    <p style="margin: 0; font-size: 11px; color: var(--muted);">Ижро %</p>
                                    <p style="margin: 2px 0 0; font-size: 15px; font-weight: 700;">
                                        {{ number_format((float) $fact->pct_of_plan, 2) }}
                                    </p>
                                </div>
                            @endif
                            @if($fact->growth_pct !== null)
                                <div style="padding: 8px; background: var(--surface); border-radius: 6px;">
                                    <p style="margin: 0; font-size: 11px; color: var(--muted);">Ўсиш</p>
                                    <p style="margin: 2px 0 0; font-size: 15px; font-weight: 700;">
                                        {{ number_format((float) $fact->growth_pct, 1) }}%
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
```

- [ ] **Step 3: Verify execution page**

Open `http://localhost:8000/execution`. Expected: 4 cards (Бюджет, Бюджет инвест, Инвестиция, Экспорт) with plan/expected/actual breakdown. Period tabs switch data.

- [ ] **Step 4: Commit**

```powershell
git add backend/app/Livewire/ExecutionPage.php `
        backend/resources/views/livewire/execution-page.blade.php
git commit -m "feat: add ExecutionPage Livewire component showing budget/investment/export breakdown"
```

---

## Task 8: TasksBoard Stub

**Files:**
- Create: `backend/app/Livewire/TasksBoard.php`
- Create: `backend/resources/views/livewire/tasks-board.blade.php`

---

- [ ] **Step 1: Create the TasksBoard stub component**

Create `backend/app/Livewire/TasksBoard.php`:

```php
<?php

namespace App\Livewire;

use Livewire\Component;

class TasksBoard extends Component
{
    public function render()
    {
        return view('livewire.tasks-board');
    }
}
```

- [ ] **Step 2: Create the tasks-board placeholder template**

Create `backend/resources/views/livewire/tasks-board.blade.php`:

```blade
<div style="padding: 48px 32px; text-align: center; color: var(--muted);">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:48px;height:48px;margin:0 auto 16px;display:block;opacity:.3;">
        <path d="M9 11l2 2 4-5M5 4h14v16H5z"/>
    </svg>
    <h3 style="margin: 0 0 8px; color: var(--ink); font-size: 18px;">Топшириқлар тайёрланмоқда</h3>
    <p style="margin: 0; font-size: 14px;">
        Кафолат хати топшириқлари маълумотлари манба ҳужжатлардан олинади.<br>
        Ушбу саҳифа кейинги режада тўлдирилади.
    </p>
</div>
```

- [ ] **Step 3: Verify tasks page**

Open `http://localhost:8000/tasks`. Expected: placeholder message centered on the page.

- [ ] **Step 4: Commit**

```powershell
git add backend/app/Livewire/TasksBoard.php `
        backend/resources/views/livewire/tasks-board.blade.php
git commit -m "feat: add TasksBoard stub Livewire component"
```

---

## Task 9: HTTP Smoke Tests

**Files:**
- Create: `backend/tests/Feature/Http/DashboardRoutesTest.php`

---

- [ ] **Step 1: Write smoke tests**

Create `backend/tests/Feature/Http/DashboardRoutesTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard route returns 200', function () {
    $this->seed();
    $this->get('/dashboard')->assertStatus(200);
});

test('districts route returns 200', function () {
    $this->seed();
    $this->get('/districts')->assertStatus(200);
});

test('tasks route returns 200', function () {
    $this->seed();
    $this->get('/tasks')->assertStatus(200);
});

test('profile route returns 200 with no district', function () {
    $this->seed();
    $this->get('/profile')->assertStatus(200);
});

test('profile route returns 200 with valid districtCode', function () {
    $this->seed();
    $this->get('/profile?districtCode=andijon_tumani')->assertStatus(200);
});

test('execution route returns 200', function () {
    $this->seed();
    $this->get('/execution')->assertStatus(200);
});

test('root redirect hits dashboard', function () {
    $this->seed();
    $this->get('/')->assertRedirect('/dashboard');
});
```

- [ ] **Step 2: Run smoke tests**

```powershell
php -d memory_limit=1G vendor/bin/pest tests/Feature/Http/DashboardRoutesTest.php -v
```

Expected: 7 PASS.

- [ ] **Step 3: Run full test suite to confirm no regressions**

```powershell
php -d memory_limit=1G vendor/bin/pest --stop-on-failure
```

Expected: all tests pass (previously 116 + new 4 from Task 1 + 7 from Task 9 = 127+).

- [ ] **Step 4: Commit**

```powershell
git add backend/tests/Feature/Http/DashboardRoutesTest.php
git commit -m "test: add HTTP smoke tests for all 5 dashboard routes"
```

---

## Task 10: End-to-End Verify with Real Data

This task has no new files — it's a manual verification that the full data flow works end-to-end using real workbooks.

---

- [ ] **Step 1: Seed and import Andijan data**

```powershell
cd backend
php artisan migrate:fresh --seed
php artisan import:region andijan 2026
```

Note the `run_id` printed in the output (e.g., `ImportRun #3 created`).

- [ ] **Step 2: Promote the staging run**

```powershell
php artisan import:promote 3
```

Expected output:
```
Promoted run #3: 648 indicator facts, N food balance rows, M warehouse rows.
```

- [ ] **Step 3: Open the dashboard and verify each page**

```powershell
php artisan serve
```

Visit each page and confirm:

| URL | Expected |
|---|---|
| `http://localhost:8000/dashboard` | Module tabs + KPI tiles with real values (e.g. Экспорт: 173.7%) |
| `http://localhost:8000/districts` | Table with 16 Andijan districts and their export/investment values |
| `http://localhost:8000/tasks` | Placeholder text (no data) |
| `http://localhost:8000/profile?districtCode=andijon_tumani` | District cards with indicators |
| `http://localhost:8000/execution` | 4 cards: Бюджет, Бюджет инвест, Инвестиция, Экспорт with plan/actual/growth |

- [ ] **Step 4: Final commit tag**

```powershell
git add -A
git commit -m "chore: Plan 9 complete — Blade/Livewire dashboard wired to PostgreSQL"
```

---

## Self-Review

**Spec coverage check:**

| Spec section | Covered by |
|---|---|
| Promote command (§3) | Task 1 |
| CSS extraction (§2) | Task 2 |
| Blade layout + routes (§2, §4 file structure) | Task 3 |
| KpiDashboard (§5) | Task 4 |
| DistrictsPage (§5) | Task 5 |
| RegionProfile (§5) | Task 6 |
| ExecutionPage (§5) | Task 7 |
| TasksBoard stub (§5, §4-A) | Task 8 |
| All routes return 200 (§2 routing) | Task 9 |
| Data availability precondition (§6) | Task 10 |
| Year hardcoded to 2026, period default year (§7) | Tasks 4–7 |
| Empty state when no promoted data (§6) | All Livewire templates |

**Idempotency check:** `import:promote` uses PostgreSQL `ON CONFLICT ... DO UPDATE` — safe to re-run. Both partial indexes (district / rollup) are handled with separate INSERT statements. ✓

**Type consistency:** `$facts->get($code)` returns `IndicatorFact|null` in all components — templates guard with `@if($fact)`. `$indicators->get($code)` returns `Indicator|null` — templates guard with `if (! $ind) continue`. ✓

**Livewire 3 syntax:** `wire:click="$set('module', 'macro')"`, `wire:model.live="indicatorCode"`, `#[Url]` attribute — all correct for Livewire 3. ✓
