# Батафсил (task-detail) Styling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the `/tasks` card "Батафсил" (`<details class="task-detail">`) interior — a styled disclosure header, sub-metric two-line rows with red/amber/green % pills, an uppercase scope/cadence caption, and grouped district chips — replacing the flat unstyled `.task-meta` rows.

**Architecture:** View + stylesheet change only. Markup in `backend/resources/views/livewire/tasks-board.blade.php` (the `<details>` block, lines ~100–128); styling in the hand-maintained `backend/public/css/portal.css`. No PHP/model/query change. Direction C from the spec: feed-style rows. The breakdown shows **sub-metrics only** (`line_no ≥ 1`); the headline (`line_no 0`) is dropped because the card face already shows it.

**Tech Stack:** Laravel 12 + Livewire 3, Blade, plain CSS, Pest 3 feature tests.

---

## ⚠️ Build / CSS mechanics (read before Task 3)

`public/css/portal.css` is hand-maintained and linked directly in `layouts/app.blade.php`
(`<link href="/css/portal.css">`). It is **not** built from `resources/css/app.css`. **Edit
`public/css/portal.css` directly; do NOT run `npm run build`.** (See the card-face plan
`2026-06-01-tasks-card-data-zone.md` for the full rationale.) `--task-amber` already exists from that work.

## ⚠️ Test DB note

The Pest suite runs on a shared PostgreSQL test DB (`hududlar_monitoringi_test`). **Never run two
suites at once** (they share the DB and `migrate:fresh` wipes schema mid-run, causing spurious
transaction-cascade failures). Run one `php artisan test` invocation at a time.

## File structure

- **Modify** `backend/resources/views/livewire/tasks-board.blade.php` — rewrite the `<details>` interior
  (lines ~100–128); add `$subLines` + a `$tlTier` closure to the existing detail `@php` block.
- **Modify** `backend/public/css/portal.css` — add a `.task-detail` block (summary bar, caption, metric
  rows, pill, district group). Keep the existing `.task-meta` rule (it is no longer used here but is a
  generic class; do not remove it).
- **Modify** `backend/tests/Feature/Tasks/TasksBoardProgressTest.php` — update the `card detail …` test
  for the new structure.

The card face, filter bar, sidebar, and import pipeline are untouched.

---

## Task 1: Update the detail test (RED)

**Files:**
- Modify/Test: `backend/tests/Feature/Tasks/TasksBoardProgressTest.php`

- [ ] **Step 1: Replace the `card detail …` test**

Find the existing test (it currently asserts the old flat markup):

```php
test('card detail shows cadence and scope demoted from the face', function () {
```

Replace that entire `test(...)` block (from its `test(` line through its closing `});`) with:

```php
test('card detail shows sub-metrics, scope, cadence and districts; drops the headline line', function () {
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
        ->assertSee('Қамров')                              // scope/cadence caption
        ->assertSee('Даврийлик')
        ->assertSee('Ойлик')                               // monthly cadence
        ->assertSee('Ижрочи ҳудудлар')                     // districts group label
        ->assertSee('Шаҳрихон тумани')                     // district chip
        ->assertSee('қайта тикланадиган ишлаб чиқариш')    // sub-metric (line_no 1) shown
        ->assertSee('млрд сўм')
        ->assertSeeHtml('tl-pill--green')                  // its 100% pill is the green tier
        ->assertDontSee('йирик корхона сони');             // headline (line_no 0) dropped from breakdown
});
```

- [ ] **Step 2: Run it and confirm it fails**

Run:
```powershell
cd backend
php artisan test --filter="card detail shows sub-metrics"
```
Expected: FAIL. The current blade renders the headline line (so `assertDontSee('йирик корхона сони')`
fails) and has no `tl-pill--green` class.

- [ ] **Step 3: Commit**

```powershell
cd backend
git add tests/Feature/Tasks/TasksBoardProgressTest.php
git commit -m "test(tasks): assert redesigned Батафсил structure (red)"
```

---

## Task 2: Rewrite the detail blade (GREEN)

**Files:**
- Modify: `backend/resources/views/livewire/tasks-board.blade.php` (lines ~100–128)

- [ ] **Step 1: Replace the detail `@php` + `<details>` block**

Replace this exact current block (the `@php $latestLines …` through the closing `@endif` at line ~129):

```blade
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
```

with:

```blade
                                @php
                                    $latestLines = $task->latest_period
                                        ? $task->progress->where('report_period', $task->latest_period)
                                        : collect();
                                    // Breakdown shows sub-metrics only; the headline (line_no 0) is on the card face.
                                    $subLines = $latestLines->where('line_no', '>', 0);
                                    // Per-line tier, same thresholds as the card face.
                                    $tlTier = fn ($p) => $p === null ? 'none'
                                        : ((float) $p >= 100 ? 'green' : ((float) $p >= 50 ? 'amber' : 'red'));
                                @endphp
                                @if($subLines->isNotEmpty() || $task->districts->isNotEmpty())
                                    <details class="task-detail">
                                        <summary>
                                            <span class="chev" aria-hidden="true"></span>
                                            <span class="lab">Батафсил</span>
                                            <span class="ct">
                                                @if($subLines->isNotEmpty())<span class="pill">{{ $subLines->count() }} кўрсаткич</span>@endif
                                                @if($task->districts->isNotEmpty())<span class="pill">{{ $task->districts->count() }} ҳудуд</span>@endif
                                            </span>
                                        </summary>
                                        <div class="task-detail-body">
                                            <div class="task-detail-cap">Қамров: <b>{{ $scopeText }}</b>@if($cadenceLabel) · Даврийлик: <b>{{ $cadenceLabel }}</b>@endif</div>
                                            @if($subLines->isNotEmpty())
                                                <div class="task-detail-lines">
                                                    @foreach($subLines as $line)
                                                        @php $lt = $tlTier($line->pct_of_plan); @endphp
                                                        <div class="tl-row">
                                                            <div class="tl-main">
                                                                <div class="tl-name">{{ $line->metric_label ?? '—' }}</div>
                                                                <div class="tl-sub">Режа <b>{{ $fmt($line->plan_value) }}</b> {{ $line->unit }} · Амалда <b>{{ $fmt($line->actual_value) }}</b> {{ $line->unit }}</div>
                                                            </div>
                                                            <span class="tl-pill tl-pill--{{ $lt }}">{{ $line->pct_of_plan !== null ? round((float) $line->pct_of_plan) . '%' : '—' }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if($task->districts->isNotEmpty())
                                                <div class="task-detail-dist">
                                                    <span class="clab">Ижрочи ҳудудлар</span>
                                                    <div class="task-detail-chips">
                                                        @foreach($task->districts as $d)
                                                            <span class="chip blue">{{ $d->name_full }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                @endif
```

- [ ] **Step 2: Run the detail test + the whole file**

Run:
```powershell
cd backend
php artisan test --filter=TasksBoardProgressTest
```
Expected: PASS (all tests in the file, including the updated detail test, green). The feature tests
assert HTML, so they pass on markup alone — CSS (Task 3) is not required for green.

- [ ] **Step 3: Commit**

```powershell
cd backend
git add resources/views/livewire/tasks-board.blade.php
git commit -m "feat(tasks): redesign Батафсил interior (sub-metric rows + tier pills)"
```

---

## Task 3: Add the `.task-detail` CSS

**Files:**
- Modify: `backend/public/css/portal.css`

No automated test: portal.css is a static asset; class names are asserted by Task 1, visual correctness
is checked in Task 4.

- [ ] **Step 1: Append the data-zone detail CSS**

Find the card data-zone block added by the earlier plan (it ends with the `.task-foot-cap { … }` rule,
around line 1538–1545). Immediately after that rule, insert:

```css
    /* --- task-detail (Батафсил) panel --- */
    .task-detail {
      border: 1px solid var(--line);
      border-radius: 8px;
      overflow: hidden;
    }
    .task-detail summary {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 9px 13px;
      background: #f7faf9;
      cursor: pointer;
      list-style: none;
    }
    .task-detail summary::-webkit-details-marker { display: none; }
    .task-detail[open] summary { border-bottom: 1px solid var(--soft, #eef2f2); }
    .task-detail summary .chev {
      width: 0;
      height: 0;
      border-left: 5px solid var(--muted);
      border-top: 4px solid transparent;
      border-bottom: 4px solid transparent;
      transition: transform .15s ease;
    }
    .task-detail[open] summary .chev { transform: rotate(90deg); }
    .task-detail summary .lab { font-weight: 700; color: var(--ink); font-size: 13px; }
    .task-detail summary .ct { margin-left: auto; display: flex; gap: 6px; }
    .task-detail summary .pill {
      font-size: 11px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 999px;
      background: #eef2f2;
      color: var(--muted);
      white-space: nowrap;
    }

    .task-detail-body { padding: 12px 13px; display: grid; gap: 12px; }
    .task-detail-cap {
      font-size: 11px;
      font-weight: 800;
      letter-spacing: .03em;
      text-transform: uppercase;
      color: var(--grey);
    }
    .task-detail-cap b { color: var(--ink); font-weight: 800; }

    .task-detail-lines { display: grid; }
    .tl-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 0;
      border-top: 1px solid #eef2f2;
    }
    .tl-row:first-child { border-top: 0; }
    .tl-main { min-width: 0; flex: 1; }
    .tl-name { font-weight: 700; font-size: 12.5px; color: var(--ink); line-height: 1.25; overflow-wrap: anywhere; }
    .tl-sub { font-size: 11.5px; color: var(--muted); margin-top: 2px; }
    .tl-sub b { color: var(--ink); }
    .tl-pill {
      font-size: 12px;
      font-weight: 800;
      padding: 3px 10px;
      border-radius: 999px;
      white-space: nowrap;
      font-variant-numeric: tabular-nums;
    }
    .tl-pill--red   { background: #fdecea; color: var(--task-red); }
    .tl-pill--amber { background: #fdf0e1; color: var(--task-amber); }
    .tl-pill--green { background: #e6f4ea; color: var(--task-green); }
    .tl-pill--none  { background: #eef2f2; color: var(--muted); }

    .task-detail-dist .clab {
      display: block;
      margin-bottom: 6px;
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: var(--grey);
    }
    .task-detail-chips { display: flex; flex-wrap: wrap; gap: 6px; }
```

Note: `--soft` is not a defined variable, so the `border-bottom` fallback `#eef2f2` is used — that is
intentional (keeps the rule self-contained). All other vars (`--line`, `--ink`, `--muted`, `--grey`,
`--task-red`, `--task-amber`, `--task-green`) exist. `.chip.blue` (used for district chips) already
exists — not redefined.

- [ ] **Step 2: Confirm the referenced vars exist**

Run:
```powershell
cd backend
Select-String -Path public/css/portal.css -Pattern "--task-amber|--grey|--ink|--muted|--line|--task-red|--task-green" | Select-Object -First 7
```
Expected: each variable appears (defined in the `:root`/theme block near the top).

- [ ] **Step 3: Commit**

```powershell
cd backend
git add public/css/portal.css
git commit -m "style(tasks): Батафсил panel — summary bar, metric rows, tier pills"
```

---

## Task 4: Verify suite + visual check

**Files:** none (verification only)

- [ ] **Step 1: Run the full suite (single invocation)**

Run:
```powershell
cd backend
php artisan test
```
Expected: `TasksBoardProgressTest` and `TasksPageTest` green. Pre-existing unrelated failures may appear
(`RegionProfileTest` count assertions; `Console` tests show PostgreSQL transaction-cascade flakiness when
the shared DB is contended — re-run a failing Console class in isolation to confirm it passes alone).
No failure should mention `task-detail`, `tl-row`, `tl-pill`, `Батафсил`, or `portal.css`.

- [ ] **Step 2: Visual check**

```powershell
cd backend
php artisan serve
```
Open `/tasks`, expand a card with sub-metrics + districts. Confirm:
- Summary bar: chevron + bold `Батафсил` + `N кўрсаткич` / `M ҳудуд` pills; chevron rotates on open.
- Caption `ҚАМРОВ: … · ДАВРИЙЛИК: …` (uppercased by CSS).
- Each sub-metric: bold name, muted `Режа … · Амалда …` line, right-aligned % pill colored by tier
  (red <50, amber 50–99, green ≥100, grey `—` when no pct).
- Headline metric (line_no 0) is **not** repeated in the breakdown.
- `Ижрочи ҳудудлар` label + blue chips.
- A districts-only task (no sub-metrics) shows the caption + chips and no metric rows.

---

## Self-review notes

- **Spec coverage:** summary header (Task 3 CSS + Task 2 markup), sub-metric two-line rows + tier pills
  (Task 2/3), drop line_no 0 + gate change (Task 2), scope caption (Task 2/3), district group (Task 2/3),
  test update (Task 1). All spec sections mapped.
- **Gate side effect:** a task with one sub-metric line now opens a detail (previously needed 2+ total
  lines). The existing `task planned only via a sub-metric line …` test still passes — its sub-line
  renders as `Режа 12 дона · Амалда —` with a `tl-pill--none` `—`, and its `—`/`--w:0%`/`var(--grey)`
  assertions still hold.
- **Type/name consistency:** tier names `none/red/amber/green` → `tl-pill--{tier}` classes, all four
  defined in CSS. `$subLines` and `$tlTier` defined in the same `@php` block they are used in. `$fmt`,
  `$scopeText`, `$cadenceLabel` are defined earlier in the card's `@php` block (unchanged).
