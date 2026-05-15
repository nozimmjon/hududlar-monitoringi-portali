# Macro hero strip redesign

**Date:** 2026-05-15
**Scope:** Replace `macro-main-panel` (title + green hero card + 4 quarter cards) with a single dark horizontal strip on 4 macro-KPI dashboard pages.

## Goal

Replace the current 3-block stacked layout (title row, green hero card, 4-card quarter grid) with one dark navy strip that puts the headline KPI on the left and the four period chips on the right. Match the layout shown in `Screenshot 2026-05-15 143622.png`.

## In scope

- KPIs: `grp` (ЯҲМ), `agriculture` (Қишлоқ хўжалиги), `construction` (Қурилиш), `services` (Хизматлар).
- Single blade: `resources/views/livewire/dashboard/panels/macro-growth.blade.php`.
- CSS in `public/css/portal.css` scoped to `.macro-hero-strip`.

## Out of scope

- `industry` KPI: keeps current `macro-section-title + macro-hero-card + macro-period-grid + industry-driver-panel` layout untouched (side driver column needs the existing space).
- Macro composition card (`macro-composition`) below: unchanged.
- Data import / new fields: none. All values come from existing `$rows` keyed by period.

## Architecture

Blade branches inside the existing `<div class="macro-main-panel">`:

- When `$showIndustryDrivers` is true → render existing markup (industry path, unchanged).
- Else → render new `<section class="macro-hero-strip">` markup.

CSS adds one new block (`.macro-hero-strip` and descendants). Existing `.macro-hero-card`, `.macro-section-title`, `.macro-period-grid`, `.macro-period-card` rules stay (still used by industry path).

## Components

### Container

```html
<section class="macro-hero-strip" aria-label="…">
    <div class="macro-hero-strip__lead">…</div>
    <div class="macro-hero-strip__chips">…</div>
</section>
```

Visual:
- Background: `linear-gradient(135deg, #0b1f3b 0%, #11294d 100%)`.
- Soft highlight blob top-right: `radial-gradient(circle at top right, rgba(120,160,220,.18), transparent 60%)` layered.
- Border-radius `14px`, padding `22px 26px`, `display: grid`, `grid-template-columns: minmax(0, 1.1fr) minmax(420px, 1fr)`, `gap: 24px`, `align-items: center`.

### Lead column

Three stacked rows:

1. **Caption row** — `display: flex`, gap 10px, items center, wrap:
   - `<span class="macro-hero-strip__caption">{{ $indicator->label_short }} ЎСИШИ · СОЛИШТИРМА НАРХЛАРДА</span>` — uppercase, 11px, letter-spacing `.12em`, color `#7aa3d6`.
   - `<span class="macro-hero-strip__pill">↑ +{{ $deltaPp }} п.п. {{ $baseYear }}-йилдан</span>` — green pill (`background: rgba(46,160,67,.16)`, `color: #4ac46c`, border-radius 999px, padding `4px 10px`, font-size 12px, weight 700). Hidden when year growth is null.

2. **Big number** — `<strong class="macro-hero-strip__value">{{ $yearGrowthFormatted }}</strong>`. Font-size `clamp(56px, 6vw, 88px)`, line-height `.9`, color `#4ad17a`, weight 900, tabular-nums, letter-spacing `-.04em`.

3. **Subtitle** — `<small class="macro-hero-strip__sub">{{ $year }} йил якуни кутилаётган баҳо</small>`. Color `#8aa5c8`, font-size 13px, weight 500.

### Chip column

`display: grid`, `grid-template-columns: repeat(4, minmax(0, 1fr))`, gap 8px.

Each chip:

```html
<div class="macro-hero-strip__chip is-{{ $cls }}">
    <span class="macro-hero-strip__chip-label">{{ $item['label'] }}</span>
    <strong class="macro-hero-strip__chip-value">{{ $growthText }}</strong>
    <span class="macro-hero-strip__chip-badge">{{ $item['state'] }}</span>
</div>
```

Variants:
- `is-actual` (q1) → `background: #eaf2ff`, label `#5a7596`, value `#0f2a52`, badge `#1f6feb`.
- `is-plan` (h1, m9) → `background: rgba(255,255,255,.06)`, border `1px solid rgba(255,255,255,.08)`, label `#7aa3d6`, value `#e4ecf7`, badge `#7aa3d6`.
- `is-target` (year) → `background: #1f6feb`, label `rgba(255,255,255,.78)`, value `#fff`, badge `rgba(255,255,255,.9)`.

Chip body: padding 12px, border-radius 10px, `display: grid`, `gap: 4px`, `justify-items: start`. Label = uppercase 10px tracked. Value = 22–26px, tabular-nums, weight 800. Badge = uppercase 10px tracked.

### Period config (blade)

```php
$macroPeriods = [
    ['label' => 'I чорак',   'period' => 'q1',   'state' => 'Амалда', 'cls' => 'actual'],
    ['label' => 'II чорак',  'period' => 'h1',   'state' => 'Режа',   'cls' => 'plan'],
    ['label' => 'III чорак', 'period' => 'm9',   'state' => 'Режа',   'cls' => 'plan'],
    ['label' => 'Йиллик',    'period' => 'year', 'state' => 'Мақсад', 'cls' => 'target'],
];
```

Note: `cls` values change from `actual/planned` to `actual/plan/target`.

### Derived values

In the blade `@php` block, after current logic:

```php
$year = config('dashboard.year', date('Y'));
$baseYear = ((int) $year) - 1;
$yearGrowth = $yearRow?->growth_pct;
$deltaPp = $yearGrowth !== null ? number_format($yearGrowth - 100, 1) : null;
$showPill = $deltaPp !== null && (float) $deltaPp > 0;
```

(If `$yearGrowth` is null or computed delta ≤ 0, pill is suppressed. Negative delta would need different arrow/color; out of scope for now — pill stays hidden.)

## Responsive

Breakpoint at 980px (single-column dashboard):
- `.macro-hero-strip` switches to `grid-template-columns: 1fr`, `gap: 16px`.
- `.macro-hero-strip__chips` becomes `grid-template-columns: repeat(2, 1fr)`.
- Lead column stays in same order (caption → value → subtitle).

Below 560px: chips collapse to single column.

## Accessibility

- Strip retains `aria-label` from existing markup (`"{{ $indicator->label_full ?? '' }} ўсиш мониторинги"`).
- Each chip wraps `label / value / badge` as visually-styled spans; no live regions.
- Contrast: green `#4ad17a` on `#0b1f3b` ≈ 7.2:1 (AA large). Pill green on its translucent bg → adequate for non-text indicator.

## Testing

- Pest browser/feature test on `/dashboard?kpi=grp`: asserts `.macro-hero-strip` exists, `.macro-hero-strip__chip` count = 4, `.is-actual`, `.is-target` classes present.
- Pest feature test on `/dashboard?kpi=industry`: asserts `.macro-hero-strip` is **absent** and `.macro-hero-card` still present (regression guard).
- Manual smoke: cycle through ЯҲМ → Қишлоқ хўжалиги → Қурилиш → Хизматлар → Саноат tabs; confirm dark strip on first four, white hero on industry.

## Risks

- `growth_pct` for `year` row may be a forecast (expected) rather than actual; pill copy `"+X.X п.п. {baseYear}-йилдан"` still reads correctly because `growth_pct` is always relative to base year.
- If `$yearRow` is missing for a region/period (data gap), big number falls back to `—`, pill suppressed.
- Industry path untouched, but shared `.macro-main-panel` padding rule applies to both branches — confirm visually that strip uses its own padding via descendant rules (or remove `.macro-main-panel` wrapper for strip case).

## Files touched

- `resources/views/livewire/dashboard/panels/macro-growth.blade.php` — branch on `$showIndustryDrivers`, add strip markup + derived values.
- `public/css/portal.css` — add `.macro-hero-strip` block (≈80 lines) + responsive overrides.
- `tests/Feature/Livewire/MacroHeroStripTest.php` (new) — two assertions per the testing section.
