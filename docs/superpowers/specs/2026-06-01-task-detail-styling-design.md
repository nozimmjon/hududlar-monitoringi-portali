# Батафсил (task-detail) styling redesign

**Date:** 2026-06-01
**Branch:** v7-design-polish
**Status:** approved design, ready for implementation plan

## Problem

On `/tasks`, each task card has a `<details class="task-detail">` ("Батафсил") expandable
(`backend/resources/views/livewire/tasks-board.blade.php`, lines ~105–128). After the card face
was redesigned, the panel interior was left untouched:

- **No `.task-detail` CSS exists** in `public/css/portal.css` — the `<details>` uses the default
  browser disclosure (bare triangle), and the summary is plain grey text.
- Every row inside uses the old flat `.task-meta` (grey 13px wrapped spans). A per-metric line renders
  as `метка — Режа: X unit — Амалда: Y unit — %` run together. With several sub-metrics, numbers don't
  align, rows blur into each other, and the percent has no color — the same "wall of grey" the card
  face redesign removed, but inside the panel.

Priorities (user-confirmed): **per-metric breakdown** (the star), **executor districts**, and the
**summary/disclosure header**. Scope/cadence is lower priority but still cleaned up.

## Scope

Restyle the `<details>` interior only:

- `backend/resources/views/livewire/tasks-board.blade.php` — rewrite the panel markup (lines ~105–128).
- `backend/public/css/portal.css` — add a new `.task-detail` block (hand-edited; **no `npm run build`** —
  portal.css is hand-maintained, linked directly in `layouts/app.blade.php`).
- No PHP/model/query change. Card face, filters, sidebar, import pipeline untouched.

## Chosen design — direction C (two-line rows + tier pill)

Picked over an aligned table (A) and per-metric mini stat-strips (B). C reads like a clean feed:
each sub-metric is a name + a quiet plan/actual line + a colored % pill — light, no header row,
and comfortable at any metric count.

### Content + gate change

- The breakdown lists **sub-metrics only** (`line_no ≥ 1`). The headline (`line_no 0`) is dropped —
  the card face already shows its Режа/Амалда/%.
  - `$subLines = $latestLines->where('line_no', '>', 0)` (where `$latestLines` is the existing
    latest-period progress collection).
- **Gate** becomes `@if($subLines->isNotEmpty() || $task->districts->isNotEmpty())`
  (was `$latestLines->count() > 1 || $task->districts->isNotEmpty()`). Effect:
  - a task with at least one sub-metric line now opens a detail (previously needed 2+ total lines);
  - a task with only a headline line and no districts shows no detail (nothing beyond the face).
- Summary count reflects what's shown: `{{ $subLines->count() }} кўрсаткич` (+ `, M ҳудуд` if any).

### Layout

```
┌ summary bar (tinted, clickable) ─────────────────────────┐
│ ▸ Батафсил        [N кўрсаткич] [M ҳудуд]                 │  chevron rotates open
├──────────────────────────────────────────────────────────┤
│ ҚАМРОВ: 5 туман/шаҳар · ДАВРИЙЛИК: Ойлик                   │  quiet uppercase caption
│                                                            │
│ йирик корхона сони                              [ 50% ]   │  sub-metric row:
│ Режа 6 дона · Амалда 3 дона                                │   name (bold) + quiet line + tier pill
│ ──────────────────────────────────────────────────────    │
│ экспорт ҳажми                                   [ 70% ]   │
│ Режа 120 млн долл · Амалда 84 млн долл                      │
│                                                            │
│ ИЖРОЧИ ҲУДУДЛАР                                            │
│ [Андижон ш.] [Асака т.] [Шаҳрихон т.] …                    │  blue chips
└────────────────────────────────────────────────────────────┘
```

- **Summary header:** suppress the default marker (`summary { list-style: none }` +
  `summary::-webkit-details-marker { display: none }`), render a custom chevron, bold `Батафсил`, and the
  count pills. Tinted bar with a bottom border. Chevron rotates via `details[open] summary .chev`.
- **Per sub-metric row:** `metric_label` bold (fallback `—`); beneath it a muted line
  `Режа <b>{{ $fmt(plan_value) }}</b> {{ unit }} · Амалда <b>{{ $fmt(actual_value) }}</b> {{ unit }}`;
  a right-aligned **% pill** tinted by tier. Reuse the existing `$fmt` closure. Null plan/actual → `—`.
  Rows separated by a hairline border.
- **Scope/cadence:** uppercase micro-caption directly under the summary:
  `Қамров: <b>{{ $scopeText }}</b>` and, if `$cadenceLabel` set, `· Даврийлик: <b>{{ $cadenceLabel }}</b>`.
- **Executor districts:** rendered only if any — uppercase label `Ижрочи ҳудудлар` + existing
  `.chip.blue` chips (reused, not redefined).
- **Metric block:** rendered only if `$subLines->isNotEmpty()` (a districts-only task shows just the
  caption + districts).

### Tier colors (% pill)

Per-line tier from `pct_of_plan`:

| pct | tier | pill |
| --- | --- | --- |
| `null` | none | grey, text `—` |
| `< 50` | red | tinted red bg + `var(--task-red)` text |
| `50–99` | amber | tinted amber bg + `var(--task-amber)` text |
| `≥ 100` | green | tinted green bg + `var(--task-green)` text |

Same thresholds as the card face. Pill text = `round(pct_of_plan) . '%'` or `—`. New CSS classes
(e.g. `.task-detail .tl-pill` + `--red/--amber/--green/--none`). `--task-amber` already exists from the
card-face work.

## Files touched

- `backend/resources/views/livewire/tasks-board.blade.php` — rewrite `<details>` interior (~105–128);
  add `$subLines` to the existing `@php` detail block.
- `backend/public/css/portal.css` — new `.task-detail` block (summary bar, caption, metric rows, pill,
  district group). Reuse `--task-red/amber/green`, `--muted`, `--ink`, `--line`, `--grey`, `.chip.blue`.

## Tests

Update `backend/tests/Feature/Tasks/TasksBoardProgressTest.php`, test
`card detail shows cadence and scope demoted from the face`:

- The fixture has progress `line_no 0` (`йирик корхона сони`) + `line_no 1`
  (`қайта тикланадиган ишлаб чиқариш`) + 1 district. With headline dropped, the breakdown shows only
  `line_no 1`.
- Assert: `Батафсил`, `Қамров`, `Даврийлик`, `Ойлик`, the district `Шаҳрихон тумани`, the sub-metric
  `қайта тикланадиган ишлаб чиқариш`, and a tier-pill class on its 100% pill (`tl-pill--green`).
- Assert the headline label `йирик корхона сони` is **not** rendered in the breakdown (line 0 dropped).
- Keep the full suite green (`php artisan test`).

## Conventions

- UI language stays Cyrillic Uzbek (labels above are final).
- Reuse existing palette/classes; new CSS added directly to `public/css/portal.css`, no build step.

## Open questions

None — direction (C), sub-metrics-only content, gate change, caption placement (under the summary),
tinted % pill, and tier thresholds are all decided.
