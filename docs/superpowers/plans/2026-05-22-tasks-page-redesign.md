# Tasks Page Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the tasks-page main content to match the reference image `tasks.png` — full-width filter bar, then a two-column body (main: big status boxes + task list; sidebar: progress donut + stacked summary boxes).

**Architecture:** Rewrite one Blade view (`tasks-board.blade.php`), add two SVG icons to the icon partial, and apply the matching CSS in `portal.css` (new layout block + restyled task card + dead-CSS removal). `TasksBoard.php` is unchanged.

**Tech Stack:** Laravel Blade + Livewire, plain CSS served raw from `public/` (no build step). Spec: `docs/superpowers/specs/2026-05-22-tasks-page-redesign-design.md`.

**Testing note:** Visual Blade/CSS change with no automated test harness for this view. Each task is verified by browser inspection; Task 7 is the full verification pass.

---

### Task 1: Add `check` and `x` icons

**Files:**
- Modify: `backend/resources/views/partials/icon.blade.php`

The status boxes need a check and an x glyph; the icon set has neither.

- [ ] **Step 1: Add the two icons**

Find this exact line:

```php
        'bank'      => '<path d="M232,96H24L128,32Z"
```

Insert immediately before it:

```php
        'check'     => '<path d="M48 132 L100 184 L208 72" fill="none" stroke="currentColor" stroke-width="24" stroke-linecap="round" stroke-linejoin="round"/>',
        'x'         => '<path d="M72 72 L184 184 M184 72 L72 184" fill="none" stroke="currentColor" stroke-width="24" stroke-linecap="round"/>',
```

- [ ] **Step 2: Commit**

```bash
git add backend/resources/views/partials/icon.blade.php
git commit -m "feat(icons): add check and x icons"
```

---

### Task 2: Add task-page color vars

**Files:**
- Modify: `backend/public/css/portal.css` (`:root` block)

- [ ] **Step 1: Add three color vars**

Find this exact line in the `:root` block:

```css
      --score-track: #c5d2e0;
```

Insert immediately after it:

```css
      --task-blue: #1754d3;
      --task-green: #01a358;
      --task-red: #e6302f;
```

- [ ] **Step 2: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(tasks): add task-page color vars from tasks.png"
```

---

### Task 3: Rewrite the tasks-board Blade view

**Files:**
- Modify: `backend/resources/views/livewire/tasks-board.blade.php`

- [ ] **Step 1: Replace the entire file**

Overwrite `backend/resources/views/livewire/tasks-board.blade.php` with exactly:

```blade
@php
    $totals = $this->totals;
    $tasks = $this->tasks;
    $moduleOptions = $this->moduleOptions;
    $indicatorOptions = $this->indicatorOptions;
    $shownScope = $status === 'done' ? 'Бажарилган' : ($status === 'open' ? 'Бажарилмаган' : 'Барчаси');
@endphp

<div>
    <div class="task-filter report-filter">
        <label>Йўналиш / жадвал
            <select wire:model.live="module">
                <option value="all">Барча 7 йўналиш</option>
                @foreach($moduleOptions as $m)
                    <option value="{{ $m->code }}">{{ $m->label }}</option>
                @endforeach
            </select>
        </label>
        <label>KPI / топшириқ йўналиши
            <select wire:model.live="indicator">
                <option value="all">Барча KPI</option>
                @foreach($indicatorOptions as $i)
                    <option value="{{ $i->code }}">{{ $i->label_short }} — {{ $i->label_full }}</option>
                @endforeach
            </select>
        </label>
        <label>Ҳолат
            <select wire:model.live="status">
                <option value="open">Бажарилмаган</option>
                <option value="all">Барчаси</option>
                <option value="done">Бажарилган</option>
            </select>
        </label>
        <label>Қидириш
            <input wire:model.live.debounce.300ms="search" placeholder="Топшириқ, масъул ёки ҳудуд">
        </label>
    </div>

    <div class="tasks-layout">
        <div class="tasks-main">
            <div class="task-stat-row">
                <button class="task-stat-box blue {{ $status === 'all' ? 'is-active' : '' }}" type="button" wire:click="selectStatus('all')">
                    <span class="task-stat-num">{{ $totals['total'] }}</span>
                    <span class="task-stat-label">Жами</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'users'])</span>
                </button>
                <button class="task-stat-box green {{ $status === 'done' ? 'is-active' : '' }}" type="button" wire:click="selectStatus('done')">
                    <span class="task-stat-num">{{ $totals['done'] }}</span>
                    <span class="task-stat-label">Бажарилди</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'check'])</span>
                </button>
                <button class="task-stat-box red {{ $status === 'open' ? 'is-active' : '' }}" type="button" wire:click="selectStatus('open')">
                    <span class="task-stat-num">{{ $totals['open'] }}</span>
                    <span class="task-stat-label">Бажарилмади</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'x'])</span>
                </button>
            </div>

            <section class="task-group">
                <div class="task-group-head">
                    <h3>{{ $shownScope }} топшириқлар</h3>
                    <span class="chip grey">{{ $tasks->count() }} та</span>
                </div>
                <div class="task-list">
                    @forelse($tasks as $task)
                        <article class="task-card" data-task-id="{{ $task->id }}">
                            <span class="task-num">{{ $loop->iteration }}</span>
                            <div class="task-body">
                                <strong>{{ $task->title }}</strong>
                                <div class="task-meta">
                                    <span>{{ $task->deadline_text }}</span>
                                    <span>{{ $task->districts->count() ? $task->districts->count() . ' туман/шаҳар' : 'вилоят' }}</span>
                                    <span>{{ $task->module?->label ?? $task->section_label }}</span>
                                </div>
                            </div>
                            <div class="task-chips">
                                <span class="chip grey">{{ $task->kind === 'kpi' ? 'KPI' : 'Чора-тадбир' }}</span>
                                @if($task->indicator)
                                    <span class="chip blue">{{ $task->indicator->label_short }}</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="muted">Бу филтр бўйича топшириқ топилмади.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="tasks-side">
            <div class="task-donut-card">
                <div class="task-donut" style="--pct:{{ $totals['pct'] }}">
                    <strong>{{ $totals['pct'] }}%</strong>
                    <span>бажарилиш</span>
                </div>
            </div>
            <div class="task-stat-stack">
                <div class="task-stat-box blue">
                    <span class="task-stat-num">{{ $totals['total'] }}</span>
                    <span class="task-stat-label">Жами</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'users'])</span>
                </div>
                <div class="task-stat-box green">
                    <span class="task-stat-num">{{ $totals['done'] }}</span>
                    <span class="task-stat-label">Бажарилди</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'check'])</span>
                </div>
                <div class="task-stat-box red">
                    <span class="task-stat-num">{{ $totals['open'] }}</span>
                    <span class="task-stat-label">Бажарилмади</span>
                    <span class="task-stat-icon">@include('partials.icon', ['name' => 'x'])</span>
                </div>
            </div>
        </aside>
    </div>
</div>
```

Notes: the `<details>` advanced filters are gone. Status filter is the 3 big `.task-stat-box` buttons. Sidebar stat boxes are `<div>` (static). Each `.task-stat-box` has only `<span>` children (no block `<div>`), per the codebase HTML pitfall.

- [ ] **Step 2: Commit**

```bash
git add backend/resources/views/livewire/tasks-board.blade.php
git commit -m "feat(tasks): rebuild tasks-board markup to match tasks.png"
```

---

### Task 4: Restyle the task card

**Files:**
- Modify: `backend/public/css/portal.css` (task-card rules, ~lines 1410-1450)

- [ ] **Step 1: Replace the card rules**

Replace this exact block:

```css
    .task-card {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      background: #fff;
      display: grid;
      gap: 8px;
      min-width: 0;
      cursor: pointer;
    }

    .task-card:hover {
      border-color: var(--blue);
      background: var(--surface);
    }

    .task-card header {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      align-items: flex-start;
    }

    .task-card strong {
      font-size: 13px;
      line-height: 1.3;
      overflow-wrap: anywhere;
    }

    .task-code {
      display: inline-flex;
      align-items: center;
      width: fit-content;
      margin-bottom: 6px;
      padding: 3px 7px;
      border-radius: 999px;
      background: #eaf3ff;
      color: var(--blue);
      font-size: 11px;
      font-weight: 900;
    }
```

with:

```css
    .task-card {
      display: grid;
      grid-template-columns: auto minmax(0, 1fr) auto;
      gap: 12px;
      align-items: start;
      padding: 14px 16px;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      min-width: 0;
    }

    .task-num {
      display: grid;
      place-items: center;
      width: 30px;
      height: 30px;
      border-radius: 8px;
      background: var(--blue-soft);
      color: var(--task-blue);
      font-size: 15px;
      font-weight: 900;
    }

    .task-body {
      display: grid;
      gap: 6px;
      min-width: 0;
    }

    .task-body strong {
      font-size: 14px;
      line-height: 1.35;
      color: var(--ink);
      overflow-wrap: anywhere;
    }

    .task-chips {
      display: grid;
      gap: 6px;
      justify-items: end;
    }
```

- [ ] **Step 2: Commit**

```bash
git commit -am "style(tasks): numbered task card layout"
```

---

### Task 5: Remove dead tasks CSS

**Files:**
- Modify: `backend/public/css/portal.css`

These rules become unreferenced once Task 3 lands (all were used only by the old `tasks-board.blade.php`).

- [ ] **Step 1: Delete `.task-workspace`**

Delete this exact block:

```css
    .task-workspace {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(300px, .38fr);
      gap: 16px;
      align-items: start;
    }
```

- [ ] **Step 2: Delete the summary-strip rules**

Delete this exact block:

```css
    .task-summary-strip {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }

    .task-summary-strip.execution-overview {
      grid-template-columns: minmax(220px, .8fr) minmax(460px, 1.25fr) minmax(110px, .28fr) minmax(170px, .42fr);
      align-items: center;
      padding: 16px;
      border: 1px solid var(--line);
      border-radius: 18px;
      background: #fff;
      box-shadow: var(--shadow);
    }

    .task-summary-strip.execution-overview .exec-status-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .task-summary-copy {
      display: grid;
      align-content: center;
      gap: 5px;
      min-width: 0;
    }

    .task-summary-copy span {
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    .task-summary-copy strong {
      color: var(--ink);
      font-size: 18px;
      line-height: 1.18;
      overflow-wrap: anywhere;
    }

    .task-summary-copy small {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
    }

    .task-summary-strip .exec-status-pill.active {
      border-color: rgba(23, 105, 224, .42);
      background: #eef6ff;
      box-shadow: 0 10px 22px rgba(23, 105, 224, .12);
    }
```

- [ ] **Step 3: Delete the advanced-filter rules**

Delete this exact block:

```css
    .task-advanced-filters {
      margin: -4px 0 16px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255,255,255,.78);
      overflow: hidden;
    }

    .task-advanced-filters summary {
      cursor: pointer;
      padding: 10px 12px;
      color: var(--blue);
      font-size: 12px;
      font-weight: 950;
      list-style-position: inside;
    }

    .task-advanced-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(180px, 1fr));
      gap: 12px;
      padding: 0 12px 12px;
    }

    .task-advanced-grid label {
      display: grid;
      gap: 4px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .task-advanced-grid select {
      width: 100%;
      min-width: 0;
    }
```

- [ ] **Step 4: Delete the unused side-stack rules**

Delete this exact block:

```css
    .task-side-stack {
      display: grid;
      gap: 10px;
      margin-top: 14px;
    }

    .task-side-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: center;
      padding: 11px 0;
      border-top: 1px solid var(--line);
    }

    .task-side-row:first-child {
      border-top: 0;
      padding-top: 0;
    }

    .task-side-row strong {
      display: block;
      color: var(--ink);
      font-size: 13px;
      line-height: 1.25;
    }

    .task-side-row span {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.25;
    }
```

- [ ] **Step 5: Delete `.task-groups` and the `.task-card.compact` rules**

Delete this exact block:

```css
    .task-groups {
      display: grid;
      gap: 12px;
    }
```

Then delete this exact block:

```css
    .task-card.compact {
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: start;
    }

    .task-card.compact header {
      display: grid;
      gap: 6px;
    }

    .task-card.compact .task-actions {
      display: grid;
      gap: 6px;
      justify-items: end;
    }
```

- [ ] **Step 6: Commit**

```bash
git commit -am "chore(tasks): remove CSS dead after tasks-board rebuild"
```

---

### Task 6: Restyle task group + add the new layout CSS

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Restyle the task-group rules**

Replace this exact block:

```css
    .task-group {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fbfdff;
      overflow: hidden;
    }

    .task-group-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
      padding: 12px 14px;
      border-bottom: 1px solid var(--line);
      background: #fff;
    }

    .task-group-head h3 {
      font-size: 15px;
      line-height: 1.2;
    }

    .task-group .task-list {
      padding: 12px;
      max-height: calc(100vh - 320px);
      overflow-y: auto;
      padding-right: 4px;
      overscroll-behavior: contain;
      scroll-behavior: smooth;
    }
```

with:

```css
    .task-group {
      display: grid;
      gap: 10px;
    }

    .task-group-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
    }

    .task-group-head h3 {
      font-size: 17px;
      line-height: 1.2;
      color: var(--ink);
    }

    .tasks-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(280px, .36fr);
      gap: 16px;
      align-items: start;
    }

    .tasks-main {
      display: grid;
      gap: 16px;
      min-width: 0;
    }

    .tasks-side {
      display: grid;
      gap: 14px;
      min-width: 0;
    }

    .task-stat-row {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
    }

    .task-stat-box {
      position: relative;
      display: grid;
      gap: 2px;
      align-content: center;
      min-height: 92px;
      padding: 16px 18px;
      border: 0;
      border-radius: 14px;
      text-align: left;
      color: #fff;
      cursor: pointer;
    }

    .task-stat-box.blue { background: var(--task-blue); }
    .task-stat-box.green { background: var(--task-green); }
    .task-stat-box.red { background: var(--task-red); }

    .task-stat-box.is-active {
      box-shadow: inset 0 0 0 3px rgba(255, 255, 255, .65);
    }

    .task-stat-num {
      font-size: 38px;
      font-weight: 900;
      line-height: 1;
      font-variant-numeric: tabular-nums;
    }

    .task-stat-label {
      font-size: 14px;
      font-weight: 700;
    }

    .task-stat-icon {
      position: absolute;
      top: 14px;
      right: 14px;
      width: 34px;
      height: 34px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: rgba(255, 255, 255, .22);
    }

    .task-stat-icon svg {
      width: 20px;
      height: 20px;
    }

    .task-stat-stack {
      display: grid;
      gap: 10px;
    }

    .task-stat-stack .task-stat-box {
      min-height: 76px;
      cursor: default;
    }

    .task-donut-card {
      display: grid;
      justify-items: center;
      padding: 22px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 16px;
      box-shadow: var(--shadow-sm);
    }

    .task-donut {
      position: relative;
      width: 150px;
      height: 150px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: conic-gradient(var(--blue-2) calc(var(--pct, 0) * 1%), var(--task-blue) 0);
    }

    .task-donut::before {
      content: "";
      position: absolute;
      inset: 14px;
      background: #fff;
      border-radius: 50%;
    }

    .task-donut strong {
      position: relative;
      font-size: 38px;
      font-weight: 900;
      line-height: 1;
      color: var(--ink);
    }

    .task-donut span {
      position: relative;
      margin-top: 2px;
      font-size: 13px;
      font-weight: 700;
      color: var(--muted);
    }
```

- [ ] **Step 2: Commit**

```bash
git commit -am "style(tasks): two-column layout, stat boxes, progress donut"
```

---

### Task 7: Fix responsive rules

**Files:**
- Modify: `backend/public/css/portal.css` (narrow-width media blocks)

- [ ] **Step 1: Drop dead selectors from the first media block**

Replace this exact line:

```css
      .task-workspace, .task-filter, .task-filter.report-filter, .task-summary-strip, .workflow-strip { grid-template-columns: 1fr; }
```

with:

```css
      .task-filter, .task-filter.report-filter, .tasks-layout, .task-stat-row, .workflow-strip { grid-template-columns: 1fr; }
```

- [ ] **Step 2: Remove the dead exec-status-grid responsive line**

Delete this exact line:

```css
      .task-summary-strip.execution-overview .exec-status-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
```

- [ ] **Step 3: Remove the second dead responsive line**

Delete this exact line:

```css
      .task-summary-strip.execution-overview .exec-status-grid, .task-advanced-grid { grid-template-columns: 1fr; }
```

- [ ] **Step 4: Remove the dead task-list scroll override**

Delete this exact line:

```css
      .task-group .task-list { max-height: none; overflow: visible; padding-right: 0; }
```

- [ ] **Step 5: Remove the dead task-card.compact responsive lines**

Delete this exact line:

```css
      .task-card.compact { grid-template-columns: 1fr; }
```

Then delete this exact line:

```css
      .task-card.compact .task-actions { justify-items: start; }
```

- [ ] **Step 6: Commit**

```bash
git commit -am "style(tasks): responsive collapse for the new layout"
```

---

### Task 8: Verify against the design

**Files:** none — verification only.

- [ ] **Step 1: Serve the app**

Run: `php artisan serve` (from `backend/`)
Expected: server on `http://127.0.0.1:8000`.

- [ ] **Step 2: Check the tasks page**

Open the tasks page. Hard refresh (`Ctrl+F5`).
Expected: full-width 4-field filter bar; below it a two-column body — main column with 3 big colored status boxes (Жами blue / Бажарилди green / Бажарилмади red) then the numbered task list; right sidebar with a blue progress donut card and 3 stacked stat boxes. Matches `tasks.png`.

- [ ] **Step 3: Check the status filter**

Click each big status box.
Expected: the list filters (all / done / open), the group header text updates, and the clicked box shows the inset-white active ring.

- [ ] **Step 4: Check a deep link**

Open `/tasks?district=<any valid id>`.
Expected: the list is filtered by district even though no district control is visible.

- [ ] **Step 5: Check the icons**

Expected: the blue box shows a users icon, green a check, red an x — all inside translucent circular badges.

- [ ] **Step 6: Check responsive collapse**

Narrow the browser window.
Expected: the two columns collapse to one; the 3-box status row collapses to a single column; nothing overflows.

---

## Self-Review

**Spec coverage:** Filter bar (advanced filters removed) → Task 3. Big status boxes as the status filter → Task 3 + Task 6. Numbered task cards → Task 3 + Task 4. Sidebar donut → Task 3 + Task 6. Sidebar stacked boxes → Task 3 + Task 6. Color vars → Task 2. check/x icons → Task 1. Dead-CSS removal → Task 5 + Task 7. `TasksBoard.php` unchanged → no task needed (confirmed: `$period`/`$district` props and `selectStatus` already exist and are untouched). All spec sections covered.

**Placeholder scan:** No TBD/TODO; every step shows complete Blade or CSS content.

**Type consistency:** Class names (`task-stat-box`, `task-stat-row`, `task-stat-stack`, `task-stat-num/label/icon`, `task-donut`, `task-donut-card`, `tasks-layout/main/side`, `task-card/num/body/chips`, `task-group/-head`) are used identically in the Task 3 Blade and the Task 4/6/7 CSS. `selectStatus` and `$totals` keys (`total`/`done`/`open`/`pct`) match `TasksBoard.php`. Icon names `users` (existing), `check`/`x` (added in Task 1) match the Blade `@include` calls.
