# Module tab task-count badge + icon

**Date:** 2026-05-15
**Scope:** Add per-module icon, "done/total" count, and progress bar to the 7 dashboard module tabs.

## Goal

Each module tab on the dashboard currently shows only its localized label. Add:
1. A leading icon (re-using the existing `partials.icon` SVG set).
2. A small `done/total` count text next to the label.
3. A thin progress bar across the bottom of the tab whose width = `done / total`.

Counts are scoped to the currently selected region (`CurrentRegion::code()`).

## In scope

- Blade: `resources/views/livewire/dashboard/kpi-module-tabs.blade.php`
- Livewire component: `app/Livewire/Dashboard/KpiModuleTabs.php`
- Catalog: `app/Support/DashboardCatalog.php` (add `MODULE_ICONS` const + helper)
- CSS: `public/css/portal.css` (`.module-tab` rules near line 313)
- Tests: `tests/Unit/Support/DashboardCatalogModuleIconTest.php` (new), `tests/Feature/Livewire/KpiModuleTabsTest.php` (new)

## Out of scope

- District-level task counts on the districts page (separate component).
- Color-coding tabs by completion rate (could be added later via `--module-color` token).
- Changing the icon set itself — only the mapping is new.

## Architecture

`KpiModuleTabs` already exists and renders one button per module from `DashboardCatalog::modules()`. The component runs once per dashboard page load, so a single grouped query is cheap.

Add to `KpiModuleTabs`:
- `public int $regionCode` hydrated in `mount()` from `CurrentRegion::code()`.
- Computed `taskCountsByModule()` returns `['macro' => ['done' => 3, 'total' => 12], …]`. All 7 modules present (zero-filled).
- Computed `moduleIcons()` returns `['macro' => 'trend', …]` from `DashboardCatalog::moduleIcon()`.

Pass both to the view alongside the existing `modules` + `currentModule`.

## Components

### Markup (kpi-module-tabs.blade.php)

```blade
<div class="dashboard-module-tabs">
    @foreach($modules as $code => $module)
        @php
            $counts = $taskCounts[$code] ?? ['done' => 0, 'total' => 0];
            $total = (int) $counts['total'];
            $done  = (int) $counts['done'];
            $pct   = $total > 0 ? round(($done / $total) * 100, 1) : 0;
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

The old `<span class="module-dot">` and bare `<strong>` are replaced by the icon span + body span + bar span.

### CSS (portal.css, around line 313)

Replace the existing `.module-tab` block + its descendants. Key changes:

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

/* Active state */
.module-tab.active {
    background: #fff;
    border-color: rgba(23, 105, 224, .50);
    box-shadow: inset 0 -4px 0 var(--blue), var(--shadow-sm);
}
.module-tab.active .module-tab__icon { color: var(--blue); }
.module-tab.active .module-tab__body strong { color: var(--ink); font-weight: 800; }
.module-tab.active .module-tab__bar { background: rgba(23, 105, 224, .18); }

/* Hover */
.module-tab:hover {
    transform: translateY(-1px);
    border-color: rgba(23, 105, 224, .30);
    box-shadow: var(--shadow-sm);
}

/* Empty state — when total = 0, dim the bar */
.module-tab__bar:has(i[style*="--w:0%"]) {
    background: rgba(120, 120, 120, .14);
}
```

The pre-existing rules at lines 326-328 (`.module-tab .module-dot { display: none; }`), 330-339 (`.module-tab strong`), 340-348 (`.module-tab span`), 357-368 (`.module-tab.active …`) and 370 (`.module-tab[data-dashboard-module]`) are superseded. Replace them; do not leave the old rules behind to drift.

### DashboardCatalog

Add a class const + helper:

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

public static function moduleIcon(string $code): string
{
    return self::MODULE_ICONS[$code] ?? 'trend';
}
```

### Livewire component

`KpiModuleTabs.php`:

```php
public int $regionCode;

public function mount(): void
{
    $this->regionCode = CurrentRegion::code();
}

public function render()
{
    // Build {module => {done, total}} for all 7 modules (zero-filled).
    $taskCounts = [];
    foreach (DashboardCatalog::moduleCodes() as $code) {
        $taskCounts[$code] = ['done' => 0, 'total' => 0];
    }
    foreach (Task::forRegion($this->regionCode)
        ->selectRaw("module_code, COUNT(*) AS total, SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done")
        ->whereNotNull('module_code')
        ->groupBy('module_code')
        ->get() as $row
    ) {
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
```

(One query, one passthrough loop — no per-tab N+1.)

## Data flow

1. Page mounts → `KpiModuleTabs::mount()` reads `CurrentRegion::code()`.
2. `render()` runs the grouped SQL → zero-fills missing modules → resolves icon names.
3. Blade renders 7 tabs with icon SVG + label + count + progress bar.
4. Region switcher already triggers a full page reload, so counts re-fetch automatically.

## Testing

### Unit — DashboardCatalog

`tests/Unit/Support/DashboardCatalogModuleIconTest.php`:

```php
test('moduleIcon returns trend for macro', fn () => expect(DashboardCatalog::moduleIcon('macro'))->toBe('trend'));
test('moduleIcon returns price for inflation', fn () => expect(DashboardCatalog::moduleIcon('inflation'))->toBe('price'));
test('moduleIcon returns bank for budget', fn () => expect(DashboardCatalog::moduleIcon('budget'))->toBe('bank'));
test('moduleIcon returns briefcase for budget_invest', fn () => expect(DashboardCatalog::moduleIcon('budget_invest'))->toBe('briefcase'));
test('moduleIcon returns globe for foreign_invest', fn () => expect(DashboardCatalog::moduleIcon('foreign_invest'))->toBe('globe'));
test('moduleIcon returns rocket for export', fn () => expect(DashboardCatalog::moduleIcon('export'))->toBe('rocket'));
test('moduleIcon returns users for employment', fn () => expect(DashboardCatalog::moduleIcon('employment'))->toBe('users'));
test('moduleIcon falls back to trend for unknown code', fn () => expect(DashboardCatalog::moduleIcon('nope'))->toBe('trend'));
```

Register in `tests/Pest.php` via `pest()->extend(Tests\TestCase::class)->in('Unit/Support/DashboardCatalogModuleIconTest.php');`.

### Feature — KpiModuleTabs

`tests/Feature/Livewire/KpiModuleTabsTest.php`:

```php
use App\Livewire\Dashboard\KpiModuleTabs;
use Livewire\Livewire;

it('renders 7 tabs with icon + count + bar', function () {
    Livewire::test(KpiModuleTabs::class)
        ->assertSeeHtml('class="module-tab__icon"')
        ->assertSeeHtml('class="module-tab__count"')
        ->assertSeeHtml('class="module-tab__bar"');
});

it('renders 0/0 count when region has no tasks', function () {
    Livewire::test(KpiModuleTabs::class)
        ->assertSeeHtml('0/0');
});
```

(Skip seeded-count assertions to keep tests independent of fixture data — count format is what we want to verify, not the numbers.)

## Risks

- The `:has()` CSS selector for the empty-state bar dim requires modern browsers (Chromium 105+, Firefox 121+, Safari 15.4+). Acceptable for the portal's target audience; if it's unavailable, the bar stays blue at 0% width — visually still a non-event.
- `module_code` for tasks may be NULL for some imported tasks (older import code allowed it). The `whereNotNull('module_code')` clause drops those rows; their counts don't appear in any tab. This is the same convention used by `Task::forModule()`.
- The 7-module list must stay in sync with `DashboardCatalog::MODULE_ICONS`. Adding a module without updating the icon map yields `trend` fallback — acceptable, but worth flagging in a code review.

## Files touched

- `app/Support/DashboardCatalog.php` — `MODULE_ICONS` const + `moduleIcon()` static.
- `app/Livewire/Dashboard/KpiModuleTabs.php` — `$regionCode`, `mount()`, count + icon arrays in `render()`.
- `resources/views/livewire/dashboard/kpi-module-tabs.blade.php` — new tab markup.
- `public/css/portal.css` — replace `.module-tab` block (~lines 313-370) with the layout above.
- `tests/Unit/Support/DashboardCatalogModuleIconTest.php` (new).
- `tests/Feature/Livewire/KpiModuleTabsTest.php` (new).
- `tests/Pest.php` — register new unit test file.
