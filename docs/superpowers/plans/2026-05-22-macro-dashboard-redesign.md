# Macro Dashboard Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the Макроиқтисодиёт dashboard view to match `Screenshot 2026-05-22 100100.png` — a single white card with a dark-blue ЯҲМ hero, a 2×2 sector grid, and a period row, plus a restyled blue module-tab strip and a restyled execution scoreline.

**Architecture:** CSS-driven restyle in `backend/public/css/portal.css` plus thin Blade edits. The macro view's three Livewire components (`kpi-front-cards`, `kpi-workspace-card`, `kpi-scoreline`) keep their PHP classes and state wiring; a conditional wrapper `<div>` turns them into one visual card for the `macro` module only. The other 6 modules render unchanged (`display: contents` wrapper).

**Tech Stack:** Laravel 11, Livewire 3, Blade, plain CSS (no build step — `portal.css` is served directly), Pest test suite.

**Spec:** `docs/superpowers/specs/2026-05-22-macro-dashboard-redesign-design.md`

---

## Context for the implementer

- `portal.css` is hand-written plain CSS served straight from `backend/public/css/`. There is **no build step**. Hex literals are used throughout the file — match that style; do not introduce a token system.
- The dashboard route is `/dashboard`. Default state is `module=macro`, `kpi=grp`. Module codes: `macro, inflation, budget, budget_invest, foreign_invest, export, employment` (7 modules — the screenshot shows 6 but **all 7 tabs are kept**, this is intentional).
- The macro front cards (`kpi-front-cards.blade.php`) render one `.front-kpi` button per KPI; the `grp` card gets an extra `parent` class. KPI order: `grp, industry, agriculture, construction, services`.
- The macro detail panel (`macro-growth.blade.php`) has two branches: `solo` (currently `macro-hero-strip`) for `grp`/`agriculture`/`construction`/`services`; `with-side` (`macro-main-panel` + `industry-driver-panel`) for `industry`. Only the `solo` branch changes.
- Run the test suite with `php artisan test` from the `backend/` directory.
- **Baseline:** before any work, the suite has 2 pre-existing failures — one in `MacroHeroStripTest` (asserts `macro-hero-strip__chip is-actual`, which current markup never rendered) and one in `KpiModuleTabsTest` (asserts `module-tab__icon`/`module-tab__bar`, removed in an earlier restyle). Both are fixed by this plan.

---

## File structure

| File | Responsibility | Tasks |
| --- | --- | --- |
| `backend/public/css/portal.css` | All visual styling | 1, 2, 3, 4, 5, 6 |
| `backend/resources/views/livewire/kpi-dashboard.blade.php` | Conditional `module-card`/`module-flow` wrapper | 2 |
| `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php` | `solo` branch → period row | 4 |
| `backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php` | `is-macro` class | 5 |
| `backend/app/Support/DashboardCatalog.php` | macro `intro` text | 2 |
| `backend/tests/Feature/Livewire/KpiModuleTabsTest.php` | Fix stale tab assertions | 1 |
| `backend/tests/Feature/Livewire/MacroHeroStripTest.php` → `MacroPeriodRowTest.php` | Period-row assertions | 4 |
| `backend/tests/Feature/Http/DashboardRoutesTest.php` | Period-row + module-card + scoreline route assertions | 2, 4, 5 |

All commands below are run from the `backend/` directory unless noted.

---

### Task 1: Module-tab strip restyle

Restyle the 7-tab strip to a row of solid blue buttons (no white track), and fix the stale `KpiModuleTabsTest`.

**Files:**
- Modify: `backend/public/css/portal.css` (lines 277-338)
- Test: `backend/tests/Feature/Livewire/KpiModuleTabsTest.php`

- [ ] **Step 1: Fix the stale tab test**

`KpiModuleTabsTest.php` asserts class names (`module-tab__icon`, `module-tab__bar`) that the current markup does not render. Replace the whole file with assertions for the markup that actually exists (`module-tab__body`, `module-tab__count`):

```php
<?php

use App\Livewire\Dashboard\KpiModuleTabs;
use Livewire\Livewire;

it('renders module tabs with body and count classes', function () {
    Livewire::test(KpiModuleTabs::class)
        ->assertSeeHtml('class="module-tab__body"')
        ->assertSeeHtml('class="module-tab__count"');
});

it('renders 0/0 count when region has no tasks for a module', function () {
    Livewire::test(KpiModuleTabs::class)
        ->assertSeeHtml('0/0');
});
```

- [ ] **Step 2: Run the test to verify it passes**

Run: `php artisan test --filter KpiModuleTabs`
Expected: PASS (2 passed). This confirms the stale assertions are fixed.

- [ ] **Step 3: Restyle the tab strip**

In `backend/public/css/portal.css`, replace this exact block (lines 277-338):

```css
    .dashboard-module-tabs {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 6px;
      margin-bottom: 16px;
      padding: 6px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 20px;
      box-shadow: var(--shadow-sm);
    }

    .module-tab {
      display: grid;
      place-items: center;
      padding: 11px 12px;
      border: 0;
      border-radius: 15px;
      background: #eef0f3;
      cursor: pointer;
      transition: background .15s ease;
    }

    .module-tab__body {
      display: grid;
      justify-items: center;
      gap: 3px;
      min-width: 0;
      text-align: center;
    }

    .module-tab__body strong {
      color: var(--ink);
      font-size: 13.5px;
      font-weight: 700;
      line-height: 1.2;
    }

    .module-tab__count {
      color: var(--muted);
      font-size: 11.5px;
      font-weight: 700;
      font-variant-numeric: tabular-nums;
    }

    .module-tab:hover {
      background: #e4e7ec;
    }

    .module-tab.active {
      background: var(--accent-grad);
      box-shadow: 0 6px 16px rgba(23, 105, 224, .28);
    }

    .module-tab.active .module-tab__body strong {
      color: #fff;
      font-weight: 800;
    }

    .module-tab.active .module-tab__count {
      color: rgba(255, 255, 255, .82);
    }
```

with:

```css
    .dashboard-module-tabs {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 8px;
      margin-bottom: 16px;
      padding: 0;
      background: transparent;
      border: 0;
      border-radius: 0;
      box-shadow: none;
    }

    .module-tab {
      display: grid;
      place-items: center;
      padding: 12px 12px;
      border: 0;
      border-radius: 12px;
      background: var(--blue);
      cursor: pointer;
      transition: background .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .module-tab__body {
      display: grid;
      justify-items: center;
      gap: 3px;
      min-width: 0;
      text-align: center;
    }

    .module-tab__body strong {
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      line-height: 1.2;
    }

    .module-tab__count {
      color: rgba(255, 255, 255, .78);
      font-size: 11px;
      font-weight: 700;
      font-variant-numeric: tabular-nums;
    }

    .module-tab:hover {
      background: #1257bd;
    }

    .module-tab.active {
      background: var(--blue-2);
      box-shadow: 0 6px 16px rgba(8, 57, 93, .32);
    }

    .module-tab.active .module-tab__body strong {
      color: #fff;
      font-weight: 800;
    }

    .module-tab.active .module-tab__count {
      color: rgba(255, 255, 255, .85);
    }
```

- [ ] **Step 4: Run the full suite to confirm no regression**

Run: `php artisan test`
Expected: 1 pre-existing failure remaining (`MacroHeroStripTest` — fixed in Task 4). The `KpiModuleTabs` failure is gone.

- [ ] **Step 5: Commit**

```bash
git add backend/public/css/portal.css backend/tests/Feature/Livewire/KpiModuleTabsTest.php
git commit -m "style(dashboard): blue solid module-tab strip"
```

---

### Task 2: Module card wrapper

Wrap the macro module's heading + front cards + panel in one white card. Other modules use a `display: contents` wrapper and render unchanged. Fix the macro subtitle copy.

**Files:**
- Modify: `backend/resources/views/livewire/kpi-dashboard.blade.php`
- Modify: `backend/app/Support/DashboardCatalog.php:33`
- Modify: `backend/public/css/portal.css` (insert before line 340 `.module-heading {`)
- Test: `backend/tests/Feature/Http/DashboardRoutesTest.php`

- [ ] **Step 1: Write the failing tests**

Append these two tests to `backend/tests/Feature/Http/DashboardRoutesTest.php`:

```php
test('dashboard macro module wraps content in a module card', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=macro');
    $response->assertStatus(200);
    $response->assertSee('class="module-card"', false);
});

test('dashboard non-macro module uses the flow wrapper, not the card', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=budget');
    $response->assertStatus(200);
    $response->assertSee('class="module-flow"', false);
    $response->assertDontSee('class="module-card"', false);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter "module card|flow wrapper"`
Expected: FAIL — `module-card` / `module-flow` not in the HTML yet.

- [ ] **Step 3: Add the conditional wrapper**

Replace the entire contents of `backend/resources/views/livewire/kpi-dashboard.blade.php` with:

```blade
<div>
    <livewire:dashboard.kpi-module-tabs :module="$module" :key="'tabs-'.$module" />

    <div class="{{ $module === 'macro' ? 'module-card' : 'module-flow' }}">
        <div class="module-heading">
            <div>
                <h2>{{ $moduleLabel }}</h2>
                <p>{{ $moduleIntro }}</p>
            </div>
        </div>

        @if($hasFrontCards)
            <livewire:dashboard.kpi-front-cards :module="$module" :kpi="$kpi" :key="'front-'.$module.'-'.$kpi" />
        @endif

        <livewire:dashboard.kpi-workspace-card :module="$module" :kpi="$kpi" :key="'work-'.$module.'-'.$kpi" />
    </div>

    <livewire:dashboard.kpi-scoreline :module="$module" :kpi="$kpi" :key="'score-'.$module.'-'.$kpi" />
</div>
```

- [ ] **Step 4: Fix the macro subtitle copy**

In `backend/app/Support/DashboardCatalog.php`, line 33, change:

```php
            'intro' => 'ЯҲМ ва асосий таркибий кўрсаткичлар',
```

to:

```php
            'intro' => 'ЯҲМ ва асосий тармоқлар кўрсаткичлари',
```

- [ ] **Step 5: Add the card CSS**

In `backend/public/css/portal.css`, insert this block immediately **before** the existing `.module-heading {` rule (line 340 — the line reading `    .module-heading {`):

```css
    .module-flow { display: contents; }

    .module-card {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 18px;
      box-shadow: var(--shadow);
      padding: 20px;
      margin-bottom: 16px;
    }

    .module-card .module-heading {
      display: block;
      margin: 0 0 16px;
    }

    .module-card .module-heading > div {
      padding-left: 0;
    }

    .module-card .module-heading > div::before {
      display: none;
    }

    .module-card .module-heading h2 {
      font-size: 18px;
      letter-spacing: -0.01em;
      font-weight: 800;
    }

    .module-card .module-heading p {
      margin-top: 3px;
      font-size: 13px;
    }

```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter "module card|flow wrapper|DashboardRoutes"`
Expected: the two new tests PASS; all other `DashboardRoutesTest` tests except `dashboard macro module renders macro hero strip` still pass (that one is fixed in Task 4).

- [ ] **Step 7: Commit**

```bash
git add backend/resources/views/livewire/kpi-dashboard.blade.php backend/app/Support/DashboardCatalog.php backend/public/css/portal.css backend/tests/Feature/Http/DashboardRoutesTest.php
git commit -m "feat(dashboard): wrap macro module view in a single card"
```

---

### Task 3: Blue ЯҲМ hero + sector grid

Restyle the macro front cards: `grp` becomes a dark-blue hero panel with a decorative up-arrow; the 4 sector cards form a 2×2 grid to its right. CSS only — no markup change.

**Files:**
- Modify: `backend/public/css/portal.css` (lines 650-652 and 688-712)

- [ ] **Step 1: Replace the macro-layout grid rule**

In `backend/public/css/portal.css`, replace this exact rule (lines 650-652):

```css
    .front-kpis.module-kpis.macro-layout {
      grid-template-columns: repeat(5, minmax(0, 1fr));
    }
```

with:

```css
    .front-kpis.module-kpis.macro-layout {
      grid-template-columns: minmax(0, 1.15fr) repeat(2, minmax(0, 1fr));
      grid-auto-rows: 1fr;
      gap: 12px;
    }
```

- [ ] **Step 2: Replace the macro-layout card rules**

In `backend/public/css/portal.css`, replace this exact block (lines 688-712):

```css
    .front-kpis.module-kpis.macro-layout .front-kpi {
      min-height: 108px;
      align-items: start;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent {
      background: #fff;
      box-shadow: none;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent .kpi-icon {
      background: var(--blue-soft);
      box-shadow: none;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent.active {
      background: #fff;
      box-shadow: inset 0 -4px 0 var(--blue), var(--shadow-sm);
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent.active .kpi-icon {
      background: var(--blue-soft);
      color: var(--blue);
      box-shadow: none;
    }
```

with:

```css
    /* Sector cards (industry / agriculture / construction / services) */
    .front-kpis.module-kpis.macro-layout .front-kpi:not(.parent) {
      min-height: 0;
      padding: 14px;
      border-radius: 14px;
      grid-template-columns: 40px minmax(0, 1fr);
      gap: 10px;
      align-items: center;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi:not(.parent) .kpi-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi:not(.parent) .kpi-icon svg {
      width: 24px;
      height: 24px;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi:not(.parent) h3 {
      font-size: 11px;
      letter-spacing: 0.05em;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi:not(.parent) .front-kpi-value {
      font-size: clamp(20px, 2vw, 26px);
    }

    /* ЯҲМ hero panel */
    .front-kpis.module-kpis.macro-layout .front-kpi.parent {
      grid-column: 1;
      grid-row: 1 / span 2;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 6px;
      padding: 24px;
      min-height: 0;
      border: 0;
      border-radius: 16px;
      background: var(--accent-grad);
      box-shadow: none;
      overflow: hidden;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent:hover {
      transform: none;
      background: var(--accent-grad);
      box-shadow: 0 10px 28px rgba(8, 57, 93, .28);
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent::before {
      content: "";
      position: absolute;
      right: 24px;
      top: 50%;
      transform: translateY(-50%);
      width: 124px;
      height: 124px;
      background: rgba(255, 255, 255, .13);
      clip-path: polygon(50% 0, 100% 50%, 72% 50%, 72% 100%, 28% 100%, 28% 50%, 0 50%);
      pointer-events: none;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent .kpi-icon {
      display: none;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent .front-kpi-copy {
      gap: 8px;
      position: relative;
      z-index: 1;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent h3 {
      justify-self: start;
      padding: 5px 12px;
      border-radius: 999px;
      background: rgba(255, 255, 255, .16);
      color: #fff;
      font-size: 13px;
      letter-spacing: 0.08em;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent .front-kpi-value {
      color: #fff;
      font-size: clamp(40px, 4.6vw, 60px);
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent .front-kpi-note {
      color: rgba(255, 255, 255, .82);
      font-size: 13px;
    }
```

- [ ] **Step 3: Run the full suite to confirm no regression**

Run: `php artisan test`
Expected: same failures as after Task 2 (only the `macro hero strip` test, fixed in Task 4). No new failures — this task is CSS-only.

- [ ] **Step 4: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): blue ЯҲМ hero and 2x2 sector grid"
```

---

### Task 4: Period row

Replace the macro `solo` panel (`macro-hero-strip`) with a 4-cell period row. Rewrite the test that asserted the old markup.

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php` (lines 72-101)
- Modify: `backend/public/css/portal.css` (lines 2239-2325)
- Delete: `backend/tests/Feature/Livewire/MacroHeroStripTest.php`
- Create: `backend/tests/Feature/Livewire/MacroPeriodRowTest.php`
- Modify: `backend/tests/Feature/Http/DashboardRoutesTest.php` (lines 55-60)

- [ ] **Step 1: Replace the hero-strip test with a period-row test**

Delete the old test file and create the new one:

```bash
git rm backend/tests/Feature/Livewire/MacroHeroStripTest.php
```

Create `backend/tests/Feature/Livewire/MacroPeriodRowTest.php`:

```php
<?php

use App\Livewire\Dashboard\KpiWorkspaceCard;
use Livewire\Livewire;

it('renders the period row on the grp KPI page', function () {
    Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'grp'])
        ->assertSeeHtml('class="macro-period-row"')
        ->assertDontSeeHtml('class="macro-hero-strip"');
});

it('renders the industry main panel, not the period row, on the industry KPI page', function () {
    Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'industry'])
        ->assertSeeHtml('class="macro-main-panel"')
        ->assertDontSeeHtml('class="macro-period-row"');
});
```

- [ ] **Step 2: Update the dashboard route test**

In `backend/tests/Feature/Http/DashboardRoutesTest.php`, replace this exact block (lines 55-60):

```php
test('dashboard macro module renders macro hero strip', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=macro');
    $response->assertStatus(200);
    $response->assertSee('macro-hero-strip', false);
});
```

with:

```php
test('dashboard macro module renders the period row', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=macro');
    $response->assertStatus(200);
    $response->assertSee('macro-period-row', false);
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter "MacroPeriodRow|period row"`
Expected: FAIL — `macro-period-row` is not rendered yet.

- [ ] **Step 4: Replace the solo branch markup**

In `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php`, replace this exact block (lines 72-101):

```blade
    @else
        <section class="macro-hero-strip" aria-label="{{ $indicator->label_full ?? '' }} ўсиш мониторинги">
            <div class="macro-hero-strip__lead">
                <div class="macro-hero-strip__value-row">
                    <strong class="macro-hero-strip__value">{{ $yearGrowth }}</strong>
                    <svg class="macro-hero-strip__arrow" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.6" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        <polyline points="3 17 9 11 13 15 21 7"/>
                        <polyline points="15 7 21 7 21 13"/>
                    </svg>
                </div>
                <span class="macro-hero-strip__growth-label">Ўсиш</span>
            </div>
            <div class="macro-hero-strip__chips">
                @foreach($macroPeriods as $item)
                    @php
                        $row = $rows->get($item['period']);
                        $growthText = $row && $row->growth_pct !== null
                            ? DashboardCatalog::growthValue($row->growth_pct)
                            : '—';
                    @endphp
                    <div class="macro-hero-strip__chip">
                        <span class="macro-hero-strip__chip-label">{{ $item['label'] }}:</span>
                        <strong class="macro-hero-strip__chip-value">{{ $growthText }}</strong>
                        <span class="macro-hero-strip__chip-badge">({{ $item['state'] }})</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
```

with:

```blade
    @else
        <div class="macro-period-row" aria-label="{{ $indicator->label_full ?? '' }} давр кесими">
            @foreach($macroPeriods as $item)
                @php
                    $row = $rows->get($item['period']);
                    $growthText = $row && $row->growth_pct !== null
                        ? DashboardCatalog::growthValue($row->growth_pct)
                        : '—';
                @endphp
                <div class="macro-period-cell {{ $item['cls'] }}">
                    <span class="macro-period-cell__label">{{ $item['label'] }}</span>
                    <strong class="macro-period-cell__value">{{ $growthText }}</strong>
                    <span class="macro-period-cell__state">({{ $item['state'] }})</span>
                </div>
            @endforeach
        </div>
    @endif
```

- [ ] **Step 5: Replace the hero-strip CSS with period-row CSS**

In `backend/public/css/portal.css`, replace this exact block (lines 2239-2325):

```css
    .macro-hero-strip {
        display: grid;
        grid-template-columns: minmax(0, auto) minmax(0, 1fr);
        gap: 26px;
        align-items: stretch;
        padding: 38px 30px;
        border-radius: 14px;
        background: #fff;
        border: 1px solid var(--line);
        box-shadow: var(--shadow-sm);
    }

    .macro-hero-strip__lead {
        display: grid;
        gap: 4px;
        align-content: center;
        min-width: 0;
    }

    .macro-hero-strip__value-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .macro-hero-strip__value {
        color: var(--blue);
        font-size: clamp(56px, 6.6vw, 88px);
        line-height: .95;
        letter-spacing: -.04em;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
    }

    .macro-hero-strip__arrow {
        flex: 0 0 auto;
        width: clamp(32px, 3.8vw, 46px);
        height: clamp(32px, 3.8vw, 46px);
        color: var(--blue);
    }

    .macro-hero-strip__growth-label {
        color: var(--blue);
        font-size: 17px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .macro-hero-strip__chips {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .macro-hero-strip__chip {
        display: grid;
        gap: 8px;
        justify-items: center;
        align-content: center;
        text-align: center;
        padding: 18px 14px;
        border-radius: 12px;
        background: #f3f5f8;
        border: 1px solid var(--line);
        min-width: 0;
    }

    .macro-hero-strip__chip-label {
        color: var(--muted);
        font-size: 16px;
        font-weight: 600;
    }

    .macro-hero-strip__chip-value {
        color: var(--ink);
        font-size: clamp(32px, 3.1vw, 44px);
        font-weight: 800;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }

    .macro-hero-strip__chip-badge {
        color: var(--muted);
        font-size: 15px;
        font-weight: 600;
    }
```

with:

```css
    .macro-period-row {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border: 1px solid var(--line);
        border-radius: 14px;
        background: var(--surface);
        overflow: hidden;
    }

    .macro-period-cell {
        display: grid;
        gap: 5px;
        justify-items: center;
        text-align: center;
        padding: 16px 12px;
        border-left: 1px solid var(--line);
        min-width: 0;
    }

    .macro-period-cell:first-child {
        border-left: 0;
    }

    .macro-period-cell__label {
        color: var(--muted);
        font-size: 13px;
        font-weight: 700;
    }

    .macro-period-cell__value {
        color: var(--ink);
        font-size: clamp(22px, 2.2vw, 30px);
        font-weight: 900;
        line-height: 1;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .macro-period-cell.actual .macro-period-cell__value {
        color: var(--blue);
    }

    .macro-period-cell__state {
        color: var(--muted);
        font-size: 12px;
        font-weight: 600;
    }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter "MacroPeriodRow|period row"`
Expected: PASS — both `MacroPeriodRowTest` cases and the `dashboard macro module renders the period row` route test.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: **0 failures** — the last pre-existing failure (`MacroHeroStripTest`) is now removed.

- [ ] **Step 8: Commit**

```bash
git add backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php backend/public/css/portal.css backend/tests/Feature/Livewire/MacroPeriodRowTest.php backend/tests/Feature/Livewire/MacroHeroStripTest.php backend/tests/Feature/Http/DashboardRoutesTest.php
git commit -m "feat(dashboard): replace macro hero strip with a period row"
```

---

### Task 5: Scoreline restyle

Restyle the macro execution scoreline: copy + plain percentage + 3 solid colored count blocks. Scoped with an `is-macro` class so the other 6 modules keep their current scoreline.

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php:1`
- Modify: `backend/public/css/portal.css` (insert before line 531 `.score {`)
- Test: `backend/tests/Feature/Http/DashboardRoutesTest.php`

- [ ] **Step 1: Write the failing test**

Append this test to `backend/tests/Feature/Http/DashboardRoutesTest.php`:

```php
test('macro scoreline carries the is-macro modifier', function () {
    $this->seed();
    $response = $this->get('/dashboard?module=macro');
    $response->assertStatus(200);
    $response->assertSee('scoreline execution-strip is-macro', false);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter "is-macro modifier"`
Expected: FAIL — `is-macro` not on the scoreline yet.

- [ ] **Step 3: Add the is-macro class**

In `backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php`, replace line 1:

```blade
<div class="scoreline execution-strip">
```

with:

```blade
<div class="{{ trim('scoreline execution-strip '.($module === 'macro' ? 'is-macro' : '')) }}">
```

- [ ] **Step 4: Add the is-macro scoreline CSS**

In `backend/public/css/portal.css`, insert this block immediately **before** the existing `.score {` rule (line 531 — the line reading `    .score {`):

```css
    .scoreline.execution-strip.is-macro {
      grid-template-columns: minmax(240px, 1fr) auto minmax(320px, 1.1fr);
      gap: 16px;
    }

    .is-macro .scoreline-copy { order: 1; }
    .is-macro .exec-progress-box { order: 2; }
    .is-macro .exec-status-grid { order: 3; }

    .is-macro .exec-donut {
      width: auto;
      height: auto;
      background: none;
      border: 0;
      border-radius: 0;
    }

    .is-macro .exec-donut strong {
      font-size: 40px;
      font-weight: 900;
      letter-spacing: -0.03em;
      color: var(--ink);
    }

    .is-macro .exec-status-pill {
      min-height: 70px;
      border: 0;
      align-content: center;
      justify-items: center;
      text-align: center;
      gap: 5px;
    }

    .is-macro .exec-status-pill:hover {
      transform: none;
      box-shadow: none;
    }

    .is-macro .exec-status-pill span { color: rgba(255, 255, 255, .85); }
    .is-macro .exec-status-pill strong { color: #fff; }
    .is-macro .exec-status-pill:first-child { background: var(--blue); }
    .is-macro .exec-status-pill.green { background: var(--green); }
    .is-macro .exec-status-pill.red { background: var(--red); }

```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter "is-macro modifier"`
Expected: PASS.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: 0 failures.

- [ ] **Step 7: Commit**

```bash
git add backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php backend/public/css/portal.css backend/tests/Feature/Http/DashboardRoutesTest.php
git commit -m "style(dashboard): restyle macro execution scoreline"
```

---

### Task 6: Responsive rules, cleanup, and visual verification

Add responsive rules for the new macro layout, remove dead `macro-hero-strip` media queries, and verify the result against the reference screenshot.

**Files:**
- Modify: `backend/public/css/portal.css` (the `@media (max-width: 760px)` block and the dead `@media (max-width: 980px)` / `(max-width: 560px)` blocks)

- [ ] **Step 1: Add macro responsive rules to the 760px breakpoint**

In `backend/public/css/portal.css`, find this exact line inside the `@media (max-width: 760px)` block:

```css
      .front-kpi { border-right: 0; border-bottom: 1px solid var(--line); }
```

Replace it with:

```css
      .front-kpi { border-right: 0; border-bottom: 1px solid var(--line); }
      .front-kpis.module-kpis.macro-layout .front-kpi.parent { grid-column: auto; grid-row: auto; }
      .macro-period-row { grid-template-columns: 1fr; }
      .macro-period-cell { border-left: 0; border-top: 1px solid var(--line); }
      .macro-period-cell:first-child { border-top: 0; }
      .module-card { padding: 14px; }
```

- [ ] **Step 2: Remove the dead hero-strip media queries**

In `backend/public/css/portal.css`, delete this exact block (the `macro-hero-strip` class no longer exists):

```css
    @media (max-width: 980px) {
      .macro-hero-strip {
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 18px 20px;
      }

      .macro-hero-strip__chips {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 560px) {
      .macro-hero-strip__chips {
        grid-template-columns: 1fr;
      }
    }

```

- [ ] **Step 3: Run the full suite**

Run: `php artisan test`
Expected: 0 failures.

- [ ] **Step 4: Start the dev server**

Run (background): `php artisan serve --host=127.0.0.1 --port=8000`

- [ ] **Step 5: Capture a headless screenshot**

Run in PowerShell (adjust the Edge path if needed — `msedge` may also be on PATH):

```powershell
& "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1500,1150 --screenshot="C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali\backend\verify-redesign.png" "http://127.0.0.1:8000/dashboard"
```

- [ ] **Step 6: Compare against the reference**

Read `backend/verify-redesign.png` and compare to the reference `C:\Users\y.utepbergenov\Desktop\Screenshot 2026-05-22 100100.png`. Confirm: blue tab strip; one white card holding the heading, the blue ЯҲМ hero with up-arrow, the 2×2 sector grid, and the 4-cell period row; the restyled scoreline below with 3 colored count blocks. Also load `/dashboard?module=budget` and confirm that module is visually unchanged from before this work.

If anything is off, fix it in `portal.css` and re-screenshot before committing.

- [ ] **Step 7: Clean up the screenshot artifact**

`verify-redesign.png` is a temporary artifact — do not commit it.

```bash
rm backend/verify-redesign.png
```

- [ ] **Step 8: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): macro layout responsive rules + cleanup"
```

---

## Self-review

**Spec coverage:**
- Module tabs restyle → Task 1 ✓
- Module card wrapper (macro only, `display: contents` for others) → Task 2 ✓
- Blue ЯҲМ hero with arrow → Task 3 ✓
- 2×2 sector grid → Task 3 ✓
- Period row (`solo` branch) → Task 4 ✓
- Scoreline restyle (`is-macro` scoped) → Task 5 ✓
- Sub-KPI behavior preserved → unchanged; `selectKpi` wiring and the `with-side` industry branch are not touched ✓
- Copy fix (subtitle) → Task 2 ✓
- Test handling (`MacroHeroStripTest`, `DashboardRoutesTest`, `KpiModuleTabsTest`) → Tasks 1, 2, 4, 5 ✓
- Responsive + verification → Task 6 ✓

**Placeholder scan:** No TBDs. Every CSS/Blade/test step shows complete code and an exact replacement target.

**Type/name consistency:** Class names are consistent across tasks — `module-card`, `module-flow`, `macro-period-row`, `macro-period-cell`, `macro-period-cell__label/__value/__state`, `is-macro`. The period-row test (Task 4) asserts `class="macro-period-row"`, which matches the Blade markup exactly. The `$item['cls']` values (`actual`/`plan`/`plan`/`target`) come from the existing `$macroPeriods` array in `macro-growth.blade.php`; only `actual` is styled (`.macro-period-cell.actual`), the others fall through to the default — intentional.

**Note on the `with-side` (industry) sub-view:** not pixel-specified by the screenshot. It inherits the `.module-card` chrome and remains structurally unchanged. Task 6 step 6 includes a manual check that selecting a sector still renders a coherent panel.
