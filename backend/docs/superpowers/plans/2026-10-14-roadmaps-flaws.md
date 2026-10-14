# /roadmaps Flaw Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Honest «Маълумот киритилмаган» status, roadmaps-only region switcher without the artisan-command leak, single-value indicator rows, readable rail section labels, and a collapsed-accordion mobile rail.

**Architecture:** Status truth stays in `App\Services\Roadmaps\MeasureRecomputer` (stored on `roadmap_measures.status`, varchar 16); the page, admin upload report and import pipelines only read it. UI changes are Blade + hand-maintained `public/css/portal.css` (wr- namespace). Spec: `backend/docs/superpowers/specs/2026-10-14-roadmaps-flaws-design.md`.

**Tech Stack:** Laravel 12, Livewire 3 (bundled Alpine — NO focus plugin, NO x-transition: it freezes in background tabs), Pest 3 on PostgreSQL, hand-edited portal.css (no npm build).

**Working directory:** `backend/` (every artisan/test command runs there). Tests: `php artisan test --filter=<Name>`. The full roadmap-suite check at the end: `php artisan test --filter="Roadmaps|RegionSwitcher|Admin"`.

---

### Task 1: `no_report` status in MeasureRecomputer

**Files:**
- Modify: `app/Services/Roadmaps/MeasureRecomputer.php:60-65` (aggregate) and `:111-118` (recompute)
- Test: `tests/Unit/Roadmaps/MeasureRecomputerTest.php`, `tests/Feature/Roadmaps/RoadmapsRecomputeTest.php`

Rule: a measure is `no_report` when it has **no indicator lines** or **no progress rows for any period** (`latest_period === null`). Rows with explicit zero/null actuals still count as entered data (row existence decides). `aggregate()` stays pure over one period's lines — only the `total === 0` branch changes label; the "no rows ever" case lives in `recompute()` where `$latest` is known.

- [ ] **Step 1: Write failing unit tests** — append to `tests/Unit/Roadmaps/MeasureRecomputerTest.php` (it already defines `rmLine(plan, actual)`; check the helper's exact shape at the top of the file and reuse it):

```php
test('no planned lines at all → no_report (was in_progress)', function () {
    expect(MeasureRecomputer::aggregate([], true)['status'])->toBe('no_report');
    expect(MeasureRecomputer::aggregate([], false)['status'])->toBe('no_report');
});
```

- [ ] **Step 2: Write failing feature tests** — append to `tests/Feature/Roadmaps/RoadmapsRecomputeTest.php`:

```php
test('recompute marks measures without lines or without any progress rows as no_report', function () {
    $this->seed();
    $roadmap = Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);

    // (a) lines defined, zero progress rows ever
    $noRows = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 1, 'title' => 'no rows', 'body_raw' => 't', 'source_row' => 1]);
    $noRows->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10]);

    // (b) no lines at all
    $noLines = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 2, 'title' => 'no lines', 'body_raw' => 't', 'source_row' => 2]);

    // (c) a row exists with zero actual → data WAS entered, not no_report
    $zero = $roadmap->measures()->create(['section_no' => 1, 'section_title' => 's', 'seq_no' => 3, 'title' => 'zero', 'body_raw' => 't', 'source_row' => 3, 'deadline_text' => '2026 йил декабрь']);
    $zero->lines()->create(['line_no' => 1, 'label' => 'a', 'plan_value' => 10])
        ->progress()->create(['report_period' => '2026-09', 'period_type' => 'month', 'actual_value' => 0, 'pct_of_plan' => 0]);

    expect(Artisan::call('roadmaps:recompute'))->toBe(0);
    expect($noRows->fresh()->status)->toBe('no_report');
    expect($noLines->fresh()->status)->toBe('no_report');
    expect($zero->fresh()->status)->toBe('in_progress');   // deadline not reached, reported zero
});
```

- [ ] **Step 3: Run both to verify they fail**

Run: `php artisan test --filter=MeasureRecomputerTest; php artisan test --filter=RoadmapsRecomputeTest`
Expected: the two new tests FAIL (`'in_progress'` returned), everything else green.

- [ ] **Step 4: Implement.** In `MeasureRecomputer::aggregate()` replace the status fallthrough block:

```php
        $status = $agg['status'];
        if ($agg['total'] === 0) {
            $status = 'no_report';                            // nothing planned yet → nothing was ever entered
        } elseif ($status === 'open' && ! $deadlineReached) {
            $status = 'in_progress';                          // running behind before the deadline = still in progress
        }
```

In `recompute()`, after the `$agg = self::aggregate(...)` line, override for the no-rows-ever case:

```php
        $agg    = self::aggregate($rows, $latest !== null && RoadmapDeadline::reached($latest, $measure->deadline_text, $roadmapYear));
        // No progress row for ANY period: whatever the per-period rules say, nothing was entered.
        $status = $latest === null ? 'no_report' : $agg['status'];
        $values = [
            'latest_period' => $latest,
            'status'        => $status,
            ...
```

(`'status' => $agg['status']` in `$values` becomes `'status' => $status`.)

Also update the class docblock line 39 comment: `// weakest link: in_progress / done / open (no_report decided here + by recompute())`.

- [ ] **Step 5: Run tests again**

Run: `php artisan test --filter=MeasureRecomputerTest; php artisan test --filter=RoadmapsRecomputeTest`
Expected: new tests PASS. Two OLD assertions now fail — `RoadmapsRecomputeTest` line 18 (`expect($m->fresh()->status)->toBe('in_progress')` after dry-run) and line 104 (rolled-back measure). They assert the column DEFAULT, which Task 2 changes to `no_report`. Update both expectations to `'no_report'` now:

```php
    expect($m->fresh()->status)->toBe('no_report');          // dry-run wrote nothing; column default
```
```php
    expect($good->fresh()->status)->toBe('no_report');       // the good measure's write was rolled back too (column default)
```

They will stay red until Task 2's migration lands — that is expected; Tasks 1+2 commit together only after both are green. Alternatively run Task 2 first; the order here keeps test-first discipline.

- [ ] **Step 6: Do NOT commit yet** — Task 2 makes the suite green.

### Task 2: column default `no_report` (migration)

**Files:**
- Create: `database/migrations/2026_10_14_000001_change_roadmap_measures_status_default.php`
- Test: `tests/Feature/Roadmaps/RoadmapsRecomputeTest.php` (updated in Task 1 Step 5)

New measures from `import:roadmap` are created without progress rows; the old default `in_progress` would lie until the next recompute. Laravel 12 changes column defaults natively (no doctrine/dbal).

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** New measures have no progress rows yet — the honest default is no_report (spec 2026-10-14). Existing rows are rewritten by `roadmaps:recompute`, run once after deploy. */
    public function up(): void
    {
        Schema::table('roadmap_measures', function (Blueprint $table) {
            $table->string('status', 16)->default('no_report')->change();
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_measures', function (Blueprint $table) {
            $table->string('status', 16)->default('in_progress')->change();
        });
    }
};
```

- [ ] **Step 2: Migrate the test run + verify green**

Run: `php artisan test --filter=RoadmapsRecomputeTest` (RefreshDatabase picks the new migration up automatically)
Expected: PASS, including the two expectations updated in Task 1.

- [ ] **Step 3: Check nothing else asserts the old default**

Run: `rg -n "in_progress" tests/ --glob "*Roadmap*"`
Expected: remaining hits are measures that genuinely have progress rows (or aggregate unit cases) — read each; update any that asserts a default-status measure to `no_report`. Known one to check: `RoadmapSchemaTest`.

- [ ] **Step 4: Migrate dev DB**

Run: `php artisan migrate --force` (dev `.env` is APP_ENV=production)
Expected: one migration runs.

- [ ] **Step 5: Commit Tasks 1+2**

```bash
git add app/Services/Roadmaps/MeasureRecomputer.php database/migrations/2026_10_14_000001_change_roadmap_measures_status_default.php tests/Unit/Roadmaps/MeasureRecomputerTest.php tests/Feature/Roadmaps/RoadmapsRecomputeTest.php
git commit -m "feat(roadmaps): no_report status for measures without entered data"
```

### Task 3: grey chip + status colours (MeasureDisplay + CSS)

**Files:**
- Modify: `app/Support/Roadmaps/MeasureDisplay.php:12-16,66-69`
- Modify: `public/css/portal.css` (wr- monitoring block, ~line 6244 `.wr-fbtns`, ~6255 `.wr-kpi`, ~6275 `.wr-status`)
- Test: `tests/Feature/Roadmaps/RoadmapsPageTest.php` (asserts land in Task 4)

- [ ] **Step 1: MeasureDisplay.** STATUS map gains the grey entry; the unknown-status fallback moves to grey:

```php
    private const STATUS = [
        'done'        => ['cls' => 'ok',   'label' => 'Бажарилди'],
        'in_progress' => ['cls' => 'wait', 'label' => 'Бажарилмоқда'],
        'open'        => ['cls' => 'bad',  'label' => 'Бажарилмаган'],
        'no_report'   => ['cls' => 'none', 'label' => 'Маълумот киритилмаган'],
    ];
```

```php
    public static function statusChip(string $status): array
    {
        return self::STATUS[$status] ?? self::STATUS['no_report'];
    }
```

- [ ] **Step 2: CSS.** In `public/css/portal.css` (hand-edited, no build):

After `.wr-fbtns .f-open i{...}` (~line 6248) add:

```css
.wr-fbtns .f-no_report i{background:var(--grey)}
```

After `.wr-kpi.bad b{...}` (~line 6257) add:

```css
.wr-kpi.none b{color:var(--grey)}
```

After `.wr-status.bad{...}` (~line 6279) add:

```css
.wr-status.none{background:var(--grey-soft);color:var(--grey)}
```

(No ring CSS needed: a `no_report` measure has `pct = null`, so the card ring already renders the `na` empty state.)

- [ ] **Step 3: Commit**

```bash
git add app/Support/Roadmaps/MeasureDisplay.php public/css/portal.css
git commit -m "feat(roadmaps): grey no_report chip and status colours"
```

### Task 4: page wiring — 5th filter, 5th KPI tile, hero note removed

**Files:**
- Modify: `app/Livewire/RoadmapsPage.php:30,152-155,168-175`
- Modify: `resources/views/livewire/roadmaps-page.blade.php` (fbtns loop, KPI strip, hero `.nl` note)
- Modify: `public/css/portal.css` (`.wr-kpis` grid, `.wr-hero .hs .nl` removal)
- Test: `tests/Feature/Roadmaps/RoadmapsPageTest.php`

- [ ] **Step 1: Write failing tests** — append to `tests/Feature/Roadmaps/RoadmapsPageTest.php`:

```php
test('measures without any entered data show the grey chip, the 5th rail filter and KPI tile; holat=no_report filters', function () {
    Session::put('region_code', 1733);
    roadmapPageMonitoring();   // Гурлан (III/2) has no lines → no_report; the 4 others have rows

    $response = $this->get('/roadmaps');
    $response->assertOk();
    $response->assertSee('Маълумот киритилмаган');
    $response->assertSeeHtml('class="wr-status none"');
    $response->assertSee('маълумот киритилмаган');                       // KPI tile label
    $response->assertDontSee('тадбирда индикатор йўқ');                  // hero note folded into the grey count

    Livewire::test(RoadmapsPage::class)
        ->call('selectStatus', 'no_report')
        ->assertSet('status', 'no_report')
        ->assertSee('52 млн м3')                                          // Гурлан measure title
        ->assertDontSee('«Куловот»');

    Livewire::withQueryParams(['holat' => 'no_report'])->test(RoadmapsPage::class)
        ->assertSet('status', 'no_report');
});
```

Also update the two existing tests this flips:
- In `cards carry ring, status chip...` (line ~224): replace `$response->assertSee('1 тадбирда индикатор йўқ');` with `$response->assertSee('Маълумот киритилмаган');`.
- In `a road map without any indicator lines renders the registry face everywhere` (line ~264): registry-only import now shows every measure grey — add `$response->assertSee('Маълумот киритилмаган');` after the `ҳисобот йўқ` assert.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=RoadmapsPageTest`
Expected: new test FAILS (no 5th button, counts key missing).

- [ ] **Step 3: RoadmapsPage.php.**

Line 30: `public const STATUSES = ['all', 'done', 'in_progress', 'open', 'no_report'];`

Counts loop (line ~153): `foreach (['done', 'in_progress', 'open', 'no_report'] as $s) {`

Hero array: delete the `'no_lines' => ...` entry (the blade note is removed; nothing else reads it — verify with `rg -n "no_lines" app resources tests`, update any test that asserts it).

- [ ] **Step 4: Blade.** In `resources/views/livewire/roadmaps-page.blade.php`:

Status filter loop (line ~39) — new array:

```blade
        @foreach(['all' => 'Барчаси', 'done' => 'Бажарилди', 'in_progress' => 'Бажарилмоқда', 'open' => 'Бажарилмаган', 'no_report' => 'Маълумот киритилмаган'] as $key => $label)
```

Hero block (line ~33): delete the line `@if($hero['no_lines'] > 0)<div class="nl">{{ $hero['no_lines'] }} тадбирда индикатор йўқ</div>@endif`.

KPI strip (line ~84): append the 5th tile:

```blade
        <div class="wr-kpi none"><b class="tnum">{{ $counts['no_report'] }}</b><span>маълумот киритилмаган</span></div>
```

- [ ] **Step 5: CSS.** `.wr-kpis` (~line 6180): `grid-template-columns:repeat(5,minmax(0,1fr))`. Delete the `.wr-hero .hs .nl{...}` rule (~line 6231). The ≤1100px override (`repeat(2,...)`) stays.

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=RoadmapsPageTest`
Expected: PASS (all, including the two updated old tests).

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/RoadmapsPage.php resources/views/livewire/roadmaps-page.blade.php public/css/portal.css tests/Feature/Roadmaps/RoadmapsPageTest.php
git commit -m "feat(roadmaps): surface no_report in rail filter, KPI strip and chips"
```

### Task 5: import report counts the new status

**Files:**
- Modify: `app/Services/Roadmaps/ProgressImport.php:106`
- Modify: `app/Services/Roadmaps/ImportReport.php:9-10,34-39`
- Modify: `resources/views/livewire/admin/progress-upload.blade.php:57` (+ the matching row-cell loop below it — read the file first)
- Modify: `app/Console/Commands/ImportRoadmapProgress.php` (table headers — find with `rg -n "Бажарилмоқда" app/Console`)
- Test: existing `php artisan test --filter="ProgressUpload|RoadmapProgress"` suites

Without this, `$s[$r['status']]++` throws "Undefined array key no_report" the first time an import leaves a measure with no rows.

- [ ] **Step 1: ProgressImport init (line ~106)** — add the key:

```php
                    'done' => 0, 'in_progress' => 0, 'open' => 0, 'no_report' => 0, 'missing' => 0];
```

- [ ] **Step 2: ImportReport.** Docblock array shape gains `no_report: int`. `rows()` appends the column and the header comment gains `· Киритилмаган`:

```php
    /** The command's table rows: Вилоят · Файлда/жами · Қаторлар · Ўчирилди · Амалда · Бажарилди · Бажарилмоқда · Бажарилмаган · Киритилмаган. @return list<array> */
    public function rows(): array
    {
        return array_map(fn (array $s) => [
            $s['region'], "{$s['in_file']}/{$s['total']}", $s['lines'], $s['removed'], $s['reported'], $s['done'], $s['in_progress'], $s['open'], $s['no_report'],
        ], $this->regions);
    }
```

- [ ] **Step 3: Headers.** Command (`ImportRoadmapProgress` — locate the `$this->table([...])` headers array) and `progress-upload.blade.php:57` both get a trailing `Киритилмаган` column header. The blade's `<tbody>` loops `$row` cells generically or names them — read it; if cells are named, append the new `<td>`.

- [ ] **Step 4: Run import suites**

Run: `php artisan test --filter="ProgressUpload|ImportRoadmapProgress|ProgressImport"`
Expected: PASS (fix any column-count assertion the suites carry).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Roadmaps/ProgressImport.php app/Services/Roadmaps/ImportReport.php resources/views/livewire/admin/progress-upload.blade.php app/Console/Commands/ImportRoadmapProgress.php
git commit -m "feat(roadmaps): import report carries the no_report column"
```

### Task 6: roadmaps-only region switcher + empty-state leak

**Files:**
- Modify: `app/Livewire/RegionSwitcher.php`
- Modify: `resources/views/livewire/region-switcher.blade.php:36` (rs-cur span)
- Modify: `resources/views/pages/roadmaps.blade.php` (pass the prop)
- Modify: `resources/views/livewire/roadmaps-page.blade.php:8-11` (empty state)
- Test: `tests/Feature/Livewire/RegionSwitcherTest.php`, `tests/Feature/Roadmaps/RoadmapsPageTest.php:38-46`

- [ ] **Step 1: Write failing tests.** Append to `tests/Feature/Livewire/RegionSwitcherTest.php`:

```php
test('roadmapsOnly limits the list to regions that have a road map; default lists all 14', function () {
    \App\Models\Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);

    Livewire::test(RegionSwitcher::class, ['roadmapsOnly' => true])
        ->assertViewHas('regions', fn ($r) => $r->count() === 1 && (int) $r->first()->code === 1733);
    Livewire::test(RegionSwitcher::class)
        ->assertViewHas('regions', fn ($r) => $r->count() === 14);
});

test('the trigger shows the session region even when it is not in the filtered list', function () {
    \App\Models\Roadmap::create(['region_code' => 1733, 'year' => 2026, 'title_text' => 't', 'source_file' => 'f']);
    Session::put('region_code', 1726);   // Тошкент шаҳри — no road map

    Livewire::test(RegionSwitcher::class, ['roadmapsOnly' => true])
        ->assertSee('Тошкент шаҳри');
});
```

In `RoadmapsPageTest`, first test (line ~38): replace `$response->assertSee('import:roadmap --region=1703');` with `$response->assertDontSee('php artisan');`.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter="RegionSwitcherTest|RoadmapsPageTest"`
Expected: the three touched tests FAIL.

- [ ] **Step 3: RegionSwitcher.php** — full new render path:

```php
use App\Models\Roadmap;

class RegionSwitcher extends Component
{
    public int $regionCode;

    /** true on /roadmaps: list only regions that have a road map (Тошкент шаҳри never will). */
    public bool $roadmapsOnly = false;

    // mount() and select() unchanged

    public function render()
    {
        $regions = CurrentRegion::regions();
        if ($this->roadmapsOnly) {
            $withMaps = Roadmap::query()->distinct()->pluck('region_code');
            $regions  = $regions->whereIn('code', $withMaps)->values();
        }

        return view('livewire.region-switcher', [
            'regions' => $regions,
            'current' => CurrentRegion::current(),
        ]);
    }
}
```

- [ ] **Step 4: Blades.**

`region-switcher.blade.php` line ~36 — the trigger no longer scans the (possibly filtered) list:

```blade
        <span class="rs-cur">{{ $current->name_full }}</span>
```

`pages/roadmaps.blade.php`:

```blade
    <livewire:region-switcher :roadmaps-only="true" />
```

`roadmaps-page.blade.php` empty state (lines ~8-11) — drop the command line:

```blade
    <div class="wr-empty">
      <h2>{{ $region->name_full }} учун сув хўжалиги йўл харитаси ҳали юкланмаган</h2>
    </div>
```

(The `.wr-empty code{...}` CSS rule at ~6319 stays — `wr-empty small` reuse elsewhere doesn't use `code`, but removing CSS is not required.)

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="RegionSwitcherTest|RoadmapsPageTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/RegionSwitcher.php resources/views/livewire/region-switcher.blade.php resources/views/pages/roadmaps.blade.php resources/views/livewire/roadmaps-page.blade.php tests/Feature/Livewire/RegionSwitcherTest.php tests/Feature/Roadmaps/RoadmapsPageTest.php
git commit -m "feat(roadmaps): roadmaps-only switcher, drop artisan text from empty state"
```

### Task 7: indicator row — single value cell

**Files:**
- Modify: `resources/views/livewire/partials/wr-line.blade.php`
- Modify: `public/css/portal.css:6281-6296` (`.wr-line` grid + `.pp` rules + ≤600px media)
- Test: `tests/Feature/Roadmaps/RoadmapsPageTest.php` (no test asserts `.pp` — verify with `rg -n '"pp"|class=\"pp' tests/`)

- [ ] **Step 1: Partial** — full new content:

```blade
{{-- One indicator row of a /roadmaps measure card. Expects $r from RoadmapsPage's $lineRows.
     No per-line % cell: the card ring carries the aggregate, the tier bar carries the line. --}}
@php use App\Support\Roadmaps\MeasureDisplay; @endphp
<div class="wr-line t-{{ $r['tier'] }}">
  <span class="lb" title="{{ $r['label'] }}">{{ $r['label'] }}</span>
  <span class="bar" aria-hidden="true"><i style="width:{{ $r['width'] }}%"></i><span class="tick"></span></span>
  <span class="pv tnum"><b>{{ MeasureDisplay::fmt($r['actual']) }}</b> / {{ MeasureDisplay::fmt($r['plan']) }} {{ $r['unit'] }}</span>
</div>
```

- [ ] **Step 2: CSS.** Replace the `.wr-line` block (~6281-6296):

```css
.wr-line{display:grid;grid-template-columns:minmax(0,1fr) 90px 136px;gap:10px;align-items:center;margin-top:7px;font-size:12.5px;color:var(--muted)}
.wr-mcard .head+.wr-line{margin-top:10px}
.wr-line .lb{color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wr-line .bar{position:relative;height:4px;background:var(--grey-soft);border-radius:3px}
.wr-line .bar i{position:absolute;inset:0 auto 0 0;border-radius:3px;background:var(--task-green)}
.wr-line .bar .tick{position:absolute;top:-2px;bottom:-2px;width:1.5px;left:83.33%;background:var(--line-strong)}
.wr-line.t-amber .bar i{background:var(--task-amber)}
.wr-line.t-red .bar i{background:var(--task-red)}
.wr-line .pv{text-align:right;white-space:nowrap}
.wr-line .pv b{color:var(--ink);font-weight:700}
.wr-line.none{grid-template-columns:1fr;font-style:italic}
.wr-line.none .lb{color:var(--muted)}
```

(All `.pp` rules are deleted, including the four `t-*` colour lines.)

≤600px media block (~6328): replace the `.wr-line` rules:

```css
@media (max-width:600px){
  .wr-mcard .head{flex-wrap:wrap}
  .wr-line{grid-template-columns:minmax(0,1fr)}
  .wr-line .bar{display:none}
  .wr-line .pv{text-align:left}
}
```

- [ ] **Step 3: Run page tests + verify no stale selector**

Run: `php artisan test --filter=RoadmapsPageTest` and `rg -n "\.pp|\"pp\"" resources public/css tests`
Expected: tests PASS; the only `pp` hits left are unrelated words.

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/partials/wr-line.blade.php public/css/portal.css
git commit -m "fix(roadmaps): indicator rows carry one value cell, ring keeps the percent"
```

### Task 8: rail section labels wrap to two lines

**Files:**
- Modify: `resources/views/livewire/roadmaps-page.blade.php:46` (sections nav class)
- Modify: `public/css/portal.css` (after `.wr-kcard button .n` rules, ~6176)

- [ ] **Step 1: Blade** — the sections nav gets a hook class:

```blade
      <nav class="wr-kcard wr-sections" aria-label="Бўлимлар">
```

- [ ] **Step 2: CSS** — append after the `.wr-kcard button.on .n` rule:

```css
/* Section titles wrap to two lines — one-line ellipsis made IV and V read identical
   («Вилоятнинг хусусиятид…»). Roman + count stay on the first line's baseline. */
.wr-sections button{align-items:flex-start}
.wr-sections button .t{
  white-space:normal;display:-webkit-box;-webkit-box-orient:vertical;
  -webkit-line-clamp:2;line-clamp:2;overflow:hidden;
}
.wr-sections button .n{margin-top:1px}
```

- [ ] **Step 3: Run page tests** (markup assertion `class="on" title="..."` on section buttons must survive — class list order in the blade keeps `{{ $on ? 'on' : '' }}` first)

Run: `php artisan test --filter=RoadmapsPageTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/roadmaps-page.blade.php public/css/portal.css
git commit -m "fix(roadmaps): section rail labels wrap to two lines"
```

### Task 9: mobile accordion rail (<1100px)

**Files:**
- Modify: `resources/views/livewire/roadmaps-page.blade.php` (the three filter navs)
- Modify: `public/css/portal.css` (wr- block + ≤1100px media)
- Test: `tests/Feature/Roadmaps/RoadmapsPageTest.php`

Pattern: each filter nav gets an Alpine `open` flag, a header button visible only on mobile, and a body div that a `closed` class hides — only under the 1100px media query, so desktop renders exactly as today. **No `x-show`, no `x-transition`** (Alpine transitions freeze in backgrounded tabs; class-binding is synchronous and desktop-inert).

- [ ] **Step 1: Write failing test** — append to `RoadmapsPageTest`:

```php
test('rail filter groups render the mobile accordion scaffolding', function () {
    Session::put('region_code', 1733);
    roadmapImportKhorezmPage();

    $response = $this->get('/roadmaps');
    $response->assertSeeHtml('wr-acc-h');
    $response->assertSeeHtml('wr-acc-b');
});
```

Run: `php artisan test --filter=RoadmapsPageTest` — the new test FAILS.

- [ ] **Step 2: Blade.** At the top of the `@else` branch (after `<aside class="wr-rail">`'s search div), define the shared status-label map once:

```blade
      @php
        $statusLabels = ['all' => 'Барчаси', 'done' => 'Бажарилди', 'in_progress' => 'Бажарилмоқда', 'open' => 'Бажарилмаган', 'no_report' => 'Маълумот киритилмаган'];
        $sectionCur   = $district !== 'all'
            ? ($districts->first(fn ($d) => (string) $d['code'] === $district)['name'] ?? 'Барчаси')
            : ($section !== 'all' ? \App\Livewire\RoadmapsPage::roman((int) $section) . ' бўлим' : 'Барчаси');
      @endphp
```

**Status nav** — replace the whole `<nav class="wr-kcard wr-fbtns" ...>` block:

```blade
      <nav class="wr-kcard wr-acc" aria-label="Ҳолат бўйича фильтр" x-data="{ open: false }">
        <button type="button" class="wr-acc-h" x-on:click="open = ! open" :aria-expanded="open">
          Ҳолат<span class="cur">{{ $statusLabels[$status] }}</span><span class="ar" :class="open && 'o'" aria-hidden="true">▾</span>
        </button>
        <div class="wr-acc-b wr-fbtns" :class="! open && 'closed'" x-on:click="open = false">
          @foreach($statusLabels as $key => $label)
            <button type="button" class="f-{{ $key }} {{ $status === $key ? 'on' : '' }}"
                    aria-pressed="{{ $status === $key ? 'true' : 'false' }}"
                    wire:click="selectStatus(@js($key))"><i></i>{{ $label }}<span class="n tnum">{{ $counts[$key] }}</span></button>
          @endforeach
        </div>
      </nav>
```

(The status loop now feeds from `$statusLabels` — Task 4's inline array is superseded; remove it.)

**Sections nav** — same wrapper (keeps Task 8's `wr-sections` class):

```blade
      <nav class="wr-kcard wr-sections wr-acc" aria-label="Бўлимлар" x-data="{ open: false }">
        <button type="button" class="wr-acc-h" x-on:click="open = ! open" :aria-expanded="open">
          Бўлимлар<span class="cur">{{ $sectionCur }}</span><span class="ar" :class="open && 'o'" aria-hidden="true">▾</span>
        </button>
        <div class="wr-acc-b" :class="! open && 'closed'" x-on:click="open = false">
          <div class="kt">Бўлимлар</div>
          ... (existing Барчаси button + @foreach($sections ...) unchanged) ...
        </div>
      </nav>
```

**Districts nav** — same wrapper; header label `Туманлар`, `cur` shows the picked district or `Барчаси`:

```blade
        <nav class="wr-kcard wr-acc" aria-label="Туманлар" x-data="{ open: false }">
          <button type="button" class="wr-acc-h" x-on:click="open = ! open" :aria-expanded="open">
            Туманлар<span class="cur">{{ $districts->first(fn ($d) => (string) $d['code'] === $district)['name'] ?? 'Барчаси' }}</span><span class="ar" :class="open && 'o'" aria-hidden="true">▾</span>
          </button>
          <div class="wr-acc-b" :class="! open && 'closed'" x-on:click="open = false">
            <div class="kt">Туманлар</div>
            ... (existing @foreach($districts ...) unchanged) ...
          </div>
        </nav>
```

Livewire note: every `@if/@foreach` opens and closes at one DOM level (the file's existing morph-marker rule); the new wrappers respect that.

- [ ] **Step 3: CSS.** Append to the wr- monitoring block (desktop first, media override after):

```css
/* ---- mobile accordion rail (2026-10-14): headers only exist <1100px; the `closed`
   class is inert on desktop so the cards render exactly as before ---- */
.wr-acc-h{display:none;width:100%;border:0;background:none;align-items:center;gap:8px;
  padding:8px 10px;border-radius:9px;font-size:12.5px;font-weight:800;color:var(--ink);text-align:left}
.wr-acc-h .cur{margin-left:auto;flex:0 0 auto;max-width:55%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  font-size:11px;font-weight:700;color:var(--blue);background:var(--blue-soft);border-radius:999px;padding:2px 9px}
.wr-acc-h .ar{flex:0 0 auto;color:var(--muted);transition:transform .2s}
.wr-acc-h .ar.o{transform:rotate(180deg)}
```

Inside the existing `@media (max-width:1100px){...}` block add:

```css
  .wr-acc-h{display:flex}
  .wr-acc .wr-acc-b.closed{display:none}
  .wr-acc .wr-acc-b .kt{display:none}   /* the header already names the group */
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=RoadmapsPageTest`
Expected: PASS — including the older `assertSeeHtml('Барчаси<span class="n tnum">5</span>')`-style assertions (button markup unchanged inside the new wrapper).

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/roadmaps-page.blade.php public/css/portal.css tests/Feature/Roadmaps/RoadmapsPageTest.php
git commit -m "feat(roadmaps): collapsed accordion filter rail on mobile"
```

### Task 10: full verification, recompute, docs

**Files:**
- Modify: `docs/roadmap-monitoring.md` (status list), `../CLAUDE.md` (/roadmaps route row)
- No code.

- [ ] **Step 1: Full affected suites**

Run: `php artisan test --filter="Roadmaps|RegionSwitcher|MeasureRecomputer|ProgressUpload|Admin"`
Expected: all PASS. If a shared-test-DB deadlock bites (another session running tests), re-run with a session-unique DB: `$env:DB_DATABASE = 'hududlar_test_<unique>'; php artisan test --filter=...` (create the DB first).

- [ ] **Step 2: Recompute dev DB**

Run: `php artisan roadmaps:recompute`
Expected: thousands of `in_progress→no_report` flips across the 13 road maps; done/open counts unchanged.

- [ ] **Step 3: Browser smoke test** (live :8000 serves the working tree — do NOT restart or kill it). Check on http://127.0.0.1:8000/roadmaps:
  - Andijan: grey chips dominate, «Бажарилмоқда» now only on genuinely reported measures; 5 KPI tiles; 5 rail filters; `?holat=no_report` works.
  - Switcher lists 13 regions (no Тошкент шаҳри); its trigger still renders if a stale session points at 1726.
  - Indicator rows show one value cell; section labels IV vs V distinguishable; narrow window (<1100px): three collapsed accordion headers with active-pick pills, tap opens, select closes.

- [ ] **Step 4: Docs.** `docs/roadmap-monitoring.md`: statuses list gains «Маълумот киритилмаган» (`no_report` — lines without any entered rows, or no lines) and the note that recompute must run once after this deploy. `CLAUDE.md` /roadmaps row: `derived status (Бажарилди · Бажарилмоқда · Бажарилмаган · Маълумот киритилмаган)`.

- [ ] **Step 5: Commit**

```bash
git add docs/roadmap-monitoring.md ../CLAUDE.md
git commit -m "docs(roadmaps): no_report status in runbook and route table"
```
