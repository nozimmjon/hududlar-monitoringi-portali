# Starter page (module chooser) — design

**Date:** 2026-08-05
**Status:** Approved (approach A — static Blade + controller aggregates)
**Branch:** `starter-page`

## Goal

A new front door at `/` presenting the portal's two modules as rich cards — **Вилоятлар** (regional monitoring, currently the map homepage) and **Тармоқлар** (sector enterprises). The current map homepage moves to `/regions`; its sectors capsule is removed. The starter page carries live aggregates per module and a high production standard: professional composition, choreographed animation, no AI-slop defaults.

## Non-goals

- No Livewire on the starter page (read-only nav + stats).
- No changes to the map page beyond the capsule swap (sectors capsule out, «← Бош саҳифа» crumb in).
- No changes to `/region/{code}`, dashboard, tasks, districts, execution, or sectors pages' internals.
- Prototypes are not committed (repo convention for `backend/public/prototypes/`).

## Page structure (`/`)

Standalone Blade shell like the other entry pages (own `<!doctype html>`, Inter via Google Fonts variable range `wght@400..900`). The page's CSS lives inline in the Blade file, self-contained like `pages/home.blade.php` — portal.css is NOT loaded. Served by new `StarterController@index`.

Content:

- Wordmark row: navy logo chip (existing `logo.svg` treatment) + portal name.
- Two module cards, equal visual weight:
  - **Вилоятлар** → `/regions`: 14 ҳудуд, топшириқ done/total, overall ижро % — STRICT reading (`status = 'done'` only), deliberately diverging from the map page's lenient done+in_progress pills; both sides carry cross-referencing comments (decided at implementation review).
  - **Тармоқлар** → `/sectors`: 17 корхона, топшириқ count, indicator-level % with the 99-cap rule via `App\Support\SectorDisplay`; while no actuals are reported the card shows the «ҳисобот кутилмоқда» state instead of a misleading 0%.
- Both cards are full-card links with keyboard focus states.

Stats come from `Cache::remember` (~10 minutes, keys `starter.regions`, `starter.sectors`). Failure-safe: if either aggregate query fails, the card renders without numbers rather than 500ing the front door.

## Routing

| Route | Name | Handler | Change |
| --- | --- | --- | --- |
| `/` | `home` | `StarterController@index` (new) | starter page takes over the root and the `home` route name — every existing «Бош саҳифа» back-link retargets automatically |
| `/regions` | `regions` | `HomeController@index` | current map homepage, unchanged internals |
| `/region/{code}` | `region.enter` | `HomeController@enter` | untouched |
| everything else | — | — | untouched |

## Map page edits (`pages/home.blade.php` → serves `/regions`)

- Remove the `.sectors-link` capsule («Тармоқлар» button) and its markup/CSS.
- Add a capsule crumb «← Бош саҳифа» → `route('home')` in the top row (existing capsule-button language, matching `dp-crumb`/`sec-back` conventions).

## Prototype round (this cycle's deliverable)

Four standalone HTML prototypes in `backend/public/prototypes/` (uncommitted), plus an entry added to the local prototypes `index.html`. All: Cyrillic Uzbek, Inter, CERR navy anchor (#2b61af → #0b4c7a), representative hardcoded numbers, full choreography (entrance sequence, animated counters, hover states), `prefers-reduced-motion` collapse, responsive to 360px.

| File | Direction | Character |
| --- | --- | --- |
| `start-a.html` | «Диптих» | Full-viewport split screen; the two halves ARE the modules (map silhouette vs enterprise motif); hover shifts the seam; equal-weight composition |
| `start-b.html` | «Тунги кино» | Cinematic dark navy; animated country-outline line-work; two glass cards rise with a light sweep |
| `start-c.html` | «Муқова» | Editorial light; oversized typographic masthead; cards as cover plates with big stat columns |
| `start-d.html` | «Панель давоми» | Continuation of the e-panel language: same top-bar DNA, kcard surfaces, ring gauges — maximum coherence with `/sectors` |

The user picks one (or a hybrid); the winning file becomes the design source for the implementation plan, same flow as the e-panel round.

## Testing (implementation phase)

- `/` renders the starter page: both module names, both stat blocks (or waiting state), links to `/regions` and `/sectors`.
- `/regions` renders the map page (title, region list markers).
- The map page no longer contains the sectors capsule; it contains the «Бош саҳифа» crumb.
- `route('home')` resolves to `/`; `route('regions')` to `/regions`.
- Existing root-route test (asserts the map at `/`) updated to point at `/regions`.
- Aggregate caching: starter controller test seeds data and asserts numbers + cache keys populated; failure-safe path covered.

## Files (implementation phase, indicative)

- `backend/app/Http/Controllers/StarterController.php` (new)
- `backend/routes/web.php` (root + regions rewire)
- `backend/resources/views/pages/start.blade.php` (new, from winning prototype)
- `backend/resources/views/pages/home.blade.php` (capsule swap only)
- `backend/tests/Feature/` route/controller tests
