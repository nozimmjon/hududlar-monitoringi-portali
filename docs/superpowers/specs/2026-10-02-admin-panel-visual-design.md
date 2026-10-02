# Admin panel visual redesign — «Ёруғ шиша» (light glass)

**Date:** 2026-10-02
**Scope:** the two admin panel screens (`/admin/roadmaps`, `/admin/roadmaps/{code}`) and the
panel's secondary surfaces (correction panel, history drawer, upload drawer, login card).
**Not in scope:** the public portal, any behaviour change, any new feature, dark mode.

## 1. Why

The panel works but looks unfinished. Three conservative directions were shown and rejected
with one sentence: *«juda oddiy ko'rinadi»*. Two high-polish directions followed, and the
user chose **Вариант 2 · Ёруғ шиша**: an all-light treatment where white sheets float on a
soft blue-grey ground, numbers are set at display size, and the entry cell is deliberately
the strongest element on the screen.

Mockups (published artifacts, for reference while implementing):

- Current-state review: https://claude.ai/code/artifact/90f95d7e-8798-4962-8a83-8610f187d8de
- The three rejected directions: https://claude.ai/code/artifact/b2b25839-9af9-4275-859f-918181dbe76f
- **The chosen direction (Вариант 2):** https://claude.ai/code/artifact/72d28416-0265-437b-831f-c37733bd523a

The mockup is the visual contract. Where this document and the mockup disagree, this
document wins (it corrects one metric, below).

## 2. One correction to the mockup: «муддати яқин» → «кечиккан»

The mockup ranks regions by measures *due soon*. Against real data that signal is dead:
almost every measure in every road map is due in December.

| Signal (2026-10, all 13 road maps) | Count |
| --- | --- |
| Deadline falls in October…December | 1 188 of 1 308 |
| Deadline month already past and the measure is not done | 111 |

So the ranking signal is **overdue** (`кечиккан`): the measure's deadline month is earlier
than the current month and its status is not `done`. Per region today:

| Region | Overdue | Region | Overdue |
| --- | --- | --- | --- |
| Қорақалпоғистон Республикаси | 28 | Тошкент вилояти | 14 |
| Сирдарё вилояти | 22 | Қашқадарё вилояти | 6 |
| Навоий вилояти | 18 | Бухоро вилояти | 3 |
| Наманган вилояти | 16 | Жиззах, Сурхондарё | 2 |
| | | Андижон, Самарқанд, Фарғона, Хоразм | 0 |

Deadlines are computed, never stored: `App\Support\Roadmaps\RoadmapDeadline::month($measure->deadline_text, $roadmap->year)`
returns `YYYY-MM`. Comparison is a plain string comparison against the current month from
`ProgressSheet::currentPeriod()`. No migration, no new column.

## 3. Visual tokens

All of these live in the `adm-`/`ad-` block of `backend/public/css/portal.css` and apply to
the panel only. The panel is light-only, like the portal: no `prefers-color-scheme` block.

**Ground** (on `.adm-body`):

```css
background:
  radial-gradient(760px 300px at 85% -10%, rgba(23,105,224,.10), transparent 60%),
  radial-gradient(620px 260px at 5% 0%, rgba(99,179,237,.12), transparent 60%),
  linear-gradient(180deg, #FBFDFF, #EEF3FA);
background-attachment: fixed;
```

**Surfaces**

| Token | Value | Used by |
| --- | --- | --- |
| Sheet | `background:rgba(255,255,255,.92)`, `border:1px solid rgba(14,28,50,.07)`, `border-radius:20px`, `box-shadow:0 2px 4px rgba(12,26,48,.04), 0 20px 46px rgba(12,26,48,.10)` | section sheets, drawers, login card |
| Card | same, `border-radius:18px`, `box-shadow:0 2px 4px rgba(12,26,48,.04), 0 16px 36px rgba(12,26,48,.09)` | region cards, KPI tiles |
| Raised control | `background:rgba(255,255,255,.9)`, `border:1px solid rgba(14,28,50,.08)`, `border-radius:13px`, `box-shadow:0 8px 22px rgba(12,26,48,.08)` | month select, search |
| Glass pill | `background:rgba(255,255,255,.85)`, `border:1px solid rgba(14,28,50,.07)`, `border-radius:999px`, `box-shadow:0 2px 8px rgba(12,26,48,.05)` | filter pills (inactive) |

**Colour**

| Role | Value |
| --- | --- |
| Accent gradient | `linear-gradient(135deg,#1769E0,#3E8BFF)`, glow `0 8px 20px rgba(23,105,224,.3)` |
| Accent solid / focus | `#1769E0`, focus halo `0 0 0 4px rgba(23,105,224,.14)` |
| Positive (percentages at or over plan) | gradient text `linear-gradient(135deg,#0F9D63,#2FBE85)` |
| Overdue / warning | text `#8A5000` on `linear-gradient(135deg,#FFF4E3,#FFE9CC)` |
| Carry (previous month) | `#B86E00` for the bar segment, grey italic for carried values |
| Neutral chip | `#F2F6FB` background, `#64748B` text |
| Ink / muted / faint | `#0B1424` / `#5A6B82` / `#8A99AC` |
| Hairlines | `#F0F4F9` (inside a sheet), `#E8EEF6` (sheet header) |

**Shape:** radius 10 for small chips, 12 for input cells, 13 for controls, 18 for cards,
20 for sheets. Keep the existing 999px pills.

## 4. Typography

Body text stays **Inter** (already loaded). Numbers move to **Manrope** 700/800, letter
spacing `-.03em`, `font-variant-numeric: tabular-nums`. Manrope is added to
`layouts/admin.blade.php` only, in the existing Google Fonts link, with the Cyrillic subset:

```
family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap&subset=cyrillic,cyrillic-ext,latin
```

Declare `--font-num:'Manrope','Inter',sans-serif` and apply it to: ring percentages, KPI
values, plan values, input cell values, line percentages, card counters. Everything else
keeps `--font-sans`.

Scale: region title 27px/800/-.03em; KPI value 30px/800; card name 14.5px/800; measure title
14px/700 capped at 74ch; line label 12.5px; input value 15px/800; chips and labels 10.5–11.5px/700
with `.1em` tracking when uppercase.

## 5. Entry grid (`/admin/roadmaps/{code}`)

### 5.1 Header

A single row, wrapping on narrow screens:

- **Ring**, 88px, `conic-gradient(from 180deg, #1769E0 0 <pct>%, #E2EAF4 <pct>% 100%)`,
  `box-shadow:0 14px 34px rgba(23,105,224,.22)`, with a 68px inner disc holding the
  percentage (21px/800) over the word «бажарилди» (9px/700 uppercase, `#8A99AC`).
  The percentage is measures saved for this month divided by total measures.
- **Title block**: region name, then a sub-line reading
  «2026 йил октябрь · 95 чора-тадбир · 200 индикатор · N кечиккан». The overdue clause is
  omitted when N is zero.
- **Filter pills** below the sub-line, unchanged in behaviour (Барчаси / Тўлдирилмаган /
  Ўзгартирилган / Ўтган ойдан / Сақланган), restyled: glass pill when off, accent gradient
  with glow when on.
- **Month select** and **search**, raised controls, pushed right. «Excel'дан юклаш» stays a
  ghost button next to them, keeping its disabled state while edits are unsaved.

The header scrolls away with the page. Only the section strips stick.

### 5.2 Section sheets

One sheet per existing group (section number plus district), replacing today's single table.
Each sheet opens with a sticky strip:

- left: «I · Вилоятда амалга ошириладиган йирик лойиҳалар», 10.5px/800 uppercase, `.11em`
  tracking, `#7B8AA0`; the district name and its head follow on the same strip when the
  group has one;
- right: the measure count as a small outlined counter, then the mini column labels
  «РЕЖА · АМАЛДА · %» aligned over their columns.

The strip is `position:sticky; top:52px` (the admin bar height) with a white-to-translucent
background so rows slide under it. The full `<thead>` disappears with the table.

### 5.3 Measure and line rows

A measure is a block inside the sheet:

- 28px rounded badge with the measure number. Grey gradient `linear-gradient(135deg,#8FA5BF,#AEC0D5)`
  normally; accent gradient with glow once the measure has a value for this month (saved or
  edited). This replaces the current left ring entirely.
- Title, 14px/700, `max-width:74ch`.
- The document deadline becomes muted inline text after the title («муддат: 2026 йил декабрь»),
  not a chip.
- Chips appear only when they carry information: «кечиккан» (warning colours), «ўзгартирилган»,
  «ўтган ойдан», «сақланган». A measure that is simply untouched and on time shows no chip.
- «Батафсил» details disclosure stays as it is.

Indicator lines sit under the title on a grid, indented to clear the badge:

```
grid-template-columns: 1fr 92px 112px 168px 64px 240px;  /* label · unit · plan · input · % · note */
padding: 10px 18px 10px 59px;
```

The note keeps its own column to the right of the percentage, restyled as a quiet underline
field rather than a boxed input. Below 1 100px the note drops to a second line spanning the
row, under the input. Everything else about notes is unchanged.

### 5.4 The input cell

The point of the redesign:

```css
border: 1.5px solid #D3DEEB; border-radius: 12px; padding: 9px 13px;
font-family: var(--font-num); font-size: 15px; font-weight: 800; text-align: right;
background: linear-gradient(180deg,#fff,#FBFCFE);
box-shadow: inset 0 1px 3px rgba(12,26,48,.05);
```

States, all of which already exist in the markup and keep their class names:

| State | Treatment |
| --- | --- |
| Focus | `border-color:#1769E0; background:#fff; box-shadow:0 0 0 4px rgba(23,105,224,.14), inset 0 1px 3px rgba(12,26,48,.04)` |
| Edited (unsaved) | amber border `#E0A33F`, amber inset tint |
| Carried from an earlier month | value in grey italic, dashed border, tooltip unchanged |
| Empty | dashed border `#D3DEEB` |
| Invalid | red border `#C0392B`, halo `0 0 0 4px rgba(192,57,43,.14)`, message below in red, 11px |
| Saved | solid border, percentage rendered in the positive gradient |

### 5.5 Save bar

A floating pill, centred, `position:sticky; bottom:16px`, dark gradient
`linear-gradient(135deg,#0D1832,#16294E)`, shadow `0 16px 38px rgba(10,22,45,.35)`, holding
the counts on the left and the primary button (accent gradient) on the right. «Бекор қилиш»
and «Қолганини ўзгаришсиз тасдиқлаш» stay as ghost buttons inside the pill, with their
two-click arms unchanged.

### 5.6 Scrolling

The inner scroll region goes away: `.ad-gridwrap`'s `max-height` and `overflow` are dropped
and the page scrolls normally. The admin bar stays sticky at the top, section strips stick
beneath it, the save pill sticks to the bottom.

## 6. Region list (`/admin/roadmaps`)

### 6.1 Header and KPI tiles

Title and month select on one row. Below, four tiles in a responsive grid, each a card with
a 10px uppercase label, a 30px Manrope value, and a 11.5px sub-line:

| Tile | Value | Sub-line |
| --- | --- | --- |
| Тўлдирилди | measures saved this month across all road maps | «1 308 тадан · N%» |
| Ўтган ойдан | carried measures | «тасдиқлашни кутмоқда» |
| Кечиккан | overdue measures | «муддати ўтган, бажарилмаган» |
| Тегилмаган вилоят | road maps with no change log entry at all | «ҳали ўзгариш йўқ» |

The «Кечиккан» tile's value uses the warning colour when it is above zero.

### 6.2 Cards

Cards are grouped under two headings, each a thin label with a hairline rule:

- **«Диққат талаб қилади»** — regions with at least one overdue measure, or with nothing
  saved this month.
- **«Қолганлари»** — everything else. The heading is omitted when the group is empty.

Within a group, sort by overdue descending, then by saved-percentage ascending, then by the
region's own `sort_order`. Each card holds:

- a 56px gauge, same conic treatment as the big ring, with the saved percentage inside;
- the region name and «N / M тўлдирилди»;
- a 6px progress bar: accent gradient for saved, `#F0B860` for carried, `#E7EDF5` track;
- a footer with the overdue tag «N кечиккан» (warning colours) when there is one, otherwise
  the idle tag «N кун ўзгариш йўқ» in neutral colours, then «Киритиш →» in accent;
- the «Excel'дан юклаш» ghost button, which keeps its browser-side dispatch.

The whole card stays a link to the entry page through the existing stretched anchor, with a
hover lift of `translateY(-2px)` and a deeper shadow.

## 7. Secondary surfaces

Restyled with the same tokens, no behaviour change:

- **Correction panel** — sheet background, input fields in the new control style, the
  destructive arm keeps its red treatment.
- **History drawer** — sheet background, entries separated by hairlines, the old→new values
  keeping their green and grey.
- **Upload drawer** — sheet background, the file control and report table in the new style.
- **Login card** — the same floating sheet on the same ground, so the panel and its door
  match. The portal's own login route is unchanged in behaviour.

## 8. Markup and JavaScript changes

The grid stops being a `<table>`. The new structure, with every `data-*` attribute preserved
exactly as it is today:

```html
<section class="ad-sheet" wire:key="g-{key}" x-show="showGroup($el)">
  <header class="ad-sheet-head">…section · district · counter · column labels…</header>
  <article class="ad-m" wire:key="m-{id}" data-measure data-state data-deadline data-search
           x-show="show($el)">
    <div class="ad-mrow">…badge, title, deadline text, chips, ✎, 🕓…</div>
    <div class="ad-lrow" wire:key="l-{id}">…label, unit, plan, input, pct, note…</div>
    …
    <div class="ad-prow">@include correction panel</div>
  </article>
</section>
```

`public/js/ad-entry.js` changes are selector-level only, because the attributes do not move:

- `root.querySelectorAll('tbody.ad-m')` → `root.querySelectorAll('.ad-m')`;
- `tbodyOf()` is renamed `measureOf()` and returns the `.ad-m` element;
- `showGroup(sheet)` iterates `sheet.querySelectorAll('.ad-m')` instead of walking siblings;
- the `.ad-empty` row becomes a sheet-level empty state with the same text and reset button;
- everything else (edit buffer, parse mirror, counters, hotkeys, focus handling, toasts,
  `ad-refresh` / `ad-imported`) is untouched.

`RoadmapOverview::render()` gains the overdue count per road map and the four totals.
`RoadmapEntry::render()` gains the region's overdue count for the sub-line. Both use
`RoadmapDeadline::month()` over the measures already loaded, so no extra queries.

## 9. Tests

- `RoadmapEntryTest` and `RoadmapOverviewTest` assert markup that changes; update the
  assertions to the new structure, keeping them specific (data attributes, counts, labels).
- New: the overdue count is correct for a road map with one past-deadline open measure, one
  past-deadline done measure (not counted) and one future-deadline measure.
- New: the region list orders «Диққат талаб қилади» first and sorts by overdue descending.
- The existing behavioural tests must pass unchanged. If one fails, the markup changed more
  than it should have.

## 10. Risks

- **The table-to-div move is the only risky edit.** Mitigation: the `data-*` contract stays
  identical, the JS edit is mechanical, and the grid's feature tests cover filtering, saving
  and the empty state.
- **Too much gradient reads as a toy.** Gradients are limited to: the ground, the accent on
  active controls, the two gauges, positive percentages and the save pill. Nothing else.
- **Sticky strips plus a sticky admin bar can collide.** The strip's `top` is pinned to the
  bar height (52px) and the bar keeps `z-index:28`, below the drawers and scrim.

## 11. Delivery order

1. Tokens, fonts and shared primitives.
2. Region list, including the overdue data.
3. Entry grid markup plus the JS selector pass.
4. Entry grid states and the save pill.
5. Secondary surfaces and the login card.
6. Tests and docs.
