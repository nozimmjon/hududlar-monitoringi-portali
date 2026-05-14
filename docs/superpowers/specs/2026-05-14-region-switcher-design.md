# Region switcher (session-backed, sidebar dropdown)

**Date:** 2026-05-14
**Status:** Approved (pending user spec review)
**Scope:** Replace hardcoded region `1703` across 8 Livewire components + the layout header with a session-backed region context. Sidebar gets a dropdown switcher at the top. Header title + page `<title>` reflect the current region.

---

## 1. Goal

Today every dashboard, districts, profile, tasks, execution view fetches data scoped to `region_code = 1703`. Header reads "Андижон вилояти мониторинг платформаси". Only Andijan is reachable through the UI even though all 14 regions are imported and seeded.

After this work, a sidebar `<select>` exposes all 14 regions. Changing it persists in the session and reloads the current page; every Livewire component receives data scoped to the active region. Header h1 + page `<title>` rebuild on each load to match.

## 2. Non-goals

- No URL-param override (session is the single source of truth).
- No persistence past browser session (closing the browser resets to default 1703).
- No region-aware data backfill or import. Pages render whatever is in the DB; some non-Andijan regions show empty tasks panels.
- No multi-region comparison views.
- No CSS work beyond a thin `region-switcher` block (mirrors existing sidebar typography).
- No tests for every page's full data flow under every region — only verify each component reads the session correctly.

## 3. Strategy

Single helper class owns the session read/write. Single Livewire component owns the `<select>`. Eight existing components swap a `const REGION_CODE = 1703` for an instance property hydrated from session at mount. Layout reads helper at top of every render.

When the user picks a new region, the switcher component writes session and triggers a full page reload via Livewire's `$this->redirect(...)`. All other components see the new value next request.

## 4. Components

### 4.1 `App\Support\CurrentRegion` (new)

```php
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

### 4.2 `App\Livewire\RegionSwitcher` (new)

```php
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

View `resources/views/livewire/region-switcher.blade.php`:

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

CSS (`public/css/portal.css`) — thin block to match sidebar typography:

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

### 4.3 Layout `app.blade.php`

At top (before `<title>`):

```blade
@php $currentRegion = \App\Support\CurrentRegion::current(); @endphp
```

Then:
- `<title>{{ $currentRegion->name_full }} мониторинг платформаси · v7</title>`
- `<h1>{{ $currentRegion->name_full }} мониторинг платформаси</h1>`
- Sidebar: `<livewire:region-switcher />` immediately before the first `<a class="nav-btn">`.

### 4.4 Eight Livewire components updated

Files affected:
- `app/Livewire/RegionProfile.php`
- `app/Livewire/DistrictsPage.php`
- `app/Livewire/TasksBoard.php`
- `app/Livewire/ExecutionPage.php`
- `app/Livewire/Dashboard/KpiScoreline.php`
- `app/Livewire/Dashboard/KpiFrontCards.php`
- `app/Livewire/Dashboard/KpiWorkspaceCard.php`
- `app/Livewire/Dashboard/MacroComposition.php`

Each component:

1. Remove `private const REGION_CODE = 1703;`.
2. Add `public int $regionCode;` property.
3. Add `mount()` method (or extend existing) that calls `$this->regionCode = \App\Support\CurrentRegion::code();`.
4. Replace every `self::REGION_CODE` or literal `1703` (where it refers to region scoping) with `$this->regionCode`.
5. Where Andijan-only labels are hardcoded (e.g. `'Андижон вилояти'` in `RegionProfile`), replace with `\App\Support\CurrentRegion::current()->name_full` — read in render() and pass to view.

Note: `DashboardCatalog::MODULES` is global and stays untouched. Per-region filtering relies on `region_indicator_availability.status='available'` and is already region-aware where it matters.

## 5. Tests

`tests/Unit/Support/CurrentRegionTest.php`:

```php
test('code returns default when session unset', function () {
    expect(\App\Support\CurrentRegion::code())->toBe(1703);
});

test('set writes session and code reflects it', function () {
    \App\Support\CurrentRegion::set(1726);
    expect(\App\Support\CurrentRegion::code())->toBe(1726);
});

test('set ignores unknown region codes', function () {
    \App\Support\CurrentRegion::set(99999);
    expect(\App\Support\CurrentRegion::code())->toBe(1703); // unchanged
});

test('current returns Region model for current code', function () {
    \App\Support\CurrentRegion::set(1726);
    expect(\App\Support\CurrentRegion::current()->code)->toBe(1726);
});

test('regions returns all 14 ordered by sort_order', function () {
    expect(\App\Support\CurrentRegion::regions())->toHaveCount(14);
    expect(\App\Support\CurrentRegion::regions()->first()->code)->toBe(1735); // Karakalpakstan first
});
```

`tests/Feature/Livewire/RegionSwitcherTest.php`:

```php
test('renders all 14 regions in select', function () {
    Livewire::test(RegionSwitcher::class)
        ->assertViewHas('regions', fn($r) => $r->count() === 14);
});

test('select() mutates session', function () {
    Livewire::test(RegionSwitcher::class)
        ->call('select', 1726);
    expect(Session::get('region_code'))->toBe(1726);
});

test('select() with invalid code is ignored', function () {
    Livewire::test(RegionSwitcher::class)
        ->call('select', 99999);
    expect(Session::get('region_code'))->toBeNull();
});
```

Component-specific tests (sanity, one per critical page):

```php
test('KpiDashboard with session=1726 reads tashkent_city region', function () {
    Session::put('region_code', 1726);
    Livewire::test(KpiDashboard::class)
        ->assertViewHas('regionCode', 1726);
});

test('RegionProfile with session=1735 reads karakalpak region', function () {
    Session::put('region_code', 1735);
    Livewire::test(RegionProfile::class, ['districtCode' => '1735401'])
        ->assertViewHas('regionCode', 1735);
});
```

Existing tests (255 passing) stay green since session defaults to 1703.

## 6. Files

| File | Action |
|---|---|
| `backend/app/Support/CurrentRegion.php` | new |
| `backend/app/Livewire/RegionSwitcher.php` | new |
| `backend/resources/views/livewire/region-switcher.blade.php` | new |
| `backend/public/css/portal.css` | append `.region-switcher` block |
| `backend/resources/views/layouts/app.blade.php` | modify (title, h1, sidebar) |
| `backend/app/Livewire/RegionProfile.php` | modify (drop const, add property+mount) |
| `backend/app/Livewire/DistrictsPage.php` | modify |
| `backend/app/Livewire/TasksBoard.php` | modify |
| `backend/app/Livewire/ExecutionPage.php` | modify |
| `backend/app/Livewire/Dashboard/KpiScoreline.php` | modify |
| `backend/app/Livewire/Dashboard/KpiFrontCards.php` | modify |
| `backend/app/Livewire/Dashboard/KpiWorkspaceCard.php` | modify |
| `backend/app/Livewire/Dashboard/MacroComposition.php` | modify |
| `backend/tests/Unit/Support/CurrentRegionTest.php` | new |
| `backend/tests/Feature/Livewire/RegionSwitcherTest.php` | new |

No new migration, no model, no seeder change.

## 7. Operator smoke

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php -d memory_limit=2G artisan import:all-regions 2026
php artisan serve --port=8765
```

1. Open `http://localhost:8765/dashboard`. Header reads "Андижон вилояти мониторинг платформаси". Sidebar shows region select set to Андижон.
2. Click select → choose "Тошкент шаҳри". Page reloads. Header now reads "Тошкент шаҳри мониторинг платформаси". KPI cards show tashkent_city data.
3. Click "Туманлар" nav. Districts page loads tashkent_city districts.
4. Click "Профил" on a tashkent district. Profile loads tashkent district data.
5. Click "Топшириқлар". Tasks list scoped to tashkent_city.
6. Switch back to Andijan via select. All pages return to Andijan view.
7. Reload browser without changing select. Region persists (session cookie).
8. Close browser, reopen. Session cleared, defaults to Andijan.

## 8. Risks

- **Risk:** Tashkent city has agriculture marked `not_applicable`. Profile + dashboard must filter via `region_indicator_availability` (already done). Verify no page hardcodes a 5-KPI macro grid that breaks.
- **Risk:** `RegionProfile` falls back to `kpi='industry'` default in `mount()`. For tashkent_city industry might not be the first available; mount logic handles this via `availableKpis->first()->code`. Already correct.
- **Risk:** `DistrictsPage` selects a default district. After region switch, the previously-selected `$districtCode` URL param may not exist in new region. Component should fall back to first ranked district of new region. Verify in smoke.
- **Risk:** Deep links break sharing across regions. Accept for now.
- **Risk:** Full reload on switch loses scroll. Accept.
- **Risk:** Session-cookie size already includes Livewire snapshots. Adding 1 int is negligible.
- **Risk:** Existing 255 pest tests don't set session; they get default 1703 via fallback. No breakage. New tests explicitly set session to assert non-default reads.
- **Risk:** Other hardcoded `'Андижон'` strings in Blade. *Mitigation:* grep `Андижон` and replace where context is dynamic; leave brand/marketing copy alone. Districts page has hardcoded `Андижон` strings — check during impl.
