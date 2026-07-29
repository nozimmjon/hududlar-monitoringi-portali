# Sectors Dashboard (тармоқлар) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** National `/sectors` page: 17 enterprise cards (compact grid) + profile-style task drilldown with expandable indicator lines, entered from the home page.

**Architecture:** One Livewire component (`SectorsDashboard`) on a standalone page (no region sidebar — portal.css body grid overridden via a body class). Overview cards aggregate `sector_tasks` snapshots; drilldown reuses the district profile's `dp-task` visual language verbatim; indicator lines come from `sector_task_progress` (latest period per task). New CSS is appended by hand to `public/css/portal.css` (hand-maintained, NO build step).

**Tech Stack:** Laravel 12, Livewire 3, Pest 3, PostgreSQL, portal.css.

**Spec:** `docs/superpowers/specs/2026-07-29-sectors-dashboard-design.md`

**Test-run note:** create a session-unique test DB once:
`PGPASSWORD=123 psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE hm_test_secui"` — then prefix every test run with `DB_DATABASE=hm_test_secui` (bash). Drop it when the plan completes.

**Grounding facts (verified):**
- portal.css `body` is a 2-column grid (`--nav-w` sidebar) — the standalone page must set `body.sectors-body { display: block; }`.
- Reusable portal.css classes (all confirmed present): `.dp-crumb`, `.dp-hero`, `.dp-hero-facts`, `.dp-fact`, `.dp-sect`, `.dp-tasks`, `.dp-task`, `.dp-task-top`, `.dp-task-title`, `.dp-task-meta`, `.task-strip` (+`.cell/.clab/.val`), `.task-foot`, `.task-foot-cap`, `.progress` (+`i` with `--w`/`--c`), `.chip.green/.amber/.violet/.grey`, vars `--task-green/--task-amber/--task-red/--grey`.
- Profile task-card PHP rules to copy exactly: `backend/resources/views/livewire/region-profile.blade.php:52-89` (pct/chip/tier/fmt/unitLabel logic).
- Data (phase 1): `Sector` (17, `sort_order` 1–17, `code`, `name_short`, `org_full`, `signer_text`), `SectorTask` (snapshot fields `status/lines_total/lines_done/latest_period/headline_*`, relation `progress()`), `SectorTaskProgress` (`line_no/metric_label/unit/plan_value/actual_value/pct_of_plan/report_period`). Fixture helper `Tests\Helpers\SectorWorkbookBuilder` + `import:sector-tasks --file=… --period=…` command (idempotent).
- `$this->seed()` seeds sectors (SectorSeeder is in DatabaseSeeder).
- Home page `backend/resources/views/pages/home.blade.php` is standalone HTML with its own inline CSS; header block is `<header class="top">…</header>` (line ~177).

---

### Task 1: Route, standalone page, `SectorsDashboard` overview (hero + summary + 17 cards)

**Files:**
- Modify: `backend/routes/web.php`
- Create: `backend/resources/views/pages/sectors.blade.php`
- Create: `backend/app/Livewire/SectorsDashboard.php`
- Create: `backend/resources/views/livewire/sectors-dashboard.blade.php`
- Modify: `backend/public/css/portal.css` (append section)
- Test: `backend/tests/Feature/Sectors/SectorsDashboardPageTest.php`

- [ ] **Step 1: Write the failing tests**

`backend/tests/Feature/Sectors/SectorsDashboardPageTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

test('/sectors renders all 17 sector cards in sheet order', function () {
    $this->seed();

    $response = $this->get('/sectors');

    $response->assertOk();
    $response->assertSeeInOrder([
        'Ўзбекнефтгаз', 'Ўзбекгидроэнерго', 'ИЭС', 'Кимё саноати', 'НКМК',
        'Навоийуран', 'Олмалиқ КМК', 'Ўзметкомбинат', 'ТМК', 'Ўзавтосаноат',
        'Ўзэлтехсаноат', 'Енгил саноат', 'Ўзтўқимачиликсаноат', 'Ўзчармсаноат',
        'Қурилиш материаллари', 'Фармацевтика', 'Ўзбекзаргарсаноати',
    ]);
    $response->assertSee('Тармоқ корхоналари топшириқлари');
});

test('sectors without any reported actuals show the waiting state', function () {
    $this->seed();
    // Plans-only import: statuses stay in_progress → card is dimmed, no percent.
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, null, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);

    $response = $this->get('/sectors');

    $response->assertOk();
    $response->assertSee('Маълумот кутилмоқда');
});

test('summary strip counts tasks by status across all sectors', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, 120, null], // done
            [2, 2, 'В2.', 'Кўрсаткич Б', 'та', '2026 йил якуни', 100, 55, null],  // open
            [3, 3, 'В3.', 'Кўрсаткич В', 'та', '2026 йил якуни', 100, null, null], // in_progress
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);

    $html = $this->get('/sectors')->getContent();

    // dp-fact summary: 17 корхона, 3 топшириқ, 1 бажарилди, 1 бажарилмаган, 1 кутилмоқда
    expect($html)->toContain('17');
    expect($html)->toContain('дан 1 таси бажарилди'); // done count phrasing (see blade)
});

test('a sector card shows counts and indicator-level percent', function () {
    $this->seed();
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'В1.', 'Кўрсаткич А', 'та', '2026 йил якуни', 100, 120, null], // done line
            [2, 2, 'В2.', 'Кўрсаткич Б', 'та', '2026 йил якуни', 100, 55, null],  // open line
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);

    $html = $this->get('/sectors')->getContent();

    // lines_done/lines_total = 1/2 → 50%
    expect($html)->toContain('50%');
    expect($html)->toContain('2 топшириқ');
});
```

- [ ] **Step 2: Run tests, verify FAIL**

Run: `cd "/c/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/backend" && DB_DATABASE=hm_test_secui php artisan test --filter=SectorsDashboardPageTest`
Expected: FAIL — 404 for `/sectors`.

- [ ] **Step 3: Implement**

`backend/routes/web.php` — add after the `/execution` line:

```php
Route::view('/sectors', 'pages.sectors')->name('sectors');
```

`backend/resources/views/pages/sectors.blade.php` (standalone, NO layouts.app — national page without the region sidebar):

```blade
<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Тармоқ корхоналари топшириқлари</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css?v={{ filemtime(public_path('css/portal.css')) }}">
  <style> a { text-decoration: none; color: inherit; } </style>
  @livewireStyles
</head>
<body class="sectors-body">
  <livewire:sectors-dashboard />
  @livewireScripts
</body>
</html>
```

`backend/app/Livewire/SectorsDashboard.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Sector;
use App\Models\SectorTask;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class SectorsDashboard extends Component
{
    /** Selected sector code (deep-linkable: /sectors?sector=nkmk). */
    #[Url(as: 'sector')]
    public ?string $sector = null;

    /** @var array<int, bool> expanded multi-line task ids in the drilldown */
    public array $expanded = [];

    public function selectSector(string $code): void
    {
        $this->sector = $this->sector === $code ? null : $code;
        $this->expanded = [];
    }

    public function toggleTask(int $taskId): void
    {
        $this->expanded[$taskId] = ! ($this->expanded[$taskId] ?? false);
    }

    public function render()
    {
        $sectors = Sector::orderBy('sort_order')->with('tasks')->get();

        $cards = $sectors->map(function (Sector $s): array {
            $tasks = $s->tasks;
            $linesTotal = (int) $tasks->sum('lines_total');
            $linesDone  = (int) $tasks->sum('lines_done');
            $hasReport  = $tasks->contains(fn (SectorTask $t) => $t->status !== 'in_progress');

            return [
                'sector'      => $s,
                'tasks_total' => $tasks->count(),
                'lines_total' => $linesTotal,
                'done'        => $tasks->where('status', 'done')->count(),
                'open'        => $tasks->where('status', 'open')->count(),
                'waiting'     => $tasks->where('status', 'in_progress')->count(),
                // Indicator-level completion; null = nothing reported yet.
                'pct'         => $hasReport && $linesTotal > 0 ? $linesDone / $linesTotal * 100 : null,
            ];
        });

        $selected = $this->sector !== null
            ? $sectors->firstWhere('code', $this->sector)
            : null;

        $selectedTasks = $selected
            ? SectorTask::where('sector_id', $selected->id)
                ->orderBy('task_no')
                ->with('progress')
                ->get()
            : new Collection();

        return view('livewire.sectors-dashboard', [
            'cards'         => $cards,
            'selected'      => $selected,
            'selectedTasks' => $selectedTasks,
            'summary'       => [
                'sectors' => $cards->count(),
                'tasks'   => $cards->sum('tasks_total'),
                'done'    => $cards->sum('done'),
                'open'    => $cards->sum('open'),
                'waiting' => $cards->sum('waiting'),
            ],
        ]);
    }
}
```

`backend/resources/views/livewire/sectors-dashboard.blade.php` — Task 1 scope renders hero + summary + card grid only; the drilldown `@if($selected)` block is added in Task 2 (leave the placeholder comment):

```blade
<div class="sectors-wrap">
    <div class="dp-crumb"><a href="{{ route('home') }}">← Бош саҳифа</a></div>

    <div class="dp-hero">
        <div>
            <div class="dp-hero-eyebrow">Кафолат хатлари · 2026 йил 2-ярим йиллик</div>
            <h1>Тармоқ корхоналари топшириқлари</h1>
        </div>
        <div class="dp-hero-facts">
            <div class="dp-fact"><div class="v">{{ $summary['sectors'] }}</div><div class="k">корхона</div></div>
            <div class="dp-fact">
                <div class="v">{{ $summary['tasks'] }}</div>
                <div class="k">топшириқдан {{ $summary['done'] }} таси бажарилди</div>
            </div>
            <div class="dp-fact">
                <div class="v"><span class="warn">{{ $summary['open'] }}</span>/{{ $summary['waiting'] }}</div>
                <div class="k">бажарилмаган / кутилмоқда</div>
            </div>
        </div>
    </div>

    <div class="dp-sect">
        <h2>Корхоналар</h2>
        <span class="dp-sect-hint">Корхонани босинг — топшириқлари пастда очилади</span>
    </div>

    <div class="sector-grid">
        @foreach($cards as $card)
            @php
                $s = $card['sector'];
                $pct = $card['pct'];
                $pctShown = $pct === null ? null : (int) round($pct);
                $tierVar = $pct === null ? '--grey'
                    : ($pct >= 100 ? '--task-green' : ($pct >= 50 ? '--task-amber' : '--task-red'));
                $isActive = $selected && $selected->code === $s->code;
            @endphp
            <div class="sector-card {{ $isActive ? 'active' : '' }} {{ $pct === null ? 'nodata' : '' }}"
                 role="button" tabindex="0" wire:key="sector-{{ $s->code }}"
                 wire:click="selectSector('{{ $s->code }}')">
                <div class="sector-card-name">{{ $s->name_short }}</div>
                <div class="sector-card-sub">{{ $card['tasks_total'] }} топшириқ · {{ $card['lines_total'] }} индикатор</div>
                <div class="progress"><i style="--w:{{ $pct === null ? 0 : max(0, min(100, $pct)) }}%;--c:var({{ $tierVar }})"></i></div>
                <div class="sector-card-foot">
                    @if($pct === null)
                        <span class="sector-card-wait">Маълумот кутилмоқда</span>
                    @else
                        <span class="sector-card-pct">{{ $pctShown }}%</span>
                        <span class="sector-card-counts">
                            <b class="ok">{{ $card['done'] }}</b> ·
                            <b class="bad">{{ $card['open'] }}</b> ·
                            <b class="wait">{{ $card['waiting'] }}</b>
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Drilldown (Task 2) --}}
</div>
```

Append to `backend/public/css/portal.css` (at end of file):

```css
/* ===== Sectors dashboard (тармоқлар) — standalone national page ===== */
body.sectors-body { display: block; }
.sectors-wrap { max-width: 1240px; margin: 0 auto; padding: 22px clamp(16px, 3vw, 40px) 60px; }
.sector-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(215px, 1fr));
  gap: 10px; margin-top: 2px;
}
.sector-card {
  background: #fff; border: 1px solid var(--line); border-radius: 14px;
  box-shadow: var(--shadow-sm); padding: 13px 14px; cursor: pointer;
  transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}
.sector-card:hover { border-color: var(--line-strong); transform: translateY(-1px); }
.sector-card.active { border-color: var(--blue); box-shadow: 0 0 0 2px rgba(43, 97, 175, .18), var(--shadow-sm); }
.sector-card.nodata { opacity: .62; }
.sector-card:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
.sector-card-name { font-size: 13.5px; font-weight: 800; letter-spacing: -.01em; }
.sector-card-sub { font-size: 11.5px; color: var(--muted); margin: 2px 0 9px; }
.sector-card .progress { margin-top: 0; }
.sector-card-foot { display: flex; justify-content: space-between; align-items: baseline; margin-top: 8px; }
.sector-card-pct { font-size: 16px; font-weight: 900; font-variant-numeric: tabular-nums; }
.sector-card-counts { font-size: 11.5px; color: var(--muted); }
.sector-card-counts .ok { color: var(--task-green); }
.sector-card-counts .bad { color: var(--task-amber); }
.sector-card-counts .wait { color: var(--grey); }
.sector-card-wait { font-size: 11.5px; font-weight: 700; color: var(--grey); }
```

- [ ] **Step 4: Run tests, verify PASS**

Run: `cd "/c/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/backend" && DB_DATABASE=hm_test_secui php artisan test --filter=SectorsDashboardPageTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/routes/web.php backend/resources/views/pages/sectors.blade.php backend/app/Livewire/SectorsDashboard.php backend/resources/views/livewire/sectors-dashboard.blade.php backend/public/css/portal.css backend/tests/Feature/Sectors/SectorsDashboardPageTest.php
git commit -m "feat(sectors): /sectors overview — hero, summary strip, 17 sector cards"
```

---

### Task 2: Drilldown — profile-style task cards

**Files:**
- Modify: `backend/resources/views/livewire/sectors-dashboard.blade.php` (replace the `{{-- Drilldown (Task 2) --}}` placeholder)
- Modify: `backend/public/css/portal.css` (append)
- Test: `backend/tests/Feature/Sectors/SectorsDrilldownTest.php`

- [ ] **Step 1: Write the failing tests**

`backend/tests/Feature/Sectors/SectorsDrilldownTest.php`:

```php
<?php

use App\Livewire\SectorsDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

function importSectorsUiFixture(): void
{
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            // Single-line task, done (104%): РЕЖА/АМАЛДА strip
            [1, 1, 'Товар маҳсулот ҳажмини етказиш.', 'Товар маҳсулот ҳажми', 'трлн сўм', '2026 йил якуни', 56.7, 58.9, null],
            // Multi-line task, open: ИНДИКАТОРЛАР/БАЖАРИЛДИ strip
            [2, 2, 'Углеводород қазиб чиқариш.', 'Табиий газ', 'млрд куб метр', '2026 йил якуни', 24.7, 13.2, null],
            [null, 3, null, 'Суюқ углеводородлар', 'минг тонна', '2026 йил якуни', 1188, 1210, null],
            // In-progress task (nothing reported)
            [3, 4, 'Инвестиция дастури.', 'Жами инвестициялар', 'млн доллар', '2026 йил якуни', 1232.1, null, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => '2026-07']);
}

test('selecting a sector shows its task cards with status chips', function () {
    $this->seed();
    importSectorsUiFixture();

    Livewire::test(SectorsDashboard::class)
        ->call('selectSector', 'uzbekneftgaz')
        ->assertSee('«Ўзбекнефтгаз» АЖ')
        ->assertSee('Товар маҳсулот ҳажмини етказиш.')
        ->assertSee('Бажарилди')       // done chip
        ->assertSee('Бажарилмаган')    // open chip (weakest link: газ 53%)
        ->assertSee('Бажарилмоқда')    // in_progress chip
        ->assertSee('Муддат:')
        ->assertSee('2026 йил якуни');
});

test('single-line task shows РЕЖА/АМАЛДА, multi-line shows indicator counts', function () {
    $this->seed();
    importSectorsUiFixture();

    Livewire::test(SectorsDashboard::class)
        ->call('selectSector', 'uzbekneftgaz')
        ->assertSee('Режа')
        ->assertSee('Амалда')
        ->assertSee('Индикаторлар')
        ->assertSee('Бажарилиш');
});

test('selecting the same sector again closes the drilldown', function () {
    $this->seed();
    importSectorsUiFixture();

    Livewire::test(SectorsDashboard::class)
        ->call('selectSector', 'uzbekneftgaz')
        ->assertSet('sector', 'uzbekneftgaz')
        ->call('selectSector', 'uzbekneftgaz')
        ->assertSet('sector', null);
});

test('sector deep link opens the drilldown from the URL', function () {
    $this->seed();
    importSectorsUiFixture();

    Livewire::withQueryParams(['sector' => 'uzbekneftgaz'])
        ->test(SectorsDashboard::class)
        ->assertSee('«Ўзбекнефтгаз» АЖ');
});
```

- [ ] **Step 2: Run tests, verify FAIL**

Run: `cd "/c/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/backend" && DB_DATABASE=hm_test_secui php artisan test --filter=SectorsDrilldownTest`
Expected: FAIL — org name / chips not rendered (placeholder comment only).

- [ ] **Step 3: Implement**

Replace `{{-- Drilldown (Task 2) --}}` in `sectors-dashboard.blade.php` with (pct/chip/tier rules copied from `region-profile.blade.php:52-89`; indicator sub-rows land in Task 3 — keep that inner placeholder):

```blade
    @if($selected)
        <div class="dp-sect" id="sector-detail">
            <h2>{{ $selected->org_full }} — {{ $selectedTasks->count() }} топшириқ</h2>
            @if($selected->signer_text)
                <span class="dp-sect-hint">{{ $selected->signer_text }} имзолаган кафолат хати</span>
            @endif
        </div>

        <div class="dp-tasks">
            @foreach($selectedTasks as $task)
                @php
                    $isMulti = (int) $task->lines_total > 1;
                    $pct = $task->status === 'in_progress' ? null
                        : ($isMulti
                            ? ($task->lines_total > 0 ? $task->lines_done / $task->lines_total * 100 : null)
                            : ($task->headline_pct !== null ? (float) $task->headline_pct : null));
                    $isDone = $task->status === 'done';
                    $pctShown = $pct === null ? null : ($isDone ? (int) round($pct) : min(99, (int) round($pct)));
                    $tierVar = $pct === null ? '--grey'
                        : ($isDone ? '--task-green' : ($pctShown >= 50 ? '--task-amber' : '--task-red'));
                    $chip = $isDone ? ['green', 'Бажарилди']
                        : ($task->status === 'in_progress' ? ['violet', 'Бажарилмоқда'] : ['amber', 'Бажарилмаган']);
                    $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
                    $unitLabel = \App\Support\DashboardCatalog::unitLabel($task->headline_unit);
                    $lines = $task->progress
                        ->where('report_period', $task->latest_period)
                        ->sortBy('line_no')
                        ->values();
                    $headLine = $lines->first();
                @endphp
                <div class="dp-task {{ $isMulti ? 'expandable' : '' }} {{ ($expanded[$task->id] ?? false) ? 'open' : '' }}"
                     wire:key="stask-{{ $task->id }}"
                     @if($isMulti) wire:click="toggleTask({{ $task->id }})" role="button" tabindex="0" @endif>
                    <div class="dp-task-top">
                        <div class="dp-task-title">{{ $task->task_no }}. {{ $task->title }}</div>
                        <span class="chip {{ $chip[0] }}">{{ $chip[1] }}</span>
                    </div>
                    <div class="task-strip">
                        @if($isMulti)
                            <div class="cell"><span class="clab">Индикаторлар</span><span class="val">{{ $task->lines_total }}<small>та</small></span></div>
                            <div class="cell"><span class="clab">Бажарилди</span><span class="val">{{ $task->lines_done }}<small>та</small></span></div>
                        @else
                            <div class="cell"><span class="clab">Режа</span><span class="val">{{ $fmt($task->headline_plan) }}<small>{{ $unitLabel }}</small></span></div>
                            <div class="cell"><span class="clab">Амалда</span><span class="val">{{ $fmt($task->headline_actual) }}<small>{{ $task->headline_actual !== null ? $unitLabel : '' }}</small></span></div>
                        @endif
                        <div class="cell"><span class="clab">Бажарилиш</span><span class="val">{{ $pctShown === null ? '—' : $pctShown . '%' }}</span></div>
                    </div>
                    <div class="task-foot">
                        <div class="progress"><i style="--w:{{ $pct === null ? 0 : max(0, min(100, $pct)) }}%;--c:var({{ $tierVar }})"></i></div>
                        @if($task->latest_period)<span class="task-foot-cap">ҳолат: {{ $task->latest_period }}</span>@endif
                    </div>
                    {{-- Indicator lines (Task 3) --}}
                    <div class="dp-task-meta">
                        Муддат: <b>{{ $headLine?->deadline_text ?? '—' }}</b>
                        @if($task->status === 'in_progress') · маълумот кутилмоқда @endif
                        @if($isMulti)<span class="stask-toggle">{{ ($expanded[$task->id] ?? false) ? '▴ ёпиш' : '▾ индикаторлар' }}</span>@endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
```

Append to `portal.css`:

```css
.dp-task.expandable { cursor: pointer; }
.dp-task.expandable:hover { border-color: var(--line-strong); }
.dp-task.open { border-color: var(--blue); }
.stask-toggle { float: right; font-weight: 800; color: var(--blue); }
```

- [ ] **Step 4: Run tests, verify PASS** (drilldown tests + Task 1 page tests):

Run: `cd "/c/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/backend" && DB_DATABASE=hm_test_secui php artisan test --filter="SectorsDashboardPageTest|SectorsDrilldownTest"`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/resources/views/livewire/sectors-dashboard.blade.php backend/public/css/portal.css backend/tests/Feature/Sectors/SectorsDrilldownTest.php
git commit -m "feat(sectors): drilldown with profile-style task cards"
```

---

### Task 3: Expandable indicator lines on multi-line tasks

**Files:**
- Modify: `backend/resources/views/livewire/sectors-dashboard.blade.php` (replace `{{-- Indicator lines (Task 3) --}}`)
- Modify: `backend/public/css/portal.css` (append)
- Test: `backend/tests/Feature/Sectors/SectorsExpandLinesTest.php`

- [ ] **Step 1: Write the failing tests**

`backend/tests/Feature/Sectors/SectorsExpandLinesTest.php`:

```php
<?php

use App\Livewire\SectorsDashboard;
use App\Models\SectorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Helpers\SectorWorkbookBuilder;

uses(RefreshDatabase::class);

function importExpandFixture(string $period, float $gasActual): void
{
    $file = SectorWorkbookBuilder::make([
        ['1. Ўзбекнефтгаз', 'орг', [
            [1, 1, 'Углеводород қазиб чиқариш.', 'Табиий газ', 'млрд куб метр', '2026 йил якуни', 24.7, $gasActual, null],
            [null, 2, null, 'Суюқ углеводородлар', 'минг тонна', '2026 йил якуни', 1188, null, null],
        ]],
    ]);
    Artisan::call('import:sector-tasks', ['--file' => $file, '--period' => $period]);
}

test('expanding a multi-line task reveals its indicator rows', function () {
    $this->seed();
    importExpandFixture('2026-07', 13.2);
    $task = SectorTask::where('task_no', 1)->firstOrFail();

    Livewire::test(SectorsDashboard::class)
        ->call('selectSector', 'uzbekneftgaz')
        ->assertDontSee('Суюқ углеводородлар')
        ->call('toggleTask', $task->id)
        ->assertSee('Табиий газ')
        ->assertSee('Суюқ углеводородлар')
        ->call('toggleTask', $task->id)
        ->assertDontSee('Суюқ углеводородлар');
});

test('expanded rows show only the latest period values', function () {
    $this->seed();
    importExpandFixture('2026-07', 10.5);
    importExpandFixture('2026-08', 13.2); // later period wins
    $task = SectorTask::where('task_no', 1)->firstOrFail();

    Livewire::test(SectorsDashboard::class)
        ->call('selectSector', 'uzbekneftgaz')
        ->call('toggleTask', $task->id)
        ->assertSee('13,2')       // latest actual, formatted with comma decimal
        ->assertDontSee('10,5');  // older period's actual must not render
});

test('rows without actuals are shown dimmed with a dash', function () {
    $this->seed();
    importExpandFixture('2026-07', 13.2);
    $task = SectorTask::where('task_no', 1)->firstOrFail();

    $component = Livewire::test(SectorsDashboard::class)
        ->call('selectSector', 'uzbekneftgaz')
        ->call('toggleTask', $task->id);

    $component->assertSee('Суюқ углеводородлар');
    $component->assertSeeHtml('stp-line dim');
});
```

- [ ] **Step 2: Run tests, verify FAIL**

Run: `cd "/c/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/backend" && DB_DATABASE=hm_test_secui php artisan test --filter=SectorsExpandLinesTest`
Expected: FAIL — indicator labels never rendered.

- [ ] **Step 3: Implement**

Replace `{{-- Indicator lines (Task 3) --}}` in the blade with:

```blade
                    @if($isMulti && ($expanded[$task->id] ?? false))
                        <div class="stp-lines" onclick="event.stopPropagation()">
                            @foreach($lines as $line)
                                @php
                                    $lineUnit = \App\Support\DashboardCatalog::unitLabel($line->unit);
                                    $linePct = $line->pct_of_plan !== null ? (int) round((float) $line->pct_of_plan) : null;
                                    $lineTier = $linePct === null ? '--grey'
                                        : ($linePct >= 100 ? '--task-green' : ($linePct >= 50 ? '--task-amber' : '--task-red'));
                                @endphp
                                <div class="stp-line {{ $line->actual_value === null ? 'dim' : '' }}" wire:key="stpl-{{ $line->id }}">
                                    <span class="stp-line-label">{{ $line->metric_label }}</span>
                                    <span class="stp-line-vals">
                                        <b>{{ $fmt($line->actual_value) }}</b> / {{ $fmt($line->plan_value) }} <small>{{ $lineUnit }}</small>
                                        <span class="stp-line-pct" style="color:var({{ $lineTier }})">{{ $linePct === null ? '—' : $linePct . '%' }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
```

Append to `portal.css`:

```css
.stp-lines { border-top: 1px dashed var(--line-strong); margin: 4px 0 10px; padding-top: 6px; }
.stp-line { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; font-size: 12.5px; }
.stp-line + .stp-line { border-top: 1px solid var(--line); }
.stp-line.dim { color: var(--muted); }
.stp-line-label { min-width: 0; }
.stp-line-vals { white-space: nowrap; font-variant-numeric: tabular-nums; }
.stp-line-vals small { color: var(--muted); }
.stp-line-pct { font-weight: 800; margin-left: 8px; }
```

Note: `$fmt` and `$lines` already exist in the surrounding `@php` block from Task 2 — do not redeclare.

- [ ] **Step 4: Run tests, verify PASS** (all three sector UI files):

Run: `cd "/c/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/backend" && DB_DATABASE=hm_test_secui php artisan test --filter="SectorsDashboardPageTest|SectorsDrilldownTest|SectorsExpandLinesTest"`
Expected: PASS (11 tests).

- [ ] **Step 5: Commit**

```bash
git add backend/resources/views/livewire/sectors-dashboard.blade.php backend/public/css/portal.css backend/tests/Feature/Sectors/SectorsExpandLinesTest.php
git commit -m "feat(sectors): expandable indicator lines in the task drilldown"
```

---

### Task 4: Home-page entry link

**Files:**
- Modify: `backend/resources/views/pages/home.blade.php`
- Test: `backend/tests/Feature/Sectors/HomeSectorsLinkTest.php`

- [ ] **Step 1: Write the failing test**

`backend/tests/Feature/Sectors/HomeSectorsLinkTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home page links to the sectors dashboard', function () {
    $this->seed();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('href="' . route('sectors') . '"', escape: false);
    $response->assertSee('Тармоқ корхоналари');
});
```

- [ ] **Step 2: Run test, verify FAIL**

Run: `cd "/c/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/backend" && DB_DATABASE=hm_test_secui php artisan test --filter=HomeSectorsLinkTest`
Expected: FAIL — link absent.

- [ ] **Step 3: Implement**

In `backend/resources/views/pages/home.blade.php`, header block (`<header class="top">…</header>`, ~line 177) — add the link as the header's right-side element:

```blade
<header class="top">
  <span class="wordmark"><span class="logo-chip"><img src="/logo.svg" alt="CERR"></span> Ҳудудлар мониторинги платформаси</span>
  <a class="sectors-link" href="{{ route('sectors') }}">Тармоқ корхоналари →</a>
</header>
```

And add to the page's inline `<style>` block (next to `.filter button` styles, matching the landing's visual language):

```css
.sectors-link {
  background: var(--card); border: 1px solid var(--line); border-radius: 999px;
  box-shadow: var(--shadow-s); padding: 11px 20px;
  font-size: 13.5px; font-weight: 800; color: var(--ink);
  transition: border-color 180ms var(--ease), box-shadow 180ms var(--ease), transform 180ms var(--ease);
}
.sectors-link:hover { border-color: var(--accent); transform: translateY(-1px); box-shadow: var(--shadow-m); }
.sectors-link:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
```

- [ ] **Step 4: Run test, verify PASS**

Run: `cd "/c/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/backend" && DB_DATABASE=hm_test_secui php artisan test --filter=HomeSectorsLinkTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/resources/views/pages/home.blade.php backend/tests/Feature/Sectors/HomeSectorsLinkTest.php
git commit -m "feat(sectors): home page entry link to the sectors dashboard"
```

---

### Task 5: Full suite + live smoke check

**Files:** none new (verification only; CLAUDE.md already documents the pipeline).

- [ ] **Step 1: Full test suite** (timeout 600000ms):

Run: `cd "/c/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/backend" && DB_DATABASE=hm_test_secui php artisan test 2>&1 | tail -5`
Expected: all green (~468). Fix anything broken before proceeding.

- [ ] **Step 2: Live smoke on the dev DB** (real imported data: 119 tasks, all in_progress):

```bash
cd "/c/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/backend" && php artisan serve --port=8010 &
sleep 2
curl -s http://127.0.0.1:8010/sectors | grep -o "Ўзбекнефтгаз\|Маълумот кутилмоқда\|Тармоқ корхоналари топшириқлари" | sort | uniq -c
curl -s http://127.0.0.1:8010/ | grep -c "sectors"
```

Expected: all three strings present on `/sectors` (17 cards dimmed with «Маълумот кутилмоқда» — real current state); home page contains the sectors link. Kill the serve process afterward.

- [ ] **Step 3: Drop the scratch test DB**

```bash
PGPASSWORD=123 psql -h 127.0.0.1 -U postgres -c "DROP DATABASE hm_test_secui"
```

---

## Self-review notes (already applied)

- Spec coverage: route/layout (T1), summary strip (T1), card grid + nodata dimming (T1), drilldown dp-task cards with exact profile rules (T2), expand-on-click lines latest-period-only (T3), home entry (T4), tests per spec §Testing (T1–T4), CSS hand-added to portal.css throughout.
- `body.sectors-body{display:block}` neutralizes portal.css's sidebar grid on the standalone page.
- Deadline on a task card comes from the headline line's `deadline_text` (lowest line_no of the latest period) — matches spec.
- `$fmt`/`$lines`/`$headLine` declared once in Task 2's `@php` block; Task 3 reuses them.
- Plain `onclick="event.stopPropagation()"` on `.stp-lines` prevents row clicks from
  collapsing the card (no dependency on Alpine directive parsing inside the loop).
- Percent display rules (cap 99 when not done, round when done) copied from the profile page so the two pages never disagree.
