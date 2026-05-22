# Industry Page Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the industry KPI panel — remove the hero card and mini-button, switch to a stacked full-width layout, unify driver cards to a blue accent, and bump fonts to match the dashboard.

**Architecture:** Restructure one Blade view (`macro-growth.blade.php`) so the period row renders unconditionally and only the industry-driver `<aside>` is conditional, then apply matching CSS changes and remove the now-dead CSS in `portal.css`.

**Tech Stack:** Laravel Blade + Livewire, plain CSS served raw from `public/` (no build step). Spec: `docs/superpowers/specs/2026-05-22-industry-page-redesign-design.md`.

**Testing note:** Visual Blade/CSS change with no automated test harness for this view. Each task is verified by browser inspection; Task 7 is the full verification pass.

---

### Task 1: Restructure the macro-growth Blade view

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php`

- [ ] **Step 1: Replace the entire file**

Overwrite `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php` with exactly:

```blade
@php
    use App\Support\DashboardCatalog;

    $macroPeriods = [
        ['label' => 'I чорак',   'period' => 'q1',   'state' => 'Амалда', 'cls' => 'actual'],
        ['label' => 'II чорак',  'period' => 'h1',   'state' => 'Режа',   'cls' => 'plan'],
        ['label' => 'III чорак', 'period' => 'm9',   'state' => 'Режа',   'cls' => 'plan'],
        ['label' => 'Йиллик',    'period' => 'year', 'state' => 'Мақсад', 'cls' => 'target'],
    ];

    $showIndustryDrivers = $kpi === 'industry';
    $industryDrivers = $showIndustryDrivers
        ? DashboardCatalog::industryDrivers($industryDriverFacts ?? null)
        : [];
@endphp

<section class="macro-growth-panel" aria-label="{{ $indicator->label_full ?? '' }} ўсиш мониторинги">
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
    @if($showIndustryDrivers)
        <aside class="industry-driver-panel" aria-label="Саноат драйверлари">
            <div class="industry-driver-head">
                <strong>Саноат драйверлари</strong>
                <span class="info-dot" title="Саноатга боғланган туманлар кесимидаги драйверлар">i</span>
            </div>
            <div class="industry-driver-list">
                @foreach($industryDrivers as $item)
                    <a class="industry-driver-card {{ $item['cls'] }}"
                       href="{{ route('districts') }}?indicatorCode={{ $item['id'] }}&period=h1">
                        <span class="driver-icon {{ $item['cls'] }}">
                            @include('partials.icon', ['name' => $item['icon']])
                        </span>
                        <span class="industry-driver-body">
                            <span class="industry-driver-title">
                                <strong>{{ $item['title'] }}</strong>
                                <span>{{ $item['desc'] }}</span>
                            </span>
                            <span class="industry-driver-metrics">
                                <span class="industry-driver-metric">
                                    <span>I ярим йиллик</span>
                                    <strong>{{ $item['h1'] }}</strong>
                                    @if($item['h1Note'] !== '')
                                        <small>{{ $item['h1Note'] }}</small>
                                    @endif
                                </span>
                                <span class="industry-driver-divider" aria-hidden="true"></span>
                                <span class="industry-driver-metric">
                                    <span>Йиллик кутилиш</span>
                                    <strong>{{ $item['year'] }}</strong>
                                    @if($item['yearNote'] !== '')
                                        <small>{{ $item['yearNote'] }}</small>
                                    @endif
                                </span>
                            </span>
                        </span>
                        <span class="industry-driver-arrow" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </div>
        </aside>
    @endif
</section>
```

Notes: the `@php` block drops the now-unused `$yearRow`, `$yearGrowth`,
`$rawYearGrowth`, `$deltaPp`, `$showPill`, `$values`, `$maxDelta`. The
`mini-button`, `.macro-main-panel`, `.macro-section-title`, `.macro-hero-card`,
and `.macro-period-grid`/`.macro-period-card` markup are gone. The
`$item['cls']` values (`green`/`blue`/`orange`) are still emitted — they simply
no longer have color-variant CSS after Task 3.

- [ ] **Step 2: Commit**

```bash
git add backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php
git commit -m "feat(industry): stacked layout, drop hero card and mini-button"
```

---

### Task 2: Panel layout — single column, blue-bordered driver panel

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Delete the two-column `with-side` rule**

Delete this exact block:

```css
    .macro-layout-card .macro-growth-panel.with-side {
      grid-template-columns: minmax(0, 1.04fr) minmax(340px, .7fr);
    }
```

- [ ] **Step 2: Replace the shared panel rule + macro-main-panel rule**

Replace this exact block:

```css
    .macro-main-panel,
    .industry-driver-panel {
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      box-shadow: var(--shadow-sm);
      min-width: 0;
    }

    .macro-main-panel {
      padding: 24px;
      display: grid;
      gap: 16px;
    }
```

with:

```css
    .industry-driver-panel {
      border: 1px solid var(--blue);
      border-radius: 12px;
      background: #fff;
      box-shadow: var(--shadow-sm);
      min-width: 0;
    }
```

- [ ] **Step 3: Commit**

```bash
git commit -am "style(industry): single-column panel, blue-bordered driver panel"
```

---

### Task 3: Driver list 3-up + blue-unified icons

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Make the driver list a 3-column grid**

Replace this exact block:

```css
    .industry-driver-list {
      display: grid;
      gap: 16px;
    }
```

with:

```css
    .industry-driver-list {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 16px;
    }
```

- [ ] **Step 2: Bump the driver-panel head font**

Replace this exact block:

```css
    .industry-driver-head strong {
      color: var(--ink);
      font-size: 18px;
      font-weight: 850;
      letter-spacing: -0.012em;
    }
```

with:

```css
    .industry-driver-head strong {
      color: var(--ink);
      font-size: 22px;
      font-weight: 850;
      letter-spacing: -0.012em;
    }
```

- [ ] **Step 3: Give `.driver-icon` a single blue treatment**

Replace this exact block:

```css
    .driver-icon {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      display: grid;
      place-items: center;
    }
```

with:

```css
    .driver-icon {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      color: var(--blue);
      background: var(--blue-soft);
    }
```

- [ ] **Step 4: Delete the per-color icon variants**

Delete this exact block:

```css
    .driver-icon.green { color: #08742d; background: #eef9f1; }
    .driver-icon.blue { color: var(--blue); background: #eef5ff; }
    .driver-icon.orange { color: #d35a0f; background: #fff3ea; }
```

- [ ] **Step 5: Delete the per-color metric-value overrides**

Delete this exact block:

```css
    .industry-driver-card.green .industry-driver-metric strong { color: #08742d; }
    .industry-driver-card.orange .industry-driver-metric strong { color: #d35a0f; }
```

`.industry-driver-metric strong` already has `color: var(--blue)` in its base
rule, so all values now render blue.

- [ ] **Step 6: Commit**

```bash
git commit -am "style(industry): 3-up driver list, blue-unified icons and values"
```

---

### Task 4: Bump driver-card fonts

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Title and description**

Replace this exact block:

```css
    .industry-driver-title strong {
      color: var(--ink);
      font-size: 16px;
      font-weight: 850;
    }

    .industry-driver-title span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.3;
    }
```

with:

```css
    .industry-driver-title strong {
      color: var(--ink);
      font-size: 19px;
      font-weight: 850;
    }

    .industry-driver-title span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 14px;
      line-height: 1.3;
    }
```

- [ ] **Step 2: Metric label, value, and note**

Replace this exact block:

```css
    .industry-driver-metric span {
      color: var(--muted);
      font-size: 11.5px;
      font-weight: 700;
    }

    .industry-driver-metric strong {
      color: var(--blue);
      font-size: 18px;
      font-weight: 900;
      letter-spacing: -0.012em;
      font-variant-numeric: tabular-nums;
    }

    .industry-driver-metric small {
      color: var(--muted);
      font-size: 10.8px;
      line-height: 1.25;
    }
```

with:

```css
    .industry-driver-metric span {
      color: var(--muted);
      font-size: 13px;
      font-weight: 700;
    }

    .industry-driver-metric strong {
      color: var(--blue);
      font-size: 26px;
      font-weight: 900;
      letter-spacing: -0.012em;
      font-variant-numeric: tabular-nums;
    }

    .industry-driver-metric small {
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.25;
    }
```

- [ ] **Step 3: Commit**

```bash
git commit -am "style(industry): larger driver-card fonts"
```

---

### Task 5: Remove dead CSS

**Files:**
- Modify: `backend/public/css/portal.css`

All blocks below are unreferenced after Task 1 (the only Blade view using them
was `macro-growth.blade.php`).

- [ ] **Step 1: Delete `.macro-section-title` rules**

Delete this exact block:

```css
    .macro-section-title {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .macro-section-title strong {
      color: var(--ink);
      font-size: 18px;
      font-weight: 850;
      letter-spacing: -0.012em;
    }

    .macro-section-title span {
      color: var(--muted);
      font-size: 13px;
      font-weight: 600;
    }
```

- [ ] **Step 2: Delete `.macro-hero-card` and `.macro-hero-copy` rules**

Delete this exact block:

```css
    .macro-hero-card {
      position: relative;
      min-height: 120px;
      display: grid;
      grid-template-columns: 1fr;
      align-items: center;
      padding: 16px 34px;
      border: 1px solid rgba(19, 126, 61, .18);
      border-radius: 12px;
      background:
        linear-gradient(180deg, #f5fbf7 0%, #eff8f3 100%),
        repeating-linear-gradient(90deg, transparent 0 56px, rgba(15, 135, 58, .035) 56px 57px);
      overflow: hidden;
    }

    .macro-hero-copy {
      min-width: 0;
      display: grid;
      gap: 6px;
      align-content: center;
      text-align: center;
      justify-items: center;
    }

    .macro-hero-copy span {
      color: var(--muted);
      font-size: 15px;
      font-weight: 800;
    }

    .macro-hero-copy strong {
      color: #08742d;
      font-size: clamp(48px, 5vw, 72px);
      line-height: .9;
      letter-spacing: -0.055em;
      font-weight: 950;
      font-variant-numeric: tabular-nums;
    }

    .macro-hero-copy small {
      color: var(--muted);
      font-size: 15px;
      font-weight: 700;
    }
```

- [ ] **Step 3: Delete the `.macro-period-grid` / `.macro-period-card` / `.macro-mini-bar` rules**

Delete this exact block:

```css
    .macro-layout-card .macro-period-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .macro-layout-card .macro-period-card {
      min-height: 128px;
      justify-items: center;
      text-align: center;
      background: #f9fbfd;
    }

    .macro-layout-card .macro-period-card.actual {
      background: #f2fbf5;
      border-color: rgba(15, 135, 58, .18);
    }

    .macro-layout-card .macro-period-card strong {
      color: var(--blue);
      font-size: clamp(28px, 2.5vw, 38px);
    }

    .macro-layout-card .macro-period-card.actual strong {
      color: #08742d;
    }

    .macro-layout-card .macro-period-head {
      width: 100%;
      display: grid;
      justify-items: center;
      gap: 6px;
    }

    .macro-layout-card .macro-mini-bar {
      display: none;
    }
```

- [ ] **Step 4: Delete the `.industry-driver-panel .mini-button` override**

Delete this exact block:

```css
    .industry-driver-panel .mini-button {
      width: 100%;
      justify-content: center;
      min-height: 38px;
      color: var(--blue-dark);
      border-color: var(--blue);
      background: #fff;
    }
```

- [ ] **Step 5: Delete the responsive hero rules**

Delete these two exact lines (inside the narrow-width media block):

```css
      .macro-hero-card { grid-template-columns: 1fr; text-align: center; }
      .macro-hero-copy { text-align: center; justify-items: center; }
```

- [ ] **Step 6: Commit**

```bash
git commit -am "chore(industry): remove CSS dead after hero/mini-button removal"
```

---

### Task 6: Responsive — collapse the driver list

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Add the single-column rule for narrow screens**

Find this exact line (inside the narrow-width media block):

```css
      .industry-driver-card { grid-template-columns: 48px minmax(0, 1fr); }
```

Insert immediately after it:

```css
      .industry-driver-list { grid-template-columns: 1fr; }
```

- [ ] **Step 2: Commit**

```bash
git commit -am "style(industry): collapse driver list to one column on narrow screens"
```

---

### Task 7: Verify against the design

**Files:** none — verification only.

- [ ] **Step 1: Serve the app**

Run: `php artisan serve` (from `backend/`)
Expected: server on `http://127.0.0.1:8000`.

- [ ] **Step 2: Check the industry KPI**

Open the dashboard → macro module → select the **Саноат** KPI. Hard refresh
(`Ctrl+F5`).
Expected: no hero card, no "Саноат деталларига ўтиш" button; a full-width
period row on top (4 cells, blue separators); below it a single row of 3
**blue** driver cards inside a blue-bordered panel.

- [ ] **Step 3: Check another macro KPI**

Select the **ЯҲМ** KPI (or agriculture/construction/services).
Expected: its period row is unchanged — no driver panel, no regression.

- [ ] **Step 4: Check driver links**

Click a driver card.
Expected: navigates to the districts page (`route('districts')` with the
driver's `indicatorCode`).

- [ ] **Step 5: Check responsive collapse**

Narrow the browser window.
Expected: the 3 driver cards collapse to a single column; the period row
collapses per its existing responsive rule.

---

## Self-Review

**Spec coverage:** Remove hero/mini-button + stacked layout → Task 1 (markup) +
Task 2 (`with-side` removal) + Task 5 (dead CSS). Blue-bordered driver panel →
Task 2. 3-up driver list + head font → Task 3. Blue-unified icons/values →
Task 3. Font bumps → Task 4. Dead-CSS removal → Task 5. Responsive driver list
→ Task 6. Out-of-scope guard (other macro KPIs unchanged) → Task 7 Step 3. All
spec sections covered.

**Placeholder scan:** No TBD/TODO; every step shows the complete Blade or CSS
content.

**Type consistency:** Class names (`industry-driver-panel`,
`industry-driver-list`, `driver-icon`, `macro-period-row`, `macro-period-cell`)
are used identically across tasks. The Blade in Task 1 emits exactly the
selectors that Tasks 2-6 style. The `$item['cls']` values are intentionally
left in the markup with no matching CSS (documented in Task 1 / Task 3).
