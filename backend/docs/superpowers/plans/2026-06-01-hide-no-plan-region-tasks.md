# Hide region tasks with no plan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On `/tasks` and `/profile`, hide any task whose plan indicator (Режа кўрсаткичи) is empty or «x» for the active region — removed from lists, counts, donut, and filter options.

**Architecture:** The workbook parser already converts empty/`x`/`х`/`-`/`—`/`–` plan cells to `null`, so a "no plan" task is exactly `tasks.headline_plan IS NULL`. We add one Eloquent query scope, `hasPlan()` = `whereNotNull('headline_plan')`, and chain it onto every region-task query in the two Livewire components. No import, migration, or blade changes. Three existing tests assert the old (show-everything) behavior and are updated to give their fixtures a plan.

**Tech Stack:** Laravel 12, Livewire 3, PostgreSQL, Pest 3 / PHPUnit 11.

**Working directory:** all commands run from `backend/`. A running local Postgres test DB (`hududlar_monitoringi_test`, per `phpunit.xml`) is required.

**Branch:** current branch `v7-design-polish` is not `main`; commit directly here (one commit per task).

---

## File Structure

| File | Responsibility | Change |
| --- | --- | --- |
| `app/Models/Task.php` | Task model + query scopes | **Modify** — add `scopeHasPlan()` |
| `app/Livewire/TasksBoard.php` | `/tasks` board: list, totals, filter options | **Modify** — chain `->hasPlan()` on 5 query methods |
| `app/Livewire/RegionProfile.php` | `/profile` district + per-KPI task panels | **Modify** — chain `->hasPlan()` on 4 query methods |
| `tests/Unit/TaskScopeTest.php` | Scope unit tests | **Modify** — add `hasPlan` test |
| `tests/Feature/Tasks/TasksBoardProgressTest.php` | Board feature tests | **Modify** — add 2 tests; fix 1 fixture |
| `tests/Feature/Tasks/ProfileDistrictTasksTest.php` | Profile feature tests | **Modify** — add 1 test; fix 1 fixture |
| `tests/Feature/Http/TasksPageTest.php` | `/tasks` HTTP tests | **Modify** — fix beforeEach fixtures |

Definition used everywhere: **"no plan" := `headline_plan IS NULL`** (the exact value shown in the card's «Режа:» line).

---

## Task 1: Add the `hasPlan` query scope

**Files:**
- Test: `tests/Unit/TaskScopeTest.php`
- Modify: `app/Models/Task.php`

- [ ] **Step 1: Write the failing test**

Append this test to the end of `tests/Unit/TaskScopeTest.php` (after the existing `District has tasks()` test, before EOF). The file's `beforeEach` already creates two region-1703 tasks, both with `headline_plan = null`.

```php
test('hasPlan keeps only tasks that have a headline plan', function () {
    // beforeEach created 2 region-1703 tasks, both with headline_plan = null.
    expect(Task::forRegion(1703)->hasPlan()->count())->toBe(0);

    Task::create([
        'region_code' => 1703, 'task_number' => '9',
        'title' => 'planned task', 'executor_text' => 'хокимлик',
        'kind' => 'kpi', 'module_code' => 'macro',
        'section_path' => 'I', 'section_label' => 'I',
        'source_paragraph_index' => 9, 'headline_plan' => 6,
    ]);

    expect(Task::forRegion(1703)->count())->toBe(3);            // unfiltered: all 3
    expect(Task::forRegion(1703)->hasPlan()->count())->toBe(1); // only the planned one
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/TaskScopeTest.php --filter="hasPlan keeps only"`
Expected: FAIL — `BadMethodCallException: Call to undefined method App\Models\Task::hasPlan()`.

- [ ] **Step 3: Add the scope**

In `app/Models/Task.php`, add this method immediately after `scopeForRegion()` (the `Illuminate\Database\Eloquent\Builder` import already exists at the top of the file). Current `scopeForRegion`:

```php
    public function scopeForRegion(Builder $q, int $code): Builder
    {
        return $q->where('region_code', $code);
    }
```

Add directly below it:

```php
    /** Only tasks that carry a plan value for the active region (Режа кўрсаткичи not empty/«x»). */
    public function scopeHasPlan(Builder $q): Builder
    {
        return $q->whereNotNull('headline_plan');
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/TaskScopeTest.php`
Expected: PASS — all tests in the file green (the pre-existing scope tests are unaffected).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Task.php tests/Unit/TaskScopeTest.php
git commit -m "feat(tasks): add hasPlan scope for plan-less task filtering"
```

---

## Task 2: Apply `hasPlan` on the `/tasks` board

**Files:**
- Test: `tests/Feature/Tasks/TasksBoardProgressTest.php`
- Modify: `app/Livewire/TasksBoard.php`
- Fix existing test: `tests/Feature/Http/TasksPageTest.php`

- [ ] **Step 1: Write the failing tests**

Append these two tests to the end of `tests/Feature/Tasks/TasksBoardProgressTest.php`. The file's `beforeEach` already inserts the `macro` module and a planned task (#1, title `ЯҲМ ўсиши`, `headline_plan = 7.2`).

```php
test('a task with no plan is hidden from the board list and the count', function () {
    // No-plan task in the active region — must not appear, must not be counted.
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '99', 'title' => 'Режасиз топшириқ',
        'module_code' => 'macro', 'indicator_code' => null, 'status' => 'open',
        'headline_plan' => null, 'latest_period' => '2026-Q1',
    ]);

    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSee('ЯҲМ ўсиши')             // planned task (from beforeEach) still visible
        ->assertDontSee('Режасиз топшириқ')   // no-plan task hidden from the list
        ->assertSee('1 та');                  // list count chip excludes the no-plan task
});

test('a no-plan task does not contribute its module to the filter options', function () {
    DB::table('modules')->insert([
        'code' => 'export', 'label' => 'Экспорт', 'sort_order' => 20,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // Only this (hidden) task uses the export module; export must not appear in the filter.
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '98', 'title' => 'Режасиз экспорт',
        'module_code' => 'export', 'indicator_code' => null, 'status' => 'open',
        'headline_plan' => null, 'latest_period' => '2026-Q1',
    ]);

    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSeeHtml('value="macro"')      // macro has a planned task -> offered
        ->assertDontSeeHtml('value="export"'); // export only has a no-plan task -> not offered
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Tasks/TasksBoardProgressTest.php`
Expected: the **two new tests FAIL** (the board does not yet apply `hasPlan`) — first on `assertDontSee('Режасиз топшириқ')` (task currently shows); second on `assertDontSeeHtml('value="export"')` (module currently offered). The pre-existing tests still pass at this point.

- [ ] **Step 3: Apply `hasPlan` to every query in `TasksBoard.php`**

Edit the five computed methods in `app/Livewire/TasksBoard.php`.

`tasks()` — add `->hasPlan()` to the builder chain:

```php
        $q = Task::with(['module', 'indicator', 'districts'])
            ->with(['progress' => function ($p) {
                $p->orderBy('line_no');
            }])
            ->forRegion($this->regionCode)
            ->hasPlan();
```

`moduleOptions()` — first line becomes:

```php
        $codes = Task::forRegion($this->regionCode)
            ->hasPlan()
            ->whereNotNull('module_code')
            ->distinct()
            ->pluck('module_code');
```

`indicatorOptions()` — first line becomes:

```php
        $q = Task::forRegion($this->regionCode)->hasPlan()->whereNotNull('indicator_code');
```

`districtOptions()` — first line becomes:

```php
        $taskIds = Task::forRegion($this->regionCode)->hasPlan()->pluck('id');
```

`totals()` — `$base` becomes:

```php
        $base = Task::forRegion($this->regionCode)->hasPlan();
```

- [ ] **Step 4: Fix the pre-existing test that asserted the old behavior**

Applying the scope intentionally hides plan-less tasks, which breaks one fixture in this same file. In `tests/Feature/Tasks/TasksBoardProgressTest.php`, the test `task without progress data renders without errors` creates a task with no `headline_plan`. Give it a plan so it stays visible while still exercising null actual/percent rendering.

Change:

```php
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '3', 'title' => 'Маълумотсиз топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        // no cadence/headline/latest_period at all (all null)
    ]);
```

to:

```php
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '3', 'title' => 'Маълумотсиз топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        // plan set so the card is visible; actual/pct/cadence stay null -> still render as em-dash
        'headline_plan' => 6,
    ]);
```

- [ ] **Step 5: Fix the `/tasks` HTTP test fixtures**

`tests/Feature/Http/TasksPageTest.php` creates both fixture tasks without a plan, so all three `assertSee` tests there break once the scope is live. Give both tasks a plan in `beforeEach`.

Change:

```php
    Task::create(['region_code'=>1703,'task_number'=>'1','title'=>'macro one','executor_text'=>'хокимлик','kind'=>'kpi','module_code'=>'macro','section_path'=>'I','section_label'=>'I','source_paragraph_index'=>1]);
    Task::create(['region_code'=>1703,'task_number'=>'2','title'=>'export two','executor_text'=>'хокимлик','kind'=>'measure','module_code'=>'export','section_path'=>'VI','section_label'=>'VI','source_paragraph_index'=>2]);
```

to:

```php
    Task::create(['region_code'=>1703,'task_number'=>'1','title'=>'macro one','executor_text'=>'хокимлик','kind'=>'kpi','module_code'=>'macro','section_path'=>'I','section_label'=>'I','source_paragraph_index'=>1,'headline_plan'=>100]);
    Task::create(['region_code'=>1703,'task_number'=>'2','title'=>'export two','executor_text'=>'хокимлик','kind'=>'measure','module_code'=>'export','section_path'=>'VI','section_label'=>'VI','source_paragraph_index'=>2,'headline_plan'=>100]);
```

- [ ] **Step 6: Run the affected test files to verify all pass**

Run: `php artisan test tests/Feature/Tasks/TasksBoardProgressTest.php tests/Feature/Http/TasksPageTest.php`
Expected: PASS — all tests in both files green (new hiding tests pass; updated fixtures keep the old assertions valid).

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/TasksBoard.php tests/Feature/Tasks/TasksBoardProgressTest.php tests/Feature/Http/TasksPageTest.php
git commit -m "feat(tasks): hide plan-less tasks from the board, totals and filters"
```

---

## Task 3: Apply `hasPlan` on the `/profile` district panels

**Files:**
- Test: `tests/Feature/Tasks/ProfileDistrictTasksTest.php`
- Modify: `app/Livewire/RegionProfile.php`

- [ ] **Step 1: Write the failing test**

Append this test to the end of `tests/Feature/Tasks/ProfileDistrictTasksTest.php`. The file's `beforeEach` runs `$this->seed()` (reference data: regions, districts, modules, indicators — no tasks).

```php
test('a no-plan task is hidden from the district panel', function () {
    session(['region_code' => 1703]);
    $district = District::where('region_code', 1703)->first();

    $planned = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '10', 'title' => 'Режали туман топшириғи',
        'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 6, 'headline_actual' => 3,
        'headline_pct' => 50, 'latest_period' => '2026-Q1',
    ]);
    $noPlan = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '11', 'title' => 'Режасиз туман топшириғи',
        'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'open',
        'headline_plan' => null, 'latest_period' => '2026-Q1',
    ]);
    $planned->districts()->sync([$district->id]);
    $noPlan->districts()->sync([$district->id]);

    Livewire::test(RegionProfile::class)
        ->set('districtCode', (string) $district->code)
        ->assertSee('Режали туман топшириғи')      // planned -> visible
        ->assertDontSee('Режасиз туман топшириғи'); // no plan -> hidden
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Tasks/ProfileDistrictTasksTest.php --filter="no-plan task is hidden"`
Expected: FAIL — `assertDontSee('Режасиз туман топшириғи')` fails (task currently shows in the district panel).

- [ ] **Step 3: Apply `hasPlan` to the four task queries in `RegionProfile.php`**

`tasksForKpi()`:

```php
        return Task::forRegion($this->regionCode)
            ->forIndicator($this->kpi)
            ->forDistrict($this->district->id)
            ->hasPlan()
            ->limit(4)
            ->get();
```

`taskCounts()` — `$base`:

```php
        $base = Task::forRegion($this->regionCode)
            ->forIndicator($this->kpi)
            ->forDistrict($this->district->id)
            ->hasPlan();
```

`tasksForDistrict()`:

```php
        return Task::forRegion($this->regionCode)
            ->forDistrict($this->district->id)
            ->hasPlan()
            ->with('indicator')
            ->orderBy('section_path')
            ->orderBy('task_number')
            ->get();
```

`districtTaskCounts()` — `$base`:

```php
        $base = Task::forRegion($this->regionCode)->forDistrict($this->district->id)->hasPlan();
```

- [ ] **Step 4: Fix the pre-existing test that asserted the old behavior**

In `tests/Feature/Tasks/ProfileDistrictTasksTest.php`, the test `district panel handles tasks without progress data` creates a task with no `headline_plan`; it would now be hidden. Give it a plan so it stays visible while still rendering null actual/percent as em-dash.

Change:

```php
    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '2', 'title' => 'Маълумотсиз туман топшириғи',
        'module_code' => 'macro', 'indicator_code' => 'grp',
        // all headline fields null, status default 'open'
    ]);
```

to:

```php
    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '2', 'title' => 'Маълумотсиз туман топшириғи',
        'module_code' => 'macro', 'indicator_code' => 'grp',
        // plan set so the card is visible; actual/pct stay null -> still render as em-dash
        'headline_plan' => 6,
    ]);
```

- [ ] **Step 5: Run the file to verify all pass**

Run: `php artisan test tests/Feature/Tasks/ProfileDistrictTasksTest.php`
Expected: PASS — all tests in the file green.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/RegionProfile.php tests/Feature/Tasks/ProfileDistrictTasksTest.php
git commit -m "feat(tasks): hide plan-less tasks from district profile panels"
```

---

## Task 4: Full-suite verification

**Files:** none (verification only).

- [ ] **Step 1: Run the whole suite**

Run: `php artisan test`
Expected: green **except** the 2 failures already known to be pre-existing on `v7-design-polish`. There must be **no new failures** beyond those 2, and every `tasks`/`profile` test must pass.

- [ ] **Step 2: If any unexpected failure appears, diagnose before continuing**

Likely cause: another test fixture creates a task via `Task::factory()`/`Task::create` without `headline_plan` and asserts it is visible. Confirm with:

Run: `php artisan test --filter=Task`
For each newly failing test, either add `'headline_plan' => <value>` to the fixture (if the task is meant to be visible) or update the assertion to `assertDontSee` (if the test should now prove hiding). Re-run until only the 2 known pre-existing failures remain.

- [ ] **Step 3: No commit** — verification task; nothing to commit if the suite matches the expected baseline.

---

## Self-Review notes (already reconciled)

- **Spec coverage:** scope (Task 1) + board list/totals/filters (Task 2) + profile panels/counts (Task 3) + suite green (Task 4) cover every requirement in the design doc.
- **No-import / no-migration / no-blade** constraints from the spec are respected — only model + two Livewire components + tests change.
- **Naming:** `hasPlan` is used identically in the scope definition and all 9 call sites.
- **Accepted behavior** (per spec): a task with no plan but a reported actual is still hidden; qualitative measures without a numeric plan are hidden too.
