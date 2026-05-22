# KPI Pages Design Rollout — Design Spec

**Date:** 2026-05-22
**Branch:** v7-design-polish

## Goal

Bring the 6 non-macro dashboard modules — `inflation`, `budget`, `budget_invest`, `foreign_invest`, `export`, `employment` — up to the macro module's design language: a white module card shell, larger fonts, `#2b61af` accent blue, dark text in place of muted greys, and consistent card/component styling.

## Scope

- **In scope:** all 6 non-macro modules and their 5 detail panels (`quarter-matrix`, `inflation-details`, `budget-investment`, `unemployment-details`, `poverty-details`), the `employment-layout` front cards, the shared module-card shell, the scoreline, and the `kpi-monitor-head`.
- **Depth:** restyle + targeted layout fixes. Each panel keeps its data and overall structure; CSS is revised to the new language; a layout is only restructured where it is visibly cramped.
- **Out of scope:** the `macro` module (already redesigned), data/query changes, new panels, the `industry-driver-panel` (concurrent work — left alone).
- **Approach:** CSS design-language rollout in `backend/public/css/portal.css` plus a small generalization in `kpi-dashboard.blade.php`. No PHP component class changes.

## Design language (the target)

Taken from the finished macro module:

- **Surface:** the white `.module-card` — `background:#fff`, `1px solid var(--line)`, `border-radius:18px`, `box-shadow:var(--shadow)`, `padding:22px`.
- **Panels render transparent** — the module card is the white surface; panels lay out content, no own background/border.
- **Sub-cards / components** inside a panel: `#fff` or `var(--surface)`, `1px solid var(--line)`, `border-radius:14-15px`.
- **Accent blue:** `var(--blue)` = `#2b61af`.
- **Values:** large, `font-weight:800-900`, colored `var(--ink)` or `var(--blue)` for emphasis.
- **Labels:** `var(--ink)` (dark) — not muted grey. **Captions/notes:** small, `var(--ink)` or a soft dark.
- **Font scale:** noticeably larger than the current non-macro panels — comparable to the macro sector cards / period row.

## Design

### 1. Module-card shell — generalize to every module

`backend/resources/views/livewire/kpi-dashboard.blade.php` currently wraps only `macro` in `.module-card`; the other 6 use `.module-flow` (`display:contents`, no card). Change so **every** module gets a `.module-card`:

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

- Modules **without** front cards (`inflation`, `budget`, `budget_invest`, `foreign_invest`, `export`) → one `.module-card` holds the heading + the workspace-card panel.
- Modules **with** front cards (`macro`, `employment`) → `.module-card` holds heading + front cards; a second `.module-card.module-panel-card` holds the panel.
- The `.module-flow` rule and the `$module === 'macro'` special-casing are removed. The `industry-driver-panel` line is preserved unchanged.

The workspace card's `.kpi-monitor-card` chrome must be neutralized inside `.module-card` for non-macro modules (it would otherwise be a card-in-card): `.module-card .kpi-monitor-card { border:0; background:transparent; box-shadow:none; }` (macro already neutralizes via `.macro-layout-card`).

### 2. `kpi-monitor-head`

The per-panel head (icon badge + `h3` short label + `p` full label + "Туманлар кесими" drill link + watermark) is shown for non-macro modules. Restyle it to the new language: larger icon badge and `h3`, dark text, `#2b61af` accent, the drill link as a clear pill button. It sits at the top of the panel inside the module card.

### 3. The 5 panels — restyle

For each panel, revise its CSS block in `portal.css` to the design language above — larger value/label fonts, `#2b61af` blue, consistent component card borders/radius, dark text over muted greys, transparent panel background. Keep the data and structure; apply a targeted layout fix only where a panel is visibly cramped.

- **`quarter-matrix`** (`budget`, `foreign_invest`, `export`, and employment sub-KPIs) — the 4-period grid; `.quarter-row` / `.q-hero` / `.q-aux`. Bigger period values, clearer plan/fact/status rows.
- **`inflation-details`** — `.drivers` → `.lagging` / `.driver-card`, `.composition` / `.component-card.product-card`. Bigger caps and product values, restyled product cards (the macro sector-card look).
- **`budget-investment`** — `.budget-invest-summary` header bar, `.budget-period-card` grid with progress bars, `.budget-dynamics-card` SVG chart. Bigger summary figures, restyled period cards and progress bars; the SVG chart recolored to `#2b61af`.
- **`unemployment-details`** / **`poverty-details`** — `.poverty-section` / `.poverty-stat` driver cards with progress bars; bigger stat values, restyled progress bars and territory grid.

### 4. Employment front cards

`kpi-front-cards.blade.php` with `employment-layout` renders 6 equal KPI buttons (no parent/hero). Restyle `.front-kpis.module-kpis.employment-layout .front-kpi` to the new front-kpi look — bigger icon badge, bigger label/value, the new card styling — as a clean multi-column grid. No hero (employment has no parent KPI).

### 5. Scoreline — unify

Every module's `kpi-scoreline` should use the new scoreline styling (currently scoped via the `is-macro` class). Generalize so all modules get it — apply the styling to `.scoreline.execution-strip` regardless of module (or always emit the modifier class). The scoreline is under concurrent edits on this branch; the **current committed scoreline styling is taken as the target** — this work only widens its scope to all modules, it does not redesign the scoreline itself.

## Files touched

| File | Change |
| --- | --- |
| `backend/public/css/portal.css` | Bulk: `.module-card` generalization rules, the 5 panels' CSS blocks, `employment-layout` front cards, `kpi-monitor-head`, scoreline scope |
| `backend/resources/views/livewire/kpi-dashboard.blade.php` | Generalized module-card shell |
| `backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php` | Scoreline modifier applied to all modules (if class-based) |

Panel Blade files are touched only where a targeted layout fix needs a markup change; CSS-first otherwise. No PHP component classes change.

## Verification

Per module: render the dashboard headless (Edge `--headless --screenshot`, `--window-size=1920,1010`) at each module's URL (`/dashboard?module=<code>`), and confirm the module shows the white card shell, larger fonts, `#2b61af` coloring, and dark labels — visually consistent with the macro module. Confirm the macro module itself is unchanged. Run the dashboard test scope (`php artisan test --filter "Dashboard|Macro|Kpi|Scoreline"`) — green, no regressions.

## Test note

Existing tests assert on some panel/route markup. `DashboardRoutesTest` checks `module-kpis employment-layout` and `scoreline execution-strip` (base classes) and the inflation panel text — these base classes/strings are preserved, so those tests stay green. If a panel restyle removes a class an existing test asserts on, that test is updated as part of the task that removes the class.
