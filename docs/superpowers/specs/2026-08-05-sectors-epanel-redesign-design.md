# Sectors page redesign — «Прототип Е · Панель» design

**Date:** 2026-08-05
**Status:** Approved (approach A — pure Livewire)
**Source design:** `backend/public/prototypes/e-panel.html` in the `sector-logos` worktree (uncommitted; embeds real plan/fact figures, stays out of git — reference locally only).

## Goal

Move the `/sectors` page (SectorsDashboard) and the `/sectors/{code}` detail page (SectorDetail) to the visual design of prototype E: dark top bar with search, sticky left rail (overall ring + enterprise rating), zoned enterprise cards, and a slide-over drawer for enterprise detail. All interactivity server-side via Livewire (approach A). The existing detail page stays for deep links and shares its task-list markup with the drawer through one Blade partial.

## Non-goals

- No client-side search/filter JS, no FLIP reorder animation (approach A trade-off).
- No changes to import pipelines, models, or status computation.
- No period switcher (still deferred).
- Prototype file itself is not committed and not modified.

## Page structure (`/sectors`)

The page keeps its standalone HTML shell (`pages/sectors.blade.php`, `sectors-body` class, Inter already loaded). New layout:

1. **Top bar** — sticky gradient header (`#2b61af → #0b4c7a`): capsule back link → home, page title, search input on the right. A 2px white progress line sits under the bar; its width is `overall_pct / 120` (prototype `SCALE = 120`), drawn on load via CSS transition.
2. **Body grid** — `282px` sticky rail + card grid, max-width 1320px. Rail collapses to a wrapping row under 1000px.

The current `dp-hero` facts block is removed — the rail replaces it.

## Rail

- **Hero card (dark gradient):** SVG ring showing the overall percentage = **indicator-level** completion across all sectors (`sum(lines_done) / sum(lines_total)`), not task counts. Center: percentage + «индикатор» label. Beside the ring, two large fractions: индикатор `ld/lt` and топшириқ `done/total`. Ring color follows the tier of the overall pct (ok ≥ 100, warn ≥ 50, bad < 50).
- **Rating card («Ижро рейтинги»):** a 17-segment strip, one segment per enterprise in `sort_order`, colored by tier (grey for no report), each `wire:click` opens that enterprise's drawer. Below: «Энг юқори» top-3 and «Энг паст» bottom-3 rows (rank number, name, tier-colored %), also `wire:click` → drawer. Only sectors with a report (`pct !== null`) participate in ranking.

## Enterprise cards

Prototype zones, one `<button>` per card, `wire:click="openSector(code)"` (cards no longer navigate):

- **Zone 1 — header:** index `01`-style counter, 50px logo (server-side monogram fallback when `logoPath()` is null, plus `onerror` monogram fallback), enterprise `cardName()` with the long-name shrink class (> 12 chars per prototype).
- **Zone 2 — measure:** large percentage (44px) with the existing 99-cap rule (only a fully done sector shows ≥ 100; `—` when no report), and two icon stats: топшириқ `done/total`, индикатор `ld/lt`.
- **Zone 3 — strip:** one segment per task, colored: done → green, waiting → grey, otherwise task-pct tier. Tooltip per segment (`Т-01: бажарилди · 119%`). If **all** tasks are waiting: replace strip with «Ҳисобот кутилмоқда» note and dim the card (`waitc`).
- **Footer:** nearest deadline bucket + «Батафсил →» revealed on hover. Bucket taken from each task line's `deadline_code` (already normalized on import to `q3 | q4 | h2 | year`); the card shows the earliest bucket present (q3 → «III чорак», q4 → «IV чорак», h2 → «2-ярим йиллик», year → «Йил якуни»).

Search with no matches renders «Ҳеч нарса топилмади» across the grid.

## Livewire state (approach A — all server-side)

`SectorsDashboard` gains:

| Property / action | Behavior |
| --- | --- |
| `$search` | `wire:model.live.debounce.300ms`; filters the card grid by `cardName()` + `org_full`, case-insensitive; rail and summary stay computed over **all** sectors |
| `$open` (nullable code) + `openSector($code)` / `closeSector()` | drawer visibility; `wire:keydown.escape.window` closes; veil click closes |
| `$drawerFilter` + `setDrawerFilter($key)` | drawer tabs: all / done / open / in_progress; resets to `all` on each `openSector` |
| `$expanded` + `toggleTask($id)` | indicator-lines accordion inside drawer tasks; cleared on open |

Body scroll lock while the drawer is open via CSS `body:has(.sec-over.on) { overflow: hidden }`.

## Drawer ↔ detail page sharing

New Blade partial `resources/views/livewire/partials/sector-tasks.blade.php` renders the shared block: enterprise head (logo, `org_full`, signer line), four stat tiles (умумий ижро %, бажарилди, бажарилмаган, индикатор `ld/lt`), status tabs with counts, and the task list — task number chip, title, status chip, plan/fact facts row (multi-line tasks show `ld/lt` instead), mini progress bar with the 100%-of-plan tick at `83.33%`, deadline, and the expandable indicator-lines table for multi-line tasks.

- **Drawer:** fixed right panel (`min(600px, 96vw)`) + veil, slides in via CSS class toggled by Livewire render; left border colored by the sector's tier.
- **`/sectors/{code}`:** keeps its route; rebuilt to the same visual language — its own top bar (back → `/sectors`, no search) and the shared partial as the page body. `SectorDetail`'s existing `$filter` / `$expanded` / `setFilter` / `toggleTask` API is aligned with the partial's expectations (names unified with SectorsDashboard's drawer actions so the partial binds identically in both components).

## CSS & animations

- Prototype CSS is ported into the hand-maintained `public/css/portal.css` (no build step), scoped under `.sectors-body` / the detail page's body class with a `sec-`-style prefix where prototype names are too generic. The old `.sector-card` / `.sdp-*` blocks are deleted.
- Animations kept: `rise` entrance for rail cards, ring draw-in + count-up (tiny presentation-only inline script, runs once on page load, ignores Livewire morphs), card strip scale-in, drawer slide + veil fade, stat-tile stagger inside the drawer. `prefers-reduced-motion` collapses all of it (prototype's rule kept).
- Livewire morphs (search results, tab switches) render without entrance animations — accepted approach-A trade-off.
- Card entrance on scroll (IntersectionObserver) applies only to the initial page load, with the prototype's fallback that reveals cards immediately if IO never fires.

## Data notes

- Overall/tile percentages all use the shared 99-cap display rule (`pshow`): fully done → round, otherwise `min(99, round)`.
- Tier thresholds unchanged: ok ≥ 100, warn ≥ 50, bad < 50, wait = no report.
- `SectorsDashboard::render()` already eager-loads tasks; the drawer additionally needs the open sector's progress lines for the latest period (same query shape as `SectorDetail`).
- A sector with zero tasks renders a card with `—`, empty strip, no crash.

## Testing

Update/extend the sector Pest feature tests:

- search narrows the card list (and empty-state message), rail unaffected;
- `openSector` renders drawer content with the sector's tasks; `closeSector` hides it;
- drawer tab filter changes visible task counts; filter resets on reopen;
- `toggleTask` expands indicator lines;
- rating rail: top-3/bottom-3 ordering, no-report sectors excluded from ranking;
- 99-cap rule preserved on cards, ring, and drawer tiles;
- `/sectors/{code}` still renders (shared partial) with tabs working.

Full suite expected green.

## Files touched

- `backend/app/Livewire/SectorsDashboard.php`, `backend/app/Livewire/SectorDetail.php`
- `backend/resources/views/livewire/sectors-dashboard.blade.php` (rewrite)
- `backend/resources/views/livewire/sector-detail.blade.php` (rewrite around partial)
- `backend/resources/views/livewire/partials/sector-tasks.blade.php` (new)
- `backend/resources/views/pages/sectors.blade.php`, `backend/resources/views/pages/sector-detail.blade.php` (top bar, body class, inline animation script)
- `backend/public/css/portal.css` (new scoped section; old sector styles removed)
- `backend/tests/Feature/` sector tests
