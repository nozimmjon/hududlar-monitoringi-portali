# Sectors e-panel Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move `/sectors` (SectorsDashboard) and `/sectors/{code}` (SectorDetail) to the «Прототип Е · Панель» design: dark top bar with search, sticky rail (ring + rating), zoned cards, and a Livewire slide-over drawer sharing one Blade partial with the detail page.

**Architecture:** Pure Livewire (approach A) — search, drawer open/close, tabs, and line toggles are all server round-trips. A new `App\Support\SectorDisplay` support class centralizes tier/percent/format/deadline logic and the detail dataset used by both the drawer and the detail page. All new CSS goes into the hand-maintained `public/css/portal.css` (NO build step — never run `npm run build`), fully prefixed `sec-` and scoped under `body.sectors-body` because portal.css defines global `.num`, `.chip`, `.task` that must not leak in. Presentation-only inline JS (ring count-up, card entrance) runs once per full page load and ignores Livewire morphs.

**Tech Stack:** Laravel 12, Livewire 3, Pest 3 on PostgreSQL, hand-edited portal.css.

**Spec:** `docs/superpowers/specs/2026-08-05-sectors-epanel-redesign-design.md`
**Visual reference:** `.claude/worktrees/sector-logos/backend/public/prototypes/e-panel.html` (uncommitted, contains real figures — read locally, never commit).

**Key repo facts for the implementer:**

- Run all commands from `backend/`. Tests: `php artisan test --filter=SomeTest` (PostgreSQL must be running). Full suite ~10 min.
- `sector_task_progress.deadline_code` is already normalized on import to `q3 | q4 | h2 | year` — use it, do NOT parse `deadline_text`.
- Status model: task `status` ∈ `done | open | in_progress`; `in_progress` means *nothing reported* and displays as «Кутилмоқда» in this design (the old chip said «Бажарилмоқда» — this plan renames it on sector pages only; the tasks board is untouched).
- 99-cap rule: only a fully done entity may display ≥ 100%; otherwise `min(99, round(pct))`.
- UI language: Cyrillic Uzbek. Commits: Conventional Commits.
- `route('home')`, `route('sectors')`, `route('sectors.detail', $code)` all exist.
- Existing global CSS hazards confirmed: `.num` (portal.css:1316, sets `text-align:right`), `.chip` (portal.css:1319), `.task` (92 uses). The new markup must never use bare `num`/`chip`/`task`/`grid` class names — always `tnum`/`sec-chip`/`sec-task`/`sec-grid`.

---

## Task 1: `SectorDisplay` support class (TDD)

**Files:**
- Create: `backend/app/Support/SectorDisplay.php`
- Test: `backend/tests/Unit/SectorDisplayTest.php`

- [ ] **Step 1: Write the failing unit test**

```php
<?php

use App\Models\SectorTask;
use App\Support\SectorDisplay;

test('tier maps percent to traffic-light buckets', function () {
    expect(SectorDisplay::tier(null))->toBe('wait');
    expect(SectorDisplay::tier(120.0))->toBe('ok');
    expect(SectorDisplay::tier(100.0))->toBe('ok');
    expect(SectorDisplay::tier(99.9))->toBe('warn');
    expect(SectorDisplay::tier(50.0))->toBe('warn');
    expect(SectorDisplay::tier(49.9))->toBe('bad');
    expect(SectorDisplay::tier(0.0))->toBe('bad');
});

test('pshow applies the 99-cap rule', function () {
    expect(SectorDisplay::pshow(null, false))->toBeNull();
    expect(SectorDisplay::pshow(119.6, true))->toBe(120);   // done → real rounded value
    expect(SectorDisplay::pshow(99.6, false))->toBe(99);    // not done → capped at 99
    expect(SectorDisplay::pshow(42.4, false))->toBe(42);
});

test('fmt renders numbers with space thousands and comma decimals', function () {
    expect(SectorDisplay::fmt(null))->toBe('—');
    expect(SectorDisplay::fmt(1234.5))->toBe('1 234,5');
    expect(SectorDisplay::fmt(56.0))->toBe('56');
    expect(SectorDisplay::fmt(0.25))->toBe('0,25');
});

test('taskPct: in_progress is null, multi-line uses line ratio, single-line uses headline_pct', function () {
    $wait = new SectorTask(['status' => 'in_progress', 'lines_total' => 3, 'lines_done' => 0]);
    expect(SectorDisplay::taskPct($wait))->toBeNull();

    $multi = new SectorTask(['status' => 'open', 'lines_total' => 4, 'lines_done' => 1]);
    expect(SectorDisplay::taskPct($multi))->toBe(25.0);

    $single = new SectorTask(['status' => 'done', 'lines_total' => 1, 'lines_done' => 1, 'headline_pct' => '119.05']);
    expect(SectorDisplay::taskPct($single))->toBeNumericallyClose(119.05);
});

test('deadline constants cover the four import buckets in order', function () {
    expect(array_keys(SectorDisplay::DEADLINE_ORDER))->toBe(['q3', 'q4', 'h2', 'year']);
    expect(SectorDisplay::DEADLINE_LABELS['q3'])->toBe('III чорак');
    expect(SectorDisplay::DEADLINE_LABELS['q4'])->toBe('IV чорак');
    expect(SectorDisplay::DEADLINE_LABELS['h2'])->toBe('2-ярим йиллик');
    expect(SectorDisplay::DEADLINE_LABELS['year'])->toBe('Йил якуни');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=SectorDisplayTest`
Expected: FAIL — `Class "App\Support\SectorDisplay" not found`

- [ ] **Step 3: Implement the class**

```php
<?php

namespace App\Support;

use App\Models\Sector;
use App\Models\SectorTask;

/**
 * Shared display logic for the sector pages (/sectors dashboard, drawer,
 * /sectors/{code} detail): tier colors, 99-cap percents, number formatting,
 * deadline buckets, and the detail dataset used by both drawer and page.
 */
class SectorDisplay
{
    /** Deadline bucket labels, keyed by sector_task_progress.deadline_code. */
    public const DEADLINE_LABELS = [
        'q3'   => 'III чорак',
        'q4'   => 'IV чорак',
        'h2'   => '2-ярим йиллик',
        'year' => 'Йил якуни',
    ];

    /** Bucket sort order, earliest deadline first. */
    public const DEADLINE_ORDER = ['q3' => 1, 'q4' => 2, 'h2' => 3, 'year' => 4];

    /** Traffic-light tier for a percent value; null percent = nothing reported. */
    public static function tier(?float $pct): string
    {
        if ($pct === null) {
            return 'wait';
        }
        if ($pct >= 100) {
            return 'ok';
        }

        return $pct >= 50 ? 'warn' : 'bad';
    }

    /** Display percent under the 99-cap rule: only a fully done entity shows ≥ 100. */
    public static function pshow(?float $pct, bool $done): ?int
    {
        if ($pct === null) {
            return null;
        }

        return $done ? (int) round($pct) : min(99, (int) round($pct));
    }

    /** Format a value: space thousands, comma decimals, trailing zeros trimmed. */
    public static function fmt(null|float|string $v): string
    {
        if ($v === null) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
    }

    /** Task-level completion percent (null while nothing is reported). */
    public static function taskPct(SectorTask $task): ?float
    {
        if ($task->status === 'in_progress') {
            return null;
        }
        if ((int) $task->lines_total > 1) {
            return $task->lines_total > 0 ? $task->lines_done / $task->lines_total * 100 : null;
        }

        return $task->headline_pct !== null ? (float) $task->headline_pct : null;
    }

    /**
     * Detail dataset shared by the dashboard drawer and the /sectors/{code} page.
     *
     * @return array{sector: Sector, tasks: \Illuminate\Support\Collection, counts: array, agg: array}
     */
    public static function detailData(Sector $sector, string $filter): array
    {
        $tasks = SectorTask::where('sector_id', $sector->id)
            ->orderBy('task_no')
            ->with('progress')
            ->get();

        $counts = [
            'all'         => $tasks->count(),
            'done'        => $tasks->where('status', 'done')->count(),
            'open'        => $tasks->where('status', 'open')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
        ];

        $linesTotal = (int) $tasks->sum('lines_total');
        $linesDone  = (int) $tasks->sum('lines_done');
        $hasReport  = $tasks->contains(fn (SectorTask $t) => $t->status !== 'in_progress');

        return [
            'sector' => $sector,
            'tasks'  => $filter === 'all' ? $tasks : $tasks->where('status', $filter)->values(),
            'counts' => $counts,
            'agg'    => [
                'lines_total' => $linesTotal,
                'lines_done'  => $linesDone,
                'pct'         => $hasReport && $linesTotal > 0 ? $linesDone / $linesTotal * 100 : null,
            ],
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=SectorDisplayTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Support/SectorDisplay.php ../backend/tests/Unit/SectorDisplayTest.php
git commit -m "feat(sectors): SectorDisplay support class for shared display logic"
```

(If the relative test path fails, use `git add app/Support/SectorDisplay.php tests/Unit/SectorDisplayTest.php` from `backend/`.)

---

## Task 2: Shared partial + SectorDetail rewrite

**Files:**
- Create: `backend/resources/views/livewire/partials/sector-tasks.blade.php`
- Modify: `backend/app/Livewire/SectorDetail.php` (render method only)
- Rewrite: `backend/resources/views/livewire/sector-detail.blade.php`
- Modify tests: `backend/tests/Feature/Sectors/SectorsDrilldownTest.php`, `backend/tests/Feature/Sectors/SectorsExpandLinesTest.php`

- [ ] **Step 1: Update the two existing test files to the new markup (failing first)**

In `SectorsDrilldownTest.php`:

Replace the first test's chip assertion block — old:

```php
    $response->assertSee('Бажарилди');       // done chip
    $response->assertSee('Бажарилмаган');    // open chip (weakest link: газ 53%)
    $response->assertSee('Бажарилмоқда');    // in_progress chip
    $response->assertSee('Муддат');
    $response->assertSee('2026 йил якуни');
```

new:

```php
    $response->assertSee('Бажарилди');       // done chip
    $response->assertSee('Бажарилмаган');    // open chip (weakest link: газ 53%)
    $response->assertSee('Кутилмоқда');      // in_progress chip (renamed in the e-panel design)
    $response->assertSee('муддат');
    $response->assertSee('2026 йил якуни');
```

Replace the second test's body — old assertions `Режа/Амалда/Индикаторлар/Бажарилиш`, new:

```php
    Livewire::test(SectorDetail::class, ['code' => 'uzbekneftgaz'])
        ->assertSee('Режа')
        ->assertSee('Факт')
        ->assertSee('Индикаторлар')
        ->assertSee('Умумий ижро');
```

Replace the `dashboard cards link to the sector detail page` test entirely with:

```php
test('the sector detail page stays reachable by direct URL', function () {
    $this->seed();
    importSectorsUiFixture();

    $this->get('/sectors/uzbekneftgaz')->assertOk()->assertSee('«Ўзбекнефтгаз» АЖ');
});
```

In `SectorsExpandLinesTest.php`, change the one markup assertion:

```php
    $component->assertSeeHtml('sec-line dim');
```

(was `sdp-line dim`).

- [ ] **Step 2: Run to verify the updated tests fail**

Run: `php artisan test --filter=SectorsDrilldownTest`
Expected: FAIL — «Кутилмоқда» not found as chip / «Факт» not found.

- [ ] **Step 3: Create the shared partial**

`backend/resources/views/livewire/partials/sector-tasks.blade.php` — expects `$sector`, `$tasks` (filtered), `$counts`, `$agg`, `$filter`, `$expanded`, `$inDrawer`:

```blade
@php
    use App\Support\DashboardCatalog;
    use App\Support\SectorDisplay;

    $aggDone  = $counts['all'] > 0 && $counts['done'] === $counts['all'];
    $aggShown = SectorDisplay::pshow($agg['pct'], $aggDone);
@endphp
<div class="sec-ohead">
    <div class="otop">
        <span class="sec-logo big">
            @if($sector->logoPath())
                <img src="{{ asset($sector->logoPath()) }}" alt="">
            @else
                <span class="sec-mono" aria-hidden="true">{{ mb_substr($sector->cardName(), 0, 1) }}</span>
            @endif
        </span>
        <div class="oid">
            <h2>{{ $sector->cardName() }}</h2>
            <div class="org">{{ $sector->org_full }}</div>
            @if($sector->signer_text)
                <div class="sg">Кафолат хати: {{ $sector->signer_text }}</div>
            @endif
        </div>
        @if($inDrawer)
            <button type="button" class="x" wire:click="closeSector" aria-label="Ёпиш">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        @endif
    </div>
    <div class="sec-ostats">
        <div class="sec-ostat"><div class="v tnum">{{ $aggShown === null ? '—' : $aggShown . '%' }}</div><div class="k">Умумий ижро</div></div>
        <div class="sec-ostat ok"><div class="v tnum">{{ $counts['done'] }}</div><div class="k">Бажарилди</div></div>
        <div class="sec-ostat bad"><div class="v tnum">{{ $counts['open'] }}</div><div class="k">Бажарилмаган</div></div>
        <div class="sec-ostat"><div class="v tnum">{{ $agg['lines_done'] }}/{{ $agg['lines_total'] }}</div><div class="k">Индикатор</div></div>
    </div>
</div>
<div class="sec-obody">
    <div class="sec-otabs" role="tablist">
        @foreach(['all' => 'Барчаси', 'done' => 'Бажарилди', 'open' => 'Бажарилмаган', 'in_progress' => 'Кутилмоқда'] as $key => $label)
            <button type="button" role="tab" class="{{ $filter === $key ? 'on' : '' }}"
                    aria-selected="{{ $filter === $key ? 'true' : 'false' }}"
                    wire:click="setFilter(@js($key))">
                {{ $label }}<span class="n tnum">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>
    <div class="sec-otasks">
        @forelse($tasks as $task)
            @php
                $isMulti  = (int) $task->lines_total > 1;
                $isDone   = $task->status === 'done';
                $tpct     = SectorDisplay::taskPct($task);
                $tshown   = SectorDisplay::pshow($tpct, $isDone);
                $ttier    = $task->status === 'in_progress' ? 'wait' : SectorDisplay::tier($tpct);
                $chip     = match ($task->status) {
                    'done'        => ['ok', 'Бажарилди'],
                    'in_progress' => ['wait', 'Кутилмоқда'],
                    default       => ['bad', 'Бажарилмаган'],
                };
                $lines    = $task->progress->where('report_period', $task->latest_period)->sortBy('line_no')->values();
                $headLine = $lines->first();
                $unit     = DashboardCatalog::unitLabel($task->headline_unit);
                $isOpen   = $expanded[$task->id] ?? false;
                // Bar scale is 120% like the prototype; the tick at 83.33% marks 100% of plan.
                $barW     = $tpct === null ? 0 : min(100, $tpct / 120 * 100);
            @endphp
            <article class="sec-task" wire:key="sec-task-{{ $task->id }}">
                <div class="thead">
                    <span class="tno tnum">№{{ $task->task_no }}</span>
                    <div class="ttl">{{ $task->title }}</div>
                    <span class="sec-chip {{ $chip[0] }}"><i></i>{{ $chip[1] }}</span>
                </div>
                <div class="tfacts">
                    @if($isMulti)
                        <span>Индикаторлар: <b class="tnum">{{ $task->lines_done }}/{{ $task->lines_total }}</b></span>
                    @else
                        <span>Режа: <b class="tnum">{{ SectorDisplay::fmt($task->headline_plan) }}</b> {{ $task->headline_plan !== null ? $unit : '' }}</span>
                        <span>Факт: <b class="tnum">{{ SectorDisplay::fmt($task->headline_actual) }}</b></span>
                    @endif
                    <span class="tb"><i style="width:{{ $barW }}%;background:var(--sec-{{ $ttier }})"></i><span class="tick"></span></span>
                    <span><b class="tnum">{{ $tshown === null ? '—' : $tshown . '%' }}</b></span>
                    @if($headLine?->deadline_text)
                        <span class="dl">муддат: {{ $headLine->deadline_text }}</span>
                    @endif
                </div>
                @if($isMulti)
                    <button type="button" class="sec-ltog {{ $isOpen ? 'open' : '' }}" wire:click="toggleTask(@js($task->id))">
                        <span class="c">▸</span> Индикаторлар ({{ $task->lines_total }})
                    </button>
                    @if($isOpen)
                        <div class="sec-lines">
                            @foreach($lines as $line)
                                @php
                                    $lpct      = $line->pct_of_plan !== null ? (float) $line->pct_of_plan : null;
                                    $lineShown = SectorDisplay::pshow($lpct, $lpct !== null && $lpct >= 100);
                                    $lineUnit  = DashboardCatalog::unitLabel($line->unit);
                                @endphp
                                <div class="sec-line {{ $line->actual_value === null ? 'dim' : '' }}" wire:key="sec-line-{{ $line->id }}">
                                    <span class="lb">{{ $line->metric_label }}@if($lineUnit) <span class="u">· {{ $lineUnit }}</span>@endif</span>
                                    <span class="lv tnum"><b>{{ SectorDisplay::fmt($line->actual_value) }}</b> / {{ SectorDisplay::fmt($line->plan_value) }}</span>
                                    <span class="lp tnum {{ $lineShown === null ? 'na' : '' }}">{{ $lineShown === null ? '—' : $lineShown . '%' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </article>
        @empty
            <div class="sec-empty">Бу ҳолатда топшириқ йўқ</div>
        @endforelse
    </div>
</div>
```

- [ ] **Step 4: Rewrite `SectorDetail::render()`**

Replace the whole `render()` method body (keep `mount`, `setFilter`, `toggleTask`, the `#[Url]` attribute — all unchanged):

```php
    public function render()
    {
        return view('livewire.sector-detail', \App\Support\SectorDisplay::detailData($this->sector, $this->filter));
    }
```

- [ ] **Step 5: Rewrite `sector-detail.blade.php`**

Full new content:

```blade
<div class="sec-shell sec-detail">
    <header class="sec-top on">
        <span class="sec-pline" style="--p:{{ min(1, ($agg['pct'] ?? 0) / 120) }}"></span>
        <div class="sec-tin">
            <a class="sec-back" href="{{ route('sectors') }}">← Барча корхоналар</a>
            <h1>{{ $sector->cardName() }}</h1>
            <span class="sp"></span>
        </div>
    </header>
    <div class="sec-detail-wrap">
        @include('livewire.partials.sector-tasks', [
            'sector'   => $sector,
            'tasks'    => $tasks,
            'counts'   => $counts,
            'agg'      => $agg,
            'filter'   => $filter,
            'expanded' => $expanded,
            'inDrawer' => false,
        ])
    </div>
</div>
```

- [ ] **Step 6: Run the detail tests**

Run: `php artisan test --filter=SectorsDrilldownTest; php artisan test --filter=SectorsExpandLinesTest`
Expected: PASS (page is unstyled until Task 4 — that's fine; tests assert content, not CSS).

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/SectorDetail.php resources/views/livewire/partials/sector-tasks.blade.php resources/views/livewire/sector-detail.blade.php tests/Feature/Sectors/SectorsDrilldownTest.php tests/Feature/Sectors/SectorsExpandLinesTest.php
git commit -m "feat(sectors): shared sector-tasks partial, detail page on e-panel markup"
```

---

## Task 3: SectorsDashboard component + view (rail, cards, drawer)

**Files:**
- Rewrite: `backend/app/Livewire/SectorsDashboard.php`
- Rewrite: `backend/resources/views/livewire/sectors-dashboard.blade.php`
- Modify tests: `backend/tests/Feature/Sectors/SectorsDashboardPageTest.php` (rewrite), `backend/tests/Feature/Sectors/SectorLogoTest.php` (one line)

- [ ] **Step 1: Rewrite `SectorsDashboardPageTest.php` (failing first)**

Full new content:

```php
<?php

use App\Livewire\SectorsDashboard;
use App\Models\SectorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

function importPanelFixture(): void
{
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', '«Ўзбекнефтгаз» АЖ', [
            [1, 1, 'В1. Биринчи вазифа', 'Кўрсаткич А', 'та', '2026 йил III-чорак', 100, 120, null], // done
            [2, 2, 'В2. Иккинчи вазифа', 'Кўрсаткич Б', 'та', '2026 йил якуни', 100, 55, null],      // open
        ]],
        ['2. Ўзбекгидроэнерго', '«Ўзбекгидроэнерго» АЖ', [
            [1, 1, 'В1. Гидро вазифа', 'Кўрсаткич В', 'та', '2026 йил якуни', 100, 130, null],       // done
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);
}

test('/sectors renders all 17 sector cards in sheet order', function () {
    $this->seed();

    $response = $this->get('/sectors');

    $response->assertOk();
    $response->assertSeeInOrder([
        'Ўзбекнефтгаз', 'Ўзбекгидроэнерго', 'Иссиқлик электр станциялари', 'Ўзкимёсаноат', 'НКМК',
        'Навоийуран', 'Олмалиқ КМК', 'Ўзметкомбинат', 'ТМК', 'Ўзавтосаноат',
        'Ўзэлтехсаноат', 'Енгил саноат агентлиги', 'Ўзтўқимачиликсаноат', 'Ўзчармсаноат',
        'Ўзсаноатқурилишматериаллари', 'Фармацевтика агентлиги', 'Ўзбекзаргарсаноати',
    ]);
    $response->assertSee('Тармоқ корхоналари топшириқлари');
    $response->assertSee('Ижро рейтинги');
});

test('sectors without any reported actuals show the waiting card state', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, null, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);

    $html = $this->get('/sectors')->getContent();

    expect($html)->toContain('waitc');
    expect($html)->toContain('Ҳисобот кутилмоқда');
});

test('the rail hero shows indicator-level overall percent and fractions', function () {
    $this->seed();
    importPanelFixture();

    $html = $this->get('/sectors')->getContent();

    // 2 of 3 indicator lines done → 67%
    expect($html)->toContain('67%');
    expect($html)->toContain('Умумий ижро');
});

test('a sector card shows the 99-capped percent, counts, and earliest deadline bucket', function () {
    $this->seed();
    importPanelFixture();

    $html = $this->get('/sectors')->getContent();

    // Neftgaz: 1/2 lines done → 50%; q3 line present → III чорак on the card.
    expect($html)->toContain('50');
    expect($html)->toContain('III чорак');
    expect($html)->toContain('Батафсил');
});

test('the rating card ranks reporting sectors best-first', function () {
    $this->seed();
    importPanelFixture();

    // Гидро 100% > Нефтгаз 50%.
    $this->get('/sectors')->assertSeeInOrder(['Энг юқори', 'Ўзбекгидроэнерго', 'Энг паст']);
});

test('search narrows the card grid but not the rail', function () {
    $this->seed();

    Livewire::test(SectorsDashboard::class)
        ->assertSeeHtml('data-code="uzavtosanoat"')
        ->set('search', 'НКМК')
        ->assertSeeHtml('data-code="nkmk"')
        ->assertDontSeeHtml('data-code="uzavtosanoat"')
        ->set('search', 'зззйўқ')
        ->assertSee('Ҳеч нарса топилмади');
});

test('openSector renders the drawer with tasks; closeSector empties it', function () {
    $this->seed();
    importPanelFixture();

    Livewire::test(SectorsDashboard::class)
        ->call('openSector', 'uzbekneftgaz')
        ->assertSet('open', 'uzbekneftgaz')
        ->assertSee('«Ўзбекнефтгаз» АЖ')
        ->assertSee('В1. Биринчи вазифа')
        ->call('closeSector')
        ->assertSet('open', null)
        ->assertDontSee('В1. Биринчи вазифа');
});

test('drawer tabs filter tasks and reset on reopen', function () {
    $this->seed();
    importPanelFixture();

    Livewire::test(SectorsDashboard::class)
        ->call('openSector', 'uzbekneftgaz')
        ->call('setFilter', 'done')
        ->assertSee('В1. Биринчи вазифа')
        ->assertDontSee('В2. Иккинчи вазифа')
        ->call('closeSector')
        ->call('openSector', 'uzbekneftgaz')
        ->assertSet('filter', 'all')
        ->assertSee('В2. Иккинчи вазифа');
});

test('toggleTask expands a multi-line task inside the drawer', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'Углеводород қазиб чиқариш.', 'Табиий газ', 'млрд куб метр', '2026 йил якуни', 24.7, 13.2, null],
            [null, 2, null, 'Суюқ углеводородлар', 'минг тонна', '2026 йил якуни', 1188, null, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);
    $task = SectorTask::where('task_no', 1)->firstOrFail();

    Livewire::test(SectorsDashboard::class)
        ->call('openSector', 'uzbekneftgaz')
        ->assertDontSee('Суюқ углеводородлар')
        ->call('toggleTask', $task->id)
        ->assertSee('Суюқ углеводородлар');
});
```

- [ ] **Step 2: Update `SectorLogoTest.php` last assertion**

```php
    // All 17 have a bundled logo now — no monogram fallback in the page.
    expect($html)->not->toContain('sec-mono');
```

(was `sector-card-mono`).

- [ ] **Step 3: Run to verify they fail**

Run: `php artisan test --filter=SectorsDashboardPageTest`
Expected: FAIL — no `Ижро рейтинги`, no `openSector` method, etc.

- [ ] **Step 4: Rewrite `SectorsDashboard.php`**

Full new content:

```php
<?php

namespace App\Livewire;

use App\Models\Sector;
use App\Models\SectorTask;
use App\Models\SectorTaskProgress;
use App\Support\SectorDisplay;
use Livewire\Component;

class SectorsDashboard extends Component
{
    public string $search = '';

    /** Code of the sector whose drawer is open, or null. */
    public ?string $open = null;

    /** Drawer status-tab filter. */
    public string $filter = 'all';

    /** @var array<int, bool> expanded multi-line task ids inside the drawer */
    public array $expanded = [];

    public function openSector(string $code): void
    {
        if (! Sector::where('code', $code)->exists()) {
            return;
        }
        $this->open     = $code;
        $this->filter   = 'all';
        $this->expanded = [];
    }

    public function closeSector(): void
    {
        $this->open = null;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'done', 'open', 'in_progress'], true) ? $filter : 'all';
    }

    public function toggleTask(int $taskId): void
    {
        $this->expanded[$taskId] = ! ($this->expanded[$taskId] ?? false);
    }

    public function render()
    {
        $sectors = Sector::orderBy('sort_order')
            ->with(['tasks' => fn ($q) => $q->orderBy('task_no')])
            ->get();

        // Earliest deadline bucket per sector, one aggregate query (deadline_code
        // is normalized on import: q3 | q4 | h2 | year).
        $deadlines = SectorTaskProgress::query()
            ->join('sector_tasks', 'sector_tasks.id', '=', 'sector_task_progress.sector_task_id')
            ->groupBy('sector_tasks.sector_id')
            ->selectRaw("sector_tasks.sector_id, min(case deadline_code when 'q3' then 1 when 'q4' then 2 when 'h2' then 3 else 4 end) as bucket")
            ->pluck('bucket', 'sector_id');

        $bucketLabels = [1 => 'III чорак', 2 => 'IV чорак', 3 => '2-ярим йиллик', 4 => 'Йил якуни'];

        $cards = $sectors->values()->map(function (Sector $s, int $idx) use ($deadlines, $bucketLabels): array {
            $tasks      = $s->tasks;
            $linesTotal = (int) $tasks->sum('lines_total');
            $linesDone  = (int) $tasks->sum('lines_done');
            $hasReport  = $tasks->contains(fn (SectorTask $t) => $t->status !== 'in_progress');

            return [
                'idx'         => $idx + 1,
                'sector'      => $s,
                'logo'        => $s->logoPath(),
                'tasks_total' => $tasks->count(),
                'lines_total' => $linesTotal,
                'lines_done'  => $linesDone,
                'done'        => $tasks->where('status', 'done')->count(),
                'open'        => $tasks->where('status', 'open')->count(),
                'waiting'     => $tasks->where('status', 'in_progress')->count(),
                // Indicator-level completion; null = nothing reported yet.
                'pct'         => $hasReport && $linesTotal > 0 ? $linesDone / $linesTotal * 100 : null,
                'strip'       => $tasks->map(fn (SectorTask $t) => [
                    'no'     => $t->task_no,
                    'status' => $t->status,
                    'pct'    => SectorDisplay::taskPct($t),
                ])->all(),
                'deadline'    => $bucketLabels[$deadlines[$s->id] ?? 4],
            ];
        });

        $needle  = mb_strtolower(trim($this->search));
        $visible = $needle === '' ? $cards : $cards->filter(function (array $c) use ($needle): bool {
            return str_contains(mb_strtolower($c['sector']->cardName().' '.$c['sector']->org_full), $needle);
        })->values();

        $summary = [
            'sectors'     => $cards->count(),
            'tasks'       => (int) $cards->sum('tasks_total'),
            'done'        => (int) $cards->sum('done'),
            'open'        => (int) $cards->sum('open'),
            'waiting'     => (int) $cards->sum('waiting'),
            'lines_total' => (int) $cards->sum('lines_total'),
            'lines_done'  => (int) $cards->sum('lines_done'),
        ];
        $summary['pct'] = $summary['lines_total'] > 0
            ? $summary['lines_done'] / $summary['lines_total'] * 100
            : 0.0;

        $ranked = $cards->filter(fn (array $c) => $c['pct'] !== null)->sortByDesc('pct')->values();

        $drawer = null;
        if ($this->open !== null) {
            $sector = Sector::where('code', $this->open)->first();
            if ($sector) {
                $drawer = SectorDisplay::detailData($sector, $this->filter);
            }
        }

        return view('livewire.sectors-dashboard', [
            'cards'   => $cards,
            'visible' => $visible,
            'summary' => $summary,
            'ranked'  => $ranked,
            'drawer'  => $drawer,
        ]);
    }
}
```

- [ ] **Step 5: Rewrite `sectors-dashboard.blade.php`**

Full new content:

```blade
@php use App\Support\SectorDisplay; @endphp
<div class="sec-shell" wire:keydown.escape.window="closeSector">
    @php
        $allDone   = $summary['tasks'] > 0 && $summary['done'] === $summary['tasks'];
        $ringShown = SectorDisplay::pshow($summary['pct'], $allDone) ?? 0;
        $ringTier  = SectorDisplay::tier($summary['pct']);
        $circ      = 282.7; // 2π × r45
    @endphp

    <header class="sec-top" id="secTop" style="--p:{{ min(1, $summary['pct'] / 120) }}">
        <span class="sec-pline" title="Умумий ижро"></span>
        <div class="sec-tin">
            <a class="sec-back" href="{{ route('home') }}">← Бош саҳифа</a>
            <h1>Тармоқ корхоналари топшириқлари</h1>
            <span class="sp"></span>
            <span class="sec-search">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Излаш…" autocomplete="off">
            </span>
        </div>
    </header>

    <div class="sec-body">
        <aside class="sec-rail">
            <div class="sec-kcard hero rise" id="secHero">
                <div class="kt">Умумий ижро</div>
                <div class="sec-ringwrap">
                    <div class="sec-ring">
                        <svg viewBox="0 0 104 104">
                            <circle class="tr" cx="52" cy="52" r="45"/>
                            <circle class="fg" id="secRingFg" cx="52" cy="52" r="45"
                                    style="stroke:var(--sec-{{ $ringTier }});stroke-dasharray:{{ $circ }};stroke-dashoffset:{{ number_format($circ * (1 - min(1, $summary['pct'] / 100)), 1, '.', '') }}"/>
                        </svg>
                        <div class="cv"><b class="tnum" id="secRingVal" data-p="{{ $ringShown }}">{{ $ringShown }}%</b><span>индикатор</span></div>
                    </div>
                    <div class="sec-hstats">
                        <div>
                            <div class="hv tnum">{{ $summary['lines_done'] }}<small>/{{ $summary['lines_total'] }}</small></div>
                            <div class="hk">Индикатор</div>
                        </div>
                        <div>
                            <div class="hv tnum">{{ $summary['done'] }}<small>/{{ $summary['tasks'] }}</small></div>
                            <div class="hk">Топшириқ</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sec-kcard rise">
                <div class="kt">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg>
                    Ижро рейтинги
                </div>
                <div class="sec-pstrip" title="Барча корхоналар — тартиб бўйича">
                    @foreach($cards as $c)
                        @php $pt = SectorDisplay::tier($c['pct']); @endphp
                        <span role="button" tabindex="0" wire:key="ps-{{ $c['sector']->code }}"
                              wire:click="openSector(@js($c['sector']->code))"
                              style="background:var(--sec-{{ $pt }})"
                              title="{{ $c['sector']->cardName() }}: {{ $c['pct'] === null ? 'кутилмоқда' : SectorDisplay::pshow($c['pct'], $c['tasks_total'] > 0 && $c['done'] === $c['tasks_total']) . '%' }}"></span>
                    @endforeach
                </div>
                @if($ranked->isNotEmpty())
                    <div class="lsec up">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg>
                        Энг юқори
                    </div>
                    @foreach($ranked->take(3) as $k => $r)
                        <div class="sec-lrow" role="button" tabindex="0" wire:key="top-{{ $r['sector']->code }}"
                             wire:click="openSector(@js($r['sector']->code))">
                            <span class="rk tnum">{{ str_pad($k + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="ln">{{ $r['sector']->cardName() }}</span>
                            <span class="lp tnum" style="color:var(--sec-{{ SectorDisplay::tier($r['pct']) }})">{{ SectorDisplay::pshow($r['pct'], $r['tasks_total'] > 0 && $r['done'] === $r['tasks_total']) }}%</span>
                        </div>
                    @endforeach
                    <div class="ldiv"></div>
                    <div class="lsec down">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l6 6 4-4 8 8"/><path d="M15 17h6v-6"/></svg>
                        Энг паст
                    </div>
                    @php $n = $ranked->count(); @endphp
                    @foreach($ranked->slice(max(0, $n - 3))->reverse()->values() as $k => $r)
                        <div class="sec-lrow" role="button" tabindex="0" wire:key="low-{{ $r['sector']->code }}"
                             wire:click="openSector(@js($r['sector']->code))">
                            <span class="rk tnum">{{ str_pad($n - $k, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="ln">{{ $r['sector']->cardName() }}</span>
                            <span class="lp tnum" style="color:var(--sec-{{ SectorDisplay::tier($r['pct']) }})">{{ SectorDisplay::pshow($r['pct'], $r['tasks_total'] > 0 && $r['done'] === $r['tasks_total']) }}%</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </aside>

        <main class="sec-main">
            <div class="sec-grid">
                @forelse($visible as $card)
                    @php
                        $s        = $card['sector'];
                        $tier     = SectorDisplay::tier($card['pct']);
                        $cardDone = $card['tasks_total'] > 0 && $card['done'] === $card['tasks_total'];
                        $shown    = SectorDisplay::pshow($card['pct'], $cardDone);
                        $allWait  = $card['tasks_total'] > 0 && $card['waiting'] === $card['tasks_total'];
                    @endphp
                    <button type="button" class="sec-card {{ $allWait ? 'waitc' : '' }}" data-code="{{ $s->code }}"
                            wire:key="sector-{{ $s->code }}" wire:click="openSector(@js($s->code))"
                            style="--tc:var(--sec-{{ $tier }})">
                        <div class="chd">
                            <span class="cidx tnum">{{ str_pad($card['idx'], 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="sec-logo">
                                @if($card['logo'])
                                    <img src="{{ asset($card['logo']) }}" alt="" loading="lazy">
                                @else
                                    <span class="sec-mono" aria-hidden="true">{{ mb_substr($s->cardName(), 0, 1) }}</span>
                                @endif
                            </span>
                            <span class="cname"><span class="nm {{ mb_strlen($s->cardName()) > 12 ? 'long' : '' }}">{{ $s->cardName() }}</span></span>
                        </div>
                        <div class="cmeasure">
                            <div class="cbig">
                                <div class="cpct tnum {{ $shown === null ? 'na' : '' }}">@if($shown === null)—@else{{ $shown }}<span class="u">%</span>@endif</div>
                            </div>
                            <div class="cstat">
                                <svg class="ic" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 11.5 2.5 2.5L17 8.5"/><path d="M21 12v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10"/></svg>
                                <div class="v tnum">{{ $card['done'] }}<small>/{{ $card['tasks_total'] }}</small></div>
                                <div class="k">Топшириқ</div>
                            </div>
                            <div class="cstat">
                                <svg class="ic" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="6" y1="20" x2="6" y2="15"/><line x1="12" y1="20" x2="12" y2="9"/><line x1="18" y1="20" x2="18" y2="4"/></svg>
                                <div class="v tnum">{{ $card['lines_done'] }}<small>/{{ $card['lines_total'] }}</small></div>
                                <div class="k">Индикатор</div>
                            </div>
                        </div>
                        @if($allWait)
                            <div class="waitnote">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                                Ҳисобот кутилмоқда
                            </div>
                        @else
                            <div class="cstrip-zone">
                                <div class="cstrip">
                                    @foreach($card['strip'] as $t)
                                        @php
                                            $st = $t['status'] === 'done' ? 'ok' : ($t['status'] === 'in_progress' ? 'wait' : SectorDisplay::tier($t['pct']));
                                            $tp = SectorDisplay::pshow($t['pct'], $t['status'] === 'done');
                                        @endphp
                                        <span style="background:var(--sec-{{ $st }})"
                                              title="Т-{{ str_pad($t['no'], 2, '0', STR_PAD_LEFT) }}: {{ $tp === null ? 'кутилмоқда' : $tp . '%' }}"></span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div class="cfoot">
                            <span class="dlx">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/></svg>
                                {{ $card['deadline'] }}
                            </span>
                            <span class="more">Батафсил →</span>
                        </div>
                    </button>
                @empty
                    <div class="sec-empty">Ҳеч нарса топилмади</div>
                @endforelse
            </div>
        </main>
    </div>

    <div class="sec-veil {{ $open ? 'on' : '' }}" wire:click="closeSector"></div>
    <aside class="sec-over {{ $open ? 'on' : '' }}" role="dialog" aria-modal="true"
           @if($drawer) style="border-left-color:var(--sec-{{ SectorDisplay::tier($drawer['agg']['pct']) }})" @endif>
        @if($drawer)
            @include('livewire.partials.sector-tasks', [
                'sector'   => $drawer['sector'],
                'tasks'    => $drawer['tasks'],
                'counts'   => $drawer['counts'],
                'agg'      => $drawer['agg'],
                'filter'   => $filter,
                'expanded' => $expanded,
                'inDrawer' => true,
            ])
        @endif
    </aside>
</div>
```

- [ ] **Step 6: Run the dashboard tests**

Run: `php artisan test --filter=SectorsDashboardPageTest; php artisan test --filter=SectorLogoTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/SectorsDashboard.php resources/views/livewire/sectors-dashboard.blade.php tests/Feature/Sectors/SectorsDashboardPageTest.php tests/Feature/Sectors/SectorLogoTest.php
git commit -m "feat(sectors): e-panel dashboard — rail, zoned cards, Livewire drawer"
```

---

## Task 4: CSS port into portal.css

**Files:**
- Modify: `backend/public/css/portal.css` — delete the old sector blocks, append the new `sec-` section.

- [ ] **Step 1: Delete the old sector CSS**

Delete everything from the line `/* ===== Sectors dashboard (тармоқлар) — standalone national page ===== */` (≈ line 5760) down to and including the `.sdp-empty { ... }` rule (≈ line 5886). **Keep** the `.dp-task.expandable` / `.stask-toggle` / `.stp-*` rules that follow — they belong to the district profile page. Keep `body.sectors-body { display: block; }` semantics — the new section re-declares it.

- [ ] **Step 2: Append the new section at the end of portal.css**

```css
/* ===== Sectors pages (тармоқлар) — «Панель» design, scoped sec- ===== */
body.sectors-body{
  display:block;
  font-family:'Inter','Segoe UI Variable Text','Segoe UI',system-ui,sans-serif;
  font-size:14px;line-height:1.5;color:var(--sec-ink);-webkit-font-smoothing:antialiased;
  background:#f0f3f8 linear-gradient(180deg,#e6edf7 0%,#eef2f8 260px,#f0f3f8 520px) no-repeat;
  --sec-ink:#102033;--sec-muted:#657386;--sec-faint:#8b98a9;
  --sec-line:#dfe7f0;--sec-line-strong:#c5d2e0;
  --sec-blue:#2b61af;--sec-blue-2:#0b4c7a;--sec-blue-soft:#eef6ff;
  --sec-ok:#0b8050;--sec-warn:#bd7a1a;--sec-bad:#8f1f2b;--sec-wait:#6b7684;
  --sec-ok-t:#e7f2ec;--sec-warn-t:#f8efdf;--sec-bad-t:#f6e9ea;--sec-wait-t:#eef1f5;
  --sec-card:#fff;
  --sec-sh-sm:0 1px 2px rgba(15,42,71,.05),0 5px 14px rgba(15,42,71,.06);
  --sec-sh-md:0 6px 18px rgba(15,42,71,.08),0 18px 44px rgba(15,42,71,.08);
  --sec-sh-lg:0 12px 26px rgba(15,42,71,.10),0 24px 60px rgba(15,42,71,.12);
  --sec-ease:cubic-bezier(.2,.8,.2,1);
}
body.sectors-body:has(.sec-over.on){overflow:hidden}
.sectors-body .tnum{font-variant-numeric:tabular-nums}
.sectors-body button{font-family:inherit;cursor:pointer}
.sec-shell :focus-visible{outline:2px solid var(--sec-blue);outline-offset:2px;border-radius:4px}
.sec-shell .rise{opacity:0;transform:translateY(14px);filter:blur(5px);animation:secRise .6s var(--sec-ease) both}
@keyframes secRise{60%{filter:blur(0)}to{opacity:1;transform:none;filter:blur(0)}}

/* ---------- top bar ---------- */
.sec-top{
  position:sticky;top:0;z-index:40;
  background:linear-gradient(135deg,#2b61af 0%,#0b4c7a 100%);
  border-bottom:2px solid rgba(255,255,255,.16);
  box-shadow:0 6px 22px rgba(11,44,84,.22);
}
.sec-pline{
  position:absolute;left:0;bottom:-2px;height:2px;background:#fff;
  width:100%;transform:scaleX(0);transform-origin:left;border-radius:0 2px 2px 0;
  transition:transform 1.1s var(--sec-ease) .5s;z-index:1;
}
.sec-top.on .sec-pline{transform:scaleX(var(--p,0))}
.sec-tin{
  position:relative;max-width:1320px;margin:0 auto;padding:12px 28px;
  display:flex;align-items:center;gap:16px;
}
.sec-back{
  display:inline-flex;align-items:center;gap:6px;text-decoration:none;
  font-size:12.5px;font-weight:600;color:#fff;
  border:1px solid rgba(255,255,255,.34);border-radius:999px;padding:5px 12px;
  background:rgba(255,255,255,.08);white-space:nowrap;
  transition:background .18s,border-color .18s;
}
.sec-back:hover{background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.55)}
.sec-tin h1{font-size:15.5px;font-weight:700;letter-spacing:-.01em;color:#fff;margin:0}
.sec-tin .sp{flex:1}
.sec-search{position:relative;width:230px}
.sec-search svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.65)}
.sec-search input{
  width:100%;font-family:inherit;font-size:13px;color:#fff;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.24);border-radius:9px;
  padding:7px 11px 7px 33px;outline:none;transition:border-color .18s,box-shadow .18s,background .18s;
}
.sec-search input:focus{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.6);box-shadow:0 0 0 3px rgba(255,255,255,.12)}
.sec-search input::placeholder{color:rgba(255,255,255,.62)}

/* ---------- body grid ---------- */
.sec-body{max-width:1320px;margin:0 auto;padding:24px 28px 80px;display:grid;grid-template-columns:282px 1fr;gap:20px;align-items:start}
@media (max-width:1000px){.sec-body{grid-template-columns:1fr}}

/* ---------- rail ---------- */
.sec-rail{display:flex;flex-direction:column;gap:14px;position:sticky;top:78px}
@media (max-width:1000px){.sec-rail{position:static;flex-direction:row;flex-wrap:wrap}.sec-rail>*{flex:1;min-width:220px}}
.sec-kcard{
  background:var(--sec-card);border:1px solid var(--sec-line);border-radius:14px;
  box-shadow:var(--sec-sh-sm);padding:18px;
}
.sec-kcard .kt{
  font-size:11.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--sec-muted);
  margin-bottom:14px;display:flex;align-items:center;gap:8px;
}
.sec-kcard .kt svg{color:var(--sec-faint);flex:0 0 auto}
.sec-kcard.hero{
  background:linear-gradient(150deg,#2b61af 0%,#174a86 55%,#0b4c7a 100%);
  border-color:transparent;box-shadow:0 10px 26px rgba(15,58,110,.28);
  padding:24px 20px;
}
.sec-kcard.hero .kt{margin-bottom:18px;color:rgba(255,255,255,.75)}
.sec-ringwrap{display:flex;align-items:center;gap:16px}
.sec-ring{width:128px;height:128px;flex:0 0 auto;position:relative}
.sec-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.sec-ring .tr{stroke:rgba(255,255,255,.22);stroke-width:8;fill:none}
.sec-ring .fg{stroke-width:8;fill:none;stroke-linecap:round;transition:stroke-dashoffset 1s var(--sec-ease) .3s}
.sec-ring .cv{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.sec-ring .cv b{font-size:30px;font-weight:780;letter-spacing:-.025em;color:#fff}
.sec-ring .cv span{font-size:10px;color:rgba(255,255,255,.65);font-weight:600;text-transform:uppercase;letter-spacing:.06em}
.sec-hstats{min-width:0}
.sec-hstats .hv{font-size:25px;font-weight:780;color:#fff;letter-spacing:-.02em;line-height:1.1}
.sec-hstats .hv small{font-size:14px;color:rgba(255,255,255,.58);font-weight:650;letter-spacing:0}
.sec-hstats .hk{
  font-size:9.5px;font-weight:700;letter-spacing:.13em;text-transform:uppercase;
  color:rgba(255,255,255,.55);margin:3px 0 14px;
}
.sec-hstats > div:last-child .hk{margin-bottom:0}
.sec-pstrip{display:flex;gap:2px;height:10px;margin-bottom:12px}
.sec-pstrip span{flex:1;border-radius:2px;cursor:pointer;transition:transform .15s var(--sec-ease)}
.sec-pstrip span:hover{transform:scaleY(1.45)}
.sec-kcard .lsec{
  display:flex;align-items:center;gap:6px;
  font-size:10px;font-weight:750;letter-spacing:.1em;text-transform:uppercase;color:var(--sec-faint);
  margin:4px 0 2px;
}
.sec-kcard .lsec svg{flex:0 0 auto}
.sec-kcard .lsec.up{color:var(--sec-ok)}
.sec-kcard .lsec.down{color:var(--sec-bad)}
.sec-lrow{
  display:flex;align-items:center;gap:9px;padding:6.5px 8px;margin:0 -8px;
  border-radius:9px;cursor:pointer;transition:background .16s;
}
.sec-lrow:hover{background:#f4f6f9}
.sec-lrow .rk{flex:0 0 20px;font-size:10.5px;font-weight:700;color:var(--sec-faint);text-align:right}
.sec-lrow .ln{flex:1;min-width:0;font-size:12.5px;font-weight:650;color:var(--sec-ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sec-lrow .lp{flex:0 0 auto;font-size:15px;font-weight:780;letter-spacing:-.015em}
.sec-kcard .ldiv{height:1px;background:var(--sec-line);margin:8px 0}

/* ---------- card grid ---------- */
.sec-main{min-width:0}
.sec-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(304px,1fr));gap:16px}
.sec-card{
  background:var(--sec-card);border:1px solid var(--sec-line);border-radius:12px;
  box-shadow:var(--sec-sh-sm);padding:0;cursor:pointer;text-align:left;
  transition:transform .22s var(--sec-ease),box-shadow .22s,border-color .22s;
  display:flex;flex-direction:column;position:relative;overflow:hidden;
}
.sec-card:hover{transform:translateY(-3px);box-shadow:var(--sec-sh-md);border-color:var(--sec-line-strong)}
.sec-card:active{transform:translateY(-1px) scale(.995)}
.sec-card:hover .nm{color:var(--sec-blue-2)}
.sec-card.pre{opacity:0;transform:translateY(16px);filter:blur(4px)}
.sec-card.in{
  opacity:1;transform:none;filter:blur(0);
  transition:opacity .5s var(--sec-ease) var(--d,0ms),transform .5s var(--sec-ease) var(--d,0ms),
    filter .5s var(--sec-ease) var(--d,0ms),box-shadow .22s,border-color .22s;
}
.sec-logo{
  width:50px;height:50px;border:1px solid var(--sec-line);border-radius:11px;
  background:linear-gradient(180deg,#fbfcfe,#f2f5f9);
  display:flex;align-items:center;justify-content:center;overflow:hidden;flex:0 0 auto;
}
.sec-logo img{max-width:38px;max-height:38px;object-fit:contain}
.sec-logo .sec-mono,.sec-mono{font-size:18px;font-weight:700;color:var(--sec-blue)}
.sec-card .chd{display:flex;align-items:center;gap:12px;padding:14px 18px 12px;border-bottom:1px solid var(--sec-line)}
.sec-card .cidx{font-size:11px;font-weight:700;color:var(--sec-faint);letter-spacing:.04em;flex:0 0 auto}
.sec-card .cname{min-width:0;flex:1}
.sec-card .nm{
  font-size:15.5px;font-weight:700;letter-spacing:-.008em;line-height:1.22;color:var(--sec-ink);
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
  overflow-wrap:anywhere;transition:color .18s;
}
.sec-card .nm.long{font-size:13px;line-height:1.3;letter-spacing:0}
.sec-card .cmeasure{display:flex;align-items:center;gap:20px;padding:15px 18px 12px}
.sec-card .cbig{flex:1;min-width:0}
.sec-card .cpct{font-size:44px;font-weight:780;letter-spacing:-.035em;line-height:.95}
.sec-card .cpct:not(.na){color:var(--tc,var(--sec-ink))}
.sec-card .cpct .u{font-size:20px;font-weight:650;color:var(--sec-muted);letter-spacing:0}
.sec-card .cpct.na{font-size:30px;font-weight:700;color:var(--sec-wait)}
.sec-card .cstat{flex:0 0 auto}
.sec-card .cstat .ic{color:var(--sec-faint);display:block;margin-bottom:6px}
.sec-card .cstat .v{font-size:18px;font-weight:750;letter-spacing:-.015em;line-height:1;color:var(--sec-ink)}
.sec-card .cstat .v small{font-size:12.5px;font-weight:650;color:var(--sec-faint)}
.sec-card .cstat .k{font-size:10px;font-weight:650;letter-spacing:.09em;text-transform:uppercase;color:var(--sec-faint);margin-top:4px}
.sec-card.waitc{opacity:.78}
.sec-card.waitc:hover,.sec-card.waitc:focus-visible{opacity:1}
.sec-card .waitnote{
  padding:0 18px 13px;display:flex;align-items:center;gap:7px;
  font-size:12px;font-weight:650;color:var(--sec-wait);
}
.sec-card .waitnote svg{flex:0 0 auto}
.sec-card .cstrip-zone{padding:0 18px 13px}
.sec-card .cstrip{display:flex;gap:2px;height:6px}
.sec-card .cstrip span{flex:1;border-radius:2px}
.sec-card .cstrip span:hover{transform:scaleY(1.5);transition:transform .16s var(--sec-ease)}
.sec-card .cfoot{
  display:flex;justify-content:space-between;align-items:center;margin-top:auto;
  padding:10px 18px 12px;border-top:1px solid var(--sec-line);
  font-size:12.5px;color:var(--sec-muted);background:#fbfcfe;
}
.sec-card .cfoot .dlx{display:inline-flex;align-items:center;gap:7px;font-weight:650}
.sec-card .cfoot .dlx svg{color:var(--sec-faint)}
.sec-card .cfoot .more{
  font-weight:650;color:var(--sec-blue);opacity:0;transform:translateX(-5px);
  transition:opacity .22s var(--sec-ease),transform .22s var(--sec-ease);
}
.sec-card:hover .cfoot .more{opacity:1;transform:none}
.sec-empty{grid-column:1/-1;padding:50px;text-align:center;color:var(--sec-muted)}

/* ---------- drawer ---------- */
.sec-veil{
  position:fixed;inset:0;background:rgba(16,32,51,.34);z-index:60;
  opacity:0;pointer-events:none;transition:opacity .3s;
  backdrop-filter:blur(2px);
}
.sec-veil.on{opacity:1;pointer-events:auto}
.sec-over{
  position:fixed;top:0;right:0;bottom:0;width:min(600px,96vw);z-index:70;
  background:var(--sec-card);box-shadow:var(--sec-sh-lg);
  border-left:3px solid var(--sec-line-strong);
  transform:translateX(103%);transition:transform .38s cubic-bezier(.32,.72,0,1);
  display:flex;flex-direction:column;
}
.sec-over.on{transform:none}
.sec-ohead{padding:20px 26px 0;flex:0 0 auto;background:linear-gradient(180deg,#f2f7fd,#fff 78%)}
.sec-ohead .otop{display:flex;align-items:flex-start;gap:14px}
.sec-ohead .sec-logo.big{width:62px;height:62px;border-radius:13px}
.sec-ohead .sec-logo.big img{max-width:47px;max-height:47px}
.sec-ohead .oid{min-width:0}
.sec-ohead h2{font-size:19px;font-weight:750;letter-spacing:-.015em;line-height:1.2;margin:0}
.sec-ohead .org{font-size:12.5px;color:var(--sec-muted);margin-top:3px}
.sec-ohead .sg{font-size:12px;color:var(--sec-faint);margin-top:2px}
.sec-ohead .x{
  margin-left:auto;flex:0 0 auto;width:34px;height:34px;border-radius:9px;
  border:1px solid var(--sec-line);background:#fff;color:var(--sec-muted);
  display:flex;align-items:center;justify-content:center;
  transition:color .18s,border-color .18s,background .18s;
}
.sec-ohead .x:hover{color:var(--sec-bad);border-color:var(--sec-bad);background:var(--sec-bad-t)}
.sec-ostats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:18px 0 16px}
.sec-ostat{border:1px solid var(--sec-line);border-radius:11px;padding:11px 13px;background:#fff}
.sec-ostat .v{font-size:19px;font-weight:750;letter-spacing:-.02em}
.sec-ostat .k{font-size:10.5px;color:var(--sec-muted);font-weight:600;margin-top:2px;text-transform:uppercase;letter-spacing:.05em}
.sec-ostat.ok .v{color:var(--sec-ok)}
.sec-ostat.bad .v{color:var(--sec-bad)}
.sec-obody{flex:1;min-height:0;padding:0 26px 30px}
.sec-over .sec-obody{overflow-y:auto}
.sec-otabs{display:flex;gap:2px;border-bottom:1px solid var(--sec-line);margin-bottom:6px;position:sticky;top:0;background:var(--sec-card);padding-top:2px;z-index:2}
.sec-otabs button{
  border:0;background:none;font-size:12.5px;font-weight:600;color:var(--sec-muted);
  padding:9px 13px;position:relative;transition:color .18s;
}
.sec-otabs button::after{
  content:"";position:absolute;left:10px;right:10px;bottom:-1px;height:2px;border-radius:1px;
  background:var(--sec-blue);transform:scaleX(0);transition:transform .22s var(--sec-ease);
}
.sec-otabs button.on{color:var(--sec-blue)}
.sec-otabs button.on::after{transform:scaleX(1)}
.sec-otabs .n{color:var(--sec-faint);margin-left:3px;font-size:11.5px}

/* ---------- task rows (drawer + detail page) ---------- */
.sec-task{padding:14px 0;border-top:1px solid var(--sec-line)}
.sec-task:first-child{border-top:0}
.sec-task .thead{display:flex;gap:12px;align-items:flex-start}
.sec-task .tno{flex:0 0 auto;font-size:11px;font-weight:700;color:var(--sec-muted);background:var(--sec-wait-t);border-radius:6px;padding:3px 8px;margin-top:1px;white-space:nowrap}
.sec-task .ttl{flex:1;font-size:13.5px;font-weight:600;line-height:1.45}
.sec-chip{
  flex:0 0 auto;display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;
  padding:4px 10px;border-radius:999px;white-space:nowrap;
}
.sec-chip i{width:6px;height:6px;border-radius:50%}
.sec-chip.ok{background:var(--sec-ok-t);color:var(--sec-ok)}
.sec-chip.ok i{background:var(--sec-ok)}
.sec-chip.bad{background:var(--sec-bad-t);color:var(--sec-bad)}
.sec-chip.bad i{background:var(--sec-bad)}
.sec-chip.wait{background:var(--sec-wait-t);color:var(--sec-wait)}
.sec-chip.wait i{background:var(--sec-wait)}
.sec-task .tfacts{display:flex;gap:20px;align-items:center;margin:9px 0 0 46px;font-size:12.5px;color:var(--sec-muted);flex-wrap:wrap}
.sec-task .tfacts b{color:var(--sec-ink);font-weight:650}
.sec-task .tb{position:relative;width:120px;height:5px;background:var(--sec-wait-t);border-radius:3px}
.sec-task .tb i{position:absolute;inset:0 auto 0 0;border-radius:3px}
.sec-task .tb .tick{position:absolute;top:-2px;bottom:-2px;width:1.5px;background:var(--sec-line-strong);left:83.33%}
.sec-task .tfacts .dl{color:var(--sec-faint)}
.sec-ltog{
  margin:9px 0 0 46px;border:0;background:none;font-size:12px;font-weight:600;color:var(--sec-blue);
  display:inline-flex;align-items:center;gap:6px;padding:2px 0;
}
.sec-ltog .c{transition:transform .25s var(--sec-ease);display:inline-block}
.sec-ltog.open .c{transform:rotate(90deg)}
.sec-lines{margin:10px 0 0 46px;border:1px solid var(--sec-line);border-radius:10px;overflow:hidden}
.sec-line{
  display:grid;grid-template-columns:1fr 170px 58px;gap:12px;align-items:center;
  padding:8px 13px;border-top:1px solid var(--sec-line);font-size:12.5px;
}
.sec-line:first-child{border-top:0}
.sec-line:nth-child(odd){background:#fbfcfd}
.sec-line .lb{color:var(--sec-ink)}
.sec-line .lb .u{color:var(--sec-faint)}
.sec-line.dim .lb{color:var(--sec-faint)}
.sec-line .lv{text-align:right;color:var(--sec-muted);white-space:nowrap}
.sec-line .lv b{color:var(--sec-ink);font-weight:650}
.sec-line .lp{text-align:right;font-weight:700}
.sec-line .lp.na{color:var(--sec-faint);font-weight:600}

/* ---------- detail page wrapper (/sectors/{code}) ---------- */
.sec-detail-wrap{
  max-width:900px;margin:24px auto 80px;padding:0;
  background:#fff;border:1px solid var(--sec-line);border-radius:16px;
  box-shadow:var(--sec-sh-sm);overflow:hidden;
}
@media (max-width:960px){.sec-detail-wrap{margin:16px 12px 60px}}

@media (prefers-reduced-motion: reduce){
  .sec-shell *,.sec-shell *::before,.sec-shell *::after,
  .sec-veil,.sec-over,.sec-pline{animation-duration:.001s!important;transition-duration:.001s!important}
}
```

- [ ] **Step 3: Verify no view references dead classes**

Run from `backend/`:

```bash
grep -rn "sector-card\|sdp-\|sectors-wrap\|dp-hero\|dp-sect\|dp-crumb" resources/views/livewire/sectors-dashboard.blade.php resources/views/livewire/sector-detail.blade.php resources/views/livewire/partials/sector-tasks.blade.php
```

Expected: no output. (`dp-crumb` remains in use on OTHER pages — do not delete its CSS.)

- [ ] **Step 4: Quick render check**

Run: `php artisan test --filter=SectorsDashboardPageTest`
Expected: PASS (CSS changes cannot break tests; this is a regression guard).

- [ ] **Step 5: Commit**

```bash
git add public/css/portal.css
git commit -m "feat(sectors): port e-panel CSS into portal.css, drop old sector styles"
```

---

## Task 5: Presentation-only animation JS in the page shells

**Files:**
- Modify: `backend/resources/views/pages/sectors.blade.php`
- Modify: `backend/resources/views/pages/sector-detail.blade.php` (no JS needed — verify only)

- [ ] **Step 1: Add the inline script to `pages/sectors.blade.php`**

Insert after `@livewireScripts`, before `</body>`:

```html
  <script>
  (() => {
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    // Top-bar progress line draw-in.
    const top = document.getElementById('secTop');
    if (top) setTimeout(() => top.classList.add('on'), 200);
    // Ring draw-in + count-up (server renders the final state; JS replays it once).
    const val = document.getElementById('secRingVal');
    const fg  = document.getElementById('secRingFg');
    if (val && fg && !reduced) {
      const target = +val.dataset.p || 0;
      const finalOffset = fg.style.strokeDashoffset;
      fg.style.transition = 'none';
      fg.style.strokeDashoffset = fg.style.strokeDasharray; // start empty
      requestAnimationFrame(() => requestAnimationFrame(() => {
        fg.style.transition = '';
        fg.style.strokeDashoffset = finalOffset;            // CSS transition draws the arc
      }));
      const t0 = performance.now(), D = 900;
      (function tick(now) {
        const p = Math.min(1, (now - t0) / D), e = 1 - Math.pow(1 - p, 3);
        val.textContent = Math.round(target * e) + '%';
        if (p < 1) requestAnimationFrame(tick); else val.textContent = target + '%';
      })(t0);
    }
    // Card entrance on first load only; Livewire morphs render plainly.
    const cards = [...document.querySelectorAll('.sec-card')];
    if (cards.length && !reduced && 'IntersectionObserver' in window) {
      let batch = 0, lastT = 0, fired = false;
      const io = new IntersectionObserver(entries => {
        fired = true;
        const now = performance.now();
        if (now - lastT > 400) batch = 0;
        lastT = now;
        entries.forEach(e => {
          if (!e.isIntersecting) return;
          const c = e.target, d = Math.min(batch++ * 45, 400);
          c.style.setProperty('--d', d + 'ms');
          c.classList.add('in');
          io.unobserve(c);
          setTimeout(() => { c.classList.remove('pre', 'in'); c.style.removeProperty('--d'); }, d + 650);
        });
      }, { threshold: .12, rootMargin: '0px 0px -4% 0px' });
      cards.forEach(c => { c.classList.add('pre'); io.observe(c); });
      // Safety: if IO never fires, reveal everything.
      setTimeout(() => { if (!fired) { io.disconnect(); cards.forEach(c => c.classList.remove('pre', 'in')); } }, 900);
    }
  })();
  </script>
```

- [ ] **Step 2: Verify the detail page shell needs no change**

`pages/sector-detail.blade.php` keeps its current shell (`sectors-body` class, Inter, portal.css) — the detail top bar renders `class="sec-top on"` server-side, so no JS. Just confirm the file still matches Task 2's assumptions (no edit needed).

- [ ] **Step 3: Run the sector suite**

Run: `php artisan test --filter=Sectors`
Expected: PASS (all sector tests).

- [ ] **Step 4: Commit**

```bash
git add resources/views/pages/sectors.blade.php
git commit -m "feat(sectors): entrance animations — pline, ring count-up, card reveal"
```

---

## Task 6: Full verification + visual smoke

- [ ] **Step 1: Run the full test suite**

Run from `backend/`: `php artisan test`
Expected: green (~335+ tests, ~10 min). If unrelated failures appear, investigate before proceeding — the suite is expected green.

- [ ] **Step 2: Visual smoke check**

```powershell
cd backend; php artisan serve
```

Open `http://127.0.0.1:8000/sectors` and verify: dark top bar + progress line draws; ring counts up; rating strip + top/bottom-3; cards rise on scroll; search narrows (300 ms debounce); card click slides the drawer in; Esc and veil close it; tabs + «Индикаторлар (n)» toggles work; `/sectors/uzbekneftgaz` deep link renders the same panel design full-page. Check a narrow window (<1000px): rail wraps above the grid, drawer takes 96vw.

- [ ] **Step 3: Update CLAUDE.md route table line**

In the root `CLAUDE.md`, extend the `/sectors` row's purpose text: card click opens a Livewire slide-over drawer; `/sectors/{code}` stays as the deep-linkable detail page sharing the same partial.

- [ ] **Step 4: Final commit**

```bash
git add ../CLAUDE.md
git commit -m "docs: sectors e-panel drawer noted in CLAUDE.md"
```

---

## Self-review notes (already applied)

- Spec's «keyword match on deadline_text» superseded: `deadline_code` already exists on import — the plan uses it (spec amended).
- Global `.num`/`.chip`/`.task` collisions verified in portal.css — markup uses `tnum`/`sec-chip`/`sec-task` everywhere.
- `setFilter`/`toggleTask` names are identical in `SectorsDashboard` and `SectorDetail`, so the shared partial binds in both; the drawer-only `closeSector` button renders only when `$inDrawer`.
- SectorLogoTest / SectorsDrilldownTest / SectorsExpandLinesTest updates are inside Tasks 2–3; no other test file references sector markup classes.
- `pages/sector-detail.blade.php` needs no shell change; `pages/sectors.blade.php` changes only by the appended script.
