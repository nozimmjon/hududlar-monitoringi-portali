# Macro Hero Fidelity Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the macro dashboard's ЯҲМ hero panel to full visual fidelity with the reference image — curved-line decorative arrow, soft light halo on the icon, corrected internal font sizes.

**Architecture:** CSS-only. Every change is inside the existing `.front-kpis.module-kpis.macro-layout .front-kpi.parent` rule block in `backend/public/css/portal.css` (≈ lines 830-909). No Blade, no PHP, no tests change. `portal.css` is plain hand-written CSS served directly — no build step.

**Tech Stack:** Plain CSS, Laravel/Livewire app (only the stylesheet is touched).

**Spec:** `docs/superpowers/specs/2026-05-22-hero-fidelity-fix-design.md`

---

## Context for the implementer

- Repo root: `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali`. Branch: `v7-design-polish` (already checked out — do not switch branches).
- The only file changed is `backend/public/css/portal.css`.
- Run `git` from the repo root. CSS is served directly — no build, no tests affected (no test asserts on these visual properties; do not run the test suite for this plan).
- Each task is verified visually with a headless screenshot, not a unit test.

## Verification recipe

Used by every task. The dev server runs on port 8123.

1. Ensure the server is up — from `backend/`: `php artisan serve --host=127.0.0.1 --port=8123` (run in background; skip if already serving — check with `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8123/dashboard`).
2. Screenshot + crop the hero (PowerShell, one line):

```powershell
& "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1920,1010 --screenshot="C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali\backend\hero-verify.png" "http://127.0.0.1:8123/dashboard"
```

Then crop the hero region for inspection:

```powershell
Add-Type -AssemblyName System.Drawing; $s=[System.Drawing.Image]::FromFile('C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali\backend\hero-verify.png'); $c=New-Object System.Drawing.Bitmap(480,310); $g=[System.Drawing.Graphics]::FromImage($c); $g.DrawImage($s,(New-Object System.Drawing.Rectangle(0,0,480,310)),(New-Object System.Drawing.Rectangle(262,210,480,310)),[System.Drawing.GraphicsUnit]::Pixel); $b=New-Object System.Drawing.Bitmap(1200,775); $g2=[System.Drawing.Graphics]::FromImage($b); $g2.InterpolationMode=[System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic; $g2.DrawImage($c,0,0,1200,775); $b.Save('C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali\backend\hero-crop.png'); $s.Dispose();$c.Dispose();$b.Dispose()
```

3. Read `backend/hero-crop.png` and compare against the same region of the reference `C:\Users\y.utepbergenov\Desktop\Screenshot 2026-05-22 100100.png`.
4. `hero-verify.png` and `hero-crop.png` are temporary — delete them before finishing (`rm backend/hero-verify.png backend/hero-crop.png`); never commit them.

---

### Task 1: Curved-line decorative arrow

Replace the hero's `::before` solid-triangle background with a curved-line growth arrow.

**Files:**
- Modify: `backend/public/css/portal.css` — the `.front-kpis.module-kpis.macro-layout .front-kpi.parent::before` rule (≈ lines 852-863)

- [ ] **Step 1: Replace the `::before` rule**

In `backend/public/css/portal.css`, replace this exact block:

```css
    .front-kpis.module-kpis.macro-layout .front-kpi.parent::before {
      content: "";
      position: absolute;
      right: -28px;
      bottom: -38px;
      width: 216px;
      height: 216px;
      background: no-repeat center / contain
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath d='M50 25 L83 79 L17 79 Z' fill='%23ffffff' stroke='%23ffffff' stroke-width='15' stroke-linejoin='round'/%3E%3C/svg%3E");
      opacity: .26;
      pointer-events: none;
    }
```

with:

```css
    .front-kpis.module-kpis.macro-layout .front-kpi.parent::before {
      content: "";
      position: absolute;
      right: -18px;
      bottom: -26px;
      width: 224px;
      height: 224px;
      background: no-repeat center / contain
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' fill='none' stroke='%23ffffff' stroke-width='9' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 84 C 40 78 52 56 84 20'/%3E%3Cpath d='M60 20 L84 20 L84 44'/%3E%3C/svg%3E");
      opacity: .32;
      pointer-events: none;
    }
```

- [ ] **Step 2: Verify**

Run the Verification recipe. Expected in `hero-crop.png`: the bottom-right of the hero shows a pale **curved line** sweeping up to the right, ending in an open arrowhead — not a solid triangle. It is partially clipped by the panel edge. If the arrow is cut off too much or barely visible, adjust `right` / `bottom` / `width` / `height` / `opacity` and re-run until it reads clearly as a curved-line arrow.

- [ ] **Step 3: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): curved-line hero arrow"
```

---

### Task 2: Hero font sizes & element positions

Tune the hero internals to the reference proportions.

**Files:**
- Modify: `backend/public/css/portal.css` — the `.front-kpi.parent` rule, `.kpi-icon`, `.kpi-icon svg`, `h3`, `.front-kpi-value`, `.front-kpi-note` rules (≈ lines 830-909)

- [ ] **Step 1: Resize the parent panel**

In `backend/public/css/portal.css`, in the `.front-kpis.module-kpis.macro-layout .front-kpi.parent {` rule, change these two declarations:

```css
      gap: 12px 18px;
      padding: 30px;
```

to:

```css
      gap: 10px 20px;
      padding: 32px;
```

- [ ] **Step 2: Resize the icon badge**

Replace this exact block:

```css
    .front-kpis.module-kpis.macro-layout .front-kpi.parent .kpi-icon {
      width: 58px;
      height: 58px;
      border-radius: 16px;
      background: #fff;
      color: var(--blue);
      position: relative;
      z-index: 1;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent .kpi-icon svg {
      width: 36px;
      height: 36px;
    }
```

with:

```css
    .front-kpis.module-kpis.macro-layout .front-kpi.parent .kpi-icon {
      width: 64px;
      height: 64px;
      border-radius: 18px;
      background: #fff;
      color: var(--blue);
      position: relative;
      z-index: 1;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent .kpi-icon svg {
      width: 40px;
      height: 40px;
    }
```

- [ ] **Step 3: Resize the ЯҲМ label**

In the `.front-kpis.module-kpis.macro-layout .front-kpi.parent h3 {` rule, change:

```css
      font-size: 31px;
```

to:

```css
      font-size: 33px;
```

- [ ] **Step 4: Resize the value and note**

In the `.front-kpis.module-kpis.macro-layout .front-kpi.parent .front-kpi-value {` rule, change:

```css
      font-size: clamp(46px, 5vw, 70px);
```

to:

```css
      font-size: clamp(52px, 5.4vw, 78px);
```

Then in the `.front-kpis.module-kpis.macro-layout .front-kpi.parent .front-kpi-note {` rule, change:

```css
      font-size: 14px;
```

to:

```css
      font-size: 15px;
```

- [ ] **Step 5: Verify**

Run the Verification recipe. Compare `hero-crop.png` to the hero region of the reference image. Check: the icon badge is a clear rounded square; "ЯҲМ" sits centered beside it just under the icon's height; "+7,8%" is the dominant element; "йиллик ўсиш" is a small caption; the three rows are evenly spaced and vertically centered as a group. If any size or gap visibly differs from the reference, adjust that value and re-run until it matches.

- [ ] **Step 6: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): tune hero font sizes and spacing to reference"
```

---

### Task 3: Light halo around the icon

Add the soft white glow the reference shows around the icon badge.

**Files:**
- Modify: `backend/public/css/portal.css` — the `.front-kpi.parent .kpi-icon` rule (post-Task-2 state)

- [ ] **Step 1: Add the glow box-shadow**

In `backend/public/css/portal.css`, replace this exact block (this is the icon rule as left by Task 2):

```css
    .front-kpis.module-kpis.macro-layout .front-kpi.parent .kpi-icon {
      width: 64px;
      height: 64px;
      border-radius: 18px;
      background: #fff;
      color: var(--blue);
      position: relative;
      z-index: 1;
    }
```

with:

```css
    .front-kpis.module-kpis.macro-layout .front-kpi.parent .kpi-icon {
      width: 64px;
      height: 64px;
      border-radius: 18px;
      background: #fff;
      color: var(--blue);
      position: relative;
      z-index: 1;
      box-shadow: 0 0 0 6px rgba(255, 255, 255, .12),
                  0 0 36px 8px rgba(255, 255, 255, .30);
    }
```

- [ ] **Step 2: Verify**

Run the Verification recipe. Expected in `hero-crop.png`: a soft white halo around the icon badge — a faint ring hugging it plus a wider glow fading into the blue, matching the reference. If the glow is too strong or too weak, adjust the two `rgba(255,255,255,…)` alpha values and/or the blur/spread radii and re-run until it matches the reference.

- [ ] **Step 3: Final compare and cleanup**

Run the Verification recipe once more. Confirm the whole hero now matches the reference: curved-line arrow, haloed icon, correct font sizes. Then delete the temp files:

```bash
rm backend/hero-verify.png backend/hero-crop.png
```

- [ ] **Step 4: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "style(dashboard): soft light halo around hero icon"
```

---

## Self-review

**Spec coverage:**
- Section 1 (curved-line arrow) → Task 1 ✓
- Section 2 (icon light halo) → Task 3 ✓
- Section 3 (font sizes & positions) → Task 2 ✓ (the spec's size table: padding, gap → Task 2 Step 1; icon width/height/radius, svg → Step 2; h3 → Step 3; value, note → Step 4)
- Verification (headless screenshot crop-compare) → Verification recipe, used by every task ✓

**Placeholder scan:** No TBDs. Every CSS step shows the exact old block and exact new block. The screenshot/crop commands are complete and concrete.

**Type/name consistency:** Task 2 changes the `.kpi-icon` rule to `64px / 64px / 18px`; Task 3's old_string for the same rule uses exactly that post-Task-2 state (`64px / 64px / 18px`) and only appends `box-shadow`. Selectors are the full `.front-kpis.module-kpis.macro-layout .front-kpi.parent …` form throughout, matching the file. Task order is arrow → sizes → glow, so the glow is appended last to the already-resized icon rule.

**Note on the arrow SVG:** the data-URI uses `%23` for `#`, `%3C`/`%3E` for `<`/`>`; spaces and quotes inside the `url("…")` are left literal (Edge/Chromium parses them). The arrow is two stroked sub-paths — a curved shaft (`M14 84 C 40 78 52 56 84 20`) and an arrowhead (`M60 20 L84 20 L84 44`).
