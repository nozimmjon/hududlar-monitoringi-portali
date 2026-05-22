# Scoreline Card Styling — match `footer.png`

**Date:** 2026-05-22
**Topic:** Restyle every module's execution scoreline card to match the reference image `footer.png`.

## Goal

Make every module's `.scoreline.execution-strip` card (rendered by
`backend/resources/views/livewire/dashboard/kpi-scoreline.blade.php`) render
100% identical to the reference image `footer.png` — for all modules, not only
the macro module.

## Reference image — extracted values

`footer.png` is a 2× retina screenshot (1516×209). CSS px ≈ image px ÷ 2.

Card layout, left → right:

1. **Copy block** — uppercase label, bold title, gray sublabel.
2. **Ring** — circular progress ring with `%` in the center.
3. **Boxes** — three solid-color boxes (blue / green / red), each with a small
   white label on top and a large white number below.

Colors sampled from the image:

| Element            | Hex        |
| ------------------ | ---------- |
| Blue box           | `#205cd6`  |
| Green box          | `#24ab5e`  |
| Red box            | `#e73a36`  |
| Ring track         | `#e8edf7`  |
| `%` / title text   | `#102033`  (existing `--ink`) |
| Box label / number | `#ffffff`  |
| Card background    | `#ffffff`  |

The image colors are brighter than the current CSS vars (`--blue #2b61af`,
`--green #15803d`, `--red #b91c1c`), so new dedicated color vars are required.

## Approach

Promote the macro-only `.is-macro` styling to the shared `.execution-strip`
selector. All module scorelines then share one rule set — single source of
truth, no drift. The now-redundant `.is-macro` override block is removed.

Rejected alternative: duplicate the macro look under non-macro selectors —
more CSS and two rule sets that drift apart.

## Scope

- **In scope:** every module's scoreline (`.scoreline.execution-strip`), macro
  and non-macro alike.
- **Out of scope:** `.exec-status-pill` usages outside `.execution-strip` (e.g.
  `.task-summary-strip` on the tasks page) — untouched; changes are scoped
  under `.execution-strip` / `.scoreline`.
- No blade/markup changes — `kpi-scoreline.blade.php` already emits the needed
  structure (`scoreline-copy`, `exec-progress-box` + `exec-donut`,
  `exec-status-grid` + 3 `exec-status-pill`).
- Global `--green` / `--red` vars stay untouched (used by maps, poverty panel,
  etc.).

## Changes — all in `backend/public/css/portal.css`

### 1. New `:root` color vars

```
--score-blue:  #205cd6;
--score-green: #24ab5e;
--score-red:   #e73a36;
--score-track: #e8edf7;
```

### 2. Card grid — `.scoreline.execution-strip`

- `grid-template-columns: minmax(240px, 1fr) auto minmax(320px, 1.1fr);`
- `gap: 16px;`
- `align-items: center;`
- `padding: 16px 20px;`
- Keep base `.scoreline` white background, `1px solid var(--line)` border,
  `var(--shadow)`, `border-radius: 18px`.

### 3. Child order — all `.execution-strip` children

- `.scoreline-copy` → `order: 1`
- `.exec-progress-box` → `order: 2`
- `.exec-status-grid` → `order: 3`

### 4. Ring — `.execution-strip .exec-donut`

- 60px × 60px circle, no border.
- `background:`
  - `radial-gradient(circle at center, #fff 0 83%, transparent 84%),`
  - `conic-gradient(var(--score-green) calc(var(--pct) * 1%), var(--score-track) 0)`
- `.exec-donut strong` → `font-size: 22px; font-weight: 800; color: var(--ink);`

### 5. Hide ring caption

- `.execution-strip .exec-progress-box small { display: none; }` — the
  "бажарилиш" caption is not present in the reference image.

### 6. Status boxes — `.execution-strip .exec-status-pill`

- Solid color background, `border: 0`, `border-radius: 12px`.
- `min-height: 52px;` `padding: 12px 14px;`
- Left-aligned: `justify-items: start; text-align: left;`
- No hover lift: hover `transform: none; box-shadow: none;`.
- Label (`span`): `color: rgba(255,255,255,.9); font-size: 11px;`
- Number (`strong`): `color: #fff; font-size: 28px; font-weight: 800;`
  `font-variant-numeric: tabular-nums;`
- Color mapping:
  - `.exec-status-grid .exec-status-pill:first-child` → `var(--score-blue)`
  - `.exec-status-pill.green` → `var(--score-green)`
  - `.exec-status-pill.red` → `var(--score-red)`
- `.exec-status-grid` keeps `repeat(3, minmax(0, 1fr))`, `gap: 10px`.

### 7. Copy block — `.execution-strip .scoreline-copy`

- `span` (uppercase label): `font-size: 11px; color: var(--muted);` uppercase,
  letter-spaced — keep current.
- `strong` (title): `font-size: 22px; font-weight: 800; color: var(--ink);`
- `small` (sublabel): `font-size: 12.5px; color: var(--muted);`

### 8. Remove redundant `.is-macro` block

- Delete the `.scoreline.execution-strip.is-macro` rule block (current
  portal.css lines ≈ 579–626) — fully superseded by the promoted
  `.execution-strip` rules.
- Replace the old 4-column `.scoreline.execution-strip` grid (lines ≈ 461–465)
  with the grid from §2.

## Verification

- Open the dashboard for the **macro** module — scoreline matches `footer.png`.
- Switch to a **non-macro** module — its scoreline shows the same solid
  blue/green/red boxes + ring (previously white boxes).
- Confirm the tasks-page `.task-summary-strip` status pills are unchanged.
- Narrow the viewport — responsive rules (portal.css ≈ lines 5823–5871) still
  collapse the grid cleanly.
- Hard refresh (`Ctrl+F5`) — `portal.css` is served raw from `public/`, no build.

## Open questions

None.
