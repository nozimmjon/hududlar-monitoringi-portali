# Tasks Page Scroll Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `.task-list` on `/tasks` scroll internally so the filter row and summary strip stay in view while a user pages through 86 task cards.

**Architecture:** Single CSS rule extension in `backend/public/css/portal.css`. Adds `max-height: calc(100vh - 320px); overflow-y: auto; padding-right: 4px; overscroll-behavior: contain; scroll-behavior: smooth;` to the existing `.task-group .task-list` rule (line 4759). Adds a one-line reset inside the existing `@media (max-width: 768px)` block (around line 5553) so mobile reverts to natural scroll.

**Tech Stack:** Plain CSS. No JS, no Blade, no Livewire.

**Spec:** `docs/superpowers/specs/2026-05-12-tasks-page-scroll-fix-design.md`

**Working directory:** `C:\Users\y.utepbergenov\Desktop\hududlar-monitoringi-portali` (project root). All git commands run from project root.

**Verification model:** Manual visual QA in a browser. No automated viewport tests in this repo.

---

## File Structure

| File | Responsibility |
|---|---|
| `backend/public/css/portal.css` | Extend existing `.task-group .task-list` rule (line 4759) and append one rule inside existing 768px media query block (around line 5553). |

No other files touched.

---

### Task 1: Add internal scroll to `.task-group .task-list`

**Files:**
- Modify: `backend/public/css/portal.css:4759-4761`

- [ ] **Step 1: Replace the existing rule block**

Open `backend/public/css/portal.css`. Find the rule at line 4759:

```css
    .task-group .task-list {
      padding: 12px;
    }
```

Replace it with:

```css
    .task-group .task-list {
      padding: 12px;
      max-height: calc(100vh - 320px);
      overflow-y: auto;
      padding-right: 4px;
      overscroll-behavior: contain;
      scroll-behavior: smooth;
    }
```

> Magic number `320px` = topbar (60) + page padding (24) + filter row (60) + summary strip (132) + slack (44). Update when layout chrome above the list changes.

- [ ] **Step 2: Verify diff**

Run from project root:

```bash
git diff backend/public/css/portal.css
```

Expected: 5 lines added, 0 deletions. Only the `.task-group .task-list` block changed.

- [ ] **Step 3: Manual verification (you cannot do this — leave for user)**

Note in your report that the user must run `cd backend && php artisan serve` and check `/tasks` to confirm the inner scroll works at 1080p viewport. Do not start a server yourself.

- [ ] **Step 4: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "feat(tasks): internal scroll on task list keeps filters in view"
```

---

### Task 2: Mobile reset inside existing 768px media query

**Files:**
- Modify: `backend/public/css/portal.css:5553` (insert one new line inside the existing `@media (max-width: 768px)` block)

- [ ] **Step 1: Insert the reset rule**

Find line 5553 inside the `@media (max-width: 768px)` block:

```css
      .task-summary-strip.execution-overview .exec-status-grid, .task-advanced-grid { grid-template-columns: 1fr; }
```

Insert this new line **immediately after** line 5553:

```css
      .task-group .task-list { max-height: none; overflow: visible; padding-right: 0; }
```

Preserve the surrounding 6-space indentation (the `@media` block uses 6-space rule indent inside).

- [ ] **Step 2: Verify diff**

```bash
git diff backend/public/css/portal.css
```

Expected: 1 line added.

- [ ] **Step 3: Commit**

```bash
git add backend/public/css/portal.css
git commit -m "fix(tasks): reset inner-scroll on small viewports"
```

---

### Task 3: User visual QA (controller-only)

**Files:** none

- [ ] **Step 1: Hand off to user**

The user runs:

```bash
cd backend && php artisan serve
```

User opens `http://127.0.0.1:8000/tasks` and checks the matrix from spec §6:

1. With all 86 tasks visible, filter row + summary strip + advanced-filters details stay in normal flow at the top of the page.
2. Only `.task-list` scrolls internally as the user pages through cards.
3. Scrollbar on right edge of `.task-list`, not overlapping content.
4. When list reaches bottom or top, the page does NOT scroll (`overscroll-behavior: contain`).
5. Apply filter `module=macro` → list shrinks to ~7 cards → no scrollbar visible.
6. Resize viewport to 768px → inner scroll resets; list flows in page normally.
7. Dashboard `/` `.task-list` inside `.macro-layout-card` unaffected (different selector path).

If any check fails, report back; we'll adjust the 320px constant or the selector specificity.

---

## Self-Review

**Spec coverage map**

| Spec section | Task |
|---|---|
| §1 Goal — internally scrolling task list | Task 1 |
| §3 Strategy: `max-height` + `overflow-y` + scrollbar gutter + `overscroll-behavior` + `scroll-behavior` | Task 1 |
| §3 Mobile fallback — reset inside existing 768px media query | Task 2 |
| §4 Cascade considerations (`.task-group .task-list` specificity 0,2,0 beats general 0,1,0) | Task 1 — uses the exact same selector that already exists |
| §5 Files touched — only `portal.css` | Task 1, 2 |
| §6 Test plan | Task 3 |
| §7 Risks — `320px` magic number documented | Task 1 includes the breakdown comment in plan body |

**Placeholder scan:** no TBD/TODO/handwave. Both code changes are complete and ready to paste.

**Type/name consistency:** the selector `.task-group .task-list` appears identically in Task 1 (extension) and Task 2 (mobile reset). The magic number `320px` appears once in Task 1 only.

No gaps. Plan covers spec end-to-end.
