# KPI Scoreline Dynamic Counts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hard-coded mock counts in `KpiScoreline::render` with real Eloquent counts from the `tasks` table, scoped to Andijan + the user's selected module/KPI.

**Architecture:** Two inline `Task` count queries inside `KpiScoreline::render`. A small `MODULES_WITH_INDICATOR_TASKS` constant on `DashboardCatalog` selects whether the `indicator_code` predicate joins the query. New `TaskFactory` powers the test fixtures.

**Tech Stack:** PHP 8.3 · Laravel 11 · Livewire 3 · Pest 3 · PostgreSQL. All commands run from `backend/`.

---

## File structure

| File | Responsibility |
|---|---|
| `backend/app/Models/Task.php` | add `HasFactory` trait |
| `backend/database/factories/TaskFactory.php` | new — supply defaults + auto-incrementing `task_number` |
| `backend/app/Support/DashboardCatalog.php` | add `MODULES_WITH_INDICATOR_TASKS` constant |
| `backend/app/Livewire/Dashboard/KpiScoreline.php` | rewrite `render()` to query real counts |
| `backend/tests/Feature/Livewire/KpiScorelineDynamicCountsTest.php` | new — 4 tests covering scope rules + edge cases |

---

### Task 1: Scaffolding (TaskFactory + HasFactory + catalog constant)

**Files:**
- Modify: `backend/app/Models/Task.php`
- Create: `backend/database/factories/TaskFactory.php`
- Modify: `backend/app/Support/DashboardCatalog.php`

This task adds no new tests — it only prepares the scaffolding Task 2 needs. Verify by running the full Pest suite before and after; no behavior change expected.

- [ ] **Step 1: Add `HasFactory` to Task model**

In `backend/app/Models/Task.php`, change the class declaration:

```php
// before
use Illuminate\Database\Eloquent\Model;
// …
class Task extends Model
{
    // …
}

// after
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// …
class Task extends Model
{
    use HasFactory;
    // …
}
```

Both `use` statements must be at the top of the file, sorted alphabetically per Laravel convention. The `use HasFactory;` line goes immediately after the opening `{` of the class.

- [ ] **Step 2: Create `TaskFactory`**

Create `backend/database/factories/TaskFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $n = ++self::$sequence;

        return [
            'region_code'            => 1703,
            'task_number'            => (string) (1000 + $n),
            'title'                  => "Test task {$n}",
            'deadline_text'          => '2026 йил якуни билан',
            'period_code'            => 'year',
            'executor_text'          => 'Test executor',
            'kind'                   => 'measure',
            'module_code'            => 'macro',
            'indicator_code'         => 'grp',
            'section_path'           => 'I.1.1',
            'section_label'          => 'Test section',
            'source_paragraph_index' => $n,
            'status'                 => 'open',
        ];
    }
}
```

The static `$sequence` counter avoids collisions on the `uq_tasks_region_number` unique constraint within a single test run. Tests that need specific `(region_code, indicator_code, status)` combinations override via `->create([...])`.

- [ ] **Step 3: Add `MODULES_WITH_INDICATOR_TASKS` constant**

In `backend/app/Support/DashboardCatalog.php`, add the constant immediately after the existing `MODULES` constant (around line 70):

```php

    /**
     * Modules whose `tasks.indicator_code` is populated. For other modules
     * tasks land with indicator_code = NULL, and the scoreline must skip the
     * indicator_code predicate.
     */
    public const MODULES_WITH_INDICATOR_TASKS = ['macro', 'employment'];
```

- [ ] **Step 4: Run full pest to confirm no regression**

```bash
cd backend && vendor/bin/pest --filter="KpiScoreline|Dashboard|Catalog"
```

Expected: PASS (or unrelated pre-existing failures untouched). The current `KpiScoreline` still returns mock counts; this task changes nothing observable.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Models/Task.php backend/database/factories/TaskFactory.php backend/app/Support/DashboardCatalog.php
git commit -m "feat(dashboard): scaffold TaskFactory + catalog MODULES_WITH_INDICATOR_TASKS"
```

---

### Task 2: KpiScoreline real counts + tests

**Files:**
- Modify: `backend/app/Livewire/Dashboard/KpiScoreline.php`
- Create: `backend/tests/Feature/Livewire/KpiScorelineDynamicCountsTest.php`

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Livewire/KpiScorelineDynamicCountsTest.php`:

```php
<?php

use App\Livewire\Dashboard\KpiScoreline;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('counts macro+grp tasks when module has indicator-tagged tasks', function () {
    // Wipe any imported andijan tasks so our count is exact.
    Task::query()->delete();

    Task::factory()->count(5)->create([
        'region_code' => 1703, 'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'open',
    ]);
    Task::factory()->count(2)->create([
        'region_code' => 1703, 'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'done',
    ]);
    Task::factory()->count(3)->create([
        'region_code' => 1703, 'module_code' => 'macro', 'indicator_code' => 'industry', 'status' => 'open',
    ]);

    Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp'])
        ->assertViewHas('total', 7)
        ->assertViewHas('done', 2)
        ->assertViewHas('open', 5)
        ->assertViewHas('pct', 29);
});

test('counts module-only tasks when module has no indicator_code', function () {
    Task::query()->delete();

    Task::factory()->count(3)->create([
        'region_code' => 1703, 'module_code' => 'budget', 'indicator_code' => null, 'status' => 'open',
    ]);

    Livewire::test(KpiScoreline::class, ['module' => 'budget', 'kpi' => 'budget'])
        ->assertViewHas('total', 3)
        ->assertViewHas('done', 0)
        ->assertViewHas('pct', 0);
});

test('renders zero counts when module has no tasks', function () {
    Task::query()->delete();

    Livewire::test(KpiScoreline::class, ['module' => 'employment', 'kpi' => 'jobs'])
        ->assertViewHas('total', 0)
        ->assertViewHas('done', 0)
        ->assertViewHas('open', 0)
        ->assertViewHas('pct', 0);
});

test('ignores tasks from other regions', function () {
    Task::query()->delete();

    Task::factory()->count(4)->create([
        'region_code' => 1706, 'module_code' => 'macro', 'indicator_code' => 'grp', 'status' => 'open',
    ]);

    Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp'])
        ->assertViewHas('total', 0);
});
```

- [ ] **Step 2: Run tests → expect FAIL**

```bash
cd backend && vendor/bin/pest tests/Feature/Livewire/KpiScorelineDynamicCountsTest.php
```

Expected: all 4 FAIL. The current `KpiScoreline::render` always returns mock `total=12, done=7, pct=58`, so every test mismatches.

- [ ] **Step 3: Rewrite `KpiScoreline::render`**

Replace the entire `render` method in `backend/app/Livewire/Dashboard/KpiScoreline.php`. Also add the `use App\Models\Task;` line at the top of the file:

```php
<?php

namespace App\Livewire\Dashboard;

use App\Models\Indicator;
use App\Models\Task;
use App\Support\DashboardCatalog;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiScoreline extends Component
{
    #[Reactive]
    public string $module = 'macro';

    #[Reactive]
    public string $kpi = 'grp';

    public function render()
    {
        $base = Task::forRegion(1703)->forModule($this->module);

        if (in_array($this->module, DashboardCatalog::MODULES_WITH_INDICATOR_TASKS, true)) {
            $base->forIndicator($this->kpi);
        }

        $total = (clone $base)->count();
        $done  = (clone $base)->where('status', 'done')->count();
        $open  = $total - $done;
        $pct   = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        $indicator = Indicator::where('code', $this->kpi)->first();
        $kpiShort  = $indicator->label_short ?? $this->kpi;
        $scope     = $kpiShort . 'га оид чора-тадбирлар';

        return view('livewire.dashboard.kpi-scoreline', [
            'total'  => $total,
            'done'   => $done,
            'open'   => $open,
            'pct'    => $pct,
            'scope'  => $scope,
            'module' => $this->module,
        ]);
    }
}
```

- [ ] **Step 4: Run tests → expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Livewire/KpiScorelineDynamicCountsTest.php
```

Expected: 4/4 PASS.

- [ ] **Step 5: Confirm no regression elsewhere**

```bash
cd backend && vendor/bin/pest --filter="KpiDashboard|KpiFrontCards|KpiWorkspace|Scoreline"
```

Expected: PASS (other dashboard tests unaffected — the change only touches `KpiScoreline`).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Livewire/Dashboard/KpiScoreline.php backend/tests/Feature/Livewire/KpiScorelineDynamicCountsTest.php
git commit -m "feat(dashboard): KpiScoreline counts real tasks per module/KPI"
```

---

### Task 3: Browser smoke

**Files:** none (operator verification).

- [ ] **Step 1: Fresh DB + seed + tasks import**

```bash
cd backend && php artisan migrate:fresh --seed
php artisan import:tasks andijan
```

Expected: `Imported 86 tasks for region '1703'.`

- [ ] **Step 2: Start dev server**

```bash
cd backend && php artisan serve --port=8000
```

(Keep it running in another terminal.)

- [ ] **Step 3: Click through KPI tabs in browser**

Open http://localhost:8000/kpi. For each verification below, the score-info strip should match the SQL count.

| Module tab | KPI selected | Expected `total` |
|---|---|---|
| Макроиқтисодиёт (macro) | grp | 2 |
| Макроиқтисодиёт (macro) | industry | 18 |
| Макроиқтисодиёт (macro) | agriculture | 3 |
| Макроиқтисодиёт (macro) | construction | 4 |
| Макроиқтисодиёт (macro) | services | 5 |
| Инфляция (inflation) | inflation | 15 |
| Бюджет (budget) | budget | 4 |
| Бюджет инвестициялари (budget_invest) | budget_investment | 10 |
| Хорижий инвестициялар (foreign_invest) | investment | 0 (no tasks tagged with module=foreign_invest+indicator=investment in Andijan) — but module-level count = 0; verify against `SELECT COUNT(*) FROM tasks WHERE region_code=1703 AND module_code='foreign_invest'`. |
| Экспорт (export) | export | 6 |
| Бандлик ва камбағаллик (employment) | poverty | 10 |
| Бандлик ва камбағаллик (employment) | unemployment | 9 |
| Бандлик ва камбағаллик (employment) | jobs | 0 |

`done` should be 0 on every tab (no task is `status='done'` yet). `pct` should be `0%`.

If the exact numbers differ from the table, query the DB directly to confirm what the count should be:

```bash
php artisan tinker --execute="echo DB::table('tasks')->where('region_code',1703)->where('module_code','macro')->where('indicator_code','grp')->count();"
```

If the displayed number matches the SQL result, the feature is correct. The table above is a snapshot taken at plan-writing time; data may drift after re-imports.

- [ ] **Step 4: Empty commit to record smoke**

```bash
git commit --allow-empty -m "test(dashboard): KpiScoreline browser smoke — real counts per module/KPI"
```

---

## Self-review

**Spec coverage:**
- §3 Strategy → Task 2
- §4.1 `KpiScoreline::render` rewrite → Task 2 step 3
- §4.2 `MODULES_WITH_INDICATOR_TASKS` constant → Task 1 step 3
- §5 Edge cases → Task 2 tests (zero count, module-only, ignores-other-region)
- §6 Files → Tasks 1 and 2 file list
- §7 Tests → Task 2 step 1
- §8 Smoke → Task 3

**No placeholders.** All code blocks are concrete. Browser-smoke values in Task 3 step 3 reference live SQL counts; the verification query is provided for drift cases.

**Type consistency:**
- `Task` (Eloquent model) is the same in factory, model, and component.
- `MODULES_WITH_INDICATOR_TASKS` is `array<int, string>` everywhere.
- `$total`, `$done`, `$open`, `$pct` are all `int` per existing view contract.
- Scope helpers `forRegion`, `forModule`, `forIndicator` already exist on `Task` (verified in `app/Models/Task.php:48-71`).
