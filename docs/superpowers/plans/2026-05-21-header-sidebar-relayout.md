# Header / Sidebar Relayout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure the app chrome so the sidebar runs full-height and the header becomes a thin, white, content-width bar with a border below.

**Architecture:** `<body>` becomes a 2-column CSS grid (`sidebar | content column`). The `<header>` moves inside the content column, above `<main>`. CSS rules for `.topbar` / `.brand` / `.sidebar` / `.main` are rewritten; `.shell` / `.mast` / `.kpi-mark` are removed.

**Tech Stack:** Laravel Blade templates, plain CSS (`backend/public/css/portal.css`), Livewire. No build step — `portal.css` is served directly. No JS/CSS test framework exists, so verification is visual: the app is rendered with Microsoft Edge headless and screenshots are inspected.

---

## Verification notes (read before starting)

- **No unit tests exist for CSS/markup.** Each task is verified by rendering the app and inspecting a screenshot. This is the project's established practice.
- **Dev server:** from the `backend/` directory run `php artisan serve --port=8741`. Leave it running in the background. It connects to a working database; pages return HTTP 200.
- **Screenshot command** (Microsoft Edge headless, already installed):
  ```
  "/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1440,1000 --screenshot="OUT.png" "http://127.0.0.1:8741/dashboard"
  ```
- **Known quirk:** Edge headless on this machine floors the viewport at ~476px wide. A `--window-size=390,...` request still renders at 476px CSS width. 476px still triggers the mobile (`max-width: 760px`) media query, so narrow verification is valid — just not pixel-exact at 390px.
- Work happens on the current branch (`v7-design-polish`).

## File Structure

- `backend/resources/views/layouts/app.blade.php` — the only layout file; defines the chrome. Restructured in Task 1.
- `backend/public/css/portal.css` — single global stylesheet (~5900 lines). Chrome rules live near the top (lines ~64–243); responsive overrides near the bottom (`@media` blocks around lines ~5650 and ~5770). Edited in Tasks 1 and 2.

No new files. No file is deleted.

---

## Task 1: Restructure markup and desktop chrome CSS

**Files:**
- Modify: `backend/resources/views/layouts/app.blade.php` (full rewrite)
- Modify: `backend/public/css/portal.css` (the `body` rule and the `.topbar`…`.main` rule block near the top)

- [ ] **Step 1: Rewrite the layout markup**

Replace the **entire contents** of `backend/resources/views/layouts/app.blade.php` with:

```blade
<!doctype html>
<html lang="uz-Cyrl">
@php $currentRegion = \App\Support\CurrentRegion::current(); @endphp
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $currentRegion->name_full }} мониторинг платформаси · v7</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <link rel="stylesheet" href="/css/portal.css">
  <style>
    a { text-decoration: none; color: inherit; }
  </style>
  @livewireStyles
</head>
<body>
  <aside class="sidebar">
    <div class="side-title">
      <strong>Бошқарув маркази</strong>
    </div>
    <a class="nav-btn {{ Route::is('dashboard') ? 'active' : '' }}"
       href="{{ route('dashboard') }}" title="KPI">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M4 13h6V4H4v9Zm10 7h6V4h-6v16ZM4 20h6v-4H4v4Z"/>
      </svg>
      <span>KPI</span>
    </a>
    <a class="nav-btn {{ Route::is('tasks') ? 'active' : '' }}"
       href="{{ route('tasks') }}" title="Топшириқлар">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M9 11l2 2 4-5M5 4h14v16H5z"/>
      </svg>
      <span>Топшириқлар</span>
    </a>
    <a class="nav-btn {{ Route::is('districts') ? 'active' : '' }}"
       href="{{ route('districts') }}" title="Туманлар">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-7h6v7"/>
      </svg>
      <span>Туманлар</span>
    </a>
    <livewire:region-switcher />
  </aside>

  <div class="content-col">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark">CERR</div>
        <h1>{{ $currentRegion->name_full }} мониторинг платформаси</h1>
      </div>
    </header>

    <main class="main">
      @yield('content')
    </main>
  </div>

  @livewireScripts
</body>
</html>
```

What changed: `<header>` moved out of the top-level and into a new `.content-col` wrapper after the sidebar; the `.shell` and `.mast` wrappers are gone; the sidebar is now a direct child of `<body>`; the brand block lost its inner `<div>` wrapper and its `<p>` subtitle line.

- [ ] **Step 2: Make `<body>` the grid container**

In `backend/public/css/portal.css`, find the `body` rule (near line 66):

```css
    body {
      margin: 0;
      color: var(--ink);
      background:
        radial-gradient(circle at 92% 0, rgba(23, 105, 224, .10), transparent 30%),
        linear-gradient(180deg, #fbfdff 0%, var(--bg) 45%, #eef3f8 100%);
      overflow-x: hidden;
    }
```

Replace it with:

```css
    body {
      margin: 0;
      color: var(--ink);
      background:
        radial-gradient(circle at 92% 0, rgba(23, 105, 224, .10), transparent 30%),
        linear-gradient(180deg, #fbfdff 0%, var(--bg) 45%, #eef3f8 100%);
      overflow-x: hidden;
      display: grid;
      grid-template-columns: var(--nav-w) minmax(0, 1fr);
      min-height: 100vh;
    }
```

- [ ] **Step 3: Replace the header rules (`.topbar` through `.kpi-mark`)**

In `portal.css`, find the contiguous block of rules starting at `.topbar {` (near line 83) and ending with the closing `}` of `.kpi-mark` (near line 169). It contains these rules in order: `.topbar`, `.mast`, `.mast::after`, `.brand`, `.brand > div`, `.brand-mark`, `.brand h1`, `.brand p`, `.kpi-mark`.

Replace that **entire block** with:

```css
    .topbar {
      position: sticky;
      top: 0;
      z-index: 30;
      display: flex;
      align-items: center;
      min-height: 62px;
      padding: 10px clamp(16px, 2.4vw, 34px);
      background: #fff;
      border-bottom: 1px solid var(--line);
      box-shadow: 0 1px 2px rgba(15, 42, 71, .04), 0 6px 16px rgba(15, 42, 71, .05);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 0;
    }

    .brand-mark {
      flex: 0 0 auto;
      height: 38px;
      padding: 0 11px;
      display: grid;
      place-items: center;
      border-radius: 8px;
      background: var(--accent-grad);
      color: #fff;
      font-weight: 950;
      font-size: 15px;
      letter-spacing: .04em;
    }

    .brand h1 {
      margin: 0;
      font-size: clamp(15px, 1.6vw, 19px);
      font-weight: 800;
      line-height: 1.15;
      letter-spacing: 0;
      color: var(--ink);
      overflow-wrap: anywhere;
    }
```

- [ ] **Step 4: Replace the shell + sidebar rules**

In `portal.css`, find the block starting at `.shell {` (near line 171) and ending with the closing `}` of the `.sidebar` rule (near line 188). It contains `.shell` and `.sidebar`.

Replace that block with:

```css
    .content-col {
      min-width: 0;
      display: flex;
      flex-direction: column;
    }

    .sidebar {
      position: sticky;
      top: 0;
      height: 100vh;
      background: linear-gradient(180deg, var(--nav), var(--nav-2));
      color: rgba(255,255,255,.78);
      padding: 18px 12px;
      overflow: auto;
      box-shadow: 16px 0 42px rgba(7, 26, 45, .16);
      display: flex;
      flex-direction: column;
    }
```

This keeps the sidebar's dark gradient and internal layout unchanged — only `top` (`92px` → `0`) and `height` (`calc(100vh - 92px)` → `100vh`) differ. The `.side-title` and `.nav-btn` rules immediately below are **not touched**.

- [ ] **Step 5: Replace the `.main` rule**

In `portal.css`, find the `.main` rule (near line 240):

```css
    .main {
      min-width: 0;
      padding: 20px clamp(16px, 2.4vw, 34px) 34px;
    }
```

Replace it with:

```css
    .main {
      flex: 1;
      min-width: 0;
      padding: 20px clamp(16px, 2.4vw, 34px) 34px;
    }
```

- [ ] **Step 6: Start the dev server and render the page**

From the `backend/` directory, if the server is not already running:

Run: `php artisan serve --port=8741` (leave running in background)
Then: `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8741/dashboard`
Expected: `200`

- [ ] **Step 7: Screenshot the desktop layout and verify**

Run:
```
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1440,1000 --screenshot="verify-desktop.png" "http://127.0.0.1:8741/dashboard"
```

Open `verify-desktop.png` and confirm ALL of the following:
- The dark sidebar runs from the very top of the page to the bottom (no header above it).
- The header is white, sits only to the right of the sidebar (not full width), and has a visible border/shadow along its bottom edge.
- The header shows the CERR mark and the text "Андижон вилояти мониторинг платформаси" on one line.
- The 3 nav buttons and the region switcher are present and intact in the sidebar.
- No content is clipped; no horizontal scrollbar.

If any check fails, fix the relevant rule and re-screenshot before continuing.

- [ ] **Step 8: Commit**

```bash
git add backend/resources/views/layouts/app.blade.php backend/public/css/portal.css
git commit -m "feat: full-height sidebar with white content-width header"
```

---

## Task 2: Update responsive breakpoints

The mobile media query still references removed classes (`.shell`, `.mast`, `.kpi-mark`) and styles the sidebar with the old assumptions. Update it for the new structure.

**Files:**
- Modify: `backend/public/css/portal.css` (the `@media (max-width: 760px)` block, near line 5773)

- [ ] **Step 1: Replace the chrome rules inside the 760px media query**

In `portal.css`, find the `@media (max-width: 760px) {` block. Inside it, find this contiguous run of rules at the top of the block:

```css
      .mast { grid-template-columns: 1fr; gap: 10px; }
      .brand { grid-template-columns: 66px minmax(0, 1fr); gap: 10px; }
      .brand-mark { width: 66px; height: 50px; font-size: 17px; }
      .brand h1 { font-size: 18px; line-height: 1.12; }
      .brand p { display: none; }
      .kpi-mark { display: none; }
      .shell { grid-template-columns: 1fr; }
      .sidebar {
        position: static;
        height: auto;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 6px;
        overflow: visible;
        padding: 10px;
      }
      .side-title { display: none; }
      .nav-btn { min-width: 0; margin: 0; gap: 6px; padding: 8px 4px; }
      .nav-btn span { display: block; font-size: 10.5px; line-height: 1.1; white-space: normal; }
      .main { padding: 14px; min-width: 0; max-width: 100vw; overflow-x: hidden; }
```

Replace that run with:

```css
      body { grid-template-columns: 1fr; }
      .topbar { padding: 10px 14px; }
      .brand-mark { height: 34px; font-size: 14px; }
      .brand h1 { font-size: 15px; }
      .sidebar {
        position: static;
        height: auto;
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        overflow: visible;
        padding: 10px;
      }
      .side-title { display: none; }
      .nav-btn { min-width: 0; width: auto; margin: 0; gap: 6px; padding: 8px 10px; }
      .nav-btn span { display: block; font-size: 11px; line-height: 1.1; white-space: normal; }
      .main { padding: 14px; min-width: 0; max-width: 100vw; overflow-x: hidden; }
```

Leave every other rule inside the 760px block (and the entire `@media (max-width: 1180px)` block) unchanged — the 1180px block only narrows `--nav-w`, which the new `body` grid already consumes.

- [ ] **Step 2: Screenshot the narrow layout and verify**

Run:
```
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=390,1400 --screenshot="verify-narrow.png" "http://127.0.0.1:8741/dashboard"
```

Open `verify-narrow.png` and confirm:
- The sidebar is a horizontal bar at the top; the white header and content stack below it.
- No content is clipped off the right edge; no horizontal scrollbar.
- Nav buttons and region switcher are reachable.

(Recall the viewport renders at ~476px even though 390 was requested — this is expected and still exercises the mobile layout.)

If a check fails, fix and re-screenshot.

- [ ] **Step 3: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "fix: update mobile breakpoint for new chrome structure"
```

---

## Task 3: Final cross-width verification

**Files:** none modified unless a defect is found.

- [ ] **Step 1: Screenshot three widths**

Run each command, then open each PNG:
```
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1440,1000 --screenshot="final-desktop.png" "http://127.0.0.1:8741/dashboard"
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=900,1100 --screenshot="final-tablet.png" "http://127.0.0.1:8741/dashboard"
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=390,1400 --screenshot="final-narrow.png" "http://127.0.0.1:8741/dashboard"
```

- [ ] **Step 2: Verify each width against the checklist**

For desktop and tablet: full-height sidebar on the left, white content-width header with a border below, no clipping, no horizontal scrollbar.
For narrow: sidebar collapses to a top bar, header + content stack, no horizontal scrollbar.

Also load one more page to confirm the shared layout holds:
```
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1440,1000 --screenshot="final-districts.png" "http://127.0.0.1:8741/districts"
```
Confirm `/districts` shows the same new frame and its content is not broken.

- [ ] **Step 3: Resolve any defects**

If any verification fails, fix the offending rule in `portal.css` (or markup in `app.blade.php`), re-screenshot, and commit the fix with a `fix:` message. If everything passes, no commit is needed — the task list is complete.

- [ ] **Step 4: Clean up**

Delete the temporary verification screenshots (`verify-*.png`, `final-*.png`) so they are not committed.

---

## Self-Review

**Spec coverage:**
- Sidebar full-height → Task 1 Step 4 (`top: 0; height: 100vh`) + Step 2 (`body` grid). ✓
- Header content-width, not full width → Task 1 Step 1 (header inside `.content-col`) + Step 2 (grid). ✓
- Header white + border below → Task 1 Step 3 (`background: #fff; border-bottom; box-shadow`). ✓
- Header content = CERR mark + brand title, subtitle dropped → Task 1 Step 1 markup. ✓
- Sidebar dark styling unchanged → Task 1 Step 4 keeps the gradient; `.side-title`/`.nav-btn` untouched. ✓
- Responsive collapse → Task 2. ✓
- Browser verification → Tasks 1, 2, 3. ✓

**Placeholder scan:** No TBD/TODO; every CSS and Blade change is given in full. ✓

**Type consistency:** Class names are consistent across tasks — `.content-col`, `.topbar`, `.brand`, `.brand-mark`, `.sidebar`, `.main` used identically in markup, desktop CSS, and the mobile media query. `.shell`, `.mast`, `.kpi-mark`, `.brand p` are fully removed and never referenced again. ✓
