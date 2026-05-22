# KPI Pages Design Rollout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the 6 non-macro dashboard modules (inflation, budget, budget invest, foreign invest, export, employment) to match the macro module's design language — white module card, larger fonts, `#2b61af` accent, dark text over muted greys.

**Architecture:** Mostly CSS in `backend/public/css/portal.css` plus one generalizing edit to `kpi-dashboard.blade.php`. `portal.css` is plain hand-written CSS served directly — no build step. Each panel keeps its data and markup; CSS is revised to a shared design language. Each task is verified visually with a headless screenshot.

**Tech Stack:** Laravel 11, Livewire 3, Blade, plain CSS, Pest.

**Spec:** `docs/superpowers/specs/2026-05-22-kpi-pages-design-rollout-design.md`

---

## Context for the implementer

- Repo root: `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali`. Branch: `v7-design-polish` (already checked out — do not switch branches).
- Run `git` from the repo root. Run `php artisan ...` from `backend/`.
- The dashboard test scope is the verification suite: `php artisan test --filter "Dashboard|Macro|Kpi|Scoreline"`. Do NOT run the bare full suite — it has a pre-existing unrelated OOM crash.
- This branch has concurrent edits. Before each task, `git status` — if `portal.css` has uncommitted changes you did not make, stop and report (do not bundle others' work).

## Design language (the target — concrete values)

The macro module is the reference. Apply these everywhere in the 6 modules:

- **Module card** (`.module-card`): `background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:var(--shadow); padding:22px`. Already defined — do not redefine.
- **Panel container**: transparent — no own `background`/`border`/`box-shadow`/`border-radius`; it lays out inside the white module card.
- **Sub-cards / component cards** inside a panel: `background:#fff; border:1px solid var(--line); border-radius:14px`.
- **Accent blue:** `var(--blue)` (`#2b61af`).
- **Big values** (the headline number in a card): `font-size: clamp(28px, 2.6vw, 38px); font-weight:900; line-height:1; letter-spacing:-0.02em; font-variant-numeric:tabular-nums;` colored `var(--ink)`, or `var(--blue)` when it is the card's emphasis figure.
- **Section titles** (e.g. `.lagging-title`, panel headers): `font-size:19px; font-weight:800; color:var(--ink)`.
- **Labels** (a card's name/caption above the value): `font-size:14px; font-weight:700; color:var(--ink)`.
- **Notes / sub-captions:** `font-size:13px; color:var(--ink)` (soft dark — never `var(--muted)`).
- **Icon badges:** `width/height:52px; border-radius:14px; background:var(--blue-soft); color:var(--blue)`; inner `svg` `30px`.
- **Progress bars / track fills:** track `var(--line)`; fill `var(--blue)`.
- Replace every `var(--muted)` text color in these panels with `var(--ink)` (matches the macro panels' "dark over muted" treatment).

## Verification recipe

Used by every task. Dev server on port 8123.

1. Ensure the server runs — from `backend/`: check `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8123/dashboard`; if not `200`, start `php artisan serve --host=127.0.0.1 --port=8123` in the background.
2. Screenshot a module (substitute `<code>`):

```powershell
& "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1920,1010 --screenshot="C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali\backend\kpi-verify.png" "http://127.0.0.1:8123/dashboard?module=<code>"
```

3. Read `backend/kpi-verify.png`. Compare against a macro-module screenshot (`?module=macro`) for consistency: white card shell, large fonts, `#2b61af` blue, dark labels.
4. `kpi-verify.png` is temporary — delete it before finishing each task; never commit it.

Module codes: `inflation`, `budget`, `budget_invest`, `foreign_invest`, `export`, `employment`, `macro`.

---

### Task 1: Module-card shell — generalize to every module

**Files:**
- Modify: `backend/resources/views/livewire/kpi-dashboard.blade.php`
- Modify: `backend/public/css/portal.css` (the `.module-flow` rule, `.module-card` area)

- [ ] **Step 1: Replace the dashboard Blade**

Replace the entire contents of `backend/resources/views/livewire/kpi-dashboard.blade.php` with:

```blade
<div>
    <livewire:dashboard.kpi-module-tabs :module="$module" :key="'tabs-'.$module" />

    <div class="module-card">
        <div class="module-heading">
            <div>
                <h2>{{ $moduleLabel }}</h2>
                <p>{{ $moduleIntro }}</p>
            </div>
        </div>

        @if($hasFrontCards)
            <livewire:dashboard.kpi-front-cards :module="$module" :kpi="$kpi" :key="'front-'.$module.'-'.$kpi" />
        @else
            <livewire:dashboard.kpi-workspace-card :module="$module" :kpi="$kpi" :key="'work-'.$module.'-'.$kpi" />
        @endif
    </div>

    @if($hasFrontCards)
        <div class="module-card module-panel-card">
            <livewire:dashboard.kpi-workspace-card :module="$module" :kpi="$kpi" :key="'work-'.$module.'-'.$kpi" />
        </div>
    @endif

    @if($module === 'macro' && $kpi === 'industry')
        <livewire:dashboard.industry-driver-panel :key="'industry-drivers'" />
    @endif

    <livewire:dashboard.kpi-scoreline :module="$module" :kpi="$kpi" :key="'score-'.$module.'-'.$kpi" />
</div>
```

- [ ] **Step 2: Neutralize the inner workspace-card chrome**

In `backend/public/css/portal.css`, find the `.module-flow { display: contents; }` rule. Replace that single line with:

```css
    .module-flow { display: contents; }

    .module-card .kpi-monitor-grid { gap: 0; }

    .module-card > .kpi-monitor-card,
    .module-card .kpi-monitor-grid > .kpi-monitor-card {
      border: 0;
      background: transparent;
      box-shadow: none;
      border-radius: 0;
      overflow: visible;
    }
```

(The `.module-flow` rule is now unused but harmless — left for safety. The new rules stop the panel's `.kpi-monitor-card` from drawing a card inside the module card.)

- [ ] **Step 3: Verify**

Run the Verification recipe for `inflation`, `budget`, and `employment`. Expected: each module now sits inside a white rounded card (no naked panel). The macro module (`?module=macro`) must be unchanged. Layout may look unstyled inside — later tasks restyle the panels; here only confirm the card shell wraps correctly and macro is intact.

- [ ] **Step 4: Run tests**

Run from `backend/`: `php artisan test --filter "Dashboard|Kpi"`
Expected: green. (`DashboardRoutesTest` asserts `class="module-card"` exists and the `module-flow`/employment tests — confirm none fail. If `dashboard non-macro module uses the flow wrapper, not the card` fails, update that test: non-macro modules now use `module-card` too — change it to assert `class="module-card"` is present for `module=budget`.)

- [ ] **Step 5: Commit**

```bash
git add backend/resources/views/livewire/kpi-dashboard.blade.php backend/public/css/portal.css backend/tests/Feature/Http/DashboardRoutesTest.php
git commit -m "feat(dashboard): module-card shell for every module"
```

---

### Task 2: Scoreline — apply the new style to all modules

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php`
- Modify: `backend/public/css/portal.css` (`.is-macro` scoreline rules)

- [ ] **Step 1: Read the current scoreline**

Read `backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php`. Its root element currently is `class="{{ trim('scoreline execution-strip '.($module === 'macro' ? 'is-macro' : '')) }}"`.

- [ ] **Step 2: Always emit the modifier class**

Change that root `class` attribute so the modifier is always present — replace the `($module === 'macro' ? 'is-macro' : '')` conditional with the literal `'is-scored'`:

```blade
<div class="scoreline execution-strip is-scored">
```

- [ ] **Step 3: Rename the CSS scope**

In `backend/public/css/portal.css`, every scoreline rule selector that uses `.is-macro` (the block of `.scoreline.execution-strip.is-macro`, `.is-macro .scoreline-copy`, `.is-macro .exec-*`, etc.) — replace `.is-macro` with `.is-scored`. This is a mechanical rename of the existing rule block; do not change any property values. Use a careful find/replace within that scoreline rule block only (do not touch `.macro-layout` or other `macro` selectors).

- [ ] **Step 4: Verify**

Run the Verification recipe for `budget` and `macro`. Both scorelines must now render the same styled execution strip (solid colored count blocks, large percentage). Confirm no other module's scoreline still shows the old plain style.

- [ ] **Step 5: Run tests**

Run from `backend/`: `php artisan test --filter "Dashboard|Scoreline"`
Expected: green. `DashboardRoutesTest` asserts `scoreline execution-strip` (base classes preserved) — still passes. If a test asserts the literal `is-macro`, update it to `is-scored`.

- [ ] **Step 6: Commit**

```bash
git add backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php backend/public/css/portal.css backend/tests/
git commit -m "style(dashboard): unified scoreline across all modules"
```

---

### Task 3: `kpi-monitor-head` restyle

The panel head (icon badge + short label + full label + "Туманлар кесими" link + watermark) shows for non-macro modules. Restyle it to the design language.

**Files:**
- Modify: `backend/public/css/portal.css` (`.kpi-monitor-head`, `.small-icon`, `.kpi-head-district`, `.head-watermark` rules — located by searching those selectors)

- [ ] **Step 1: Read the current rules**

In `backend/public/css/portal.css`, read the rule blocks for `.kpi-monitor-head`, `.kpi-monitor-head h3`, `.kpi-monitor-head p`, `.small-icon`, `.small-icon svg`, `.kpi-head-district`.

- [ ] **Step 2: Restyle to the design language**

Update those rules so the head matches the Design language section: `.small-icon` icon badge `56px`, `border-radius:14px`, `background:var(--blue-soft)`, `color:var(--blue)`, inner `svg 32px`; `.kpi-monitor-head h3` `font-size:21px; font-weight:800; color:var(--ink)`; `.kpi-monitor-head p` `font-size:14px; color:var(--ink)`; `.kpi-head-district` styled as a clear pill button — `background:var(--blue); color:#fff; border:0; border-radius:10px; padding:9px 14px; font-weight:700` (and its `:hover` a touch darker, `#234f8f`). Keep the head's grid layout. The head's `border-bottom` stays as a `1px solid var(--line)` separator. Show the exact before/after for each rule when you make the edits.

- [ ] **Step 3: Verify**

Run the Verification recipe for `inflation` and `budget`. The panel head reads large and dark, the drill link is a blue pill. Compare to the macro look.

- [ ] **Step 4: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): restyle kpi-monitor-head to design language"
```

---

### Task 4: `quarter-matrix` panel restyle

`quarter-matrix` is used by `budget`, `foreign_invest`, `export` (and employment sub-KPIs). CSS block: `portal.css` ~lines 1851-2066.

**Files:**
- Modify: `backend/public/css/portal.css` (the `.quarter-matrix` block)

- [ ] **Step 1: Read the panel + its CSS**

Read `backend/resources/views/livewire/dashboard/panels/quarter-matrix.blade.php` and the `.quarter-matrix` CSS block in `portal.css` (search for `.quarter-matrix {`).

- [ ] **Step 2: Restyle to the design language**

Revise the `.quarter-matrix` / `.quarter-row` / `.q-head` / `.q-hero` / `.q-hero-value` / `.q-hero-label` / `.q-aux` / `.q-aux-row` rules to the Design language section: panel background transparent; each `.quarter-row` a white sub-card (`#fff`, `1px solid var(--line)`, `border-radius:14px`); `.q-hero-value` a big value (`clamp(28px,2.6vw,38px)`, weight 900, `var(--ink)`, `var(--blue)` if it is the headline figure); labels `var(--ink)` 14px; replace all `var(--muted)` with `var(--ink)`; status chips keep their semantic colors. Keep the 4-column grid and the markup. Show before/after for each rule edited.

- [ ] **Step 3: Verify**

Run the Verification recipe for `budget`, `foreign_invest`, and `export` (all three use this panel). Each must show large dark values, white sub-cards, `#2b61af` accents, consistent with macro.

- [ ] **Step 4: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): restyle quarter-matrix panel"
```

---

### Task 5: `inflation-details` panel restyle

CSS block: `portal.css` ~lines 3125-3390 (`.drivers`, `.lagging`, `.composition`, `.driver-card`, `.component-card`, `.product-card`).

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Read the panel + its CSS**

Read `backend/resources/views/livewire/dashboard/panels/inflation-details.blade.php` and the CSS block (search `.drivers {`, `.lagging {`, `.composition {`, `.component-card`, `.product-card`).

- [ ] **Step 2: Restyle to the design language**

Revise `.drivers` / `.lagging` / `.lagging-title` / `.driver-grid` / `.driver-card` / `.composition` / `.composition-grid` / `.component-card` / `.product-card` / `.product-icon` / `.product-body` / `.product-name` / `.product-value` / `.product-note` to the Design language section: panel transparent; `.lagging-title` a 19px/800/`var(--ink)` section title; `.driver-card` and `.product-card` white sub-cards with `1px solid var(--line)`, radius 14; `.product-value` / `.driver-card strong` big values; all label/name/note text `var(--ink)`; the `.product-icon` badge per the icon-badge spec. Keep markup. Show before/after per rule.

- [ ] **Step 3: Verify**

Run the Verification recipe for `inflation`. Caps, food price cards, warehouse cards — large dark values, white cards, consistent with macro.

- [ ] **Step 4: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): restyle inflation panel"
```

---

### Task 6: `budget-investment` panel restyle

CSS block: `portal.css` ~lines 2607-2818.

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Read the panel + its CSS**

Read `backend/resources/views/livewire/dashboard/panels/budget-investment.blade.php` and the `.budget-invest-*` CSS block.

- [ ] **Step 2: Restyle to the design language**

Revise `.budget-invest-panel` / `.budget-invest-summary` / `.budget-invest-body` / `.budget-periods-grid` / `.budget-period-card` / `.budget-period-top` / `.budget-period-meta` / `.budget-progress` / `.budget-dynamics-card` / `.budget-dynamics-head` / `.budget-dynamics-list` to the Design language section: panel transparent; the summary bar cells and `.budget-period-card` as white sub-cards; big dark values; progress bars track `var(--line)` / fill `var(--blue)`; all muted text → `var(--ink)`. In `budget-investment.blade.php` the SVG line chart uses stroke/fill colors — change any hard-coded blue in the chart markup to `#2b61af` (a small Blade edit is allowed here). Show before/after per rule.

- [ ] **Step 3: Verify**

Run the Verification recipe for `budget_invest`. Summary bar, period cards, SVG trend chart — large dark values, `#2b61af` chart, white cards.

- [ ] **Step 4: Commit**

```bash
git add backend/public/css/portal.css backend/resources/views/livewire/dashboard/panels/budget-investment.blade.php
git commit -m "style(dashboard): restyle budget-investment panel"
```

---

### Task 7: `unemployment-details` + `poverty-details` panels restyle

Both share the `.poverty-section` / `.poverty-stat` classes. CSS block: `portal.css` ~lines 3392-3647.

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Read the panels + CSS**

Read `backend/resources/views/livewire/dashboard/panels/unemployment-details.blade.php`, `poverty-details.blade.php`, and the `.poverty-*` / `.employment-driver-section` CSS block.

- [ ] **Step 2: Restyle to the design language**

Revise `.poverty-section` / `.poverty-head` / `.poverty-stats` / `.poverty-stat` / `.poverty-stat-icon` / `.poverty-stat-body` / `.poverty-stat-label` / `.poverty-stat-value` / `.poverty-stat-meta` / `.poverty-progress` / `.poverty-territory-*` / `.employment-driver-section` to the Design language section: panel transparent; `.poverty-stat` white sub-cards; `.poverty-stat-icon` per the icon-badge spec; `.poverty-stat-value` a big value; progress bars track `var(--line)` / fill `var(--blue)`; all label/meta text `var(--ink)`. Keep markup. Show before/after per rule.

- [ ] **Step 3: Verify**

Run the Verification recipe for `employment` and click into `poverty` (`?module=employment&kpi=poverty`) and `unemployment` (`?module=employment&kpi=unemployment`). Driver stat cards — large dark values, white cards, `#2b61af` progress, consistent with macro.

- [ ] **Step 4: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): restyle employment driver panels"
```

---

### Task 8: Employment front cards restyle

`employment-layout` front cards — 6 KPI buttons, no hero. CSS: `portal.css` — search `.front-kpis.module-kpis.employment-layout`.

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Read the current rules**

In `portal.css`, read the `.front-kpis.module-kpis.employment-layout` rule and its `.front-kpi`, `.kpi-icon`, `.kpi-icon svg`, `h3`, `.front-kpi-copy` child rules. Compare to the macro sector-card rules (`.front-kpis.module-kpis.macro-layout .front-kpi:not(.parent) ...`) which are the styling reference.

- [ ] **Step 2: Restyle to match the macro sector cards**

Update the `employment-layout` `.front-kpi` rules so the 6 cards match the macro sector-card look — bigger icon badge (`var(--blue-soft)` bg, `var(--blue)` icon, per the icon-badge spec), bigger label, the new card border/radius, dark text. Keep it a multi-column grid sized for 6 cards (the existing `grid-template-columns` for `employment-layout` — keep its column count or set `repeat(3, minmax(0,1fr))`). No hero/parent treatment. Show before/after per rule.

- [ ] **Step 3: Verify**

Run the Verification recipe for `employment`. The 6 KPI cards — bigger icons, dark labels, new card style, consistent with the macro sector cards.

- [ ] **Step 4: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): restyle employment front cards"
```

---

### Task 9: Responsive sweep + final cross-module verification

**Files:**
- Modify: `backend/public/css/portal.css` (responsive `@media` blocks ~lines 5789-5812 and the 760px block)

- [ ] **Step 1: Check responsive rules**

In `portal.css`, read the responsive `@media` overrides that reference `quarter-matrix`, `budget-invest`, `composition-grid`, `driver-grid`, `poverty`-related grids. Confirm the restyled panels still collapse cleanly to 1 column on narrow widths; adjust any grid override whose selector or intent the restyle changed. Show before/after for any edited rule.

- [ ] **Step 2: Full cross-module verification**

Run the Verification recipe for every module: `inflation`, `budget`, `budget_invest`, `foreign_invest`, `export`, `employment`, and `macro`. Confirm: each non-macro module shows the white module card, large fonts, `#2b61af` accent, dark labels, unified scoreline — visually consistent with `macro`; and `macro` itself is unchanged.

- [ ] **Step 3: Run the dashboard test scope**

Run from `backend/`: `php artisan test --filter "Dashboard|Macro|Kpi|Scoreline"`
Expected: green, no regressions.

- [ ] **Step 4: Cleanup + commit**

Delete any leftover `kpi-verify.png` / temp screenshots (`rm -f backend/kpi-verify.png`). Then:

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): responsive sweep for restyled KPI panels"
```

---

## Self-review

**Spec coverage:**
- Module-card shell generalized → Task 1 ✓
- `kpi-monitor-head` restyle → Task 3 ✓
- 5 panels restyled — `quarter-matrix` → Task 4; `inflation-details` → Task 5; `budget-investment` → Task 6; `unemployment-details` + `poverty-details` → Task 7 ✓
- Employment front cards → Task 8 ✓
- Scoreline unified → Task 2 ✓
- Responsive + verification → Task 9 ✓
- Design language (the spec's target values) → the "Design language" section, referenced by every restyle task ✓

**Placeholder scan:** Task 1 and Task 2 carry exact code. The panel restyle tasks (3-8) are necessarily applied-and-tuned: each gives the exact CSS block location (file + line range + selector list), the concrete Design-language values to apply, and a screenshot-verification step — the same applied-and-verified pattern used successfully for the macro hero. Each task instructs "show before/after per rule," so the implementer produces concrete diffs. This is the correct granularity for a restyle whose exact per-panel values can only be settled against a render.

**Type/name consistency:** The scoreline modifier class is `is-scored` consistently (Task 2 Blade + CSS rename). `.module-card` is the shell class throughout. Module codes used in verification (`inflation`, `budget`, `budget_invest`, `foreign_invest`, `export`, `employment`, `macro`) match `DashboardCatalog::MODULES`. Task order: shell (1) → scoreline (2) → head (3) → panels (4-7) → front cards (8) → responsive (9); each restyle task runs after the shell exists.

**Concurrency note:** the scoreline and an `industry-driver-panel` are under concurrent edits on this branch. Task 2 renames the scoreline scope (`is-macro` → `is-scored`); if a concurrent commit changes the scoreline rules first, re-base Task 2's rename onto the current rule block. Every task starts with a `git status` check (see Context) to avoid bundling others' uncommitted work.
