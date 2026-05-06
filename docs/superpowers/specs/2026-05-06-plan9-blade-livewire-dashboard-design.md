# Plan 9 — Blade + Livewire Dashboard Design

**Date:** 2026-05-06
**Status:** Approved through all sections. Ready for implementation plan.
**Branch:** `v7-design-polish`
**Scope:** Replace JS-toggled single-page `index.html` with a Laravel Blade + Livewire multi-page dashboard. Data sourced from `indicator_facts`, `food_balance`, `warehouses` (PostgreSQL). Includes `import:promote` command to move staging rows to production tables.

**Predecessors:**
- Plans 1-8: full importer pipeline, 648 staging rows for Andijan 2026 in `import_staging_indicator_facts`
- No promote command yet — production `indicator_facts` is empty

---

## 1. Context

The existing `index.html` prototype hardcodes Andijan data in a ~394KB JS `DATA` blob. Plans 1-8 built a complete importer that populates staging tables from real `.xlsx` workbooks. Plan 9 wires the frontend to PostgreSQL:

1. `import:promote` command moves staging → production
2. New Blade + Livewire frontend reads from production tables
3. Five JS-toggled "pages" become five real Laravel routes with separate URLs

`index.html` stays at repo root as visual reference. New frontend lives in `backend/`.

---

## 2. Architecture

**Routing:** `routes/web.php` — plain view routes, no auth:

```php
Route::get('/', fn() => redirect()->route('dashboard'));
Route::view('/dashboard', 'pages.dashboard')->name('dashboard');
Route::view('/districts', 'pages.districts')->name('districts');
Route::view('/tasks', 'pages.tasks')->name('tasks');
Route::view('/profile', 'pages.profile')->name('profile');
Route::view('/execution', 'pages.execution')->name('execution');
```

**Layout:** `resources/views/layouts/app.blade.php` — shared shell with sidebar and topbar. Sidebar uses `<a href="{{ route(...) }}">`; active link detected via `Route::is(...)`. Pure Blade, no Livewire.

**Components:** One Livewire component per page. Each component owns its DB queries and `$period` state.

**CSS:** `<style>` block from `index.html` extracted to `public/css/portal.css`. Linked via `<link>` in the layout head. No Vite, no npm, no bundler.

**Livewire install:** `composer require livewire/livewire`. `@livewireStyles` in `<head>`, `@livewireScripts` before `</body>`.

---

## 3. Promote Command

`php artisan import:promote {run_id}`

Steps:
1. Load `ImportRun` by ID; assert `status = 'awaiting_review'` (abort with error if not)
2. Upsert `import_staging_indicator_facts` → `indicator_facts`
   - Conflict resolution: UPDATE all fact columns on match (partial unique indexes handle NULL district_code)
3. Upsert `import_staging_food_balance` → `food_balance`
4. Upsert `import_staging_warehouses` → `warehouses`
5. Set `ImportRun.status = 'promoted'`, `promoted_at = now()`
6. Output: row counts per table

No interactive prompts. Idempotent — re-running with same run_id upserts same values.

---

## 4. File Structure

**New files (12):**
```
backend/
  app/
    Livewire/
      KpiDashboard.php
      DistrictsPage.php
      TasksBoard.php
      RegionProfile.php
      ExecutionPage.php
    Console/Commands/
      PromoteImportRunCommand.php
  resources/
    views/
      layouts/
        app.blade.php
      pages/
        dashboard.blade.php
        districts.blade.php
        tasks.blade.php
        profile.blade.php
        execution.blade.php
  public/
    css/
      portal.css
```

**Modified (1):**
- `backend/routes/web.php` — add 5 view routes + redirect

---

## 5. Livewire Components

### KpiDashboard

Queries rollup rows (`district_code IS NULL`) for the current year across all indicator codes. Maps to KPI tiles matching the current dashboard layout.

```php
public string $period = 'year';

public function getFacts(): Collection
{
    return IndicatorFact::where('region_code', 'andijan')
        ->where('year', 2026)
        ->whereNull('district_code')
        ->where('period', $this->period)
        ->get()
        ->keyBy('indicator_code');
}
```

Period switcher tabs (Q1 / H1 / Year) update `$period` via `wire:click`.

### DistrictsPage

District comparison view. `$indicatorCode` switches the displayed metric.

```php
public string $period = 'year';
public string $indicatorCode = 'export';

public function getRows(): Collection
{
    return IndicatorFact::where('region_code', 'andijan')
        ->where('year', 2026)
        ->where('period', $this->period)
        ->where('indicator_code', $this->indicatorCode)
        ->whereNotNull('district_code')
        ->get();
}
```

### RegionProfile

Per-district drilldown. `$districtCode` passed as Livewire URL parameter.

```php
#[Url]
public string $districtCode = '';

public function getFacts(): Collection
{
    return IndicatorFact::where('region_code', 'andijan')
        ->where('year', 2026)
        ->where('district_code', $this->districtCode)
        ->get();
}
```

### TasksBoard

Stub — renders "Топшириқлар маълумотлари тайёрланмоқда" placeholder. No DB query. Tasks data sourced from real documents in a future plan.

### ExecutionPage

Queries budget + investment + export indicator facts for execution monitoring view. `$period` switchable.

---

## 6. Data Availability Precondition

Before the dashboard shows real data:

```bash
cd backend
php artisan import:region andijan 2026
# note the run_id printed in output
php artisan import:promote {run_id}
```

After promote, `indicator_facts` contains 648 rows for Andijan 2026.

If `indicator_facts` is empty, components render a "Маълумот топилмади" empty state (not an error).

---

## 7. Year + Period Handling

- `year` hardcoded to `2026` in each component. No year switcher for now — single-year data.
- `period` default: `'year'` for all components.
- Period enum values used: `q1`, `h1`, `year` (m9 and q2 exist but no dashboard tiles use them yet).

---

## 8. Scope Guardrails

- **No auth.** Internal tool; authentication is a future plan.
- **Andijan only.** Other regions deferred until Navoi contamination is resolved.
- **No Vite/npm.** CSS linked directly as static file.
- **No map.** `districts.json` wiring deferred (existing guardrail from CLAUDE.md).
- **Tasks stub only.** Real tasks table is a separate future plan.
- **`index.html` untouched.** Stays at repo root as reference prototype.

---

## 9. Out of Scope (Deferred)

- **Plan 10:** Filament admin UI + promote/reject flow with review UI
- **Plan 11:** All 14 regions rollout (after Navoi fix)
- **Plan 12:** Tasks table + real task data
- **Auth/permissions:** Spatie Permission + Filament users
- **Interactive map:** GeoJSON wiring for districts.json
- **Multi-year support:** Year dropdown when 2025 data onboarded

---

## 10. Task Estimate

~10 tasks:
1. PromoteImportRunCommand (with tests)
2. CSS extraction → `public/css/portal.css`
3. Blade layout (`layouts/app.blade.php`) + routes
4. `KpiDashboard` Livewire component + `pages/dashboard.blade.php`
5. `DistrictsPage` Livewire component + `pages/districts.blade.php`
6. `RegionProfile` Livewire component + `pages/profile.blade.php`
7. `ExecutionPage` Livewire component + `pages/execution.blade.php`
8. `TasksBoard` stub + `pages/tasks.blade.php`
9. Smoke test: all 5 routes return 200, KPI tiles show real data
10. Commit + end-to-end verify with `php artisan serve`
