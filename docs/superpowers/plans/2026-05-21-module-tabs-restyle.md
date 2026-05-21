# Module Tabs Restyle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the dashboard module tabs to match the supplied reference image — a white rounded track holding equal-width pill tabs, each a 2-line label + count, active tab filled with a blue gradient, no icons, no progress bar.

**Architecture:** Pure presentation change. Trim the tab markup in one Blade partial; replace the tab CSS rule block in `portal.css`. The `wire:click` selection behaviour is untouched.

**Tech Stack:** Laravel Blade, Livewire, plain CSS (`backend/public/css/portal.css`, served directly — no build step). No CSS test framework — verification is visual via Microsoft Edge headless screenshots, the project's established practice.

---

## Verification notes (read before starting)

- **No unit tests for CSS/markup.** Verification is by rendering the app and inspecting screenshots.
- **Dev server:** from `backend/`, if `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8741/dashboard` is not `200`, run `php artisan serve --port=8741` as a background process and re-check.
- **Screenshot command** (Microsoft Edge headless, installed):
  ```
  "/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1440,1000 --screenshot="OUT.png" "http://127.0.0.1:8741/dashboard"
  ```
- **Known quirk:** Edge headless on this machine floors the viewport at ~476px; a `--window-size=390` request renders at ~476px CSS width. 476px still triggers the `max-width: 760px` media query, so narrow verification is valid.
- Work happens on the current branch (`v7-design-polish`).

## File Structure

- `backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php` — the module-tabs partial. Markup trimmed (icon + bar removed, count parenthesised).
- `backend/public/css/portal.css` — global stylesheet. The `.dashboard-module-tabs` / `.module-tab*` rule block (currently lines ~277–380) is replaced.

No new files. No file deleted.

---

## Task 1: Restyle the module tabs

**Files:**
- Modify: `backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php` (full rewrite)
- Modify: `backend/public/css/portal.css` (replace the `.dashboard-module-tabs` … `.module-tab__bar:has(...)` rule block)

- [ ] **Step 1: Rewrite the tab markup**

Replace the **entire contents** of `backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php` with:

```blade
<div class="dashboard-module-tabs">
    @foreach($modules as $code => $module)
        @php
            $counts = $taskCounts[$code] ?? ['done' => 0, 'total' => 0];
            $total  = (int) $counts['total'];
            $done   = (int) $counts['done'];
        @endphp
        <button class="module-tab {{ $code === $currentModule ? 'active' : '' }}"
                wire:click="selectModule('{{ $code }}')"
                type="button">
            <span class="module-tab__body">
                <strong>{{ preg_replace('/^\d+\.\s*/u', '', $module['label']) }}</strong>
                <span class="module-tab__count">({{ $done }}/{{ $total }})</span>
            </span>
        </button>
    @endforeach
</div>
```

What changed: the `.module-tab__icon` span and the `.module-tab__bar` span are removed; the `$pct` and `$iconName` PHP variables are removed (no longer used); the count is wrapped in parentheses.

- [ ] **Step 2: Replace the tab CSS block**

In `backend/public/css/portal.css`, find the contiguous rule block that begins at `.dashboard-module-tabs {` (near line 277) and ends with the closing `}` of the rule `.module-tab__bar:has(i[style*="--w:0%"]) { … }` (near line 380). That block contains, in order: `.dashboard-module-tabs`, `.module-tab`, `.module-tab__icon`, `.module-tab__icon svg`, `.module-tab__body`, `.module-tab__body strong`, `.module-tab__count`, `.module-tab__bar`, `.module-tab__bar i`, `.module-tab:hover`, `.module-tab.active`, `.module-tab.active .module-tab__icon`, `.module-tab.active .module-tab__body strong`, `.module-tab.active .module-tab__bar`, `.module-tab[data-dashboard-module]`, `.module-tab__bar:has(...)`.

Read the file to get the exact current text of that block, then replace the **entire block** with:

```css
    .dashboard-module-tabs {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 6px;
      margin-bottom: 16px;
      padding: 6px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 20px;
      box-shadow: var(--shadow-sm);
    }

    .module-tab {
      display: grid;
      place-items: center;
      padding: 11px 12px;
      border: 0;
      border-radius: 15px;
      background: #eef0f3;
      cursor: pointer;
      transition: background .15s ease;
    }

    .module-tab__body {
      display: grid;
      justify-items: center;
      gap: 3px;
      min-width: 0;
      text-align: center;
    }

    .module-tab__body strong {
      color: var(--ink);
      font-size: 13.5px;
      font-weight: 700;
      line-height: 1.2;
    }

    .module-tab__count {
      color: var(--muted);
      font-size: 11.5px;
      font-weight: 700;
      font-variant-numeric: tabular-nums;
    }

    .module-tab:hover {
      background: #e4e7ec;
    }

    .module-tab.active {
      background: var(--accent-grad);
      box-shadow: 0 6px 16px rgba(23, 105, 224, .28);
    }

    .module-tab.active .module-tab__body strong {
      color: #fff;
      font-weight: 800;
    }

    .module-tab.active .module-tab__count {
      color: rgba(255, 255, 255, .82);
    }
```

The removed selectors (`.module-tab__icon`, `.module-tab__bar`, `.module-tab[data-dashboard-module]`, etc.) are gone because their markup no longer exists.

- [ ] **Step 3: Start the dev server and render**

From `backend/`: if `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8741/dashboard` is not `200`, run `php artisan serve --port=8741` in the background and re-check until `200`.

- [ ] **Step 4: Screenshot the desktop layout and verify**

Run:
```
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1440,1000 --screenshot="verify-tabs.png" "http://127.0.0.1:8741/dashboard"
```
Open `verify-tabs.png` with the Read tool and confirm ALL of:
- All 7 module tabs sit inside one white rounded "track" container with a soft shadow.
- Each tab is a rounded pill: inactive tabs are light grey, the active tab is filled with a blue gradient.
- Each tab shows two centered lines: the label on top, the `(done/total)` count below in parentheses.
- No module icons and no progress bars appear on the tabs.
- The active tab's label is white.

If any check fails, fix the relevant rule and re-screenshot before continuing.

- [ ] **Step 5: Screenshot narrow widths and verify**

Run:
```
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=1000,900 --screenshot="verify-tabs-mid.png" "http://127.0.0.1:8741/dashboard"
"/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless --disable-gpu --force-device-scale-factor=1 --hide-scrollbars --window-size=390,1400 --screenshot="verify-tabs-narrow.png" "http://127.0.0.1:8741/dashboard"
```
Open both. Confirm the white track holds together and the pill tabs wrap cleanly (3-per-row at the mid width, 1-per-row at the narrow width), with no clipping or horizontal scrollbar. If a check fails, fix and re-screenshot.

- [ ] **Step 6: Delete the screenshots and commit**

```bash
rm verify-tabs.png verify-tabs-mid.png verify-tabs-narrow.png
git add backend/resources/views/livewire/dashboard/kpi-module-tabs.blade.php backend/public/css/portal.css
git commit -m "style(tabs): restyle module tabs as pills in a white track"
```

---

## Self-Review

**Spec coverage:**
- White rounded track wrapping all tabs → Step 2, `.dashboard-module-tabs` (white bg, 20px radius, border, shadow). ✓
- Pill tabs, grey inactive / blue-gradient active → Step 2, `.module-tab` + `.module-tab.active`. ✓
- Two centered lines, label over `(0/32)` count → Step 1 markup + Step 2 `.module-tab__body`. ✓
- Icon removed → Step 1 (span removed) + Step 2 (`.module-tab__icon` rules gone). ✓
- Progress bar removed → Step 1 (span removed) + Step 2 (`.module-tab__bar` rules gone). ✓
- Unused `$pct` / `$iconName` removed → Step 1. ✓
- Responsive breakpoints unchanged, track still works → Step 5 verifies; no media-query edits per spec. ✓
- Browser verification → Steps 4–5. ✓

**Placeholder scan:** No TBD/TODO; full Blade file and full CSS block given verbatim. ✓

**Type consistency:** Class names consistent — `.dashboard-module-tabs`, `.module-tab`, `.module-tab__body`, `.module-tab__count` used identically in the markup and CSS. `.module-tab__icon` and `.module-tab__bar` are fully removed from both. PHP vars `$counts`/`$total`/`$done` are defined and used; `$pct`/`$iconName` are removed and never referenced. ✓
