# Module Tab Task-Count + Icon Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a leading icon, a `done/total` count, and a thin progress bar to each of the 7 dashboard module tabs.

**Architecture:** Three layers, one per concern. (1) `DashboardCatalog::MODULE_ICONS` const + `moduleIcon()` static helper resolves a tab's icon name. (2) `KpiModuleTabs` Livewire component does one grouped SQL query for the current region's tasks, zero-fills missing modules, and passes `$taskCounts` + `$icons` to the view. (3) Blade renders the new BEM-class markup (`.module-tab__icon`, `__body`, `__count`, `__bar`); CSS replaces the existing `.module-tab` block with a 2-column / 2-row grid layout.

**Tech Stack:** Laravel 11 + Livewire 3, Pest 3, PostgreSQL, vanilla CSS in `public/css/portal.css`, SVG icons via `partials.icon`.

---

## File Structure

- `backend/app/Support/DashboardCatalog.php` — add `MODULE_ICONS` const + `moduleIcon()` static.
- `backend/app/Livewire/Dashboard/KpiModuleTabs.php` — add `$regionCode`, `mount()`, grouped task-count query in `render()`.
- `backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php` — new BEM markup.
- `backend/public/css/portal.css` — replace `.module-tab` rule block (lines ~313-370).
- `backend/tests/Unit/Support/DashboardCatalogModuleIconTest.php` (new) — icon-map unit tests.
- `backend/tests/Feature/Livewire/KpiModuleTabsTest.php` (new) — markup-presence feature tests.
- `backend/tests/Pest.php` — register the new unit test file.

---

## Task 1: DashboardCatalog icon map + unit tests

**Files:**
- Modify: `backend/app/Support/DashboardCatalog.php` (add const + helper)
- Create: `backend/tests/Unit/Support/DashboardCatalogModuleIconTest.php`
- Modify: `backend/tests/Pest.php` (register the new test file)

- [ ] **Step 1: Write the failing unit test**

Create `backend/tests/Unit/Support/DashboardCatalogModuleIconTest.php` with exactly:

```php
<?php

use App\Support\DashboardCatalog;

test('moduleIcon returns trend for macro', function () {
    expect(DashboardCatalog::moduleIcon('macro'))->toBe('trend');
});

test('moduleIcon returns price for inflation', function () {
    expect(DashboardCatalog::moduleIcon('inflation'))->toBe('price');
});

test('moduleIcon returns bank for budget', function () {
    expect(DashboardCatalog::moduleIcon('budget'))->toBe('bank');
});

test('moduleIcon returns briefcase for budget_invest', function () {
    expect(DashboardCatalog::moduleIcon('budget_invest'))->toBe('briefcase');
});

test('moduleIcon returns globe for foreign_invest', function () {
    expect(DashboardCatalog::moduleIcon('foreign_invest'))->toBe('globe');
});

test('moduleIcon returns rocket for export', function () {
    expect(DashboardCatalog::moduleIcon('export'))->toBe('rocket');
});

test('moduleIcon returns users for employment', function () {
    expect(DashboardCatalog::moduleIcon('employment'))->toBe('users');
});

test('moduleIcon falls back to trend for unknown code', function () {
    expect(DashboardCatalog::moduleIcon('does_not_exist'))->toBe('trend');
});
```

- [ ] **Step 2: Register the unit test file in Pest.php**

In `backend/tests/Pest.php`, append after the last existing `pest()->extend(...)->in('Unit/...')` rule:

```php
pest()->extend(Tests\TestCase::class)
    ->in('Unit/Support/DashboardCatalogModuleIconTest.php');
```

- [ ] **Step 3: Run the test to verify failure**

Run: `php -d memory_limit=2048M vendor/bin/pest tests/Unit/Support/DashboardCatalogModuleIconTest.php`
Expected: FAIL — `Error: Call to undefined method App\Support\DashboardCatalog::moduleIcon()`.

- [ ] **Step 4: Add the icon map + helper to DashboardCatalog**

In `backend/app/Support/DashboardCatalog.php`, immediately after the `MACRO_GROWTH_KPIS` const (line 18) — or anywhere among the other top-of-class consts — add:

```php
public const MODULE_ICONS = [
    'macro'          => 'trend',
    'inflation'      => 'price',
    'budget'         => 'bank',
    'budget_invest'  => 'briefcase',
    'foreign_invest' => 'globe',
    'export'         => 'rocket',
    'employment'     => 'users',
];
```

Then, near the existing static helpers (e.g. just after `isMacroGrowthKpi()` around line 169), add:

```php
public static function moduleIcon(string $code): string
{
    return self::MODULE_ICONS[$code] ?? 'trend';
}
```

- [ ] **Step 5: Run the unit test to verify pass**

Run: `php -d memory_limit=2048M vendor/bin/pest tests/Unit/Support/DashboardCatalogModuleIconTest.php`
Expected: `Tests: 8 passed`.

- [ ] **Step 6: Run the whole Unit suite to confirm no regression**

Run: `php -d memory_limit=2048M vendor/bin/pest tests/Unit`
Expected: previous baseline `70 passed` plus the new `8 passed` → `78 passed`.

- [ ] **Step 7: Commit**

```powershell
git add backend/app/Support/DashboardCatalog.php backend/tests/Pest.php backend/tests/Unit/Support/DashboardCatalogModuleIconTest.php
git commit -m "feat(catalog): module icon map + moduleIcon() helper"
```

---

## Task 2: KpiModuleTabs component — region + task counts

**Files:**
- Modify: `backend/app/Livewire/Dashboard/KpiModuleTabs.php`

- [ ] **Step 1: Open the current component**

Run: `cat backend/app/Livewire/Dashboard/KpiModuleTabs.php` (or use Read).
Current contents:

```php
<?php

namespace App\Livewire\Dashboard;

use App\Support\DashboardCatalog;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiModuleTabs extends Component
{
    #[Reactive]
    public string $module = 'macro';

    public function selectModule(string $code): void
    {
        $this->dispatch('module-selected', module: $code);
    }

    public function render()
    {
        return view('livewire.dashboard.kpi-module-tabs', [
            'modules'      => DashboardCatalog::modules(),
            'currentModule' => $this->module,
        ]);
    }
}
```

- [ ] **Step 2: Replace the whole file**

Overwrite `backend/app/Livewire/Dashboard/KpiModuleTabs.php` with:

```php
<?php

namespace App\Livewire\Dashboard;

use App\Models\Task;
use App\Support\CurrentRegion;
use App\Support\DashboardCatalog;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class KpiModuleTabs extends Component
{
    #[Reactive]
    public string $module = 'macro';

    public int $regionCode;

    public function mount(): void
    {
        $this->regionCode = CurrentRegion::code();
    }

    public function selectModule(string $code): void
    {
        $this->dispatch('module-selected', module: $code);
    }

    public function render()
    {
        $taskCounts = [];
        foreach (DashboardCatalog::moduleCodes() as $code) {
            $taskCounts[$code] = ['done' => 0, 'total' => 0];
        }

        $rows = Task::forRegion($this->regionCode)
            ->selectRaw("module_code, COUNT(*) AS total, SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done")
            ->whereNotNull('module_code')
            ->groupBy('module_code')
            ->get();

        foreach ($rows as $row) {
            $taskCounts[$row->module_code] = [
                'done'  => (int) $row->done,
                'total' => (int) $row->total,
            ];
        }

        $icons = [];
        foreach (DashboardCatalog::moduleCodes() as $code) {
            $icons[$code] = DashboardCatalog::moduleIcon($code);
        }

        return view('livewire.dashboard.kpi-module-tabs', [
            'modules'       => DashboardCatalog::modules(),
            'currentModule' => $this->module,
            'taskCounts'    => $taskCounts,
            'icons'         => $icons,
        ]);
    }
}
```

- [ ] **Step 3: Run the existing feature suite touching this component to confirm no immediate break**

Run: `vendor/bin/pest tests/Feature/Livewire --filter=KpiScoreline 2>&1 | tail -15`
Expected: existing tests still pass (KpiModuleTabs is not yet covered by a feature test; we add one in Task 3).

If no `KpiScoreline` tests exist or the filter matches nothing, just confirm the dev server doesn't 500 in the next step.

- [ ] **Step 4: Smoke the dashboard route**

Run: `curl -s -o nul -w "%{http_code}" "http://127.0.0.1:8765/dashboard"`
Expected: `200` (the view file still has the old markup at this point — it ignores the new `$taskCounts` and `$icons` variables, which is fine for Blade).

- [ ] **Step 5: Commit**

```powershell
git add backend/app/Livewire/Dashboard/KpiModuleTabs.php
git commit -m "feat(KpiModuleTabs): region-scoped task-count + icon arrays"
```

---

## Task 3: Feature test — new tab markup (failing first)

**Files:**
- Create: `backend/tests/Feature/Livewire/KpiModuleTabsTest.php`

- [ ] **Step 1: Write the failing feature test**

Create `backend/tests/Feature/Livewire/KpiModuleTabsTest.php`:

```php
<?php

use App\Livewire\Dashboard\KpiModuleTabs;
use Livewire\Livewire;

it('renders module tabs with icon, count, and progress bar classes', function () {
    Livewire::test(KpiModuleTabs::class)
        ->assertSeeHtml('class="module-tab__icon"')
        ->assertSeeHtml('class="module-tab__count"')
        ->assertSeeHtml('class="module-tab__bar"');
});

it('renders 0/0 count when region has no tasks for a module', function () {
    Livewire::test(KpiModuleTabs::class)
        ->assertSeeHtml('0/0');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Livewire/KpiModuleTabsTest.php`
Expected: FAIL — `assertSeeHtml`: cannot find `class="module-tab__icon"` (markup still uses old `<span class="module-dot">` + bare `<strong>`).

- [ ] **Step 3: Commit the failing test**

```powershell
git add backend/tests/Feature/Livewire/KpiModuleTabsTest.php
git commit -m "test(KpiModuleTabs): failing assertions for new tab markup"
```

---

## Task 4: Blade markup rewrite

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php`

- [ ] **Step 1: Replace the blade file entirely**

Overwrite `backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php` with:

```blade
<div class="dashboard-module-tabs">
    @foreach($modules as $code => $module)
        @php
            $counts   = $taskCounts[$code] ?? ['done' => 0, 'total' => 0];
            $total    = (int) $counts['total'];
            $done     = (int) $counts['done'];
            $pct      = $total > 0 ? round(($done / $total) * 100, 1) : 0;
            $iconName = $icons[$code] ?? 'trend';
        @endphp
        <button class="module-tab {{ $code === $currentModule ? 'active' : '' }}"
                wire:click="selectModule('{{ $code }}')"
                type="button">
            <span class="module-tab__icon" aria-hidden="true">
                @include('partials.icon', ['name' => $iconName])
            </span>
            <span class="module-tab__body">
                <strong>{{ preg_replace('/^\d+\.\s*/u', '', $module['label']) }}</strong>
                <span class="module-tab__count">{{ $done }}/{{ $total }}</span>
            </span>
            <span class="module-tab__bar" aria-hidden="true">
                <i style="--w:{{ $pct }}%"></i>
            </span>
        </button>
    @endforeach
</div>
```

- [ ] **Step 2: Run the feature test from Task 3 — both should now pass**

Run: `vendor/bin/pest tests/Feature/Livewire/KpiModuleTabsTest.php`
Expected: `Tests: 2 passed`.

- [ ] **Step 3: Smoke the dashboard**

Run: `curl -s -o nul -w "%{http_code}" "http://127.0.0.1:8765/dashboard"`
Expected: `200`.

- [ ] **Step 4: Commit**

```powershell
git add backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php
git commit -m "feat(module-tabs): icon + count + progress bar markup"
```

---

## Task 5: CSS — replace `.module-tab` block

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Locate the existing `.module-tab` block**

Run: `grep -n "^\s*\.module-tab\b\|^\s*\.module-tab\." backend/public/css/portal.css | head -20`
Expected output: matches around lines 313, 326, 330, 340, 349, 357, 364, 368, 370 (the existing rules to replace).

- [ ] **Step 2: Read the lines to confirm boundaries**

Run: `sed -n '310,375p' backend/public/css/portal.css` (or use Read with `offset: 310, limit: 65`).
Confirm the block starts at the `.module-tab {` rule near line 313 and ends with `.module-tab[data-dashboard-module] { --module-color: var(--blue); }` near line 370.

- [ ] **Step 3: Replace lines 313-370 with the new CSS block**

Replace the entire span from the first `.module-tab {` line (≈313) through the `.module-tab[data-dashboard-module] { --module-color: var(--blue); }` rule (≈370), inclusive, with:

```css
    .module-tab {
        position: relative;
        display: grid;
        grid-template-columns: 22px minmax(0, 1fr);
        grid-template-rows: auto 3px;
        column-gap: 10px;
        row-gap: 6px;
        align-items: center;
        padding: 10px 14px;
        border: 1px solid var(--line);
        border-radius: var(--r-md);
        background: #fff;
        cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }

    .module-tab__icon {
        grid-column: 1;
        grid-row: 1;
        display: inline-flex;
        width: 18px;
        height: 18px;
        color: var(--muted);
    }

    .module-tab__icon svg {
        width: 100%;
        height: 100%;
        stroke-width: 1.6;
    }

    .module-tab__body {
        grid-column: 2;
        grid-row: 1;
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
        min-width: 0;
    }

    .module-tab__body strong {
        color: var(--ink);
        font-size: 14px;
        font-weight: 700;
        line-height: 1.18;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .module-tab__count {
        color: #718199;
        font-size: 11px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .module-tab__bar {
        grid-column: 1 / -1;
        grid-row: 2;
        height: 3px;
        border-radius: 2px;
        background: rgba(23, 105, 224, .10);
        overflow: hidden;
    }

    .module-tab__bar i {
        display: block;
        height: 100%;
        width: var(--w, 0%);
        background: var(--blue);
        transition: width .25s ease;
    }

    .module-tab:hover {
        transform: translateY(-1px);
        border-color: rgba(23, 105, 224, .30);
        box-shadow: var(--shadow-sm);
    }

    .module-tab.active {
        background: #fff;
        border-color: rgba(23, 105, 224, .50);
        box-shadow: inset 0 -4px 0 var(--blue), var(--shadow-sm);
    }

    .module-tab.active .module-tab__icon { color: var(--blue); }
    .module-tab.active .module-tab__body strong { color: var(--ink); font-weight: 800; }
    .module-tab.active .module-tab__bar { background: rgba(23, 105, 224, .18); }

    .module-tab[data-dashboard-module] { --module-color: var(--blue); }

    .module-tab__bar:has(i[style*="--w:0%"]) {
        background: rgba(120, 120, 120, .14);
    }
```

The `.module-tab[data-dashboard-module]` rule is preserved verbatim. The old `.module-tab .module-dot`, `.module-tab strong`, and `.module-tab span` rules are removed because the new markup uses BEM-class children instead of bare `strong` / `span`.

- [ ] **Step 4: Verify the old rules are gone and the new ones are present**

Run: `grep -n "module-tab__icon\|module-tab__bar\|module-tab__count" backend/public/css/portal.css`
Expected: at least 8-10 matches (the new BEM classes).

Run: `grep -n "module-tab .module-dot" backend/public/css/portal.css`
Expected: no matches (old rule removed).

- [ ] **Step 5: Smoke 3 dashboard tabs**

```powershell
curl -s -o nul -w "macro %{http_code}`n" "http://127.0.0.1:8765/dashboard?module=macro&kpi=grp"
curl -s -o nul -w "inflation %{http_code}`n" "http://127.0.0.1:8765/dashboard?module=inflation&kpi=inflation"
curl -s -o nul -w "employment %{http_code}`n" "http://127.0.0.1:8765/dashboard?module=employment&kpi=unemployment"
```
Expected: all three end with `200`.

- [ ] **Step 6: Commit**

```powershell
git add backend/public/css/portal.css
git commit -m "style(module-tab): 2-col 2-row grid layout w/ icon + count + bar"
```

---

## Task 6: Full verification

**Files:** none — verification only.

- [ ] **Step 1: Unit suite**

Run: `php -d memory_limit=2048M vendor/bin/pest tests/Unit`
Expected: `78 passed` (70 baseline + 8 new icon tests).

- [ ] **Step 2: New feature test**

Run: `vendor/bin/pest tests/Feature/Livewire/KpiModuleTabsTest.php`
Expected: `Tests: 2 passed`.

- [ ] **Step 3: Smoke all 7 modules**

```powershell
curl -s -o nul -w "macro=%{http_code} " "http://127.0.0.1:8765/dashboard?module=macro&kpi=grp"
curl -s -o nul -w "inflation=%{http_code} " "http://127.0.0.1:8765/dashboard?module=inflation&kpi=inflation"
curl -s -o nul -w "budget=%{http_code} " "http://127.0.0.1:8765/dashboard?module=budget&kpi=budget"
curl -s -o nul -w "budget_invest=%{http_code} " "http://127.0.0.1:8765/dashboard?module=budget_invest&kpi=budget_investment"
curl -s -o nul -w "foreign=%{http_code} " "http://127.0.0.1:8765/dashboard?module=foreign_invest&kpi=investment"
curl -s -o nul -w "export=%{http_code} " "http://127.0.0.1:8765/dashboard?module=export&kpi=export"
curl -s -o nul -w "employment=%{http_code}`n" "http://127.0.0.1:8765/dashboard?module=employment&kpi=unemployment"
```
Expected: every line ends with `200`.

- [ ] **Step 4: Check git status — should be clean**

Run: `git status`
Expected: `nothing to commit, working tree clean`.

---

## Notes for the implementer

- The `:has()` CSS selector in Task 5 Step 3 is supported by all modern browsers (Chromium 105+, Firefox 121+, Safari 15.4+). If a fallback is needed, the bar at 0% still renders blue — acceptable.
- `module_code` on tasks may be NULL (older imports). The query uses `whereNotNull('module_code')` to skip those rows; they don't contribute to any tab.
- Region switching reloads the page (`window.location.reload()` from the region switcher), so counts re-fetch automatically — no extra wire-up.
- The full pest feature suite has a pre-existing memory blow-up that is unrelated to this change. Run individual test files; do NOT run `vendor/bin/pest` without a filter.
