# KPI scoreline dynamic counts

**Date:** 2026-05-13
**Status:** Approved (pending user spec review)
**Scope:** Replace hard-coded mock counts (`$total = 12; $done = 7;`) in `App\Livewire\Dashboard\KpiScoreline` with real Eloquent counts from the `tasks` table, scoped to Andijan + the currently-selected module and KPI. Modules whose tasks carry no `indicator_code` fall back to module-only filter.

---

## 1. Goal

The KPI dashboard's "Чора-тадбирлар ижроси" strip displays three counters (Жами / Бажарилди / Бажарилмади) and a donut percentage. The component currently renders the same numbers regardless of which module or KPI is selected — `KpiScoreline::render()` lines 22–24 use placeholder integers tagged "Plan 10 Q1=C decision (real tasks data lands in Plan 12)".

After this change, the strip reflects the real task workload for the user's current selection. For example, on `module=macro, kpi=grp` the strip will show 2 tasks (Andijan, grp); on `module=macro, kpi=industry` it will show 18; on `module=budget` it will show 4 (module-level, no KPI filter). Until the task status-edit UI lands separately, `done` will always be 0 — that is intentional and accurate for the current state of the data.

## 2. Non-goals

- No region selector — region stays hard-coded to Andijan (`1703`) to match `KpiFrontCards` and `KpiWorkspaceCard`.
- No task-status-edit UI. Every task is currently `status='open'`; `done`-count will be 0 across the board until that ships.
- No new service class, no caching, no aggregate table.
- No change to the Blade view, route, or task model schema.
- No district drilldown — district remains national-only at this layer.

## 3. Strategy

Inline the count queries inside `KpiScoreline::render()` using existing `Task` model scopes. Decide whether to filter by `indicator_code` using a small hard-coded list of "modules whose tasks carry an indicator_code" — empirically `macro` and `employment` are the only ones (every other module's tasks land with `indicator_code IS NULL`).

This mirrors the pattern of the sibling `KpiFrontCards` component, which queries `IndicatorFact` directly in its `render()` without an intervening service. Minimal diff, no extra abstraction.

## 4. Component change

### 4.1 `KpiScoreline::render` rewrite

```php
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
```

`use App\Models\Task;` added at the top of the file.

### 4.2 DashboardCatalog addition

Append to `App\Support\DashboardCatalog`:

```php
/**
 * Modules whose `tasks.indicator_code` is populated. For all other modules
 * tasks carry indicator_code = NULL, and the scoreline filter must skip the
 * indicator_code predicate.
 */
public const MODULES_WITH_INDICATOR_TASKS = ['macro', 'employment'];
```

This is a domain fact, not a derived value; hard-coding it is correct.

## 5. Edge cases

| Case | Behavior |
|---|---|
| Module = macro, KPI = grp | total = count(tasks where region=1703, module=macro, indicator_code=grp); pct = (done/total)*100 |
| Module = budget, KPI = budget | total = count(tasks where region=1703, module=budget); KPI ignored |
| Module = employment, KPI = jobs (no tasks) | total = 0, done = 0, pct = 0; view renders without crash |
| Unknown module / unknown KPI | DashboardCatalog already constrains module + kpi via URL guards in `KpiDashboard::mount`. If somehow bypassed, scopes return 0 — no crash |
| `Indicator::where('code', $kpi)->first()` returns null | Scope text falls back to `$this->kpi` literal (existing behavior preserved) |

## 6. Files

| File | Action |
|---|---|
| `backend/app/Livewire/Dashboard/KpiScoreline.php` | modify `render()` |
| `backend/app/Support/DashboardCatalog.php` | add `MODULES_WITH_INDICATOR_TASKS` const |
| `backend/tests/Feature/Livewire/KpiScorelineDynamicCountsTest.php` | new |

No view changes, no migrations, no model changes.

## 7. Tests

`tests/Feature/Livewire/KpiScorelineDynamicCountsTest.php`:

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
    Task::factory()->count(5)->create([
        'region_code' => 1703, 'module_code' => 'macro', 'indicator_code' => 'grp',
        'status' => 'open',
    ]);
    Task::factory()->count(2)->create([
        'region_code' => 1703, 'module_code' => 'macro', 'indicator_code' => 'grp',
        'status' => 'done',
    ]);
    Task::factory()->count(3)->create([
        'region_code' => 1703, 'module_code' => 'macro', 'indicator_code' => 'industry',
        'status' => 'open',
    ]); // noise: different KPI

    Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp'])
        ->assertViewHas('total', 7)
        ->assertViewHas('done', 2)
        ->assertViewHas('open', 5)
        ->assertViewHas('pct', 29);
});

test('counts module-only tasks when module has no indicator_code', function () {
    Task::factory()->count(3)->create([
        'region_code' => 1703, 'module_code' => 'budget', 'indicator_code' => null,
        'status' => 'open',
    ]);

    Livewire::test(KpiScoreline::class, ['module' => 'budget', 'kpi' => 'budget'])
        ->assertViewHas('total', 3)
        ->assertViewHas('done', 0)
        ->assertViewHas('pct', 0);
});

test('renders zero counts when module has no tasks', function () {
    Livewire::test(KpiScoreline::class, ['module' => 'employment', 'kpi' => 'jobs'])
        ->assertViewHas('total', 0)
        ->assertViewHas('done', 0)
        ->assertViewHas('open', 0)
        ->assertViewHas('pct', 0);
});

test('ignores tasks from other regions', function () {
    Task::factory()->count(4)->create([
        'region_code' => 1706, 'module_code' => 'macro', 'indicator_code' => 'grp',
        'status' => 'open',
    ]); // Bukhara — must not leak

    Livewire::test(KpiScoreline::class, ['module' => 'macro', 'kpi' => 'grp'])
        ->assertViewHas('total', 0);
});
```

If `TaskFactory` does not yet exist, the implementer adds one at `database/factories/TaskFactory.php` with these defaults: `task_number=fake unique int`, `title='task'`, `kind='measure'`, `module_code='macro'`, `indicator_code='grp'`, `region_code=1703`, `status='open'`.

## 8. Smoke

```bash
cd backend && php artisan migrate:fresh --seed
php artisan import:tasks andijan
```

Open `http://localhost:8000/kpi`. Click through every module tab. For modules with KPI strips (macro, employment) click through every KPI card. Confirm the score-info strip updates `total` / `done` / `open` / `pct` to match the SQL `SELECT COUNT(*) FROM tasks WHERE region_code=1703 AND module_code=... AND indicator_code=...`. For modules without KPIs (inflation, budget, budget_invest, foreign_invest, export) the strip stays on the module-total regardless of any KPI clicks.

## 9. Risks

- **Risk:** Status enum drift — if a future commit renames `'done'` to `'completed'` in tasks, counts silently break. *Mitigation:* the existing view already references `status=done` in `route('tasks')?status=done`. Tests assert the string. Drift surfaces immediately.
- **Risk:** N+1 / query cost on every reactive click. *Mitigation:* Postgres index `idx_tasks_region_module` covers the predicates. Two count queries per render are cheap.
- **Risk:** Pre-existing `Module=employment` task data only includes 2 of 6 catalog KPIs (poverty, unemployment); the other 4 (jobs, legalization, mfy_clear, microprojects) render 0/0/—. *Mitigation:* intentional — reflects source-data reality; no test or UX guard needed.
- **Risk:** Region 1703 hard-code drifts further from sibling components when one of them adopts a real selector. *Mitigation:* refactor `KpiScoreline`, `KpiFrontCards`, and `KpiWorkspaceCard` together when the selector lands. No code comment needed.
