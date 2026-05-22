# Macro Hero Fidelity Fix — Design Spec

**Date:** 2026-05-22
**Branch:** v7-design-polish
**Reference:** `C:\Users\y.utepbergenov\Desktop\Screenshot 2026-05-22 100100.png`

## Goal

Bring the macro dashboard's ЯҲМ hero panel (`.front-kpi.parent`) to full visual fidelity with the reference image: a curved-line decorative arrow, a soft light halo around the icon badge, and corrected internal font sizes / element positions.

## Scope

- **In scope:** the `.front-kpi.parent` hero panel only.
- **CSS-only.** All changes in `backend/public/css/portal.css`, in the existing `.front-kpis.module-kpis.macro-layout .front-kpi.parent` rule block. The Blade markup already renders the needed elements (`.kpi-icon`, `h3`, `.front-kpi-value`, `.front-kpi-note`) — no markup change.
- **Out of scope:** sector cards, period card, scoreline, tabs, every other module.

## Current state

The hero is a bright royal-blue panel (`linear-gradient(160deg,#2360d6,#1e59cc)`) holding: a white `.kpi-icon` badge + "ЯҲМ" `h3` on row 1, the "+7,8%" `.front-kpi-value` on row 2, the "йиллик ўсиш" `.front-kpi-note` on row 3. A `::before` pseudo-element currently draws a **solid rounded triangle** bottom-right. The icon badge has **no glow**.

## Design

### 1. Curved-line decorative arrow

Replace the `::before` solid-triangle background with a **curved-line growth arrow** — a pale line sweeping up from lower-left to upper-right, ending in an open arrowhead. Inline SVG data-URI, `fill:none`, stroked, round caps/joins.

The `::before` rule becomes:

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

The arrow is anchored bottom-right and partially clipped by the panel's existing `overflow: hidden`. `opacity: .32` keeps it a pale decorative element over the royal-blue background. This overrides the image's literal solid triangle per the user's explicit choice.

### 2. Light halo around the icon

Add a layered white `box-shadow` glow to the hero icon badge so it matches the soft halo in the reference:

```css
    .front-kpis.module-kpis.macro-layout .front-kpi.parent .kpi-icon {
      ...existing width/height/border-radius/background/color/position/z-index...
      box-shadow: 0 0 0 6px rgba(255, 255, 255, .12),
                  0 0 36px 8px rgba(255, 255, 255, .30);
    }
```

The first layer is a soft 6px translucent-white ring hugging the badge; the second is a wide soft bloom. The glow is naturally contained by the panel's `overflow: hidden`.

### 3. Font sizes & element positions

Tune the hero internals toward the reference proportions. Starting values (from measuring the reference, then verified and adjusted by screenshot — see Verification):

| Element | Property | From | To |
| --- | --- | --- | --- |
| `.front-kpi.parent` | `padding` | `30px` | `32px` |
| `.front-kpi.parent` | `gap` | `12px 18px` | `10px 20px` |
| `.kpi-icon` | `width` / `height` | `58px` | `64px` |
| `.kpi-icon` | `border-radius` | `16px` | `18px` |
| `.kpi-icon svg` | `width` / `height` | `36px` | `40px` |
| `h3` (ЯҲМ) | `font-size` | `31px` | `33px` |
| `.front-kpi-value` (+7,8%) | `font-size` | `clamp(46px, 5vw, 70px)` | `clamp(52px, 5.4vw, 78px)` |
| `.front-kpi-note` | `font-size` | `14px` | `15px` |

Intent: the icon badge slightly larger and clearly haloed; "ЯҲМ" sized just under the icon height; "+7,8%" the dominant element; "йиллик ўсиш" a small caption. Row 1 = icon + ЯҲМ (vertically centered together), row 2 = value, row 3 = note — the existing grid (`grid-template-columns: auto minmax(0,1fr)`, value/note spanning `1 / -1`, `align-content: center`) is unchanged.

## Verification

After implementation, render the dashboard headless (Edge `--headless --screenshot`, `--window-size=1920,1010`) and crop the hero region. Compare crop-to-crop against the same region of the reference image. If the icon size, "ЯҲМ"/"+7,8%" sizes, spacing, glow strength, or arrow shape/placement do not visually match, adjust the Section 3 values (and glow opacity / arrow size) and re-render until they do. The arrow must read as a curved line with an arrowhead; the icon must show a visible soft halo.

## Files touched

| File | Change |
| --- | --- |
| `backend/public/css/portal.css` | `.front-kpi.parent` `::before` (curved arrow), `.kpi-icon` (glow `box-shadow`), and the parent / icon / `h3` / value / note size values |

No Blade, no PHP, no test changes (no test asserts on these visual properties).
