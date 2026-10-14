# /roadmaps flaw fixes — design

**Date:** 2026-10-14 · **Status:** approved by user (chat) · **Scope:** /roadmaps page, RegionSwitcher, roadmap status model, admin panel status split.

Audit of the standalone /roadmaps page found the page's "no data yet" reality had no honest visual language, plus a handful of trust/UX flaws. User picked five fixes; the rest (stale-period stamp, KPI-strip dedupe, export/print) are parked.

## 1. New status `no_report` — «Маълумот киритилмаган»

Today a measure with zero reports shows purple «Бажарилмоқда» (104 of Andijan's 105) — reads as "work underway" to an official. New fourth status makes "nothing entered" explicit.

**Rule** (in `App\Services\Roadmaps\MeasureRecomputer`, the single status authority):

- `lines_total === 0` (no indicator lines defined) → `no_report`. (Was: `in_progress`.)
- Lines exist but the measure has **zero progress rows for any period** (`latest_period === null`) → `no_report`.
- Rows exist with all-zero actuals → data WAS entered; keep existing aggregate/deadline logic (`in_progress`/`open`). Row existence decides, not the values.
- `done` logic unchanged.

**Storage:** written to `roadmap_measures.status` (varchar(16), no migration needed). `php artisan roadmaps:recompute` rebuilds after deploy.

**Display:**

- `MeasureDisplay::STATUS` gains `'no_report' => ['cls' => 'none', 'label' => 'Маълумот киритилмаган']` — grey chip. Unknown-status fallback switches from `in_progress` to the grey chip.
- RoadmapsPage rail: 5th filter button (grey dot) after «Бажарилмаган»; counts array + `holat=no_report` URL value.
- Top KPI strip: 5th grey tile «маълумот киритилмаган».
- Hero: drop the «N тадбирда индикатор йўқ» note — that population is now inside the grey count. Ring math unchanged (mean over reported measures only).
- Admin panel: `Admin\RoadmapOverview` per-region status split gains the grey segment; `Admin\RoadmapEntry` status chip mapping gains the label (recomputer already feeds both).

## 2. Region switcher: roadmaps-only list + empty-state leak

- `RegionSwitcher` gets an optional boolean prop `roadmapsOnly` (default false). When true, the option list is regions having a row in `roadmaps` (13 today — Тошкент шаҳри 1726 has no file and never will). Not a hardcoded 1726 exclusion.
- Only `pages/roadmaps.blade.php` passes `:roadmaps-only="true"`. Portal sidebar keeps all 14 regions.
- Edge: if the session region is not in the filtered list (e.g. 1726 set earlier), the trigger button still renders that region's name via `CurrentRegion::current()`; the page shows its empty state as today.
- Empty state text drops the `php artisan import:roadmap --region=…` line — developer runbook text must not reach officials. Keep only «<Region> учун сув хўжалиги йўл харитаси ҳали юкланмаган».

## 3. Indicator row: single value cell

`livewire/partials/wr-line.blade.php` drops the right-hand `.pp` percent span (duplicated the card ring; on unreported rows it was a second dangling «—»). Row becomes: label · tier bar · «actual / plan unit». `.wr-line` grid templates (base + ≤600px override) lose the percent column.

## 4. Rail section labels: 2-line clamp

Section buttons currently one-line-ellipsis — sections IV and V both truncate to «Вилоятнинг хусусиятид…». Switch `.wr-kcard button .t` (section rows) to a 2-line clamp (`display:-webkit-box; -webkit-line-clamp:2`). Count badge stays top-right aligned.

## 5. Mobile (<1100px): collapsed accordion rail

At <1100px the rail stacks above the cards (~2 screens of filters). Chosen pattern (user): keep search + full hero on top, collapse the three filter cards (Ҳолат · Бўлимлар · Туманлар) into tap-to-expand accordions, collapsed by default.

- Each `wr-kcard` gets an accordion header button, visible only <1100px (desktop: cards render as today, always open, header hidden).
- Header shows the group name plus the active pick when filtered («Ҳолат: Бажарилди», «Бўлим: II», «Туман: Асака»).
- Alpine `x-data {open:false}` per card, `x-show` on the body — plain show, no x-transition (rAF pitfall), CSS animation if any.
- Selecting an option inside closes that accordion (one-handed flow); state is per-card, not persisted.

## Testing

- Unit: `MeasureRecomputer::aggregate`/`recompute` — no lines → no_report; lines without rows → no_report; rows with zero actuals → not no_report; existing done/open cases unchanged.
- Feature: RoadmapsPage — 5th count key, `holat=no_report` filter, grey chip rendering, hero note removed; RegionSwitcher — `roadmapsOnly` filters list, sidebar unaffected, 1726-session edge renders name; empty state has no artisan text; admin overview split includes no_report.
- Existing suites re-run: RoadmapsPageTest, RoadmapsRecomputeTest, RegionSwitcherTest, Admin\RoadmapOverviewTest, Admin\RoadmapEntryTest.
- Deploy note: run `php artisan roadmaps:recompute` once after merge.

## Out of scope (parked)

Stale-period stamp near the title; KPI strip/rail count dedupe; export/print; period history switcher.
