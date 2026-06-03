# Premium map redesign ("Refined Light") — design spec

**Date:** 2026-06-03
**Branch:** v7-design-polish
**Scope:** `backend/` — the `/districts` choropleth map (palette + map CSS + palette unit test). The map markup/geometry and the `DistrictsPage` component are unchanged.
**Status:** Approved (direction A in mockup `.superpowers/mock-map.html`); ready for implementation plan.

## Problem

The current map reads like a flat "rainbow heatmap," not premium: a saturated red→orange→yellow→green palette, thin dark inter-district borders, district names hidden until hover, and a plain light-blue gradient canvas. It looks utilitarian against an otherwise polished page.

## Goal

Make the map look premium while keeping its meaning and the rest of the page's light theme: refined palette, visible depth, crisp separators, clean always-on labels.

## Non-goals

- No change to map geometry, the SVG markup structure, `DistrictsPage`, or `RegionMapGeometry`/`AndijanMapGeometry`.
- Keep the good→bad color semantics (low = warm, high = green). No mono-blue.
- No `npm run build` — `portal.css` is hand-maintained, edited directly.
- Dark theme (direction B) and mono-brand (C) are explicitly out.

## Confirmed decision

**Direction A — Refined Light.** Cream canvas, crisp white separators, a soft drop-shadow lifting the whole region (depth), a muted/sophisticated palette, always-on clean labels, and a subtle hover-lift. All other premium touches (refined selection, legend matching the new palette) come along.

## Design

### 1. Palette (`app/Support/MapColorScale.php`)

Replace the 5 saturated `STOPS` with a muted, sophisticated ramp that preserves low→high meaning (warm terracotta → sage/eucalyptus green):

| norm | hex | rgb |
| --- | --- | --- |
| 0.00 | `#cf7e6b` (terracotta) | 207,126,107 |
| 0.25 | `#e0a878` (sand) | 224,168,120 |
| 0.50 | `#e8cf8e` (wheat) | 232,207,142 |
| 0.75 | `#a9c79a` (sage) | 169,199,154 |
| 1.00 | `#6fa888` (eucalyptus) | 111,168,136 |

`NO_DATA` stays `#e8e6dd` (reads fine on the cream canvas). Interpolation/clamping/`lowerIsBetter` logic is unchanged — only the stop colors change.

### 2. Map canvas + region depth (`public/css/portal.css`)

- `.districts-map-canvas` background: replace the blue gradient (`linear-gradient(180deg,#f8fbff,#eef4fa)`) with a refined warm neutral (`#fbfaf7`); keep the rounded border and subtle inset.
- `.andijan-map`: add elevation — `filter: drop-shadow(0 10px 16px rgba(40,55,80,.14));` (lifts the whole silhouette off the canvas).

### 3. Separators + labels

- `.map-cell .map-fill`: change the stroke from dark (`rgba(15,42,71,.14)`) to crisp **white** `#ffffff`, `stroke-width: 1.6` — gives clean premium separation between districts. NOTE: `.map-cell .map-fill` is declared twice in `portal.css` (around lines 4039 and 4076); the **second** block wins for `stroke`, so update that one (and reconcile the first so they don't conflict).
- `.map-label` (district names): make **always visible** (`opacity: 1` instead of 0), keep the white halo (`paint-order: stroke fill`, refined `stroke-width`). Names + the existing `.map-value` % render stacked (the geometry already positions name at `cy-4`, value at `cy+10`). `.map-label.is-city` keeps its smaller size for the tiny city cells.
- `.map-label.selected` keeps the blue emphasis.

### 4. Hover + selection

- Hover (`.map-cell:hover .map-fill`): a gentle lift — `filter: brightness(1.03) drop-shadow(0 4px 8px rgba(40,55,80,.20));` plus a slightly stronger white stroke. (Replaces the current brightness+dark-stroke treatment.)
- Selection (`.map-cell.selected .map-fill`): keep the existing blue outline + glow (consistent with the app accent and the rank-list/peek selection).

### 5. Legend (`public/css/portal.css`)

Update `.legend-bar` gradient (and `.legend-bar.reverse`) to the new muted palette so the legend matches the cells:
`linear-gradient(90deg, #cf7e6b, #e0a878, #e8cf8e, #a9c79a, #6fa888)` (reverse = mirror).

## Testing (TDD — update the palette unit test first, to red)

`tests/Unit/Support/MapColorScaleTest.php` pins exact hexes; update to the new palette:

- `0.0, false` → `#cf7e6b`; `1.0, false` → `#6fa888`; `0.5, false` → `#e8cf8e`.
- `0.0, true` → `#6fa888`; `1.0, true` → `#cf7e6b`.
- clamp: `-0.5` → `#cf7e6b`; `1.5` → `#6fa888`.
- `null` → `NO_DATA` (unchanged); interpolation-produces-a-hex and "0.125 differs from both endpoints" stay valid (endpoints become `#cf7e6b`/`#e0a878`).
- Rename the three value-specific test titles from red/green/yellow to low/high/mid for accuracy.

Run (pure unit test, fast): `php artisan test --filter=MapColorScale`. Then a quick `php artisan test --filter=DistrictsPage` to confirm the page still renders (markup unaffected).

CSS changes are presentational — verify with a headless screenshot of `/districts` (Edge `--headless --screenshot`), confirming cream canvas, white separators, elevation, muted palette, visible labels, and a legend that matches.

## Conceptual integrity

Color still encodes plan-vs-fact standing (warm = lagging, green = leading); the refinement only changes aesthetics, not meaning. The map remains a selection source feeding the rank list + peek.
