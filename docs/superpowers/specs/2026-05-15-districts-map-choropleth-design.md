# Districts map choropleth + rollup banner + tooltip

**Date:** 2026-05-15
**Status:** Approved
**Scope:** Replace 4-bucket threshold coloring with continuous choropleth gradient; add inline value text per district; replace chip legend with gradient bar; show region rollup banner above the map; add custom HTML hover tooltip; outline-glow on selected district.

---

## 1. Goal

Today's districts map paints every cell the same color because real-world data clusters in a narrow band (e.g. industry growth 105-110% all hits the `≥95 = green` threshold). The 4-color legend doesn't say what those thresholds mean numerically. Users can't see *where* the variation actually is.

After this work the map renders a 5-stop gradient where each district's color reflects its rank relative to the region's min/max for the selected KPI. Each cell shows its numeric value. Hovering pops a card with district name + value + rank + status. The legend is a gradient bar with the actual min/max. A banner above shows the region rollup.

## 2. Non-goals

- No new map library.
- No animation/transition framework.
- No multi-KPI overlay/comparison.
- No mobile-specific redesign.
- No new permanent CSS class system — extend existing `.map-cell` patterns.

## 3. Strategy

`MapColorScale` helper does linear RGB interpolation across a 5-stop palette. `DistrictsPage::colorScale()` normalizes per-region values and returns hex colors keyed by district code. Blade pulls colors inline, drops the 4 fixed gradients, adds value text in each cell, adds Alpine-driven tooltip.

## 4. Components

### 4.1 `App\Support\MapColorScale` (new)

```php
namespace App\Support;

class MapColorScale
{
    /** 5-stop palette: red → orange → yellow → light green → green */
    private const STOPS = [
        [0.00, [217,  87,  87]],
        [0.25, [240, 163,  86]],
        [0.50, [233, 198,  59]],
        [0.75, [155, 203, 111]],
        [1.00, [ 74, 155,  95]],
    ];

    public const NO_DATA = '#e8e6dd';

    public static function palette(?float $norm, bool $lowerIsBetter): string
    {
        if ($norm === null) return self::NO_DATA;
        if ($lowerIsBetter) $norm = 1.0 - $norm;
        $norm = max(0.0, min(1.0, $norm));

        for ($i = 0; $i < count(self::STOPS) - 1; $i++) {
            [$tA, $cA] = self::STOPS[$i];
            [$tB, $cB] = self::STOPS[$i + 1];
            if ($norm <= $tB) {
                $t = $tB === $tA ? 0 : ($norm - $tA) / ($tB - $tA);
                return sprintf('#%02x%02x%02x',
                    (int) round($cA[0] + ($cB[0] - $cA[0]) * $t),
                    (int) round($cA[1] + ($cB[1] - $cA[1]) * $t),
                    (int) round($cA[2] + ($cB[2] - $cA[2]) * $t),
                );
            }
        }
        return '#' . sprintf('%02x%02x%02x', ...self::STOPS[count(self::STOPS) - 1][1]);
    }
}
```

### 4.2 `DistrictsPage` Livewire

Add two computed properties:

```php
#[Computed]
public function colorScale(): array
{
    $values = [];
    foreach ($this->facts as $code => $fact) {
        $v = $fact->pct_of_plan ?? $fact->growth_pct;
        if ($v !== null) $values[$code] = (float) $v;
    }
    if (empty($values)) return [];

    $min = min($values);
    $max = max($values);
    $range = $max - $min;
    $lower = (bool) ($this->indicator?->lower_is_better);

    $out = [];
    foreach ($values as $code => $v) {
        $norm = $range > 0 ? ($v - $min) / $range : 0.5;
        $out[$code] = [
            'color' => \App\Support\MapColorScale::palette($norm, $lower),
            'value' => $v,
        ];
    }
    return $out;
}

#[Computed]
public function colorRange(): array
{
    $entries = collect($this->colorScale);
    if ($entries->isEmpty()) {
        return ['min' => null, 'max' => null, 'lowerIsBetter' => false];
    }
    return [
        'min' => $entries->min('value'),
        'max' => $entries->max('value'),
        'lowerIsBetter' => (bool) ($this->indicator?->lower_is_better),
    ];
}
```

Pass both through `render()` view data.

### 4.3 `districts-page.blade.php` map block rewrite

**Before the SVG**: add rollup banner:

```blade
@php
    $rollupValue = $rollup?->pct_of_plan !== null
        ? $fmt($rollup->pct_of_plan, 1) . '%'
        : ($rollup?->growth_pct !== null ? $fmt($rollup->growth_pct, 1) . '%' : '—');
    $rollupStatus = \App\Support\DistrictStatus::statusFor(
        $rollup?->pct_of_plan !== null ? (float) $rollup->pct_of_plan : null,
        $rollup?->growth_pct !== null ? (float) $rollup->growth_pct : null,
        (bool) ($this->indicator?->lower_is_better)
    );
@endphp

<div class="districts-rollup-banner">
    <div>
        <span class="rollup-label">{{ \App\Support\CurrentRegion::current()->name_full }}</span>
        <strong class="rollup-value">{{ $rollupValue }}</strong>
    </div>
    <span class="chip {{ $rollupStatus }}">{{ $kpiShort }} · {{ $period }}</span>
</div>
```

**SVG block**: drop the 4 linearGradients in `<defs>`. Replace cell loop:

```blade
<div class="districts-map-canvas" x-data="{hovered:null,x:0,y:0}">
    <svg viewBox="{{ $mapGeometry['viewBox'] }}" class="andijan-map" role="img">
        <g>
            @foreach($mapGeometry['cells'] as $cell)
                @php
                    $cellCode = $cell['code'] ?? null;
                    $cellDistrict = $cellCode !== null ? $districts->get($cellCode) : null;
                    $scaleEntry = $cellCode !== null ? ($colorScale[$cellCode] ?? null) : null;
                    $cellColor = $scaleEntry['color'] ?? \App\Support\MapColorScale::NO_DATA;
                    $cellValue = $scaleEntry['value'] ?? null;
                    $valueText = $cellValue !== null ? $fmt($cellValue, 1) . '%' : '—';
                    $cellSelected = $cellCode !== null && (string) $cellCode === (string) $selectedCode ? 'selected' : '';
                    $cellName = $cellDistrict?->name_full ?? $cell['name'];
                @endphp
                <g class="map-cell {{ $cellSelected }}"
                   wire:click="selectDistrict('{{ $cellCode }}')"
                   x-on:mouseenter="hovered={name:@js($cellName), value:@js($valueText), color:@js($cellColor)}"
                   x-on:mouseleave="hovered=null"
                   x-on:mousemove="x=$event.offsetX; y=$event.offsetY"
                   tabindex="0">
                    <path class="map-fill" d="{{ $cell['path'] }}" fill="{{ $cellColor }}"/>
                </g>
            @endforeach
        </g>
        <g class="map-labels">
            @foreach($mapGeometry['cells'] as $cell)
                @php
                    $cellCode = $cell['code'] ?? null;
                    $scaleEntry = $cellCode !== null ? ($colorScale[$cellCode] ?? null) : null;
                    $cellValue = $scaleEntry['value'] ?? null;
                    $shortLabel = $cellCode !== null && $districts->get($cellCode)
                        ? $districts->get($cellCode)->name_short
                        : $cell['name'];
                @endphp
                <text class="map-label" x="{{ $cell['cx'] }}" y="{{ $cell['cy'] - 4 }}"
                      text-anchor="middle">{{ $shortLabel }}</text>
                @if($cellValue !== null)
                    <text class="map-value" x="{{ $cell['cx'] }}" y="{{ $cell['cy'] + 10 }}"
                          text-anchor="middle">{{ $fmt($cellValue, 1) }}%</text>
                @endif
            @endforeach
        </g>
    </svg>
    <div class="map-tooltip" x-show="hovered" x-cloak
         :style="`left:${x + 14}px; top:${y + 14}px; --c:${hovered?.color}`">
        <strong x-text="hovered?.name"></strong>
        <span x-text="hovered?.value"></span>
    </div>
</div>
```

**Legend**: replace 4 chips with gradient bar:

```blade
<div class="districts-map-legend">
    @php
        $rangeMin = $colorRange['min'];
        $rangeMax = $colorRange['max'];
    @endphp
    <span class="legend-bound">{{ $rangeMin !== null ? $fmt($rangeMin, 1) . '%' : '—' }}</span>
    <span class="legend-bar {{ $colorRange['lowerIsBetter'] ? 'reverse' : '' }}"></span>
    <span class="legend-bound">{{ $rangeMax !== null ? $fmt($rangeMax, 1) . '%' : '—' }}</span>
</div>
```

### 4.4 CSS additions in `public/css/portal.css`

```css
.districts-rollup-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: 10px;
    margin-bottom: 12px;
}
.rollup-label { display: block; color: var(--muted); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.rollup-value { color: var(--ink); font-size: 22px; font-weight: 800; }

.districts-map-canvas { position: relative; }
.map-cell .map-fill { stroke: rgba(15, 42, 71, .12); stroke-width: 0.8; transition: filter var(--motion), stroke var(--motion); }
.map-cell:hover .map-fill { filter: brightness(1.06); }
.map-cell.selected .map-fill {
    stroke: var(--blue-2);
    stroke-width: 3;
    filter: drop-shadow(0 0 6px rgba(23, 105, 224, .55));
}

.map-label { fill: #0f1d22; font-size: 13px; font-weight: 700; pointer-events: none; paint-order: stroke; stroke: rgba(255,255,255,.85); stroke-width: 3; }
.map-value { fill: #0f1d22; font-size: 11px; font-weight: 800; pointer-events: none; paint-order: stroke; stroke: rgba(255,255,255,.85); stroke-width: 2.5; }

.map-tooltip {
    position: absolute;
    background: #0f1d22;
    color: #fff;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    box-shadow: 0 8px 24px rgba(7, 26, 45, .28);
    border-left: 3px solid var(--c, var(--blue-2));
    pointer-events: none;
    z-index: 50;
}
.map-tooltip strong { display: block; font-size: 13px; margin-bottom: 2px; }
[x-cloak] { display: none !important; }

.districts-map-legend {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
}
.legend-bound { color: var(--ink); font-size: 12px; font-weight: 700; min-width: 50px; text-align: center; }
.legend-bar {
    flex: 1;
    height: 10px;
    border-radius: 999px;
    background: linear-gradient(90deg, #d95757, #f0a356, #e9c63b, #9bcb6f, #4a9b5f);
}
.legend-bar.reverse {
    background: linear-gradient(90deg, #4a9b5f, #9bcb6f, #e9c63b, #f0a356, #d95757);
}
```

### 4.5 Alpine.js wiring

Add Alpine to the layout if not present. Check `layouts/app.blade.php` — Livewire 3 ships Alpine by default. Should be available.

## 5. Tests

`tests/Unit/Support/MapColorScaleTest.php`:

```php
test('palette extreme 0.0 returns red for higher-is-better', function () {
    expect(MapColorScale::palette(0.0, false))->toBe('#d95757');
});

test('palette extreme 1.0 returns green for higher-is-better', function () {
    expect(MapColorScale::palette(1.0, false))->toBe('#4a9b5f');
});

test('palette 0.0 returns green for lower-is-better (inverted)', function () {
    expect(MapColorScale::palette(0.0, true))->toBe('#4a9b5f');
});

test('palette 0.5 returns yellow midpoint', function () {
    expect(MapColorScale::palette(0.5, false))->toBe('#e9c63b');
});

test('palette null returns no-data grey', function () {
    expect(MapColorScale::palette(null, false))->toBe(MapColorScale::NO_DATA);
});

test('palette interpolates between stops', function () {
    $c = MapColorScale::palette(0.125, false);
    // halfway between #d95757 and #f0a356 → blend
    expect($c)->not->toBe('#d95757')->not->toBe('#f0a356');
});
```

DistrictsPage colorScale test:
- Seed facts for 3 districts with values 100, 110, 120 (industry, higher-is-better).
- Assert colorScale[low] color is closer to red, [high] closer to green.

## 6. Files

| File | Action |
|---|---|
| `backend/app/Support/MapColorScale.php` | new |
| `backend/app/Livewire/DistrictsPage.php` | add `colorScale()` + `colorRange()` computed |
| `backend/resources/views/livewire/districts-page.blade.php` | rewrite map block |
| `backend/public/css/portal.css` | add new styles |
| `backend/tests/Unit/Support/MapColorScaleTest.php` | new |
| `backend/tests/Pest.php` | extend TestCase binding for the new test |

## 7. Operator smoke

After implementation:

1. Visit `/districts?kpi=industry`. Map shows 14 districts with VARYING colors (gradient red→green based on growth values 105-110%). Each cell has its name on top and value below.
2. Hover any cell → tooltip card appears at cursor with district name + value, left-bordered by cell color.
3. Click cell → blue glow ring + selected style + aside updates.
4. Legend at bottom: gradient bar with min/max values.
5. Rollup banner above map shows region name + total rollup value + status pill.
6. Switch KPI to `poverty` (lower-is-better) → gradient inverts (low value = green).

## 8. Risks

- **Tight cell text**: city polygons (Хонобод ш., Янгиер ш.) are tiny — value text may overlap. Mitigation: small font (11px) + paint-order stroke for legibility. Skip value text if cell too small (future polish).
- **Alpine not loaded**: Livewire 3 includes Alpine. Confirm in `@livewireScripts` includes it. If missing, register via `<script src=".../alpine.min.js">`.
- **Mouse coordinates**: `$event.offsetX` is relative to the hovered element, not the canvas. For consistent positioning use `$event.pageX - canvas.offsetLeft` via wrapper. Verify in smoke.
- **No-data districts**: stay grey via `MapColorScale::NO_DATA`. Tooltip should still work showing "—" for value.
- **Single-value range**: all districts have same value → `$range = 0` → normalized 0.5 → all yellow. Acceptable.
- **Browser SVG hover lag**: too many `mouseenter` events may cause Livewire spam. Mitigation: Alpine state is client-side only, no Livewire calls.
