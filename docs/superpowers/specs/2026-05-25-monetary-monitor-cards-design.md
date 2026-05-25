# Monetary Monitor Cards Redesign — Design Spec

**Date:** 2026-05-25
**Branch:** v7-design-polish
**Reference:** `C:\Users\y.utepbergenov\Desktop\Untitled.png` (foreign_invest module shown — same design applies to budget and export)

## Goal

Redesign the quarter monitor cards for the `budget`, `foreign_invest`, and `export` modules to match the reference image — filled period cards are dark royal-blue gradient panels with white text; empty period cards are a light blue-grey surface with dark text; the panel head shows only icon + title (no drill pill).

## Scope

- **In scope:** the 3 modules' quarter-matrix monitor cards (kpis `budget`, `investment`, `export`) and their `kpi-monitor-head` drill-pill suppression.
- **Out of scope:** every other module; the employment sub-KPIs that also use `quarter-matrix` (jobs, legalization, mfy_clear, microprojects) keep the recent white-card restyle; macro / inflation / employment driver panels untouched.
- **Approach:** scope a new CSS variant `.quarter-matrix.is-monetary` so only the 3 modules get the new look. Two small Blade tweaks add the class and gate the drill pill.

## Design

### 1. Head — drop the drill pill

In `backend/resources/views/livewire/dashboard/kpi-workspace-card.blade.php`, the head currently renders a "Туманлар кесими" pill for every KPI except `grp`. Gate it so the 3 monetary KPIs also skip it.

Change the existing condition:

```blade
@if($kpi !== 'grp')
    <a class="mini-button primary kpi-head-district" ...>Туманлар кесими</a>
@endif
```

to:

```blade
@if(! in_array($kpi, ['grp', 'budget', 'investment', 'export'], true))
    <a class="mini-button primary kpi-head-district" ...>Туманлар кесими</a>
@endif
```

Result: the head on these 3 modules shows only the `.small-icon` badge + `h3` short label + `p` full label, matching the image.

### 2. Mark the panel monetary

In `backend/resources/views/livewire/dashboard/panels/quarter-matrix.blade.php`, add an `is-monetary` modifier to the root `<div class="quarter-matrix">` when the KPI is one of the 3 monetary ones:

```blade
<div class="quarter-matrix {{ in_array($kpi, ['budget','investment','export'], true) ? 'is-monetary' : '' }}">
```

(Trim leading/trailing space or use a small `@php` to compose — keep it a single attribute, no extra classes when the modifier is absent.)

No other markup change. The `.quarter-row.actual / .planned / .empty` state classes the panel already emits remain the hook for per-state styling.

### 3. CSS — `.quarter-matrix.is-monetary` overrides

Append to `backend/public/css/portal.css` after the existing `.quarter-matrix` rule block (after `.quarter-row .q-trend.flat::before` near the end of the quarter-matrix block).

**Filled cards** (`.actual`, `.planned`):

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
```

**Empty cards** (`.empty`):

```css
    .quarter-matrix.is-monetary .quarter-row.empty {
      background: #eef2f7;
      border: 1px solid var(--line);
    }
```

Inside empty cards no text-color override is needed — the existing base rules (now `var(--ink)` after the T4 restyle) read well on the light surface. The `—` placeholder, period label, and the grey "Давр белгиланмаган" chip carry through unchanged.

Status chips are left alone — `.actual` green, `.planned` blue, `.empty` grey — the chip pill keeps its own background so it stays readable on the gradient cards.

## Files touched

| File | Change |
| --- | --- |
| `backend/resources/views/livewire/dashboard/kpi-workspace-card.blade.php` | Gate drill pill for `budget` / `investment` / `export` |
| `backend/resources/views/livewire/dashboard/panels/quarter-matrix.blade.php` | Add `is-monetary` class to root for the 3 KPIs |
| `backend/public/css/portal.css` | Append `.quarter-matrix.is-monetary` overrides for `.actual` / `.planned` / `.empty` rows |

No PHP component changes. No data changes. No test changes are required (route tests assert base classes like `quarter-matrix`, `module-card`, `scoreline execution-strip` — all preserved).

## Verification

After implementation, headless screenshot each of the 3 modules (`/dashboard?module=budget`, `?module=foreign_invest`, `?module=export`) at `1920x1010` and compare to `Untitled.png`. Confirm: head shows icon + title + subtitle only (no drill pill); filled period cards are blue-gradient panels with white text, big value, amber dot before the period name, status chip readable; empty period cards are a light blue-grey surface with dark text and a grey chip. Also screenshot `?module=employment&kpi=jobs` to confirm the employment sub-KPI quarter-matrix (no `is-monetary` class) still shows the previous white-card style — untouched.
