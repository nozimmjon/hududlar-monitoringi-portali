# Front Macro Cards / Composition Merge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show the yearly growth value on the dashboard's front-KPI cards and remove the now-redundant inline macro-composition panel.

**Architecture:** `KpiFrontCards` already loads `period='year'` `IndicatorFact` rows but the Blade never renders them. Task 1 renders that value on each card. Task 2 removes the duplicate `macro-composition` render and deletes the dead component. No PHP/query changes.

**Tech Stack:** Laravel Blade, Livewire, plain CSS (`backend/public/css/portal.css`, served directly — no build step). No CSS test framework — verification is visual via Microsoft Edge headless screenshots, the project's established practice.

---

## Verification notes (read before starting)

- **No unit tests for CSS/markup.** Verification is by rendering the app and inspecting screenshots.
- **Dev server:** from `backend/`, if `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8741/dashboard` is not `200`, run `php artisan serve --port=8741` in the background and re-check.
- **Screenshot:** `"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1440,1000 --screenshot="OUT.png" "http://127.0.0.1:8741/dashboard"`
- Edge headless floors the viewport at ~476px — a `--window-size=390` request still renders ~476px CSS width; still valid for the mobile breakpoint.
- Work happens on the current branch (`v7-design-polish`).

## File Structure

- `backend/resources/views/livewire/dashboard/kpi-front-cards.blade.php` — front-KPI card markup. Task 1.
- `backend/public/css/portal.css` — global stylesheet. Task 1 (add value/note rules).
- `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php` — Task 2 (drop the inline composition render).
- `backend/app/Livewire/Dashboard/MacroComposition.php` — deleted in Task 2.
- `backend/resources/views/livewire/dashboard/macro-composition.blade.php` — deleted in Task 2.

---

## Task 1: Show yearly growth value on front-KPI cards

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/kpi-front-cards.blade.php` (full rewrite)
- Modify: `backend/public/css/portal.css` (add two rules; adjust the macro-layout card rule)

- [ ] **Step 1: Rewrite the front-cards markup**

Replace the **entire contents** of `backend/resources/views/livewire/dashboard/kpi-front-cards.blade.php` with:

```blade
<div class="front-kpis module-kpis {{ $layoutClass }}">
    @foreach($codes as $code)
        @php
            $ind = $indicators->get($code);
            if (! $ind) continue;
            $fact = $facts->get($code);
            $active = $code === $selectedKpi ? 'active' : '';
            $parent = ($code === 'grp' && $isMacro) ? 'parent' : '';
            $growth = $fact && $fact->growth_pct !== null
                ? \App\Support\DashboardCatalog::growthValue($fact->growth_pct)
                : null;
        @endphp
        <button class="front-kpi {{ $active }} {{ $parent }}"
                wire:click="selectKpi('{{ $code }}')"
                type="button"
                aria-label="{{ $ind->label_full }}">
            <div class="kpi-icon">
                @include('partials.icon', ['name' => $ind->icon ?? 'trend'])
            </div>
            <div class="front-kpi-copy">
                <h3>{{ $ind->label_short }}</h3>
                @if($growth !== null)
                    <strong class="front-kpi-value">{{ $growth }}</strong>
                    <span class="front-kpi-note">йиллик ўсиш</span>
                @else
                    <p>{{ $ind->label_full }}</p>
                @endif
            </div>
        </button>
    @endforeach
</div>
```

What changed: the `$meta` variable and the `<span class="front-kpi-meta">` block are gone. A `$growth` variable is derived from the already-loaded `$fact`. When a yearly growth value exists (true for all 5 macro KPIs) the card shows `front-kpi-value` + `front-kpi-note`; otherwise it keeps the `<p>` short description (graceful fallback for modules whose year facts have no `growth_pct`).

- [ ] **Step 2: Add the value/note CSS rules**

In `backend/public/css/portal.css`, find the `.front-kpi-dot` rule (near line 869):

```css
    .front-kpi-dot {
      width: 7px;
      height: 7px;
      border-radius: 999px;
      background: #9aa8b5;
      flex: 0 0 auto;
    }
```

Immediately **after** that rule's closing `}`, add:

```css

    .front-kpi-value {
      color: var(--blue);
      font-size: clamp(22px, 2vw, 28px);
      font-weight: 900;
      line-height: 1;
      letter-spacing: -0.02em;
      font-variant-numeric: tabular-nums;
    }

    .front-kpi-note {
      color: var(--muted);
      font-size: 11px;
      font-weight: 700;
    }
```

- [ ] **Step 3: Give the macro cards room for the extra line**

In `backend/public/css/portal.css`, find this rule (near line 688):

```css
    .front-kpis.module-kpis.macro-layout .front-kpi {
      min-height: 92px;
    }
```

Replace it with:

```css
    .front-kpis.module-kpis.macro-layout .front-kpi {
      min-height: 108px;
      align-items: start;
    }
```

- [ ] **Step 4: Render and screenshot**

Ensure the dev server is up (see Verification notes). Run:
```
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1440,1000 --screenshot="verify-cards.png" "http://127.0.0.1:8741/dashboard"
```
Open `verify-cards.png` with the Read tool and confirm:
- All 5 macro front-KPI cards (ЯҲМ, Саноат, Қишлоқ хўжалиги, Қурилиш, Хизматлар) show a yearly growth value in blue, with a small `йиллик ўсиш` note under it.
- Each card still shows its icon and name.
- The selected card is clearly marked (inset accent border).
- No clipping; no horizontal scrollbar.

If any check fails, fix and re-screenshot.

- [ ] **Step 5: Delete the screenshot and commit**

```bash
rm verify-cards.png
git add backend/resources/views/livewire/dashboard/kpi-front-cards.blade.php backend/public/css/portal.css
git commit -m "feat(dashboard): show yearly growth value on front-KPI cards"
```

---

## Task 2: Remove the redundant inline macro-composition

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php`
- Delete: `backend/app/Livewire/Dashboard/MacroComposition.php`
- Delete: `backend/resources/views/livewire/dashboard/macro-composition.blade.php`

- [ ] **Step 1: Remove the inline composition render**

In `backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php`, find this block (it sits right after the closing `</section>` of `.macro-hero-strip`, inside the `@else` branch):

```blade
        @if($kpi === 'grp')
            <livewire:dashboard.macro-composition :key="'macro-comp-inline'" />
        @endif
```

Delete those three lines entirely. Nothing else in the file changes — the `@php` block, the `macro-hero-strip` markup, the `@if($showIndustryDrivers)` branches, and the closing tags stay exactly as they are.

- [ ] **Step 2: Confirm no other usage, then delete the dead component**

Run: `grep -rn "macro-composition" backend/resources/views backend/app`
Expected: after Step 1, **no** `<livewire:dashboard.macro-composition` reference remains (only the file `macro-composition.blade.php` itself may appear). If any other live reference exists, STOP and report it — do not delete.

If clear, delete both files:
```bash
git rm backend/app/Livewire/Dashboard/MacroComposition.php
git rm backend/resources/views/livewire/dashboard/macro-composition.blade.php
```

Do NOT touch `portal.css`: the `.macro-composition-*` rules become dead but are harmless, and the same file's `.composition-grid` / `.component-card` classes are still used by the `inflation-details` and `poverty-details` panels.

- [ ] **Step 3: Render and screenshot**

Ensure the dev server is up. Run:
```
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1440,1000 --screenshot="verify-nocomp.png" "http://127.0.0.1:8741/dashboard"
```
Open `verify-nocomp.png` and confirm:
- The dashboard renders without error (HTTP 200, page intact).
- The growth panel shows the `macro-hero-strip` (+7,8% panel) with **no** composition dropdown below it.
- The 5 front-KPI cards (with values, from Task 1) are intact above.

Then load a page that uses the shared `.component-card` class to confirm nothing broke:
```
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1440,1000 --screenshot="verify-inflation.png" "http://127.0.0.1:8741/dashboard"
```
(The inflation panel renders when the Инфляция module tab is selected; if it cannot be reached in a static screenshot, instead confirm `grep -n "component-card" backend/public/css/portal.css` still returns the rule — proving the shared CSS was not removed.)

If any check fails, fix and re-screenshot.

- [ ] **Step 4: Delete screenshots and commit**

```bash
rm -f verify-nocomp.png verify-inflation.png
git add backend/resources/views/livewire/dashboard/panels/macro-growth.blade.php
git commit -m "refactor(dashboard): remove redundant inline macro-composition"
```

(The two `git rm` deletions from Step 2 are already staged and are included in this commit.)

---

## Self-Review

**Spec coverage:**
- Display yearly growth value on each macro front-KPI card → Task 1 Steps 1–3. ✓
- Beautiful card — icon + name + prominent value + note → Task 1 Step 1 markup + Step 2/3 CSS. ✓
- Value from already-loaded `$facts`, no query change → Task 1 Step 1 uses `$facts->get($code)`. ✓
- Graceful when `growth_pct` null → Task 1 Step 1 `@if($growth !== null) … @else <p> …`. ✓
- `selectKpi` / active / parent preserved → Task 1 Step 1 keeps `wire:click`, `$active`, `$parent`. ✓
- Remove `macro-comp-inline` → Task 2 Step 1. ✓
- Delete unused `MacroComposition` component → Task 2 Step 2. ✓
- Leave `.macro-composition-*` CSS, don't touch shared classes → Task 2 Step 2 explicit instruction. ✓
- Hero strip unchanged → Task 2 Step 1 changes only the `@if` block. ✓
- Browser verification → Task 1 Step 4, Task 2 Step 3. ✓

**Placeholder scan:** No TBD/TODO; full Blade file and full CSS rules given verbatim.

**Type consistency:** `front-kpi-value` and `front-kpi-note` class names match between the Blade (Step 1) and the CSS (Step 2). `$growth` is defined in the `@php` block and used in the same template. The deleted component (`MacroComposition`) is removed only after Task 2 Step 2 verifies it has no remaining references.
