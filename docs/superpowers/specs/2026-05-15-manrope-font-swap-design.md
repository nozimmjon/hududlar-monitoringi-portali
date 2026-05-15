# Swap dashboard font from Inter/Inter Tight to Manrope

**Date:** 2026-05-15
**Scope:** Replace Inter + Inter Tight with Manrope as the single sans-serif family across the portal, with a CSS custom property for one-point-of-change.

## Goal

The current portal loads two Google Fonts families — Inter (body, weights 400-800) and Inter Tight (display, weights 600-800). Cyrillic letter shapes are functional but generic. The user wants a font that "matches and shows beautifully" Uzbek Cyrillic. Manrope was chosen: geometric humanist sans with strong Cyrillic coverage, weights 200-800, widely used in modern dashboards.

Side benefit: introduce `--font-sans` CSS variable so future swaps are a one-liner instead of a 15-file search-and-replace.

## In scope

- `backend/resources/views/layouts/app.blade.php` — Google Fonts `<link>` URL.
- `backend/resources/views/welcome.blade.php` — same `<link>` URL.
- `backend/public/css/portal.css` — replace every `font-family: "Inter…"` declaration with `font-family: var(--font-sans);`, define the variable on `:root`.

## Out of scope

- Self-hosting the font (still Google Fonts CDN).
- Italic variants (Manrope has none; no current rule requires italic).
- Body copy size / line-height / spacing changes — only the family changes.
- Print stylesheet adjustments (Manrope works in print too; no separate rule).

## Architecture

One CSS custom property on `:root`:

```css
:root {
    --font-sans: "Manrope", "Segoe UI", Arial, sans-serif;
}
```

Every `font-family` rule in `portal.css` references `var(--font-sans)`. The Google Fonts link tag in the two blade layouts is updated to load Manrope at weights 400, 500, 600, 700, 800 with the `cyrillic` and `cyrillic-ext` subsets (required for Қ, Ў, Ҳ, Ғ).

## Components

### Google Fonts link

Replace the existing `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">` in both:
- `backend/resources/views/layouts/app.blade.php` (line 10)
- `backend/resources/views/welcome.blade.php` (line 18)

with:

```html
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
```

Keep the `preconnect` lines unchanged — they're for `fonts.googleapis.com` and `fonts.gstatic.com`, both still used.

### CSS variable + family swap

In `backend/public/css/portal.css`:

1. Inside the existing `:root { ... }` block (top of the file, contains design tokens like `--ink`, `--blue`), add the variable:

```css
--font-sans: "Manrope", "Segoe UI", Arial, sans-serif;
```

2. Replace every line of the form:

```css
font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
```

or

```css
font-family: "Inter", "Inter Tight", "Segoe UI", Arial, sans-serif;
```

with:

```css
font-family: var(--font-sans);
```

(Match indentation to the surrounding rule. The earlier grep showed 15+ occurrences; all should become a single shared variable reference.)

### Font-weight ceiling

The existing portal uses `font-weight: 850`, `900`, `950` in some rules (e.g. `.macro-hero-strip__value`, `.macro-hero-copy strong`). Manrope tops at 800. Browsers round excess weights down to the closest available, so visually these will render at 800 instead of 900 — slightly less heavy.

This is acceptable. Do **not** edit those rules in this pass. If a hero number visibly weakens, follow up with a tightening (`letter-spacing: -.045em`) — out of scope for this spec.

## Data flow

None — CSS-only change.

## Testing

- Manual smoke (5 pages):
  - `/dashboard?kpi=grp` — macro hero strip, KPI cards, module tabs.
  - `/dashboard?kpi=industry` — industry driver panel.
  - `/districts` — choropleth map labels + table rows.
  - `/tasks` — task list, executor names.
  - `/profile` — district profile sections.
  - `/execution` — execution monitoring.
- For each: confirm Cyrillic Uzbek-specific glyphs (Қ, Ў, Ҳ, Ғ, Ё, Й) render in Manrope (use DevTools → Computed → `font-family` shows `Manrope`).
- `curl -s http://127.0.0.1:8765/dashboard | grep -i manrope` returns the Google Fonts link.
- Pest: no test changes needed. Run full unit suite to confirm no regression (78 passed).

## Risks

- **Cyrillic-ext subset:** Some Uzbek-specific glyphs (Қ, Ў, Ҳ, Ғ) live in the `cyrillic-ext` Unicode range. The URL above includes both `cyrillic` and `cyrillic-ext` subsets so the browser fetches the right WOFF2.
- **Weight rounding:** Rules using 850/900/950 will render at 800. Acceptable visual impact. Documented in "Font-weight ceiling" above.
- **Table column width drift:** Manrope at the same point size is slightly narrower than Inter. Tables on `/districts` may shift column widths by a few pixels; spot-check during manual smoke. No code action expected.
- **FOIT vs FOUT:** `display=swap` is preserved → fallback to Segoe UI / Arial during load, then swap to Manrope.

## Files touched

- `backend/resources/views/layouts/app.blade.php` — line 10.
- `backend/resources/views/welcome.blade.php` — line 18.
- `backend/public/css/portal.css` — add `--font-sans` to `:root`, replace ~15 `font-family` declarations with `var(--font-sans)`.
