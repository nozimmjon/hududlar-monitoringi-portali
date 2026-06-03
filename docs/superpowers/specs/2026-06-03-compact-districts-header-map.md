# Compact districts header + map (fit one screen) — design spec

**Date:** 2026-06-03
**Branch:** v7-design-polish
**Scope:** `backend/` — `/districts` header + map sizing. View + `portal.css`; small `DistrictsPage` cleanup.
**Status:** Approved; ready for implementation plan.

## Problem / goal

At 1366×768 the header + map overflow one screen — the lower perimeter pills (Булоқбоши, Шаҳрихон, Асака, Мархамат…) are clipped below the fold. Goal: header + map fit one screen with **no vertical scroll at 1366×768** (so it fits larger screens too). Shrink the header and the map; remove the map legend; keep every other map element.

## Confirmed decisions

1. **Target:** fit 1366×768 (no vertical scroll).
2. **Stat-cards dropped:** remove the three KPI value-cards. KPI switching stays via a slim label-only KPI pill row. (Trade-off accepted: other KPIs' region values are no longer shown at a glance — only the active KPI's value in the hero.)
3. **Legend removed.**
4. **Keep all other map elements:** binary green/red/grey districts, perimeter pills (name + value), curved leaders + anchor dots, hover tooltip, click → slide-over peek, selection highlight.

## Design

### 1. Header — slimmed (~150px → ~80px)

- **Module** segmented control: kept; smaller button padding/font (`.module-seg-btn` ~`8px 15px`→`6px 12px`, 13→12.5px).
- **Drop the `.kpi-stats` block** (the three `.kpi-stat-card`s). Replace with a **`.kpi-switch`** thin row of small label-only pills — one `.kpi-switch-btn` per `kpiOptions` entry (text = `label_short`, `title` = `label_full`, active gets `on`), `wire:click="selectKpi(...)"`. Shown only when `$kpiOptions->count() > 1`.
- **Hero** (`.districts-hero`) kept, compacted via CSS: icon 48→~34 (svg 24→18), title `h2` 22→~17px, subtitle 12.5→11px, value `strong` 28→~22px, tighter padding/gap. Layout unchanged: icon + KPI title (left) · region value + period chip (right).
- Reduce `.districts-header` padding + `margin-bottom`.

### 2. Map — smaller via a height cap (uniform scale-down)

- Cap the map so the whole SVG scales down as one unit (the `region-map` SVG already preserves aspect ratio, so districts + pills + leaders + dots shrink together — no reflow, the proven `MapLabelLayout` math is untouched).
- Implementation: `.region-map { width: 100%; height: auto; display: block; margin: 0 auto; max-height: calc(100vh - <chrome>px); }` where `<chrome>` ≈ top bar + slim header + map-stage head/padding (start ~230, **tune against a 1366×768 screenshot** so the bottom pills are fully visible with a small margin). The `calc(100vh - …)` makes the cap responsive — it fits 768 and grows on bigger screens.
- Shrink `.mapstage-head` (smaller title/subtitle) and reduce `.districts-mapstage` padding to reclaim vertical space.

### 3. Legend — removed

- Delete the `.map-legend` element (currently the absolute chip inside `.mapstage-canvas`) and its CSS (`.map-legend`, `.map-legend span`, `.map-legend i`, `.map-legend i.ok/.bad/.nd`).

### 4. Component cleanup (`DistrictsPage.php`)

- The KPI value-cards were the only consumer of `moduleKpiStats()`. **Remove the `moduleKpiStats()` computed method.** It is not passed via `render()`; the view reads it through the top `@php` block (`$moduleKpiStats = $this->moduleKpiStats;`) — remove that line too. Keep `mapColors()`, `mapLayout()`, and everything else.

## Files

| File | Change |
| --- | --- |
| `backend/resources/views/livewire/districts-page.blade.php` | Replace `.kpi-stats` block with `.kpi-switch`; remove `.map-legend`; remove the `$moduleKpiStats` `@php` line. |
| `backend/app/Livewire/DistrictsPage.php` | Remove `moduleKpiStats()` computed. |
| `backend/public/css/portal.css` | Shrink header/hero/module-seg; add `.kpi-switch*`; remove `.kpi-stat*` + `.map-legend*`; cap `.region-map` height; shrink `.mapstage-head`/`.districts-mapstage` padding. |

## Testing

- No feature test asserts `.kpi-stats`/`.map-legend`/`moduleKpiStats`; the bare-GET test asserts `districts-header`, `module-seg`, `districts-mapstage`, `map-pill` (all retained). Run `php artisan test --filter=Districts` to confirm green; run `--filter=MapLabelLayout` (unaffected, should stay 7).
- **Visual gate:** headless screenshot at exactly `--window-size=1366,768`; confirm the entire header + map (including the lowest pills) is visible with no clipping and a small bottom margin. Tune the `max-height` `<chrome>` constant until it fits. Also spot-check 1920×1080 (should look roomy, not stretched).

## Out of scope / preserved

- `MapLabelLayout` algorithm, the peek, the binary palette, curved leaders, pill placement — unchanged.
- Conceptual integrity intact: hero region value + pills + peek keep plan-vs-fact; map/pill → peek → Профил/Журнал drill chain.
