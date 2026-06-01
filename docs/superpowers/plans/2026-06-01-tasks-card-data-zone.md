# /tasks Card Data-Zone Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two flat `.task-meta` rows on each `/tasks` card with a labeled stat strip (Режа / Амалда / Бажарилиш columns), a quiet context line (Срок / Йўналиш), a period caption, and threshold (red/amber/green) color on the percent and progress bar.

**Architecture:** Pure view + stylesheet change. Markup lives in the Livewire view `backend/resources/views/livewire/tasks-board.blade.php`; styling lives in the hand-maintained `backend/public/css/portal.css`. No PHP, model, query, route, or DB change — every field used (`headline_plan/actual/pct/unit`, `deadline_text`, `module->label`, `latest_period`, `cadence`, `districts`, `status`) already exists on `App\Models\Task`.

**Tech Stack:** Laravel 12 + Livewire 3, Blade, plain CSS (Pest 3 feature tests via `Livewire::test`).

---

## ⚠️ Build / CSS mechanics (read before Task 3)

CLAUDE.md says portal styling is "built via Vite/Tailwind from `resources/css/app.css`, run `npm run build`." **This is false for portal.css.** Verified during planning:

- `layouts/app.blade.php:11` links `/css/portal.css` directly: `<link rel="stylesheet" href="/css/portal.css">`. No `@vite` for portal styles.
- `resources/css/app.css` is a 10-line Tailwind v4 entry (`@import 'tailwindcss'` + `@theme`) and contains **none** of the `.task-card` / `.progress` / `--task-*` styles.
- `vite build` emits to `public/build/` (laravel-vite-plugin default), never to `public/css/portal.css`.

**Therefore: edit `public/css/portal.css` directly. Do NOT run `npm run build` for this change — it will not regenerate portal.css and is not needed.**

## File structure

- **Modify** `backend/resources/views/livewire/tasks-board.blade.php` — rewrite the card-body data zone (current lines ~48–99). Card frame (`.task-num`, title, `.task-chips`, `<details>` trigger logic) preserved.
- **Modify** `backend/public/css/portal.css` — add one CSS var (`--task-amber`) and one block of data-zone classes. Keep the existing `.task-meta` class (still used by the detail lines inside `<details>`).
- **Modify** `backend/tests/Feature/Tasks/TasksBoardProgressTest.php` — update assertions for the new structure; add red/amber tier tests; move the cadence assertion into the detail test.
- **Unchanged** `backend/tests/Feature/Http/TasksPageTest.php` — only checks class names (`task-card`, `task-filter`, `task-stat-stack`) + titles, all preserved.

Isolation note: the District Profile "Туман топшириқлари" panel uses its own `.dpc-task-*` classes (`portal.css:4697+`), not this markup — it is unaffected.

---

## Task 1: Update the feature tests (RED)

**Files:**
- Modify/Test: `backend/tests/Feature/Tasks/TasksBoardProgressTest.php`

- [ ] **Step 1: Replace the whole test file with the new spec**

Replace the entire contents of `backend/tests/Feature/Tasks/TasksBoardProgressTest.php` with:

```php
<?php
// backend/tests/Feature/Tasks/TasksBoardProgressTest.php

use App\Livewire\TasksBoard;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('regions')->insert([
        'id' => 1, 'code' => 1703, 'name_short' => 'Андижон', 'name_full' => 'Андижон вилояти',
        'name_latin' => 'andijan', 'folder_name' => '2. Андижон', 'sort_order' => 2,
        'has_districts' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('modules')->insert([
        'code' => 'macro', 'label' => 'Макро иқтисодиёт', 'sort_order' => 10,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '1', 'title' => 'ЯҲМ ўсиши',
        'module_code' => 'macro', 'indicator_code' => null,
        'deadline_text' => '2026 йил якунигача',   // feeds the Срок context field
        'cadence' => 'quarterly', 'status' => 'open',
        'headline_unit' => 'фоиз', 'headline_plan' => 7.2, 'headline_actual' => 3.6,
        'headline_pct' => 50, 'latest_period' => '2026-Q1',
    ]);
});

test('board card shows labeled plan, actual, percent and context', function () {
    session(['region_code' => 1703]); // TasksBoard::mount() reads CurrentRegion::code()
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSee('Режа')               // stat-strip labels
        ->assertSee('Амалда')
        ->assertSee('Бажарилиш')
        ->assertSee('7.2')                 // plan value
        ->assertSee('3.6')                 // actual value
        ->assertSee('50')                  // pct value (50%)
        ->assertSee('Срок')                // context label
        ->assertSee('2026 йил якунигача')  // deadline value
        ->assertSee('Йўналиш')             // module label heading
        ->assertSee('Макро иқтисодиёт')    // module value
        ->assertSee('ҳолат:')              // period caption label
        ->assertSee('2026-Q1');            // period value
});

test('percent under 50 uses the red tier', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '9', 'title' => 'Орқада топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 100, 'headline_actual' => 30,
        'headline_pct' => 30, 'latest_period' => '2026-Q1',
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->set('search', 'Орқада')              // isolate this card
        ->assertSeeHtml('task-pct--red')        // pct value gets the red modifier
        ->assertSeeHtml('var(--task-red)');     // progress bar fill var
});

test('percent between 50 and 99 uses the amber tier', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '10', 'title' => 'Ярим топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 100, 'headline_actual' => 70,
        'headline_pct' => 70, 'latest_period' => '2026-Q1',
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->set('search', 'Ярим')
        ->assertSeeHtml('task-pct--amber')
        ->assertSeeHtml('var(--task-amber)');
});

test('done task shows done badge and green tier', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '2', 'title' => 'Экспорт ҳажми',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'done',
        'headline_unit' => 'млн долл', 'headline_plan' => 10, 'headline_actual' => 12,
        'headline_pct' => 120, 'latest_period' => '2026-Q1',
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'done')
        ->assertSee('Бажарилди')
        ->assertSee('Экспорт ҳажми')
        ->assertSeeHtml('task-pct--green')      // pct >= 100 -> green tier
        ->assertSeeHtml('--task-green')         // progress bar fill var
        // open task filtered out in done view; its indicator label 'ЯҲМ' never renders
        ->assertDontSee('ЯҲМ ўсиши');
});

test('card detail shows cadence and scope demoted from the face', function () {
    DB::table('districts')->insert([
        'region_id' => 1, 'region_code' => 1703, 'code' => 1703230,
        'name_short' => 'Шаҳрихон т.', 'name_full' => 'Шаҳрихон тумани',
        'kind' => 'district', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $task = Task::factory()->create([
        'region_code' => 1703, 'task_number' => '7', 'title' => 'Кўп кўрсаткичли топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        'cadence' => 'monthly', 'status' => 'open',
        'headline_unit' => 'дона', 'headline_plan' => 6, 'headline_actual' => 3,
        'headline_pct' => 50, 'latest_period' => '2026-Q1',
    ]);
    $task->progress()->createMany([
        ['line_no' => 0, 'metric_label' => 'йирик корхона сони', 'unit' => 'дона',
         'report_period' => '2026-Q1', 'period_type' => 'quarter', 'plan_value' => 6, 'actual_value' => 3, 'pct_of_plan' => 50],
        ['line_no' => 1, 'metric_label' => 'қайта тикланадиган ишлаб чиқариш', 'unit' => 'млрд сўм',
         'report_period' => '2026-Q1', 'period_type' => 'quarter', 'plan_value' => 55, 'actual_value' => 55, 'pct_of_plan' => 100],
    ]);
    $task->districts()->sync(DB::table('districts')->where('code', 1703230)->pluck('id'));

    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSee('Батафсил')
        ->assertSee('қайта тикланадиган ишлаб чиқариш')
        ->assertSee('млрд сўм')
        ->assertSee('Шаҳрихон тумани')
        ->assertSee('Қамров')           // scope demoted into the detail
        ->assertSee('Даврийлик')        // cadence label demoted into the detail
        ->assertSee('Ойлик');           // monthly cadence value, now inside detail
});

test('task without progress data renders without errors', function () {
    Task::factory()->create([
        'region_code' => 1703, 'task_number' => '3', 'title' => 'Маълумотсиз топшириқ',
        'module_code' => 'macro', 'indicator_code' => null,
        // no cadence/headline/latest_period at all (all null)
    ]);
    session(['region_code' => 1703]);
    Livewire::test(TasksBoard::class)
        ->set('status', 'all')
        ->assertSee('Маълумотсиз топшириқ')
        ->assertSee('—'); // null plan/actual/pct render as em-dash
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:
```powershell
cd backend
php artisan test --filter=TasksBoardProgressTest
```
Expected: FAIL. The new tests assert markup the current blade doesn't emit yet — e.g. `Срок`, `ҳолат:`, `task-pct--red`, `var(--task-amber)`, `Қамров`, `Даврийлик`. (`var(--task-amber)` also fails because the CSS var doesn't exist yet, but the assertion is on the inline style string emitted by the blade, added in Task 2.)

- [ ] **Step 3: Commit the failing tests**

```powershell
cd backend
git add tests/Feature/Tasks/TasksBoardProgressTest.php
git commit -m "test(tasks): assert new card data-zone structure (red)"
```

---

## Task 2: Rewrite the blade data zone (GREEN)

**Files:**
- Modify: `backend/resources/views/livewire/tasks-board.blade.php` (the `@forelse` card body, lines ~48–99)

- [ ] **Step 1: Replace the `@php` block + card body**

In `tasks-board.blade.php`, replace everything from the `@php` block at line 48 through the closing of the `<details>`/`@endif` at line 99 (i.e. the inner content of `<div class="task-body">` plus the preceding `@php`) with the block below. The surrounding `<article class="task-card" ...>`, `<span class="task-num">`, and `<div class="task-chips">…</div>` stay exactly as they are.

Find this opening context (unchanged, keep it):
```blade
                        <article class="task-card" data-task-id="{{ $task->id }}">
                            <span class="task-num">{{ $loop->iteration }}</span>
                            <div class="task-body">
```

Replace the `@php` block (currently lines 48–54) and the body content (currently lines 57–99) so the result reads:

```blade
                        @php
                            $pct = $task->headline_pct !== null ? (float) $task->headline_pct : null;
                            $statusChip = $task->status === 'done' ? 'green' : 'grey';
                            $statusLabel = $task->status === 'done' ? 'Бажарилди' : 'Бажарилмаган';
                            $cadenceLabel = $task->cadence === 'monthly' ? 'Ойлик' : ($task->cadence === 'quarterly' ? 'Чорак' : '');
                            $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ' '), '0'), '.');
                            $tier = $pct === null ? 'none' : ($pct >= 100 ? 'green' : ($pct >= 50 ? 'amber' : 'red'));
                            $tierVar = ['none' => '--grey', 'red' => '--task-red', 'amber' => '--task-amber', 'green' => '--task-green'][$tier];
                            $srok = $task->deadline_text;
                            $yonalish = $task->module?->label ?? $task->section_label;
                            $scopeText = $task->districts->count() ? $task->districts->count() . ' туман/шаҳар' : 'вилоят';
                        @endphp
                        <article class="task-card" data-task-id="{{ $task->id }}">
                            <span class="task-num">{{ $loop->iteration }}</span>
                            <div class="task-body">
                                <strong>{{ $task->title }}</strong>

                                <div class="task-ctx">
                                    @if($srok)<span><span class="k">Срок</span> <span class="v">{{ $srok }}</span></span>@endif
                                    @if($yonalish)<span><span class="k">Йўналиш</span> <span class="v">{{ $yonalish }}</span></span>@endif
                                </div>

                                <div class="task-strip">
                                    <div class="cell">
                                        <span class="clab">Режа</span>
                                        <span class="val">{{ $fmt($task->headline_plan) }}<small>{{ $task->headline_unit }}</small></span>
                                    </div>
                                    <div class="cell">
                                        <span class="clab">Амалда</span>
                                        <span class="val">{{ $fmt($task->headline_actual) }}<small>{{ $task->headline_unit }}</small></span>
                                    </div>
                                    <div class="cell">
                                        <span class="clab">Бажарилиш</span>
                                        <span class="val task-pct task-pct--{{ $tier }}">{{ $pct === null ? '—' : round($pct) . '%' }}</span>
                                    </div>
                                </div>

                                @if($pct !== null)
                                    <div class="task-foot">
                                        <div class="progress"><i style="--w:{{ max(0, min(100, $pct)) }}%;--c:var({{ $tierVar }})"></i></div>
                                        @if($task->latest_period)<span class="task-foot-cap">ҳолат: {{ $task->latest_period }}</span>@endif
                                    </div>
                                @endif

                                @php
                                    $latestLines = $task->latest_period
                                        ? $task->progress->where('report_period', $task->latest_period)
                                        : collect();
                                @endphp
                                @if($latestLines->count() > 1 || $task->districts->isNotEmpty())
                                    <details class="task-detail">
                                        <summary class="muted">Батафсил ({{ $latestLines->count() }} кўрсаткич{{ $task->districts->isNotEmpty() ? ', ' . $task->districts->count() . ' ҳудуд' : '' }})</summary>
                                        <div class="task-meta">
                                            <span>Қамров: {{ $scopeText }}</span>
                                            @if($cadenceLabel)<span>Даврийлик: {{ $cadenceLabel }}</span>@endif
                                        </div>
                                        @foreach($latestLines as $line)
                                            <div class="task-meta">
                                                <span>{{ $line->metric_label ?? '—' }}</span>
                                                <span>Режа: <b>{{ $fmt($line->plan_value) }}</b> {{ $line->unit }}</span>
                                                <span>Амалда: <b>{{ $fmt($line->actual_value) }}</b> {{ $line->unit }}</span>
                                                <span><b>{{ $line->pct_of_plan !== null ? round((float) $line->pct_of_plan) . '%' : '—' }}</b></span>
                                            </div>
                                        @endforeach
                                        @if($task->districts->isNotEmpty())
                                            <div class="task-meta">
                                                <span>Ижрочи ҳудудлар:</span>
                                                @foreach($task->districts as $d)
                                                    <span class="chip blue">{{ $d->name_full }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </details>
                                @endif
                            </div>
                            <div class="task-chips">
                                <span class="chip {{ $statusChip }}">{{ $statusLabel }}</span>
                                <span class="chip grey">{{ $task->kind === 'kpi' ? 'KPI' : 'Чора-тадбир' }}</span>
                                @if($task->indicator)
                                    <span class="chip blue">{{ $task->indicator->label_short }}</span>
                                @endif
                            </div>
                        </article>
```

Note: the `.task-chips` block and `@empty`/`@endforelse` that follow are unchanged — this replacement ends at the `</article>` that already exists.

- [ ] **Step 2: Run the tests to verify they pass**

Run:
```powershell
cd backend
php artisan test --filter=TasksBoardProgressTest
```
Expected: PASS (all tests in the file green). The feature tests assert HTML strings, so they pass on the markup alone — CSS (Task 3) is not required for green.

- [ ] **Step 3: Commit**

```powershell
cd backend
git add resources/views/livewire/tasks-board.blade.php
git commit -m "feat(tasks): labeled stat strip + threshold tiers on task cards"
```

---

## Task 3: Add the data-zone CSS to portal.css

**Files:**
- Modify: `backend/public/css/portal.css`

No automated test: `portal.css` is a static asset with no test harness; the class names are already asserted by Task 1, and visual correctness is checked by running the app in Task 4.

- [ ] **Step 1: Add the `--task-amber` CSS variable**

In `backend/public/css/portal.css`, find the existing block (around line 25–27):
```css
      --task-blue: #1754d3;
      --task-green: #01a358;
      --task-red: #e6302f;
```
Add one line directly after `--task-red`:
```css
      --task-blue: #1754d3;
      --task-green: #01a358;
      --task-red: #e6302f;
      --task-amber: #d97706;
```

- [ ] **Step 2: Add the data-zone classes**

In `backend/public/css/portal.css`, find the `.task-meta` block (around line 1466):
```css
    .task-meta {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      color: var(--muted);
      font-size: 13px;
    }
```
Immediately **after** that block (keep `.task-meta` — it still styles the detail lines), insert:

```css
    /* --- task card data zone (v7 redesign) --- */
    .task-ctx {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      font-size: 12.5px;
      line-height: 1.3;
    }
    .task-ctx .k {
      color: var(--muted);
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .task-ctx .v { color: var(--ink); font-weight: 600; }

    .task-strip {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      border: 1px solid var(--line);
      border-radius: 8px;
      overflow: hidden;
    }
    .task-strip .cell {
      display: grid;
      gap: 2px;
      padding: 7px 12px;
      border-left: 1px solid var(--line);
      min-width: 0;
    }
    .task-strip .cell:first-child { border-left: 0; }
    .task-strip .clab {
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: var(--muted);
    }
    .task-strip .val {
      font-size: 18px;
      font-weight: 800;
      color: var(--ink);
      line-height: 1.05;
      overflow-wrap: anywhere;
    }
    .task-strip .val small {
      font-size: 11px;
      font-weight: 600;
      color: var(--muted);
      margin-left: 3px;
    }
    .task-pct--red   { color: var(--task-red); }
    .task-pct--amber { color: var(--task-amber); }
    .task-pct--green { color: var(--task-green); }
    .task-pct--none  { color: var(--muted); }

    .task-foot {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .task-foot .progress { flex: 1; }
    .task-foot-cap {
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: var(--muted);
      white-space: nowrap;
    }
```

- [ ] **Step 3: Sanity-check no build is run**

Do **not** run `npm run build`. The layout links `/css/portal.css` directly; the edit is live on reload.

- [ ] **Step 4: Commit**

```powershell
cd backend
git add public/css/portal.css
git commit -m "style(tasks): stat strip, context line, threshold tier colors"
```

---

## Task 4: Verify full suite + visual check

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run:
```powershell
cd backend
php artisan test
```
Expected: green (note the 2 known pre-existing failures unrelated to this change, if still present on the branch — confirm they are the same ones, not introduced here). `TasksBoardProgressTest` and `TasksPageTest` must pass.

- [ ] **Step 2: Visual check in the browser**

Run the app and open `/tasks`:
```powershell
cd backend
php artisan serve
```
Confirm on a card with progress data:
- Context line shows `СРОК · …` and `ЙЎНАЛИШ · …` (uppercased by CSS).
- Three aligned columns: Режа / Амалда / Бажарилиш, with units small after the plan/actual values.
- Percent text and progress bar are **red** when <50%, **amber** 50–99%, **green** at 100%/done.
- Period caption `ҳолат: 2026-Q1` sits to the right of the bar.
- Expanding `Батафсил` shows the `Қамров: … · Даврийлик: …` line, then metric lines, then executor district chips.
- A task with no headline data shows `—` in all three cells and no progress bar, no errors.

- [ ] **Step 3: Confirm columns align down the list**

Scroll the task list; the three strip columns should line up vertically from card to card (single-column list → equal `1fr` columns align).

---

## Self-review notes (resolved during planning)

- **Spec said "edit app.css + npm run build."** Corrected: portal.css is edited directly (see build-mechanics box). The spec file's "Files touched" section is updated to match.
- **Cadence on the face → demoted.** The old test asserted `Чорак` on the face; cadence now lives only inside `<details>`, which renders only when a task has >1 metric line or ≥1 district. On a card with no detail, cadence is not shown — accepted trade-off of the demotion. Test updated: face assertion removed; cadence asserted inside the detail test instead.
- **Type/name consistency:** `$tier` ∈ {none,red,amber,green}; `$tierVar` maps each to an existing CSS var (`--grey`, `--task-red`, `--task-amber`, `--task-green`). Class names `task-pct--{tier}` and `.task-pct--{tier}` selectors match. `--task-amber` is the only new var.
