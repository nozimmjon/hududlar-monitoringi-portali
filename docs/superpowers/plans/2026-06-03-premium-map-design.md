# Premium Map Redesign ("Refined Light") Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the `/districts` choropleth a premium "Refined Light" look — cream canvas, soft elevation shadow, white separators, a muted terracotta→sage palette, adaptive labels (districts named in-cell, too-small cities shown as a dot), hover-lift, and a matching legend.

**Architecture:** Three coordinated edits to existing files — the palette (`MapColorScale.php`), the map's label markup (`districts-page.blade.php` map-labels loop), and the map CSS (`portal.css`). The map geometry, `DistrictsPage` component, and selection logic are untouched. The only behavior with a unit test is the palette, which is updated test-first.

**Tech Stack:** Laravel 12, Livewire 3, Blade + SVG, Alpine (map tooltip), Pest 3. Hand-maintained `portal.css` (no build step).

---

## File Structure

| File | Change | Responsibility |
| --- | --- | --- |
| `backend/tests/Unit/Support/MapColorScaleTest.php` | Modify | Pin the new palette hexes (red first). |
| `backend/app/Support/MapColorScale.php` | Modify | New muted 5-stop palette. |
| `backend/resources/views/livewire/districts-page.blade.php` | Modify | Adaptive map labels (district → name, city → dot). |
| `backend/public/css/portal.css` | Modify | Cream canvas, elevation, white separators, label/dot styling, hover-lift, legend gradient. |

**Conventions (CLAUDE.md / memory):** Cyrillic UI (don't translate). `portal.css` hand-maintained — **no `npm run build`**. Tests share one Postgres DB — run ONLY the targeted filter. Windows PowerShell: chain with `;`; artisan from `backend/`. Single cohesive commit at the end (keeps the branch green).

---

## Task 1: Palette — update test, then implementation

**Files:**
- Test: `backend/tests/Unit/Support/MapColorScaleTest.php`
- Modify: `backend/app/Support/MapColorScale.php`

- [ ] **Step 1: Rewrite the palette test to the new hexes (red)**

Overwrite `backend/tests/Unit/Support/MapColorScaleTest.php` with:

```php
<?php

use App\Support\MapColorScale;

test('palette extreme 0.0 returns low-tier terracotta for higher-is-better', function () {
    expect(MapColorScale::palette(0.0, false))->toBe('#cf7e6b');
});

test('palette extreme 1.0 returns high-tier green for higher-is-better', function () {
    expect(MapColorScale::palette(1.0, false))->toBe('#6fa888');
});

test('palette 0.0 returns high-tier green for lower-is-better (inverted)', function () {
    expect(MapColorScale::palette(0.0, true))->toBe('#6fa888');
});

test('palette 1.0 returns low-tier terracotta for lower-is-better (inverted)', function () {
    expect(MapColorScale::palette(1.0, true))->toBe('#cf7e6b');
});

test('palette 0.5 returns the wheat midpoint', function () {
    expect(MapColorScale::palette(0.5, false))->toBe('#e8cf8e');
});

test('palette null returns no-data grey', function () {
    expect(MapColorScale::palette(null, false))->toBe(MapColorScale::NO_DATA);
});

test('palette interpolates between stops (0.125 between stop 0 and stop 1)', function () {
    $c = MapColorScale::palette(0.125, false);
    expect($c)->not->toBe('#cf7e6b');
    expect($c)->not->toBe('#e0a878');
    expect($c)->toMatch('/^#[0-9a-f]{6}$/');
});

test('palette clamps out-of-range values', function () {
    expect(MapColorScale::palette(-0.5, false))->toBe('#cf7e6b');
    expect(MapColorScale::palette(1.5, false))->toBe('#6fa888');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend; php artisan test --filter=MapColorScale`
Expected: FAIL — current palette still returns `#d95757`/`#4a9b5f`/`#e9c63b`, not the new hexes.

- [ ] **Step 3: Update the palette stops**

In `backend/app/Support/MapColorScale.php`, replace this block:

```php
    /** 5-stop palette: red → orange → yellow → light green → green */
    private const STOPS = [
        [0.00, [217,  87,  87]],
        [0.25, [240, 163,  86]],
        [0.50, [233, 198,  59]],
        [0.75, [155, 203, 111]],
        [1.00, [ 74, 155,  95]],
    ];
```

with:

```php
    /** 5-stop muted palette: terracotta → sand → wheat → sage → eucalyptus */
    private const STOPS = [
        [0.00, [207, 126, 107]],
        [0.25, [224, 168, 120]],
        [0.50, [232, 207, 142]],
        [0.75, [169, 199, 154]],
        [1.00, [111, 168, 136]],
    ];
```

Leave `NO_DATA`, the constructor-less interpolation logic, and the `lowerIsBetter` handling unchanged.

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd backend; php artisan test --filter=MapColorScale`
Expected: PASS (8 tests). `0.0→#cf7e6b`, `1.0→#6fa888`, `0.5→#e8cf8e`, inversion + clamp updated.

Do NOT commit (single commit at the end).

---

## Task 2: Adaptive labels in the view

**Files:**
- Modify: `backend/resources/views/livewire/districts-page.blade.php`

The map-labels group currently renders an (opacity-0) name + an always-on % for every cell. Replace it so districts get an always-on name (+%), while cities render a dot (name appears on hover via the existing tooltip, and on selection).

- [ ] **Step 1: Replace the `<g class="map-labels"> … </g>` block**

Find this exact block:

```blade
                    <g class="map-labels">
                        @foreach($mapGeometry['cells'] as $cell)
                            @php
                                $cellCode = $cell['code'] ?? null;
                                $cellDistrict = $cellCode !== null ? $districts->get($cellCode) : null;
                                $scaleEntry = $cellCode !== null ? ($colorScale[$cellCode] ?? null) : null;
                                $cellValue = $scaleEntry['value'] ?? null;
                                $cellCity = str_ends_with($cell['name'], ' ш.') ? 'is-city' : '';
                                $shortLabel = $cellDistrict?->name_short ?? $cell['name'];
                            @endphp
                            <text class="map-label {{ $cellCity }}"
                                  x="{{ $cell['cx'] }}" y="{{ $cell['cy'] - 4 }}"
                                  text-anchor="middle">{{ $shortLabel }}</text>
                            @if($cellValue !== null)
                                <text class="map-value" x="{{ $cell['cx'] }}" y="{{ $cell['cy'] + 10 }}"
                                      text-anchor="middle">{{ $fmt($cellValue, 1) }}%</text>
                            @endif
                        @endforeach
                    </g>
```

Replace with:

```blade
                    <g class="map-labels">
                        @foreach($mapGeometry['cells'] as $cell)
                            @php
                                $cellCode = $cell['code'] ?? null;
                                $cellDistrict = $cellCode !== null ? $districts->get($cellCode) : null;
                                $scaleEntry = $cellCode !== null ? ($colorScale[$cellCode] ?? null) : null;
                                $cellValue = $scaleEntry['value'] ?? null;
                                $isCity = str_ends_with($cell['name'], ' ш.');
                                $shortLabel = $cellDistrict?->name_short ?? $cell['name'];
                                $cellSel = $cellCode !== null && (string) $cellCode === (string) $selectedCode;
                            @endphp
                            @if($isCity)
                                <circle class="map-dot {{ $cellSel ? 'selected' : '' }}"
                                        cx="{{ $cell['cx'] }}" cy="{{ $cell['cy'] }}" r="3"/>
                                <text class="map-label is-city {{ $cellSel ? 'selected' : '' }}"
                                      x="{{ $cell['cx'] }}" y="{{ $cell['cy'] - 6 }}"
                                      text-anchor="middle">{{ $shortLabel }}</text>
                            @else
                                <text class="map-label {{ $cellSel ? 'selected' : '' }}"
                                      x="{{ $cell['cx'] }}" y="{{ $cell['cy'] - 4 }}"
                                      text-anchor="middle">{{ $shortLabel }}</text>
                                @if($cellValue !== null)
                                    <text class="map-value" x="{{ $cell['cx'] }}" y="{{ $cell['cy'] + 10 }}"
                                          text-anchor="middle">{{ $fmt($cellValue, 1) }}%</text>
                                @endif
                            @endif
                        @endforeach
                    </g>
```

(`$selectedCode` is already defined in the view's top `@php` block. The `selected` class on the city label/dot drives the on-selection reveal via CSS in Task 3.)

- [ ] **Step 2: Confirm the page still renders**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: PASS (markup assertions don't touch map labels; this verifies no Blade error).

---

## Task 3: Premium map CSS

**Files:**
- Modify: `backend/public/css/portal.css`

Match each rule by its selector/declaration text (line numbers drift). All these rules live in the map block.

- [ ] **Step 1: Cream canvas**

Replace:

```css
      background: linear-gradient(180deg, #f8fbff 0%, #eef4fa 100%);
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 18px 12px;
      min-height: 430px;
```

with:

```css
      background: #fbfaf7;
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 18px 12px;
      min-height: 430px;
```

- [ ] **Step 2: Elevation shadow on the region**

In the `.andijan-map { … }` rule, replace `filter: none;` with:

```css
      filter: drop-shadow(0 10px 16px rgba(40, 55, 80, .14));
```

- [ ] **Step 3: White separators (the effective `.map-fill` rule)**

`.map-cell .map-fill` is declared twice; the **second** wins for stroke. Replace that second block:

```css
    .map-cell .map-fill {
      stroke: rgba(15, 42, 71, .14);
      stroke-width: 0.9;
      transition: filter var(--motion), stroke var(--motion);
    }
```

with:

```css
    .map-cell .map-fill {
      stroke: #ffffff;
      stroke-width: 1.6;
      transition: filter var(--motion), stroke var(--motion), stroke-width var(--motion);
    }
```

- [ ] **Step 4: District labels always visible; city labels hidden until selected**

In `.map-label { … }` replace `opacity: 0;` with `opacity: 1;`.

In the `.map-label.is-city { … }` rule, add an `opacity: 0;` declaration (so city names hide by default). The block becomes:

```css
    .map-label.is-city {
      font-size: 11px;
      stroke-width: 3.2;
      opacity: 0;
    }
```

(The existing `.map-label.selected, .map-label.hover { opacity: 1; }` rule comes later in the file and has equal specificity, so a city label carrying `selected` correctly re-shows. Leave that rule and `.map-label.selected { fill: var(--blue); }` as-is.)

- [ ] **Step 5: City dot styling**

Add immediately after the `.map-label.selected { fill: var(--blue); }` rule:

```css
    .map-dot {
      fill: #ffffff;
      stroke: rgba(60, 70, 90, .55);
      stroke-width: 1.3;
      transition: stroke var(--motion), stroke-width var(--motion);
    }
    .map-dot.selected {
      stroke: var(--blue);
      stroke-width: 2;
    }
```

- [ ] **Step 6: Hover-lift**

Replace:

```css
    .map-cell:hover .map-fill,
    .map-cell:focus .map-fill {
      filter: brightness(1.06);
      stroke: rgba(15, 42, 71, .35);
      stroke-width: 1.4;
    }
```

with:

```css
    .map-cell:hover .map-fill,
    .map-cell:focus .map-fill {
      filter: brightness(1.03) drop-shadow(0 4px 8px rgba(40, 55, 80, .20));
      stroke: #ffffff;
      stroke-width: 1.9;
    }
```

(Leave `.map-cell.selected .map-fill { … blue glow … }` unchanged.)

- [ ] **Step 7: Legend gradient matches the new palette**

Replace:

```css
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

with:

```css
    .legend-bar {
      flex: 1;
      height: 10px;
      border-radius: 999px;
      background: linear-gradient(90deg, #cf7e6b, #e0a878, #e8cf8e, #a9c79a, #6fa888);
    }
    .legend-bar.reverse {
      background: linear-gradient(90deg, #6fa888, #a9c79a, #e8cf8e, #e0a878, #cf7e6b);
    }
```

- [ ] **Step 8: Verify no stale palette hexes remain in the map/legend CSS**

Run: `cd backend; Select-String -Path public/css/portal.css -Pattern '#d95757|#4a9b5f|#e9c63b|#f0a356|#9bcb6f' | Select-Object -First 10`
Expected: NO output (old palette gone from CSS). If a match is from an unrelated component (not map/legend), leave it — but the legend stops above are the only expected occurrences and are now replaced.

---

## Task 4: Verify and commit

**Files:** none (verification + commit).

- [ ] **Step 1: Run the palette + page filters (alone — shared DB)**

Run: `cd backend; php artisan test --filter=MapColorScale`  → expected PASS (8).
Then: `cd backend; php artisan test --filter=Districts`  → expected PASS (39).

- [ ] **Step 2: Headless screenshot check**

Dev server may already run on port 8000; if not: `cd backend; php artisan serve --host=127.0.0.1 --port=8000` (background). Capture:

```
& "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless=new --disable-gpu --hide-scrollbars --no-first-run --user-data-dir="C:/Users/y.utepbergenov/AppData/Local/Temp/edge-mapverify" --window-size=1400,1100 --screenshot="C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/.superpowers/verify-map.png" "http://127.0.0.1:8000/districts"
```

Confirm: cream canvas, soft elevation under the region, crisp white borders between districts, muted terracotta→sage fills, district names visible in-cell, the two cities shown as dots (no overflowing labels), legend matching the fills. Then load `…/districts?district=<city-code>` and confirm the selected city's name appears.

- [ ] **Step 3: Commit**

```bash
git add backend/app/Support/MapColorScale.php backend/public/css/portal.css backend/resources/views/livewire/districts-page.blade.php backend/tests/Unit/Support/MapColorScaleTest.php
git commit -m "feat(districts): premium Refined Light map + adaptive labels"
```

End the commit message with the project's `Co-Authored-By` trailer.

---

## Self-review notes

- **Spec coverage:** palette (T1) · cream canvas (T3 S1) · elevation (T3 S2) · white separators incl. the duplicate-rule gotcha (T3 S3) · adaptive labels district/city + dot (T2, T3 S4/S5) · hover-lift (T3 S6) · selection unchanged (T3 S6 note) · legend match (T3 S7) · MapColorScaleTest hexes (T1). All spec sections map to a task.
- **Hex consistency:** palette stops, legend gradient, and test expectations all use the same five hexes (`#cf7e6b`, `#e0a878`, `#e8cf8e`, `#a9c79a`, `#6fa888`).
- **DOM caveat handled:** map labels live in a separate `<g class="map-labels">`, not inside `<g class="map-cell">`, so the city-on-select reveal uses a `selected` class **on the label/dot element** (set in the view) + the existing `.map-label.selected` rule — not a `.map-cell.selected .map-label` descendant selector.
- **Conceptual integrity:** color still encodes lagging→leading; only aesthetics change. Names never lost (district in-cell, city via hover tooltip + rank list + on-selection).
