# Scoreline Card Styling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle every module's execution scoreline card to render 100% identical to the reference image `footer.png`.

**Architecture:** Pure CSS change in one file (`backend/public/css/portal.css`). Promote the macro-only `.is-macro` styling to the shared `.execution-strip` selector so all module scorelines share one rule set, then delete the redundant `.is-macro` block. No markup/blade changes.

**Tech Stack:** Plain CSS served raw from Laravel `public/` — no build step, no bundler. Spec: `docs/superpowers/specs/2026-05-22-scoreline-card-styling-design.md`.

**Testing note:** This is a visual CSS change with no automated test harness in the repo. Each task is verified by browser inspection against `footer.png`. The verification task at the end covers the full check.

---

### Task 1: Add score color vars

**Files:**
- Modify: `backend/public/css/portal.css` (`:root` block, near line 20)

- [ ] **Step 1: Add four color vars**

Find this line in the `:root` block:

```css
      --red-soft: #fee5e5;
```

Insert immediately after it:

```css
      --score-blue: #205cd6;
      --score-green: #24ab5e;
      --score-red: #e73a36;
      --score-track: #e8edf7;
```

- [ ] **Step 2: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(scoreline): add score-card color vars sampled from footer.png"
```

---

### Task 2: Replace card grid + add child order

**Files:**
- Modify: `backend/public/css/portal.css` (`.scoreline.execution-strip`, ~lines 461-465)

- [ ] **Step 1: Replace the grid rule**

Replace this exact block:

```css
    .scoreline.execution-strip {
      grid-template-columns: minmax(240px, 1fr) minmax(330px, .9fr) minmax(120px, .32fr) minmax(170px, .42fr);
      align-items: center;
      margin-top: 16px;
    }
```

with:

```css
    .scoreline.execution-strip {
      grid-template-columns: minmax(240px, 1fr) auto minmax(320px, 1.1fr);
      gap: 16px;
      align-items: center;
      margin-top: 16px;
      padding: 16px 20px;
    }

    .execution-strip .scoreline-copy { order: 1; }
    .execution-strip .exec-progress-box { order: 2; }
    .execution-strip .exec-status-grid { order: 3; }
```

- [ ] **Step 2: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(scoreline): 3-col grid + copy/ring/boxes order for all modules"
```

---

### Task 3: Restyle the progress ring + hide caption

**Files:**
- Modify: `backend/public/css/portal.css` (`.exec-donut`, `.exec-donut strong`, `.exec-progress-box small`, ~lines 527-550)

- [ ] **Step 1: Replace the `.exec-donut` rule**

Replace this exact block:

```css
    .exec-donut {
      width: 54px;
      height: 54px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background:
        radial-gradient(circle at center, #fff 0 56%, transparent 57%),
        conic-gradient(#16a34a calc(var(--pct) * 1%), #eef2f6 0);
      border: 1px solid var(--line);
    }
```

with:

```css
    .exec-donut {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background:
        radial-gradient(circle at center, #fff 0 83%, transparent 84%),
        conic-gradient(var(--score-green) calc(var(--pct) * 1%), var(--score-track) 0);
      border: 0;
    }
```

- [ ] **Step 2: Replace the `.exec-donut strong` rule**

Replace this exact block:

```css
    .exec-donut strong {
      font-size: 14px;
      font-weight: 950;
      color: var(--ink);
    }
```

with:

```css
    .exec-donut strong {
      font-size: 22px;
      font-weight: 800;
      color: var(--ink);
    }
```

- [ ] **Step 3: Hide the ring caption**

Find this exact block:

```css
    .exec-progress-box small {
      color: var(--muted);
      font-size: 11px;
      font-weight: 800;
      text-align: center;
    }
```

Insert immediately after it:

```css
    .execution-strip .exec-progress-box small { display: none; }
```

- [ ] **Step 4: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(scoreline): 60px ring, 22px percent, hide caption"
```

---

### Task 4: Restyle the status boxes

**Files:**
- Modify: `backend/public/css/portal.css` (add `.execution-strip`-scoped pill rules after `.exec-status-pill.red strong`, ~line 518)

- [ ] **Step 1: Add scoped pill rules**

Find this exact block:

```css
    .exec-status-pill.green strong { color: #16a34a; }
    .exec-status-pill.red strong { color: #ef4444; }
```

Insert immediately after it:

```css
    .execution-strip .exec-status-grid { gap: 10px; }

    .execution-strip .exec-status-pill {
      min-height: 52px;
      border: 0;
      border-radius: 12px;
      padding: 12px 14px;
      justify-items: start;
      text-align: left;
      gap: 4px;
    }

    .execution-strip .exec-status-pill:hover {
      transform: none;
      box-shadow: none;
      border-color: transparent;
    }

    .execution-strip .exec-status-pill span {
      color: rgba(255, 255, 255, .9);
      font-size: 11px;
    }

    .execution-strip .exec-status-pill strong {
      color: #fff;
      font-size: 28px;
      font-weight: 800;
    }

    .execution-strip .exec-status-grid .exec-status-pill:first-child { background: var(--score-blue); }
    .execution-strip .exec-status-pill.green { background: var(--score-green); }
    .execution-strip .exec-status-pill.red { background: var(--score-red); }
    .execution-strip .exec-status-pill.green strong,
    .execution-strip .exec-status-pill.red strong { color: #fff; }
```

- [ ] **Step 2: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(scoreline): solid blue/green/red boxes, left-aligned, white text"
```

---

### Task 5: Restyle the copy block

**Files:**
- Modify: `backend/public/css/portal.css` (add `.execution-strip`-scoped copy rules after `.scoreline-copy small`, ~line 577)

- [ ] **Step 1: Add scoped copy rules**

Find this exact block:

```css
    .scoreline-copy small {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.3;
    }
```

Insert immediately after it:

```css
    .execution-strip .scoreline-copy strong {
      font-size: 22px;
      font-weight: 800;
    }

    .execution-strip .scoreline-copy small { font-size: 12.5px; }
```

- [ ] **Step 2: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(scoreline): 22px bold title, 12.5px sublabel"
```

---

### Task 6: Remove the redundant `.is-macro` block

**Files:**
- Modify: `backend/public/css/portal.css` (delete `.scoreline.execution-strip.is-macro` block, ~lines 579-626)

The `.is-macro` rules are fully superseded by the `.execution-strip` rules added in Tasks 2-5. Until removed, they override the new rules for the macro module (equal specificity, later in source).

- [ ] **Step 1: Delete the entire `.is-macro` block**

Delete this exact block (every line from `.scoreline.execution-strip.is-macro {` through the last `.is-macro` rule):

```css
    .scoreline.execution-strip.is-macro {
      grid-template-columns: minmax(240px, 1fr) auto minmax(320px, 1.1fr);
      gap: 16px;
      padding: 14px;
    }

    .is-macro .scoreline-copy { order: 1; }
    .is-macro .exec-progress-box { order: 2; }
    .is-macro .exec-status-grid { order: 3; }
    .is-macro .scoreline-copy strong { font-size: 19px; }
    .is-macro .scoreline-copy small { font-size: 12.5px; }

    .is-macro .exec-donut {
      width: auto;
      height: auto;
      background: none;
      border: 0;
      border-radius: 0;
    }

    .is-macro .exec-donut strong {
      font-size: 44px;
      font-weight: 900;
      letter-spacing: -0.03em;
      color: var(--ink);
    }

    .is-macro .exec-status-pill {
      min-height: 74px;
      border: 0;
      align-content: center;
      justify-items: center;
      text-align: center;
      gap: 5px;
    }

    .is-macro .exec-status-pill:hover {
      transform: none;
      box-shadow: none;
    }

    .is-macro .exec-status-pill span { color: rgba(255, 255, 255, .85); font-size: 11.5px; }
    .is-macro .exec-status-pill strong { color: #fff; font-size: 31px; }
    .is-macro .exec-status-pill.green strong { color: #fff; }
    .is-macro .exec-status-pill.red strong { color: #fff; }
    .is-macro .exec-status-grid .exec-status-pill:first-child { background: var(--blue); }
    .is-macro .exec-status-pill.green { background: var(--green); }
    .is-macro .exec-status-pill.red { background: var(--red); }
```

Leave the blade's `is-macro` class in place — with no matching CSS it is a harmless no-op (spec: no markup changes).

- [ ] **Step 2: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(scoreline): drop redundant is-macro overrides"
```

---

### Task 7: Verify against the reference image

**Files:** none — verification only.

- [ ] **Step 1: Serve the app**

Run: `php artisan serve` (from `backend/`)
Expected: server on `http://127.0.0.1:8000`.

- [ ] **Step 2: Check the macro module**

Open the dashboard, macro module. Hard refresh (`Ctrl+F5` — `portal.css` is served raw, browser caches it).
Expected: scoreline card matches `footer.png` — white card, copy block left, 60px ring with `%` center, three solid boxes (blue `#205cd6` / green `#24ab5e` / red `#e73a36`) with white left-aligned label + number.

- [ ] **Step 3: Check a non-macro module**

Switch to any non-macro module (e.g. budget, export).
Expected: same solid blue/green/red boxes + ring layout — previously white boxes with colored numbers.

- [ ] **Step 4: Check the tasks page is unchanged**

Open the tasks page.
Expected: `.task-summary-strip` status pills look exactly as before (white pills, colored numbers) — the change was scoped to `.execution-strip` and must not leak here.

- [ ] **Step 5: Check responsive collapse**

Narrow the browser window.
Expected: scoreline grid collapses to a single column cleanly (responsive rules at portal.css ~lines 5823-5871 still apply).

---

## Self-Review

**Spec coverage:** §1 vars → Task 1. §2 grid → Task 2. §3 order → Task 2. §4 ring → Task 3. §5 hide caption → Task 3. §6 boxes → Task 4. §7 copy → Task 5. §8 remove is-macro + replace old grid → Tasks 2 + 6. Out-of-scope guard (task-summary unaffected) → Task 7 Step 4. All spec sections covered.

**Placeholder scan:** No TBD/TODO; every CSS step shows complete before/after blocks.

**Type consistency:** Var names `--score-blue` / `--score-green` / `--score-red` / `--score-track` defined in Task 1 and used identically in Tasks 3-4. Selector `.execution-strip` used consistently across Tasks 2-5.
