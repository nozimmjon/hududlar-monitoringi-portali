# Monetary Monitor Cards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the quarter monitor cards for the `budget`, `foreign_invest`, and `export` modules to match `Untitled.png` — dark royal-blue gradient panels (filled periods) and a light blue-grey panel (empty period), with a simplified head that drops the drill pill.

**Architecture:** Two small Blade tweaks scope a new CSS variant `.quarter-matrix.is-monetary` to only the 3 monetary KPIs (`budget`, `investment`, `export`). The shared `quarter-matrix` panel and the employment sub-KPIs that use it are untouched.

**Tech Stack:** Laravel 11, Livewire 3, Blade, plain CSS in `backend/public/css/portal.css` (no build step).

**Spec:** `docs/superpowers/specs/2026-05-25-monetary-monitor-cards-design.md`

---

## Context for the implementer

- Repo root: `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali`. Branch: `v7-design-polish` (already checked out).
- Run `git` from the repo root. `php artisan ...` from `backend/`.
- Branch has concurrent edits. Before each task, `git status` — if `portal.css` or a touched Blade has uncommitted changes you did not make, stop and report.
- The 3 monetary KPI codes (used everywhere in this plan): `budget`, `investment`, `export`. Module codes are `budget`, `foreign_invest`, `export` — they map to those KPIs via `DashboardCatalog::MODULES`.
- `kpi-monitor-card` chrome is already neutralized inside `.module-card` from an earlier rollout — the panel is a transparent layout inside the white module card; do not re-add background/border on `.quarter-matrix` itself.

## Verification recipe

Used by Task 3. Dev server runs on port 8123.

1. Server check from `backend/`: `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8123/dashboard` — if not `200`, start `php artisan serve --host=127.0.0.1 --port=8123` in background.
2. Screenshot a module (substitute `<code>`):

```powershell
& "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1920,1010 --screenshot="C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali\backend\mon-verify.png" "http://127.0.0.1:8123/dashboard?module=<code>"
```

3. Read `backend/mon-verify.png`. Compare against `C:\Users\y.utepbergenov\Desktop\Untitled.png`.
4. Delete the temp PNG before finishing the task; never commit it.

Module URLs used:
- `?module=budget` (monetary)
- `?module=foreign_invest` (monetary — the reference image)
- `?module=export` (monetary)
- `?module=employment&kpi=jobs` (regression — also uses `quarter-matrix` but must stay white-card)

---

### Task 1: Drill pill gate in the workspace head

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/kpi-workspace-card.blade.php`

- [ ] **Step 1: Replace the drill-pill condition**

In `backend/resources/views/livewire/dashboard/kpi-workspace-card.blade.php`, replace this exact block:

```blade
            @if($kpi !== 'grp')
                <a class="mini-button primary kpi-head-district"
                   href="{{ route('districts') }}?indicatorCode={{ $kpi }}">Туманлар кесими</a>
            @endif
```

with:

```blade
            @if(! in_array($kpi, ['grp', 'budget', 'investment', 'export'], true))
                <a class="mini-button primary kpi-head-district"
                   href="{{ route('districts') }}?indicatorCode={{ $kpi }}">Туманлар кесими</a>
            @endif
```

- [ ] **Step 2: Commit**

```bash
git add backend/resources/views/livewire/dashboard/kpi-workspace-card.blade.php
git commit -m "feat(dashboard): hide drill pill on monetary monitor heads"
```

---

### Task 2: `is-monetary` class on the quarter-matrix panel

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/panels/quarter-matrix.blade.php`

- [ ] **Step 1: Add the monetary modifier**

In `backend/resources/views/livewire/dashboard/panels/quarter-matrix.blade.php`, replace this exact line:

```blade
<div class="quarter-matrix">
```

with:

```blade
<div class="{{ trim('quarter-matrix '.(in_array($kpi, ['budget','investment','export'], true) ? 'is-monetary' : '')) }}">
```

(The `trim()` keeps the class attribute clean — no trailing space when the modifier is absent.)

- [ ] **Step 2: Commit**

```bash
git add backend/resources/views/livewire/dashboard/panels/quarter-matrix.blade.php
git commit -m "feat(dashboard): mark monetary KPI quarter-matrix panels"
```

---

### Task 3: CSS overrides + cross-module verification

**Files:**
- Modify: `backend/public/css/portal.css` (append after the `.quarter-row .q-trend.flat::before` rule near the end of the quarter-matrix block — search for the unique line `.quarter-row .q-trend.flat::before { width: 7px;` and insert the block immediately after its `}` closer)

- [ ] **Step 1: Locate the insertion point**

In `backend/public/css/portal.css`, search for the line:

```
    .quarter-row .q-trend.flat::before { width: 7px; height: 1.5px; border: 0; background: currentColor; align-self: center; }
```

The new rules go immediately after that line.

- [ ] **Step 2: Append the `.is-monetary` overrides**

Insert this block right after the line found in Step 1 (use an Edit that anchors on that exact line and prepends the new block to the line directly following it). The block:

```css

    .quarter-matrix.is-monetary .quarter-row.actual,
    .quarter-matrix.is-monetary .quarter-row.planned {
      --row-accent: var(--amber);
      background: linear-gradient(160deg, #2360d6 0%, #1e59cc 100%);
      border: 0;
      box-shadow: 0 10px 24px rgba(8, 57, 93, .22);
    }

    .quarter-matrix.is-monetary .quarter-row.actual:hover,
    .quarter-matrix.is-monetary .quarter-row.planned:hover {
      transform: none;
      border-color: transparent;
      box-shadow: 0 12px 28px rgba(8, 57, 93, .26);
    }

    .quarter-matrix.is-monetary .quarter-row.actual .q-period,
    .quarter-matrix.is-monetary .quarter-row.planned .q-period {
      color: #fff;
    }

    .quarter-matrix.is-monetary .quarter-row.actual .q-hero-value,
    .quarter-matrix.is-monetary .quarter-row.planned .q-hero-value,
    .quarter-matrix.is-monetary .quarter-row.actual .q-hero .q-trend,
    .quarter-matrix.is-monetary .quarter-row.planned .q-hero .q-trend {
      color: #fff;
    }

    .quarter-matrix.is-monetary .quarter-row.actual .q-hero-label,
    .quarter-matrix.is-monetary .quarter-row.planned .q-hero-label {
      color: rgba(255, 255, 255, .78);
    }

    .quarter-matrix.is-monetary .quarter-row.actual .q-aux,
    .quarter-matrix.is-monetary .quarter-row.planned .q-aux {
      border-top-color: rgba(255, 255, 255, .18);
    }

    .quarter-matrix.is-monetary .quarter-row.actual .q-aux-row,
    .quarter-matrix.is-monetary .quarter-row.planned .q-aux-row {
      color: rgba(255, 255, 255, .78);
    }

    .quarter-matrix.is-monetary .quarter-row.actual .q-aux-row b,
    .quarter-matrix.is-monetary .quarter-row.planned .q-aux-row b {
      color: #fff;
    }

    .quarter-matrix.is-monetary .quarter-row.empty {
      background: #eef2f7;
      border: 1px solid var(--line);
    }
```

The concrete Edit:

- `old_string` = `    .quarter-row .q-trend.flat::before { width: 7px; height: 1.5px; border: 0; background: currentColor; align-self: center; }`
- `new_string` = that same line, then a blank line, then the entire block above.

- [ ] **Step 3: Verify all three monetary modules + the employment regression**

Run the Verification recipe in turn for `budget`, `foreign_invest`, `export`. For each, the head must show only icon + title + subtitle (no drill pill), filled period cards must be the dark royal-blue gradient with white text and an amber dot before the period name, the empty period must be a light blue-grey card with dark text. Status chips keep their colors (green / blue / grey).

Then run the Verification recipe for `employment&kpi=jobs` (regression). Its `quarter-matrix` must still render the white-card style (no `is-monetary` class → no overrides applied). If anything looks off, adjust the CSS values and re-render.

Delete the temp PNG when done: `rm -f backend/mon-verify.png`.

- [ ] **Step 4: Run the dashboard test scope**

Run from `backend/`: `php artisan test --filter "DashboardRoutes|MacroPeriodRow"`
Expected: same state as before this plan — `DashboardRoutes` all pass; the one known `MacroPeriodRowTest` industry failure (concurrent `industry-driver-panel` work) is unchanged. No new failures.

- [ ] **Step 5: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): gradient monetary monitor cards"
```

---

## Self-review

**Spec coverage:**
- Spec §1 (drop drill pill on 3 modules) → Task 1 ✓
- Spec §2 (is-monetary class) → Task 2 ✓
- Spec §3 (CSS overrides for filled `.actual` / `.planned` and empty `.empty`) → Task 3 ✓
- Verification (per-module screenshots + employment regression) → Task 3 Step 3 ✓
- Tests note (no test changes required) → Task 3 Step 4 ✓

**Placeholder scan:** No TBDs. Every step shows the exact old block and the exact new block. The screenshot/crop commands are complete and concrete. The CSS block is the full set of rules, ready to paste.

**Type/name consistency:** The class string is `is-monetary` in both Tasks 2 and 3. The KPI list `['budget', 'investment', 'export']` (plus `'grp'` in Task 1) is the same triple of KPI codes in both Blade gates. Selector form `.quarter-matrix.is-monetary .quarter-row.<state> ...` is consistent across every rule. Task order: Blade gate (T1) → Blade class (T2) → CSS that depends on T2's class (T3); the order matters because the CSS in T3 has no effect until T2 emits the class.

**Concurrency note:** the branch has active parallel work on portal.css and the scoreline. Task 3 appends a new rule block at an anchor near the end of the existing `.quarter-matrix` block — it does not modify any existing rule, so it is conflict-safe with concurrent edits elsewhere in the file. Task 1 and Task 2 each touch a single Blade file; if either file shows uncommitted changes from concurrent work, stop and surface them before editing.
