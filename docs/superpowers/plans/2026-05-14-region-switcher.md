# Region Switcher Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace hardcoded region 1703 across 8 Livewire components + layout header with a session-backed region context exposed via a sidebar dropdown.

**Architecture:** Single `CurrentRegion` helper owns session read/write. `RegionSwitcher` Livewire component renders the `<select>` and calls `$this->redirect(...)` on change for full reload. Layout reads helper at top to render dynamic header + page title. Eight existing components swap `const REGION_CODE = 1703` for an instance property hydrated from session at mount.

**Tech Stack:** PHP 8.3 · Laravel 11 · Livewire 3 · Pest 3 · PostgreSQL. All commands run from `backend/`.

---

## File structure

| File | Responsibility |
|---|---|
| `backend/app/Support/CurrentRegion.php` | session helper (code/current/set/regions) |
| `backend/app/Livewire/RegionSwitcher.php` | Livewire component with `select()` action |
| `backend/resources/views/livewire/region-switcher.blade.php` | sidebar dropdown markup |
| `backend/public/css/portal.css` | append `.region-switcher` block |
| `backend/resources/views/layouts/app.blade.php` | dynamic header + sidebar slot |
| `backend/app/Livewire/{RegionProfile,DistrictsPage,TasksBoard,ExecutionPage}.php` | swap hardcoded 1703 |
| `backend/app/Livewire/Dashboard/{KpiScoreline,KpiFrontCards,KpiWorkspaceCard,MacroComposition}.php` | swap hardcoded 1703 |
| `backend/tests/Unit/Support/CurrentRegionTest.php` | 5 unit tests |
| `backend/tests/Feature/Livewire/RegionSwitcherTest.php` | 3 feature tests |
| `backend/tests/Feature/Livewire/RegionContextTest.php` | 2 feature tests asserting components read session |

---

### Task 1: CurrentRegion helper + unit tests

**Files:**
- Create: `backend/app/Support/CurrentRegion.php`
- Create: `backend/tests/Unit/Support/CurrentRegionTest.php`

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Unit/Support/CurrentRegionTest.php`:

```php
<?php

use App\Support\CurrentRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('code returns default when session unset', function () {
    expect(CurrentRegion::code())->toBe(1703);
});

test('set writes session and code reflects it', function () {
    CurrentRegion::set(1726);
    expect(CurrentRegion::code())->toBe(1726);
});

test('set ignores unknown region codes', function () {
    Session::flush();
    CurrentRegion::set(99999);
    expect(CurrentRegion::code())->toBe(1703);
});

test('current returns Region model for current code', function () {
    CurrentRegion::set(1726);
    expect(CurrentRegion::current()->code)->toBe(1726);
});

test('regions returns 14 ordered by sort_order', function () {
    $regions = CurrentRegion::regions();
    expect($regions)->toHaveCount(14);
    expect($regions->first()->code)->toBe(1735);  // Karakalpakstan sort=1
});
```

- [ ] **Step 2: Run tests → expect FAIL**

```bash
cd backend && vendor/bin/pest tests/Unit/Support/CurrentRegionTest.php
```

Expected: FAIL (class doesn't exist).

- [ ] **Step 3: Create the helper**

Create `backend/app/Support/CurrentRegion.php`:

```php
<?php

namespace App\Support;

use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CurrentRegion
{
    public const DEFAULT_CODE = 1703;

    public static function code(): int
    {
        return (int) Session::get('region_code', self::DEFAULT_CODE);
    }

    public static function current(): Region
    {
        return Region::where('code', self::code())->firstOrFail();
    }

    public static function set(int $code): void
    {
        if (! Region::where('code', $code)->exists()) {
            return;
        }
        Session::put('region_code', $code);
    }

    public static function regions(): Collection
    {
        return Region::orderBy('sort_order')->get();
    }
}
```

- [ ] **Step 4: Run tests → expect PASS**

```bash
cd backend && vendor/bin/pest tests/Unit/Support/CurrentRegionTest.php
```

Expected: 5/5 PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Support/CurrentRegion.php backend/tests/Unit/Support/CurrentRegionTest.php
git commit -m "feat(region): CurrentRegion session helper"
```

---

### Task 2: RegionSwitcher Livewire component

**Files:**
- Create: `backend/app/Livewire/RegionSwitcher.php`
- Create: `backend/resources/views/livewire/region-switcher.blade.php`
- Create: `backend/tests/Feature/Livewire/RegionSwitcherTest.php`

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Livewire/RegionSwitcherTest.php`:

```php
<?php

use App\Livewire\RegionSwitcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('renders all 14 regions in select view data', function () {
    Livewire::test(RegionSwitcher::class)
        ->assertViewHas('regions', fn ($r) => $r->count() === 14);
});

test('select mutates session', function () {
    Livewire::test(RegionSwitcher::class)
        ->call('select', 1726);
    expect(Session::get('region_code'))->toBe(1726);
});

test('select with invalid code does not write session', function () {
    Livewire::test(RegionSwitcher::class)
        ->call('select', 99999);
    expect(Session::has('region_code'))->toBeFalse();
});
```

- [ ] **Step 2: Run tests → expect FAIL**

```bash
cd backend && vendor/bin/pest tests/Feature/Livewire/RegionSwitcherTest.php
```

Expected: FAIL.

- [ ] **Step 3: Create the component**

`backend/app/Livewire/RegionSwitcher.php`:

```php
<?php

namespace App\Livewire;

use App\Support\CurrentRegion;
use Livewire\Component;

class RegionSwitcher extends Component
{
    public int $regionCode;

    public function mount(): void
    {
        $this->regionCode = CurrentRegion::code();
    }

    public function select(int $code): void
    {
        CurrentRegion::set($code);
        $this->redirect(request()->path(), navigate: false);
    }

    public function render()
    {
        return view('livewire.region-switcher', [
            'regions' => CurrentRegion::regions(),
        ]);
    }
}
```

`backend/resources/views/livewire/region-switcher.blade.php`:

```blade
<div class="region-switcher">
    <label for="region-select">Вилоят</label>
    <select id="region-select" wire:change="select($event.target.value)">
        @foreach($regions as $r)
            <option value="{{ $r->code }}" @selected($r->code === $regionCode)>{{ $r->name_full }}</option>
        @endforeach
    </select>
</div>
```

- [ ] **Step 4: Run tests → expect PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Livewire/RegionSwitcherTest.php
```

Expected: 3/3 PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Livewire/RegionSwitcher.php backend/resources/views/livewire/region-switcher.blade.php backend/tests/Feature/Livewire/RegionSwitcherTest.php
git commit -m "feat(region): RegionSwitcher Livewire component"
```

---

### Task 3: Layout integration

**Files:**
- Modify: `backend/resources/views/layouts/app.blade.php`
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Update layout for dynamic header**

In `backend/resources/views/layouts/app.blade.php`, change the top of the file:

Replace this block (lines 1-15):

```blade
<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Андижон вилояти мониторинг платформаси · v7</title>
```

with:

```blade
<!doctype html>
<html lang="uz-Cyrl">
@php $currentRegion = \App\Support\CurrentRegion::current(); @endphp
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $currentRegion->name_full }} мониторинг платформаси · v7</title>
```

Then replace the brand h1 (around line 22):

```blade
<h1>Андижон вилояти мониторинг платформаси</h1>
```

with:

```blade
<h1>{{ $currentRegion->name_full }} мониторинг платформаси</h1>
```

- [ ] **Step 2: Add region switcher to sidebar**

In the same file, find the `<aside class="sidebar">` block (around line 30). Replace:

```blade
<aside class="sidebar">
  <div class="side-title">
    <strong>Бошқарув маркази</strong>
  </div>
  <a class="nav-btn ...
```

with:

```blade
<aside class="sidebar">
  <div class="side-title">
    <strong>Бошқарув маркази</strong>
  </div>
  <livewire:region-switcher />
  <a class="nav-btn ...
```

- [ ] **Step 3: Append CSS to `portal.css`**

In `backend/public/css/portal.css`, append at the end of the sidebar-related styles (find `.sidebar` definition and add after the last related rule):

```css
.region-switcher {
    padding: 12px 14px 0;
}
.region-switcher label {
    display: block;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin-bottom: 4px;
}
.region-switcher select {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #fff;
    color: var(--ink);
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
}
.region-switcher select:focus {
    outline: 2px solid var(--blue-2);
    outline-offset: 1px;
}
```

- [ ] **Step 4: Run dev server + smoke**

```bash
cd backend && php artisan serve --port=8765
```

In another shell:

```bash
curl -sS http://localhost:8765/dashboard | grep -oE "region-switcher|Андижон вилояти мониторинг" | sort -u
```

Expected: both strings appear (switcher rendered, default-region header).

- [ ] **Step 5: Commit**

```bash
git add backend/resources/views/layouts/app.blade.php backend/public/css/portal.css
git commit -m "feat(region): dynamic header + sidebar switcher slot"
```

---

### Task 4: Wire 8 Livewire components to session

**Files:** all under `backend/app/Livewire/`.

This task batches a uniform pattern across 8 files. Each gets the same 4-step rewrite:
1. Drop `private const REGION_CODE = 1703;`
2. Add `public int $regionCode;`
3. Add or extend `mount()` that calls `$this->regionCode = \App\Support\CurrentRegion::code();`
4. Replace `self::REGION_CODE` and bare `1703` (where it refers to region scoping, not other meaning) with `$this->regionCode`.

Where the component already has a `mount()`, inject the assignment at the top.

Where Andijan-only labels are hardcoded (`'Андижон вилояти'`), replace with `\App\Support\CurrentRegion::current()->name_full` and pass to view.

- [ ] **Step 1: Write a regression-context test**

Create `backend/tests/Feature/Livewire/RegionContextTest.php`:

```php
<?php

use App\Livewire\DistrictsPage;
use App\Livewire\Dashboard\KpiFrontCards;
use App\Livewire\Dashboard\KpiScoreline;
use App\Livewire\Dashboard\KpiWorkspaceCard;
use App\Livewire\Dashboard\MacroComposition;
use App\Livewire\ExecutionPage;
use App\Livewire\RegionProfile;
use App\Livewire\TasksBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('region-aware components read session for region context', function () {
    Session::put('region_code', 1726);

    foreach ([
        DistrictsPage::class,
        KpiFrontCards::class,
        KpiScoreline::class,
        KpiWorkspaceCard::class,
        MacroComposition::class,
        ExecutionPage::class,
        RegionProfile::class,
        TasksBoard::class,
    ] as $component) {
        $rendered = Livewire::test($component);
        expect($rendered->get('regionCode'))->toBe(1726, "{$component} regionCode");
    }
});

test('default session falls back to Andijan 1703', function () {
    Session::flush();
    Livewire::test(DistrictsPage::class)
        ->assertSet('regionCode', 1703);
});
```

- [ ] **Step 2: Run test → expect FAIL on every component**

```bash
cd backend && vendor/bin/pest tests/Feature/Livewire/RegionContextTest.php
```

Expected: FAIL (none of the components expose a `regionCode` property yet, and they currently hardcode 1703).

- [ ] **Step 3: Update each component**

For each of the 8 components listed in `RegionContextTest`, apply the exact pattern below. Show the change pattern for `RegionProfile.php` (around line 21):

Before:

```php
private const REGION_CODE = 1703;
private const YEAR        = 2026;
```

After:

```php
public int $regionCode;
private const YEAR = 2026;
```

In the existing `mount()` (line 35):

Before:

```php
public function mount(): void
{
    $kpis = $this->availableKpis();
    if ($kpis->isNotEmpty() && ! $kpis->firstWhere('code', $this->kpi)) {
        $this->kpi = $kpis->first()->code;
    }
}
```

After:

```php
public function mount(): void
{
    $this->regionCode = \App\Support\CurrentRegion::code();
    $kpis = $this->availableKpis();
    if ($kpis->isNotEmpty() && ! $kpis->firstWhere('code', $this->kpi)) {
        $this->kpi = $kpis->first()->code;
    }
}
```

Then replace every `self::REGION_CODE` with `$this->regionCode` (and bare `1703` where it refers to region scoping).

Apply the same pattern (drop const, add property, hydrate in mount, replace usages) to the other 7 components:
- `DistrictsPage.php`
- `TasksBoard.php`
- `ExecutionPage.php`
- `Dashboard/KpiScoreline.php`
- `Dashboard/KpiFrontCards.php`
- `Dashboard/KpiWorkspaceCard.php`
- `Dashboard/MacroComposition.php`

For components that don't already have `mount()`, add one with just the regionCode assignment.

Where a view template contains literal `'Андижон вилояти'` (e.g. some title text in dashboards or profile copy), grep:

```bash
grep -rn "Андижон вилояти" backend/resources/views/ backend/app/
```

For each match where context is dynamic-region (page content, not branding fallback), replace with `\App\Support\CurrentRegion::current()->name_full` (in PHP) or pass through the component's render() data.

- [ ] **Step 4: Run all profile + region tests → expect ALL PASS**

```bash
cd backend && vendor/bin/pest tests/Feature/Livewire tests/Unit/Support/CurrentRegionTest.php
```

Expected: every test green, including:
- 5 unit tests for `CurrentRegion`
- 3 feature tests for `RegionSwitcher`
- 2 feature tests for `RegionContextTest`
- All previous Livewire tests (KpiScoreline, RegionProfile, etc.) still passing (session defaults to 1703).

- [ ] **Step 5: Full pest suite**

```bash
cd backend && php -d memory_limit=2G vendor/bin/pest
```

Expected: 265+ pass, 0 fail.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Livewire/ backend/resources/views/livewire/ backend/tests/Feature/Livewire/RegionContextTest.php
git commit -m "feat(region): all Livewire components read region from session"
```

---

### Task 5: Browser smoke

**Files:** none (operator verification).

- [ ] **Step 1: Fresh DB + import + dev server**

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php -d memory_limit=2G artisan import:all-regions 2026
php artisan serve --port=8765
```

- [ ] **Step 2: Verify each scenario**

| URL | Action | Expected |
|---|---|---|
| `/dashboard` | Default load | Header: "Андижон вилояти мониторинг платформаси". Sidebar select shows "Андижон вилояти" selected. |
| `/dashboard` | Click sidebar select → "Тошкент шаҳри" | Page reloads. Header: "Тошкент шаҳри мониторинг платформаси". KPI cards rebuild with tashkent_city data. |
| `/districts` | After region switch | District table lists tashkent_city's 12 inner districts. |
| `/profile?districtCode=1726262` | After region switch | Profile loads tashkent_city's Учтепа тумани. |
| `/tasks` | After region switch | Task list scoped to tashkent_city (81 tasks per session DB). |
| `/dashboard` | Switch to Қорақалпоғистон → reload | Persists across reload (session cookie). |
| `/dashboard` | Close browser, reopen | Default Andijan (session cleared). |

- [ ] **Step 3: Empty commit to record smoke**

```bash
git commit --allow-empty -m "test(region): browser smoke — switcher rebuilds all pages per region"
```

---

## Self-review

**Spec coverage:**
- §3 Strategy → Tasks 1-4.
- §4.1 CurrentRegion helper → Task 1.
- §4.2 RegionSwitcher component + view + CSS → Tasks 2, 3.
- §4.3 Layout integration → Task 3.
- §4.4 Eight Livewire components updated → Task 4.
- §5 Tests → Tasks 1, 2, 4 (unit + feature, plus full suite verification).
- §7 Operator smoke → Task 5.
- §8 Risks → addressed via existing availability filter + components fall back via mount logic.

**No placeholders.** All code blocks concrete; the per-component swap pattern in Task 4 is described once with one concrete example (`RegionProfile`) and a uniform "apply this pattern" instruction for the other 7 files. This matches the actual repetitive nature of the change.

**Type consistency:**
- `CurrentRegion::code()` returns `int` everywhere.
- `CurrentRegion::current()` returns `Region`.
- `public int $regionCode` on all 8 components is consistent.
- Session key `'region_code'` is constant across helper + tests + components.
