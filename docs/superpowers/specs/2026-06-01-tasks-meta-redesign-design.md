# /tasks card data-zone redesign

**Date:** 2026-06-01
**Branch:** v7-design-polish
**Status:** approved design, ready for implementation plan

## Problem

On `/tasks` (Livewire `TasksBoard`, view `backend/resources/views/livewire/tasks-board.blade.php`),
each task card renders two `.task-meta` rows (blade lines 59–70). Both are identical flat runs of
grey 13px wrapped `<span>`s (`portal.css:1466`):

- **Row 1 (context):** deadline, scope (`N туман/шаҳар` / `вилоят`), module, cadence, last period — bare values, no labels.
- **Row 2 (the point):** `Режа` / `Амалда` / `Бажарилиши` — plan vs actual vs %.

Because both rows look the same, the four observed pains all hold (user confirmed all four):

1. No labels / hierarchy — can't tell what a value means.
2. Plan-vs-actual buried — the core comparison doesn't stand out.
3. Too many fields crammed.
4. Hard to scan a long list — nothing aligns vertically card-to-card.

This regresses the portal's core concept (CLAUDE.md): *"Кафолат хатидаги ваъдалар бажариляптими?"* —
the plan (promise) vs actual (fact) comparison should be the visual hero, not buried text.

## Scope

Rewrite **only the data zone** inside the card body (blade lines 59–73). Keep the card frame:

- number badge (`.task-num`)
- title (`.task-body strong`)
- right-side chips (`.task-chips`: status / KPI–Чора-тадбир / indicator)
- the `Батафсил` `<details>` expandable (lines 74–99)

**Out of scope:** filter bar, right sidebar (donut + stat boxes), card frame, import pipeline, DB/models.
All required fields already exist on `App\Models\Task` — no schema or query changes.

## Chosen design — Layout A (stat strip) + threshold color

Picked over a fraction-forward layout (B) and a boxed panel (C) because it solves all four pains:
labeled cells, hero plan-vs-actual, fewer face fields, and equal columns that align card-to-card.

### Card body structure (replaces lines 59–73)

```
[title — unchanged]
СРОК · 2026 йил якунигача    ЙЎНАЛИШ · Иқтисодиёт        ← context line: labeled key:value, quiet
┌──────────┬──────────┬──────────────┐
│ РЕЖА     │ АМАЛДА   │ БАЖАРИЛИШ     │                   ← stat strip: micro-label per cell
│ 1 200 та │ 840 та   │ 70%          │                   ← big values; % colored by tier
└──────────┴──────────┴──────────────┘
[████████░░░░░░]  ҳолат: 2026-Q1                          ← progress bar (tier color) + period caption
▸ Батафсил (…)  — unchanged
```

- **Stat strip:** `display:grid; grid-template-columns: repeat(3, 1fr)`. `.task-list` is a single column, so
  cards share width → the three cells line up vertically down the whole list. Each cell: uppercase micro-label
  (`Режа` / `Амалда` / `Бажарилиш`) above a large value. Units (`headline_unit`) shown small after Режа/Амалда.
- **Context line:** `Срок` ← `deadline_text`; `Йўналиш` ← `module?->label ?? section_label`.
  Omit either pair when its value is null.
- **Period caption:** `ҳолат: {{ latest_period }}` — rendered only if `latest_period` set. This is the
  "as of" period for the plan/actual numbers.
- **Number formatting:** reuse the existing `$fmt` closure (trims trailing zeros).
- **Label casing:** the uppercase look (`РЕЖА`, `СРОК`, …) is produced by CSS `text-transform: uppercase`.
  The DOM text stays title-case (`Режа`, `Срок`, `Йўналиш`, `Бажарилиш`, …) — test assertions match the DOM text.

### Color (threshold / traffic-light)

Tier computed in blade from `headline_pct` (`$pctTier`):

| pct | tier | color |
| --- | --- | --- |
| `null` | none | grey; value `—`; **no progress bar** (matches today) |
| `< 50` | behind | red |
| `50–99` | partial | amber |
| `≥ 100` | done | green |

Color applies to **both** the `Бажарилиш` value text and the progress-bar fill, via a CSS modifier class
(e.g. `.task-pct--red/amber/green` and a bar variable). Tasks with `status === 'done'` land in the green
tier naturally (done means ≥100% of plan). No new status logic.

### Demoted into Батафсил

Scope and cadence leave the card face. Add one line at the top of the existing `<details>`:

```
Қамров: 5 туман/шаҳар · Даврийлик: Ойлик
```

(scope from `districts->count()` / `вилоят`; cadence from the existing `Ойлик`/`Чорак` mapping). Executor
district chips inside the details stay unchanged. If a value is absent, drop that segment.

### Right chips — unchanged

Status / kind / indicator chips stay in `.task-chips`.

## Files touched

- `backend/resources/views/livewire/tasks-board.blade.php` — rewrite card-body data zone (lines ~48–99).
- `backend/public/css/portal.css` — add `--task-amber` var + new classes (stat strip, context line, micro-labels, color tiers), **edited directly**.
- No PHP/`TasksBoard.php` change required (all fields already provided).

**Build note (corrects CLAUDE.md):** `portal.css` is a hand-maintained stylesheet linked directly in
`layouts/app.blade.php` (`<link href="/css/portal.css">`), NOT built from `resources/css/app.css`. Vite
builds `app.css` → `public/build/`, which portal styling does not use. Edit `public/css/portal.css`
directly; do **not** run `npm run build` for this change.

## Tests

Markup changes will break feature tests asserting the old text/structure.

- Audit `backend/tests/Feature/Http/TasksPageTest.php` and `backend/tests/Feature/Tasks/TasksBoardProgressTest.php`
  for assertions on the old `.task-meta` text (e.g. inline `Режа:` spans, `Бажарилиши:`), and update them to the
  new structure (labels `Режа`/`Амалда`/`Бажарилиш`, `Срок`, `Йўналиш`, `ҳолат:`).
- Keep coverage for: null `headline_pct` → `—` and no bar; done task → green tier; period caption present/absent.
- Full suite must stay green (`php artisan test`).

## Conventions

- UI language stays Cyrillic Uzbek (labels above are final wording).
- Reuse existing palette/classes where possible; new CSS only where needed, added directly to `public/css/portal.css`.
- `data/` untouched; nothing committed from it.

## Open questions

None — layout (A), face fields (Срок / Йўналиш / period caption), demotions (scope, cadence → Батафсил),
and color (threshold) are all decided.
