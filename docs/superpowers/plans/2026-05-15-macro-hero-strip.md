# Macro Hero Strip Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the stacked title/hero/quarter-grid layout on grp, agriculture, construction, services dashboard pages with a single dark horizontal strip (caption + delta pill + big % on left, 4 period chips on right).

**Architecture:** One blade conditional branches the existing `.macro-main-panel`: industry KPI keeps the old hero markup (it owns the side-driver column); the other 4 macro KPIs render `<section class="macro-hero-strip">`. CSS adds one self-contained block scoped to `.macro-hero-strip`. No data-model changes — all values come from the existing `$rows` keyed by period and from `$indicator`.

**Tech Stack:** Laravel 11 + Livewire 3, Blade templates, vanilla CSS in `public/css/portal.css`, Pest 3 feature tests.

---

## File Structure

- `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php` — branch on `$showIndustryDrivers`, add `@php` derived values + new `<section class="macro-hero-strip">` markup.
- `backend/public/css/portal.css` — add `.macro-hero-strip` block (≈90 lines) at the end of the macro-layout section + responsive overrides inside the existing 980px media query.
- `backend/tests/Feature/Livewire/MacroHeroStripTest.php` — new Pest feature test (strip-present for grp, strip-absent for industry).
- `backend/tests/Pest.php` — register the new feature test (the `Feature` glob already covers it; no change expected — verify).

---

## Task 1: Feature test — strip renders on grp KPI

**Files:**
- Create: `backend/tests/Feature/Livewire/MacroHeroStripTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Livewire\Dashboard\KpiWorkspaceCard;
use Livewire\Livewire;

it('renders dark hero strip on grp KPI page', function () {
    Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'grp'])
        ->assertSeeHtml('class="macro-hero-strip"')
        ->assertSeeHtml('macro-hero-strip__chip is-actual')
        ->assertSeeHtml('macro-hero-strip__chip is-target');
});

it('does not render hero strip on industry KPI page', function () {
    Livewire::test(KpiWorkspaceCard::class, ['kpi' => 'industry'])
        ->assertDontSeeHtml('class="macro-hero-strip"')
        ->assertSeeHtml('class="macro-hero-card"');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Livewire/MacroHeroStripTest.php`
Expected: FAIL with `assertSeeHtml` not finding `macro-hero-strip` — strip markup not yet added.

- [ ] **Step 3: Commit the failing test**

```powershell
git add backend/tests/Feature/Livewire/MacroHeroStripTest.php
git commit -m "test(macro-hero-strip): failing assertions for dark strip rollout"
```

---

## Task 2: Blade — add derived values + branch markup

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php` (lines 1-69 region — `@php` block + `.macro-main-panel` interior)

- [ ] **Step 1: Replace the `@php` block (lines 1-27) with extended derived values**

Replace the entire `@php ... @endphp` block at the top of the file with:

```php
@php
    use App\Support\DashboardCatalog;

    $macroPeriods = [
        ['label' => 'I чорак',   'period' => 'q1',   'state' => 'Амалда', 'cls' => 'actual'],
        ['label' => 'II чорак',  'period' => 'h1',   'state' => 'Режа',   'cls' => 'plan'],
        ['label' => 'III чорак', 'period' => 'm9',   'state' => 'Режа',   'cls' => 'plan'],
        ['label' => 'Йиллик',    'period' => 'year', 'state' => 'Мақсад', 'cls' => 'target'],
    ];

    $yearRow = $rows->get('year');
    $yearGrowth = $yearRow && $yearRow->growth_pct !== null
        ? DashboardCatalog::growthValue($yearRow->growth_pct)
        : '—';

    $rawYearGrowth = $yearRow && $yearRow->growth_pct !== null ? (float) $yearRow->growth_pct : null;
    $deltaPp = $rawYearGrowth !== null ? round($rawYearGrowth - 100, 1) : null;
    $showPill = $deltaPp !== null && $deltaPp > 0;

    $values = [];
    foreach ($macroPeriods as $item) {
        $r = $rows->get($item['period']);
        $values[] = $r ? (float) ($r->growth_pct ?? 0) : 0;
    }
    $maxDelta = max(1, ...array_map(fn ($v) => abs($v - 100), $values));

    $showIndustryDrivers = $kpi === 'industry';
    $industryDrivers = $showIndustryDrivers
        ? DashboardCatalog::industryDrivers($industryDriverFacts ?? null)
        : [];
@endphp
```

Changes vs current:
- `cls` values renamed: `actual/planned/planned/planned` → `actual/plan/plan/target`.
- Added `$rawYearGrowth`, `$deltaPp`, `$showPill` for the pill.

- [ ] **Step 2: Replace the interior of `.macro-main-panel` (lines 30-64) with a conditional branch**

Find this block (current lines 30-64):

```blade
    <div class="macro-main-panel">
        <div class="macro-section-title">
            ...
        </div>
        <div class="macro-hero-card">
            ...
        </div>
        <div class="macro-period-grid">
            @foreach($macroPeriods as $item)
                ...
            @endforeach
        </div>
```

Replace the whole `<div class="macro-main-panel"> ... </div>` block (lines 30-69) with:

```blade
    @if($showIndustryDrivers)
        <div class="macro-main-panel">
            <div class="macro-section-title">
                <strong>{{ $indicator->label_short ?? '' }} ўсиши</strong>
                <span>(солиштирма нархларда)</span>
            </div>
            <div class="macro-hero-card">
                <div class="macro-hero-copy">
                    <span>Йиллик ўсиш (мақсад)</span>
                    <strong>{{ $yearGrowth }}</strong>
                    <small>2026 йил</small>
                </div>
            </div>
            <div class="macro-period-grid">
                @foreach($macroPeriods as $item)
                    @php
                        $row = $rows->get($item['period']);
                        $growthText = $row && $row->growth_pct !== null
                            ? DashboardCatalog::growthValue($row->growth_pct)
                            : '—';
                        $rawGrowth = $row && $row->growth_pct !== null ? (float) $row->growth_pct : null;
                        $delta = $rawGrowth !== null ? abs($rawGrowth - 100) : 0;
                        $width = max(8, min(100, ($delta / $maxDelta) * 100));
                        $chipClass = $item['cls'] === 'actual' ? 'blue' : 'grey';
                        $legacyCls = $item['cls'] === 'actual' ? 'actual' : 'planned';
                    @endphp
                    <div class="macro-period-card {{ $legacyCls }}">
                        <div class="macro-period-head">
                            <b>{{ $item['label'] }}</b>
                            <span class="chip {{ $chipClass }}">{{ $item['state'] }}</span>
                        </div>
                        <strong>{{ $growthText }}</strong>
                        <small>ўсиш суръати</small>
                        <i class="macro-mini-bar" aria-hidden="true"><i style="--w:{{ number_format($width, 1) }}%"></i></i>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <section class="macro-hero-strip" aria-label="{{ $indicator->label_full ?? '' }} ўсиш мониторинги">
            <div class="macro-hero-strip__lead">
                <div class="macro-hero-strip__caption-row">
                    <span class="macro-hero-strip__caption">{{ mb_strtoupper($indicator->label_short ?? '') }} ЎСИШИ · СОЛИШТИРМА НАРХЛАРДА</span>
                    @if($showPill)
                        <span class="macro-hero-strip__pill">↑ +{{ number_format($deltaPp, 1) }} п.п. 2025-йилдан</span>
                    @endif
                </div>
                <strong class="macro-hero-strip__value">{{ $yearGrowth }}</strong>
                <small class="macro-hero-strip__sub">2026 йил якуни кутилаётган баҳо</small>
            </div>
            <div class="macro-hero-strip__chips">
                @foreach($macroPeriods as $item)
                    @php
                        $row = $rows->get($item['period']);
                        $growthText = $row && $row->growth_pct !== null
                            ? DashboardCatalog::growthValue($row->growth_pct)
                            : '—';
                    @endphp
                    <div class="macro-hero-strip__chip is-{{ $item['cls'] }}">
                        <span class="macro-hero-strip__chip-label">{{ $item['label'] }}</span>
                        <strong class="macro-hero-strip__chip-value">{{ $growthText }}</strong>
                        <span class="macro-hero-strip__chip-badge">{{ $item['state'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
```

Note: the `@if/@else` block above stands in for the original `<div class="macro-main-panel">…</div>` wrapper entirely. The strip is a sibling of the (industry-only) `.macro-main-panel`, not nested inside it. Macro-composition (currently at line 66-68 with guard `if(!$showIndustryDrivers && $kpi === 'grp')`) moves into the `@else` branch after the strip — see Step 3.

- [ ] **Step 3: Preserve macro-composition for grp**

The current file (line 66-68) renders `<livewire:dashboard.macro-composition />` after the period grid when `$kpi === 'grp'` and `!$showIndustryDrivers`. The strip branch must keep this. After the `<section class="macro-hero-strip">…</section>` block, append:

```blade
            @if($kpi === 'grp')
                <livewire:dashboard.macro-composition :key="'macro-comp-inline'" />
            @endif
```

Place this between `</section>` (strip close) and the `@endif` that closes the outer `$showIndustryDrivers` branch.

Final file structure should be:

```blade
<section class="macro-growth-panel ..." aria-label="...">
    @if($showIndustryDrivers)
        <div class="macro-main-panel"> ... industry hero markup ... </div>
    @else
        <section class="macro-hero-strip" aria-label="..."> ... strip markup ... </section>
        @if($kpi === 'grp')
            <livewire:dashboard.macro-composition :key="'macro-comp-inline'" />
        @endif
    @endif
    @if($showIndustryDrivers)
        <aside class="industry-driver-panel"> ... unchanged ... </aside>
    @endif
</section>
```

- [ ] **Step 4: Run feature test to verify strip-present assertion passes; chip variant classes pending CSS**

Run: `vendor/bin/pest tests/Feature/Livewire/MacroHeroStripTest.php`
Expected: PASS (both tests) — `macro-hero-strip`, `is-actual`, `is-target` classes are now in rendered HTML; industry path renders `macro-hero-card` and no strip.

- [ ] **Step 5: Run full unit suite to confirm no regression**

Run: `php -d memory_limit=2048M vendor/bin/pest tests/Unit`
Expected: 70 passed (no change from baseline).

- [ ] **Step 6: Commit blade changes**

```powershell
git add backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php
git commit -m "feat(macro-hero-strip): blade branch for dark strip on 4 macro KPIs"
```

---

## Task 3: CSS — base strip container + lead column

**Files:**
- Modify: `backend/public/css/portal.css` — append a new block after the `.macro-layout-card .macro-mini-bar { display: none; }` rule (around line 2253), before the `.macro-layout-card .macro-composition-panel` rule.

- [ ] **Step 1: Append base strip CSS**

Insert this block between line 2253 and line 2255:

```css
    .macro-hero-strip {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(420px, 1fr);
        gap: 24px;
        align-items: center;
        padding: 22px 26px;
        border-radius: 14px;
        background:
            radial-gradient(circle at top right, rgba(120, 160, 220, .18), transparent 60%),
            linear-gradient(135deg, #0b1f3b 0%, #11294d 100%);
        color: #e4ecf7;
        box-shadow: 0 6px 24px rgba(11, 31, 59, .18);
        overflow: hidden;
    }

    .macro-hero-strip__lead {
        display: grid;
        gap: 10px;
        min-width: 0;
    }

    .macro-hero-strip__caption-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }

    .macro-hero-strip__caption {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: #7aa3d6;
    }

    .macro-hero-strip__pill {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(46, 160, 67, .16);
        color: #4ac46c;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
    }

    .macro-hero-strip__value {
        color: #4ad17a;
        font-size: clamp(56px, 6vw, 88px);
        line-height: .9;
        letter-spacing: -.04em;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
    }

    .macro-hero-strip__sub {
        color: #8aa5c8;
        font-size: 13px;
        font-weight: 500;
    }
```

- [ ] **Step 2: Smoke check that page still serves**

Run: `curl -s -o nul -w "%{http_code}" http://127.0.0.1:8765/dashboard?kpi=grp`
Expected: `200`

- [ ] **Step 3: Commit base strip CSS**

```powershell
git add backend/public/css/portal.css
git commit -m "style(macro-hero-strip): dark panel + lead column CSS"
```

---

## Task 4: CSS — chip column + 3 chip variants

**Files:**
- Modify: `backend/public/css/portal.css` — append after the `.macro-hero-strip__sub` rule from Task 3.

- [ ] **Step 1: Append chip CSS**

Insert directly after the Task 3 block:

```css
    .macro-hero-strip__chips {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
    }

    .macro-hero-strip__chip {
        display: grid;
        gap: 4px;
        padding: 12px;
        border-radius: 10px;
        justify-items: start;
        min-width: 0;
    }

    .macro-hero-strip__chip-label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .macro-hero-strip__chip-value {
        font-size: clamp(20px, 1.7vw, 26px);
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        line-height: 1;
    }

    .macro-hero-strip__chip-badge {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .macro-hero-strip__chip.is-actual {
        background: #eaf2ff;
    }
    .macro-hero-strip__chip.is-actual .macro-hero-strip__chip-label { color: #5a7596; }
    .macro-hero-strip__chip.is-actual .macro-hero-strip__chip-value { color: #0f2a52; }
    .macro-hero-strip__chip.is-actual .macro-hero-strip__chip-badge { color: #1f6feb; }

    .macro-hero-strip__chip.is-plan {
        background: rgba(255, 255, 255, .06);
        border: 1px solid rgba(255, 255, 255, .08);
    }
    .macro-hero-strip__chip.is-plan .macro-hero-strip__chip-label { color: #7aa3d6; }
    .macro-hero-strip__chip.is-plan .macro-hero-strip__chip-value { color: #e4ecf7; }
    .macro-hero-strip__chip.is-plan .macro-hero-strip__chip-badge { color: #7aa3d6; }

    .macro-hero-strip__chip.is-target {
        background: #1f6feb;
    }
    .macro-hero-strip__chip.is-target .macro-hero-strip__chip-label { color: rgba(255, 255, 255, .78); }
    .macro-hero-strip__chip.is-target .macro-hero-strip__chip-value { color: #fff; }
    .macro-hero-strip__chip.is-target .macro-hero-strip__chip-badge { color: rgba(255, 255, 255, .9); }
```

- [ ] **Step 2: Smoke check page**

Run: `curl -s -o nul -w "%{http_code}" http://127.0.0.1:8765/dashboard?kpi=agriculture`
Expected: `200`

- [ ] **Step 3: Commit chip CSS**

```powershell
git add backend/public/css/portal.css
git commit -m "style(macro-hero-strip): chip column + 3 variants (actual/plan/target)"
```

---

## Task 5: CSS — responsive overrides

**Files:**
- Modify: `backend/public/css/portal.css` — find the existing 980px media query block around line 5667-5685 and extend it.

- [ ] **Step 1: Locate the existing media query**

Run: `grep -n "macro-hero-card { grid-template-columns: 1fr" backend/public/css/portal.css`
Expected output: one match near line 5680.

- [ ] **Step 2: Append strip responsive overrides inside the same `@media` block**

Find the closing `}` of that media query (the one that contains the `.macro-hero-card { grid-template-columns: 1fr ... }` rule) and insert before the closing `}`:

```css
      .macro-hero-strip {
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 18px 20px;
      }

      .macro-hero-strip__chips {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
```

Then add a new media query immediately after the closing `}` of the 980px block:

```css
    @media (max-width: 560px) {
      .macro-hero-strip__chips {
        grid-template-columns: 1fr;
      }
    }
```

- [ ] **Step 3: Smoke check**

Run: `curl -s -o nul -w "%{http_code}" http://127.0.0.1:8765/dashboard?kpi=construction`
Expected: `200`

- [ ] **Step 4: Commit responsive overrides**

```powershell
git add backend/public/css/portal.css
git commit -m "style(macro-hero-strip): responsive overrides for 980/560 breakpoints"
```

---

## Task 6: Full suite verification + final smoke

**Files:** none — verification only.

- [ ] **Step 1: Run unit tests**

Run: `php -d memory_limit=2048M vendor/bin/pest tests/Unit`
Expected: `70 passed` (matches the pre-change baseline).

- [ ] **Step 2: Run the new feature test**

Run: `vendor/bin/pest tests/Feature/Livewire/MacroHeroStripTest.php`
Expected: `2 passed (4+ assertions)`.

- [ ] **Step 3: Smoke all 5 macro pages**

Run each in sequence:

```powershell
curl -s -o nul -w "grp %{http_code}`n" "http://127.0.0.1:8765/dashboard?kpi=grp"
curl -s -o nul -w "industry %{http_code}`n" "http://127.0.0.1:8765/dashboard?kpi=industry"
curl -s -o nul -w "agriculture %{http_code}`n" "http://127.0.0.1:8765/dashboard?kpi=agriculture"
curl -s -o nul -w "construction %{http_code}`n" "http://127.0.0.1:8765/dashboard?kpi=construction"
curl -s -o nul -w "services %{http_code}`n" "http://127.0.0.1:8765/dashboard?kpi=services"
```

Expected: all five lines end with `200`.

- [ ] **Step 4: Final commit (only if any uncommitted polish remains)**

```powershell
git status
```

If clean: skip. Otherwise stage the polish file and commit with `chore(macro-hero-strip): final polish`.

---

## Risks / verification notes

- The current `.macro-main-panel` rule (line 2141-2145) applies `padding: 24px` and a white background. The new strip is a sibling element of that wrapper, not nested inside it (see Task 2 Step 3 final structure). Confirm the rendered DOM does not double-pad: the strip sits directly under `<section class="macro-growth-panel solo">`, not inside `.macro-main-panel`.
- `MACRO_GROWTH_KPIS` list (`DashboardCatalog.php:18`) is the source of which KPIs render this panel. The blade branch uses `$kpi === 'industry'` (via `$showIndustryDrivers`) — `grp/agriculture/construction/services` all go through the strip branch automatically. No catalog change.
- Year baseline in pill copy is hardcoded `2025-йилдан` and subtitle `2026 йил` — matches the existing `2026 йил` copy in the unchanged industry branch (line 39 of the original). When the project rolls to 2027, both strings (pill + subtitle + industry hero `<small>`) will need updating together.
