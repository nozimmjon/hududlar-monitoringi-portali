# Professional map redesign (navy + binary + perimeter pills) — design spec

**Date:** 2026-06-03
**Branch:** v7-design-polish
**Scope:** `backend/` — the `/districts` map. New `MapLabelLayout` helper, `DistrictsPage` map data, the view's map section, `portal.css`, tests.
**Status:** Approved (prototype `.superpowers/mock-map-pro.html` validated on the real Andijan shape); ready for implementation plan.
**Supersedes:** the "Refined Light" premium map (`2026-06-03-premium-map-design.md`) and its follow-ups (names-off, number declutter). The cream/gradient palette and on-map % numbers are replaced by this navy/binary/pill design.

## Problem / goal

The map should look like a professional government dashboard (per the user's reference `.superpowers/map.png`): a dark navy canvas, districts colored by a simple pass/fail signal, and clean name+value labels arranged **around** the map instead of inside the cells. It must work for **every region** (not just Andijan).

The map already renders any region: `DistrictsPage::mapGeometry()` → `RegionMapGeometry::forRegion()` projects district polygons from the national GeoJSON `districts.json`. So this is a styling + labeling change that applies to all regions automatically — no new geometry data is required.

## Confirmed decisions

1. **Full replica of the reference** — navy canvas, binary green/red districts, **perimeter pill labels** (name + value) auto-placed around the map with leader lines, all region-agnostic.
2. **Layout:** the map + pills become a **full-width hero**. The separate **rank list is removed** (the pills are the labels). The header card (module segmented control + KPI stat-cards) stays on top. Clicking a district *or* a pill opens the existing **slide-over peek**.
3. **Color (binary):** reuse the existing district status — `green` = on track; `amber` + `red` → **red**; no-data → neutral **grey**. (Status already handles plan %, growth, and lower-is-better.)

## Architecture

- **New `App\Support\MapLabelLayout`** — a pure function that, given the projected geometry + per-district label data, returns the expanded viewBox, the map translate, and a list of placed pills (position, size, side, leader path, anchor dot). Pure and **unit-testable**; region-agnostic (driven only by centroids + count).
- **`DistrictsPage`** — provides per-district label data (short name, formatted value, binary color) and exposes the computed layout to the view. Drops the gradient machinery.
- **View** — renders one full-width navy map stage: the projected cells (binary fills, clickable) inside a translated group, plus the layout's leaders/dots/pills (clickable), a small binary legend, and the kept tooltip. Removes the rank-list section.
- **`portal.css`** — navy stage, binary cell colors, pill + leader styles, legend; remove the superseded cream/gradient/in-cell-label/dot/rank-list rules.

### `MapLabelLayout::build()`

```
build(array $geometry, array $labels, int $gutter = 230): array
```

- `$geometry`: the `RegionMapGeometry` output — `['viewBox' => '0 0 600 500', 'cells' => [['code','name','path','cx','cy'], …]]`.
- `$labels`: `code => ['name' => short, 'value' => '264,2%', 'color' => 'ok'|'bad'|'nd']`.
- Returns:
  ```
  [
    'viewBox'      => '0 0 1060 500',   // VW + 2*gutter
    'mapTranslate' => 230,               // x shift applied to the map <g>
    'pills' => [
      ['code','side','x','y','w','h','name','value','color','leader','dotX','dotY'], …
    ],
  ]
  ```

**Algorithm:**
1. Parse `VW`,`VH` from the viewBox; `outerW = VW + 2*gutter`; `centerX = VW/2`.
2. Side: `cx < centerX` → left, else right. Sort each side by `cy` ascending.
3. Distribute vertically: for a side with `n` pills, `y_i = top + i * (VH - 2*top)/(n-1)` (`top = 20`; single pill → `VH/2`). Even spacing guarantees no overlap.
4. Pill width: estimate server-side from text length — `w = 2*padX + mb_strlen(name)*nameCharW + gap + mb_strlen(value)*valCharW` (`padX≈9`, `gap≈8`, `nameCharW≈6.4`, `valCharW≈6.6`, `h=20`). Left pills `x=6`; right pills `x = outerW - 6 - w`.
5. Leader: an elbow `path` from the pill's inner edge (`L: x+w`, `R: x`) at `y` to the anchor `(cx + gutter, cy)`; anchor dot at the same point.
6. To reduce leader crossings, place each side's pills in the **same top→bottom order as their centroids** (step 2's sort) — adjacent districts get adjacent pills.

Cities (tiny cells) need no special handling — their label is an external pill like everyone else.

Every geometry cell with a `code` gets a pill. A cell with no matching district fact is `color: 'nd'` (grey) with `value: '—'` but still placed and labeled. Cells with a null/blank `code` (shouldn't occur for real regions) are skipped.

### `DistrictsPage` changes

- **Add** `mapLayout()` computed: builds `$labels` from `rankedDistricts`/`districts`/`statusByDistrict` — `name` = `District.name_short`, `value` = formatted primary (`pct_of_plan`, else `growth_pct`, else `—`), `color` = `green→ok`, `amber|red→bad`, `grey/no-data→nd` — then returns `MapLabelLayout::build($this->mapGeometry, $labels)`.
- **Add** a small map fill-color map (code → `ok|bad|nd`) for the cell `<path>` fills (or reuse the same `$labels` color).
- **Remove** `colorScale()` and `colorRange()` computed and their `render()` payload (gradient is gone).
- **Keep** `facts`, `statusByDistrict`, `rankedDistricts`, `districts`, `rollup`, `mapGeometry`, `taskCountByDistrict`, `targetCountByDistrict`, `moduleOptions`, `kpiOptions`, `moduleKpiStats`, `selectedDistrict`, `selectDistrict`, `clearDistrict`, and the peek.

### View changes (`districts-page.blade.php`)

- Replace the `.districts-grid` (map | rank list) with a **single full-width** `.districts-mapstage` (navy) containing one `<svg :viewBox="$layout['viewBox']">`:
  - `<g transform="translate({mapTranslate},0)">` with one clickable `<g class="map-cell {color}" wire:click="selectDistrict(code)" + keydown + tooltip handlers>` per cell (`<path class="map-fill">`). Selected cell gets a `selected` class.
  - leaders (`<path class="map-leader">`) + anchor dots (`<circle>`).
  - pills: `<g class="map-pill {color} {selected?}" wire:click="selectDistrict(code)">` → `<rect class="pill-bg">` + `<text class="pill-name">` + `<text class="pill-value">`.
  - keep the Alpine hover `.map-tooltip`.
- **Remove** the `.districts-ranklist` section entirely.
- **Keep** the header card (module seg + hero + KPI stats) and the slide-over peek + backdrop.
- A small binary **legend** (HTML, stage corner): green = `Режада`, red = `Эътибор`, grey = `Маълумот йўқ`.

### CSS (`portal.css`)

- **Add:** `.districts-mapstage` (navy `#0e1c3f`, rounded, padding, shadow), `.map-cell.ok/.bad/.nd .map-fill` fills (`#37a34a` / `#e0473a` / `#6b748c`) with dark separators, `.map-cell.selected` emphasis, `.map-leader`, `.map-pill` (`.pill-bg`, `.pill-name`, `.pill-value.ok/.bad`), `.map-pill.selected`, `.map-legend` (on-navy).
- **Remove:** the cream-canvas/gradient/in-cell-label/`map-dot`/`map-value` rules and the `.districts-ranklist`/`rank-row`/`ranklist-*` rules (no longer rendered). Reconcile the responsive map rules.

## Removals (superseded)

- `app/Support/MapColorScale.php` and `tests/Unit/Support/MapColorScaleTest.php` — the gradient palette is unused after binary fills. Delete both.
- `DistrictsPage::colorScale()` / `colorRange()`.
- Rank-list markup + CSS; cream/gradient/in-cell-number/dot map CSS.

## Testing (TDD)

- **New `tests/Unit/Support/MapLabelLayoutTest.php`** (pure unit — fast): with a small synthetic geometry (a few cells at known cx,cy in a `0 0 600 500` viewBox):
  - returns one pill per labeled cell; `viewBox` width = `600 + 2*gutter`; `mapTranslate` = gutter.
  - side assignment: cells with `cx < 300` → `side==='L'`, else `'R'`.
  - left pills have `x` near the left gutter; right pills satisfy `x + w <= outerW`.
  - within a side, pill `y` values are strictly increasing and ordered by centroid `cy` (no two equal → no overlap).
  - each pill's `leader` ends at `(cx + gutter, cy)` (dotX/dotY).
- **Update `tests/Feature/Http/DistrictsPageTest.php`:** the bare-GET test asserts `districts-ranklist` — change to assert the new `districts-mapstage` + a pill class (e.g., `map-pill`) and `assertDontSee('districts-ranklist')`. The peek-on-`?district` and plain-label peek tests stay (peek kept). The no-pre-selection assertions stay.
- **Delete `MapColorScaleTest.php`** (class removed).
- Run targeted, alone: `php artisan test --filter=MapLabelLayout` then `php artisan test --filter=Districts`.
- Visual: headless screenshot of `/districts` (and ideally a second region via the region switcher / `?region=` if available) to confirm pills place sensibly for a different shape.

## Conceptual integrity

Color still encodes standing (green = meeting the promise, red = lagging) — now binary for clarity. Plan-vs-fact lives in the pills (value), the hero (region value), and the peek (Режа/Факт). Drill chain preserved: map/pill → peek → Профил/Журнал. Because pills are inside the SVG viewBox, the whole map scales as one unit (responsive; no overflow). Known limitation: on very narrow screens pill text shrinks with the map — acceptable; revisit if needed.
