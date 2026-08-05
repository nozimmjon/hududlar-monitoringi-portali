# Starter Page (Диптих) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the winning «Диптих» prototype (`backend/public/prototypes/start-a.html`) as the new `/` homepage with live cached aggregates; move the map homepage to `/regions`; swap the map page's sectors capsule for a «Бош саҳифа» crumb.

**Architecture:** New `StarterController@index` renders `pages/start.blade.php` (standalone Blade shell converted from the prototype, CSS inline, no Livewire). Two `Cache::remember` aggregates (600s), each individually failure-safe (a throwing aggregate renders the card without numbers, never a 500). Route name `home` moves to the starter page so all existing «Бош саҳифа» links retarget automatically; the map page keeps `HomeController@index` under new route `regions`.

**Tech Stack:** Laravel 12, plain Blade (no Livewire on this page), Pest 3 on PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-08-05-starter-page-design.md`
**Design source:** `backend/public/prototypes/start-a.html` (561 lines, on disk, uncommitted — read it before Task 1; the view is a mechanical conversion of it).
**Branch:** `starter-page`. Run all artisan/test commands from `backend/`.

**Key repo facts:**

- `Task` model: regional tasks; scope `hasPlan()` exists; statuses `done | open | in_progress`. Strict «бажарилди» = `status = 'done'` only (the map page's lenient done+in_progress reading stays map-only; the starter card shows the strict count — deliberate, matches the tasks board and the «бажарилди» label).
- `Sector` / `SectorTask`: 17 sectors; `lines_total` / `lines_done` per task; `in_progress` = nothing reported. Sector module has a report ⇔ any task's status ≠ `in_progress`.
- `App\Support\SectorDisplay::pshow(?float, bool)` exists (99-cap display rule) — reuse for the sector percent when reported.
- Existing `route('home')` consumers that must KEEP working (they'll point at the starter page, label «Бош саҳифа» stays correct): `resources/views/layouts/app.blade.php:19` (side-brand), `resources/views/livewire/sectors-dashboard.blade.php:13` (sec-back).
- Number formatting on the page uses the narrow no-break space (U+202F) as thousands separator, like the prototype.
- Tests: `Task::factory()` exists. Feature tests start with `uses(RefreshDatabase::class);` and call `$this->seed()` explicitly.

---

## Task 1: StarterController + routes + start view

**Files:**
- Create: `backend/app/Http/Controllers/StarterController.php`
- Create: `backend/resources/views/pages/start.blade.php` (converted from `backend/public/prototypes/start-a.html`)
- Modify: `backend/routes/web.php`
- Test: `backend/tests/Feature/Http/StarterPageTest.php` (new)

- [ ] **Step 1: Write the failing test file**

`backend/tests/Feature/Http/StarterPageTest.php`:

```php
<?php

use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Cache::forget('starter.regions');
    Cache::forget('starter.sectors');
});

test('GET / renders the starter page with both module cards', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Вилоятлар');
    $response->assertSee('Тармоқлар');
    $response->assertSee('href="' . route('regions') . '"', escape: false);
    $response->assertSee('href="' . route('sectors') . '"', escape: false);
    $response->assertSee('Ҳудудлар мониторинги платформаси');
    $response->assertDontSee('layouts.app'); // standalone shell
});

test('the regions card shows strict done counts and percent', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '1',
        'status' => 'done', 'headline_plan' => 10,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
    ]);
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '2',
        'status' => 'in_progress', 'headline_plan' => 5,
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
    ]);
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '3',
        'status' => 'open', 'headline_plan' => 7,
        'period_code' => 'year', 'deadline_text' => '2026 йил якуни билан',
    ]);
    // Plan-less task counts nowhere.
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '4',
        'status' => 'done', 'headline_plan' => null,
        'period_code' => 'h1', 'deadline_text' => '2026 йил I ярим йиллик',
    ]);

    $html = $this->get('/')->getContent();

    // 3 planned tasks, 1 strictly done → 33%.
    expect($html)->toContain('data-count="3"');
    expect($html)->toContain('data-count="1"');
    expect($html)->toContain('data-count="33"');
});

test('the sectors card shows the waiting state while nothing is reported', function () {
    // Seeded sectors exist; no SectorTask rows at all → no report.
    $html = $this->get('/')->getContent();

    expect($html)->toContain('Ҳисобот кутилмоқда');
    expect($html)->toContain('data-count="17"'); // 17 seeded sectors
});

test('the sectors card shows the indicator percent once a report exists', function () {
    $sector = Sector::where('code', 'uzbekneftgaz')->firstOrFail();
    SectorTask::create([
        'sector_id' => $sector->id, 'task_no' => 1, 'title' => 'Т1',
        'status' => 'done', 'lines_total' => 2, 'lines_done' => 1,
    ]);

    $html = $this->get('/')->getContent();

    expect($html)->not->toContain('Ҳисобот кутилмоқда');
    expect($html)->toContain('data-count="50"'); // 1/2 indicator lines → 50%
});

test('aggregates are cached under the starter keys', function () {
    $this->get('/')->assertOk();

    expect(Cache::has('starter.regions'))->toBeTrue();
    expect(Cache::has('starter.sectors'))->toBeTrue();
});

test('a failing aggregate degrades to a card without numbers, not a 500', function () {
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('db down'));

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Вилоятлар');
    $response->assertSee('Тармоқлар');
    $response->assertDontSee('data-count', escape: false);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=StarterPageTest`
Expected: FAIL — `Route [regions] not defined` / root still renders the map page.

- [ ] **Step 3: Rewire routes**

`backend/routes/web.php` — replace the first route block so the file's top reads:

```php
<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\StarterController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StarterController::class, 'index'])->name('home');
Route::get('/regions', [HomeController::class, 'index'])->name('regions');
Route::get('/region/{code}', [HomeController::class, 'enter'])
    ->whereNumber('code')->name('region.enter');
```

(Everything from `Route::view('/dashboard', ...)` down stays untouched.)

- [ ] **Step 4: Create `StarterController`**

`backend/app/Http/Controllers/StarterController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\Task;
use App\Support\SectorDisplay;
use Illuminate\Support\Facades\Cache;

class StarterController extends Controller
{
    /** Front door: module chooser with live aggregates per module. */
    public function index()
    {
        return view('pages.start', [
            'regions' => $this->regionsAggregate(),
            'sectors' => $this->sectorsAggregate(),
        ]);
    }

    /**
     * @return array{regions:int,total:int,done:int,pct:?int}|null null = aggregate
     *         unavailable; the card renders without numbers instead of 500ing.
     */
    private function regionsAggregate(): ?array
    {
        try {
            return Cache::remember('starter.regions', 600, function (): array {
                $total = Task::hasPlan()->count();
                $done  = Task::hasPlan()->where('status', 'done')->count();

                return [
                    'regions' => Region::count(),
                    'total'   => $total,
                    'done'    => $done,
                    'pct'     => $total > 0 ? (int) round($done / $total * 100) : null,
                ];
            });
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{sectors:int,tasks:int,lines:int,reported:bool,pct:?int}|null
     */
    private function sectorsAggregate(): ?array
    {
        try {
            return Cache::remember('starter.sectors', 600, function (): array {
                $lines     = (int) SectorTask::sum('lines_total');
                $linesDone = (int) SectorTask::sum('lines_done');
                $reported  = SectorTask::where('status', '!=', 'in_progress')->exists();

                return [
                    'sectors'  => Sector::count(),
                    'tasks'    => SectorTask::count(),
                    'lines'    => $lines,
                    'reported' => $reported,
                    'pct'      => $reported && $lines > 0
                        ? SectorDisplay::pshow($linesDone / $lines * 100, false)
                        : null,
                ];
            });
        } catch (\Throwable) {
            return null;
        }
    }
}
```

- [ ] **Step 5: Convert the prototype into `pages/start.blade.php`**

Copy the file, then apply the exact edits below:

```bash
cp public/prototypes/start-a.html resources/views/pages/start.blade.php
```

Edit 5a — at the very top of the file, BEFORE `<!doctype html>`, insert this Blade prep block (the Hallmark stamp comment inside `<style>` stays untouched):

```blade
@php
    // Narrow no-break space thousands separator, matching the prototype's JS fmt.
    $fmt = fn (?int $n) => $n === null ? '—' : number_format($n, 0, ',', "\u{202f}");
@endphp
```

Edit 5b — the left half's opening tag (line ≈462): replace

```html
  <a class="half half-a" href="/regions">
```

with

```blade
  <a class="half half-a" href="{{ route('regions') }}">
```

Edit 5c — the right half's opening tag (line ≈494): replace

```html
  <a class="half half-b" href="/sectors">
```

with

```blade
  <a class="half half-b" href="{{ route('sectors') }}">
```

Edit 5d — the left stats block (lines ≈483–486): replace

```html
        <span class="stat"><span class="num" data-count="14">14</span><span class="label">ҳудуд</span></span>
        <span class="stat"><span class="num" data-count="1551">1&#8239;551</span><span class="label">топшириқ</span></span>
        <span class="stat"><span class="num" data-count="187">187</span><span class="label">бажарилди</span></span>
        <span class="stat"><span class="num"><span data-count="12">12</span><span class="unit">%</span></span><span class="label">ижро</span></span>
```

with

```blade
        @if($regions !== null)
        <span class="stat"><span class="num" data-count="{{ $regions['regions'] }}">{{ $fmt($regions['regions']) }}</span><span class="label">ҳудуд</span></span>
        <span class="stat"><span class="num" data-count="{{ $regions['total'] }}">{{ $fmt($regions['total']) }}</span><span class="label">топшириқ</span></span>
        <span class="stat"><span class="num" data-count="{{ $regions['done'] }}">{{ $fmt($regions['done']) }}</span><span class="label">бажарилди</span></span>
        @if($regions['pct'] !== null)
        <span class="stat"><span class="num"><span data-count="{{ $regions['pct'] }}">{{ $regions['pct'] }}</span><span class="unit">%</span></span><span class="label">ижро</span></span>
        @endif
        @endif
```

Edit 5e — the right module description (line ≈527): replace

```html
      <span class="module-desc anim" style="--d: 240ms">Тармоқ корхоналарининг кафолат хатларидаги вазифалари — июль режалари киритилган, амал ҳисоботи кутилмоқда.</span>
```

with

```blade
      <span class="module-desc anim" style="--d: 240ms">@if($sectors !== null && ! $sectors['reported'])Тармоқ корхоналарининг кафолат хатларидаги вазифалари — режалар киритилган, амал ҳисоботи кутилмоқда.@else Тармоқ корхоналарининг кафолат хатларидаги вазифалари — режа ва факт мониторинги.@endif</span>
```

Edit 5f — the right stats block (lines ≈529–532): replace

```html
        <span class="stat"><span class="num" data-count="17">17</span><span class="label">корхона</span></span>
        <span class="stat"><span class="num" data-count="119">119</span><span class="label">топшириқ</span></span>
        <span class="stat"><span class="num" data-count="515">515</span><span class="label">индикатор</span></span>
        <span class="stat"><span class="state-val"><span class="dot"></span>Ҳисобот кутилмоқда</span><span class="label">жорий давр</span></span>
```

with

```blade
        @if($sectors !== null)
        <span class="stat"><span class="num" data-count="{{ $sectors['sectors'] }}">{{ $fmt($sectors['sectors']) }}</span><span class="label">корхона</span></span>
        <span class="stat"><span class="num" data-count="{{ $sectors['tasks'] }}">{{ $fmt($sectors['tasks']) }}</span><span class="label">топшириқ</span></span>
        <span class="stat"><span class="num" data-count="{{ $sectors['lines'] }}">{{ $fmt($sectors['lines']) }}</span><span class="label">индикатор</span></span>
        @if($sectors['pct'] !== null)
        <span class="stat"><span class="num"><span data-count="{{ $sectors['pct'] }}">{{ $sectors['pct'] }}</span><span class="unit">%</span></span><span class="label">ижро</span></span>
        @else
        <span class="stat"><span class="state-val"><span class="dot"></span>Ҳисобот кутилмоқда</span><span class="label">жорий давр</span></span>
        @endif
        @endif
```

Nothing else changes — CSS, wordmark, motifs, entrance choreography, the count-up script (it already carries the occluded-tab guard), and the reduced-motion block are used verbatim. Note the count-up script only touches `[data-count]` elements, so hidden/absent stats degrade silently.

- [ ] **Step 6: Run the test file**

Run: `php artisan test --filter=StarterPageTest`
Expected: PASS (6 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/StarterController.php resources/views/pages/start.blade.php routes/web.php tests/Feature/Http/StarterPageTest.php
git commit -m "feat(start): Диптих starter page at / with cached module aggregates"
```

---

## Task 2: Map page moves to /regions, capsule swap

**Files:**
- Modify: `backend/resources/views/pages/home.blade.php` (capsule swap only)
- Modify: `backend/tests/Feature/Http/HomePageTest.php`
- Rewrite: `backend/tests/Feature/Sectors/HomeSectorsLinkTest.php`

- [ ] **Step 1: Update the tests (failing first)**

In `backend/tests/Feature/Http/HomePageTest.php`:
- Every `$this->get('/')` (three occurrences, lines ≈33, 64, 72) → `$this->get('/regions')`.
- Test names: `'GET / renders the entry map page with per-region task stats'` → `'GET /regions renders the entry map page with per-region task stats'`; `'GET / works with no tasks at all'` → `'GET /regions works with no tasks at all'`.
- Everything else (region enter tests, geometry test) unchanged.

Rewrite `backend/tests/Feature/Sectors/HomeSectorsLinkTest.php` entirely:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the map page links back to the starter page, not to sectors', function () {
    $this->seed();

    $response = $this->get('/regions');

    $response->assertOk();
    $response->assertSee('href="' . route('home') . '"', escape: false);
    $response->assertSee('Бош саҳифа');
    $response->assertDontSee('href="' . route('sectors') . '"', escape: false);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=HomeSectorsLinkTest`
Expected: FAIL — the map page still links to sectors, no «Бош саҳифа» crumb.
(`HomePageTest` should already pass — `/regions` exists since Task 1 — run it too to confirm: `php artisan test --filter=HomePageTest` → PASS.)

- [ ] **Step 3: Swap the capsule in `home.blade.php`**

Replace the header line (≈189):

```blade
  <a class="sectors-link" href="{{ route('sectors') }}">Тармоқ корхоналари →</a>
```

with

```blade
  <a class="crumb-link" href="{{ route('home') }}">← Бош саҳифа</a>
```

And rename the capsule's CSS class (3 rules, lines ≈115–122): `.sectors-link` → `.crumb-link` in all three selectors (`.crumb-link {`, `.crumb-link:hover {`, `.crumb-link:focus-visible {`). Rule bodies unchanged.

- [ ] **Step 4: Run the affected tests**

Run: `php artisan test --filter=HomeSectorsLinkTest; php artisan test --filter=HomePageTest; php artisan test --filter=StarterPageTest`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/pages/home.blade.php tests/Feature/Http/HomePageTest.php tests/Feature/Sectors/HomeSectorsLinkTest.php
git commit -m "feat(start): map page serves /regions with Бош саҳифа crumb, sectors capsule removed"
```

---

## Task 3: Docs + full verification

**Files:**
- Modify: root `CLAUDE.md` (route table)

- [ ] **Step 1: Update the CLAUDE.md route table**

In the backend Architecture table, add two rows ABOVE the `/dashboard` row:

```markdown
| `/` | `StarterController` → `pages/start` | Front door: module chooser (Вилоятлар · Тармоқлар) with cached aggregates |
| `/regions` | `HomeController@index` → `pages/home` | Country map with per-region task execution; region click → `/region/{code}` → dashboard |
```

- [ ] **Step 2: Run the full test suite**

Run: `php artisan test`
Expected: green (~500 tests, ~10 min). Investigate any failure before proceeding.

- [ ] **Step 3: Visual smoke**

`php artisan serve` → check `http://127.0.0.1:8000/`: diptych renders with REAL numbers (current dev DB: 14 ҳудуд, 1 551 топшириқ, 187 бажарилди, 12%; sectors waiting state), seam hover works, cards navigate to `/regions` and `/sectors`, map page shows the «← Бош саҳифа» crumb and no sectors capsule, sectors page's own «← Бош саҳифа» goes to the new starter.

- [ ] **Step 4: Commit**

```bash
git -C .. add CLAUDE.md
git -C .. commit -m "docs: starter page and /regions rows in route table"
```

---

## Self-review notes (already applied)

- Spec coverage: page structure (Task 1 Step 5), routing (Task 1 Step 3), map edits (Task 2), caching + failure-safe (Task 1 Steps 1/4), tests incl. root-route retarget (Tasks 1–2), CLAUDE.md (Task 3). Prototype round already delivered. Memory update happens at branch finish, not in this plan.
- `route('regions')` referenced in tests before Task 1 Step 3 defines it — within-task ordering (test written first, red, then route) is intentional TDD.
- Strict-done semantics on the starter card vs the map page's lenient reading: called out in Key repo facts; the StarterPageTest's 33% fixture pins it.
- `Cache::shouldReceive('remember')` mocks BOTH aggregate calls (they both go through the facade) — one expectation with `andThrow` covers the two invocations because Mockery reuses the expectation for repeated calls.
