# Professional Map Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the `/districts` map as a full-width navy hero with binary green/red districts and name+value pill labels auto-placed around the map (region-agnostic), replacing the gradient map + rank list.

**Architecture:** A new pure `App\Support\MapLabelLayout` computes pill placement + leader paths from the projected geometry (unit-tested). `DistrictsPage` feeds it binary colors + values. The view renders one navy SVG stage (cells + leaders + pills), drops the rank list, keeps the header card + slide-over peek. The superseded gradient palette (`MapColorScale`) and its computed are removed.

**Tech Stack:** Laravel 12, Livewire 3, Blade + SVG, Alpine, Pest 3. Hand-maintained `portal.css` (no build step).

---

## File Structure

| File | Change | Responsibility |
| --- | --- | --- |
| `backend/app/Support/MapLabelLayout.php` | Create | Pure pill/leader placement from geometry + labels. |
| `backend/tests/Unit/Support/MapLabelLayoutTest.php` | Create | Unit tests for the layout. |
| `backend/tests/Feature/Http/DistrictsPageTest.php` | Modify | Assert the new map markup; drop rank-list asserts. |
| `backend/app/Livewire/DistrictsPage.php` | Modify | Add `mapColors()` + `mapLayout()`; later remove `colorScale()`/`colorRange()`. |
| `backend/resources/views/livewire/districts-page.blade.php` | Modify | Full-width navy map stage + pills; remove rank list. |
| `backend/public/css/portal.css` | Modify | Navy stage/binary/pill/leader/legend; remove gradient/label/dot/ranklist rules. |
| `backend/app/Support/MapColorScale.php` | Delete | Gradient palette superseded by binary. |
| `backend/tests/Unit/Support/MapColorScaleTest.php` | Delete | Tests the deleted class. |

**Conventions (CLAUDE.md / memory):** Cyrillic UI (don't translate). `portal.css` hand-maintained — **no `npm run build`**. Tests share one Postgres DB — run ONLY the targeted filter. Windows PowerShell: chain with `;`; artisan from `backend/`. Single cohesive commit at the end (keeps the branch green).

---

## Task 1: `MapLabelLayout` helper (TDD)

**Files:**
- Create: `backend/app/Support/MapLabelLayout.php`
- Create: `backend/tests/Unit/Support/MapLabelLayoutTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Support/MapLabelLayoutTest.php`:

```php
<?php

use App\Support\MapLabelLayout;

function mll_geometry(): array
{
    return [
        'viewBox' => '0 0 600 500',
        'cells' => [
            ['code' => 1, 'name' => 'A', 'path' => '', 'cx' => 100.0, 'cy' => 50.0],
            ['code' => 2, 'name' => 'B', 'path' => '', 'cx' => 120.0, 'cy' => 400.0],
            ['code' => 3, 'name' => 'C', 'path' => '', 'cx' => 500.0, 'cy' => 80.0],
            ['code' => 4, 'name' => 'D', 'path' => '', 'cx' => 480.0, 'cy' => 300.0],
        ],
    ];
}

function mll_labels(): array
{
    return [
        1 => ['name' => 'Бир',  'value' => '120%', 'color' => 'ok'],
        2 => ['name' => 'Икки', 'value' => '80%',  'color' => 'bad'],
        3 => ['name' => 'Уч',   'value' => '—',    'color' => 'nd'],
        4 => ['name' => 'Тўрт', 'value' => '150%', 'color' => 'ok'],
    ];
}

test('expands the viewBox by the gutter on both sides', function () {
    $r = MapLabelLayout::build(mll_geometry(), mll_labels(), 230);
    expect($r['viewBox'])->toBe('0 0 1060 500');
    expect($r['mapTranslate'])->toBe(230);
});

test('produces one pill per labeled cell', function () {
    $r = MapLabelLayout::build(mll_geometry(), mll_labels());
    expect($r['pills'])->toHaveCount(4);
});

test('assigns sides by centroid x relative to map center', function () {
    $byCode = collect(MapLabelLayout::build(mll_geometry(), mll_labels())['pills'])->keyBy('code');
    expect($byCode[1]['side'])->toBe('L');
    expect($byCode[2]['side'])->toBe('L');
    expect($byCode[3]['side'])->toBe('R');
    expect($byCode[4]['side'])->toBe('R');
});

test('left pills sit in the gutter and right pills stay within the canvas', function () {
    foreach (MapLabelLayout::build(mll_geometry(), mll_labels(), 230)['pills'] as $p) {
        if ($p['side'] === 'L') {
            expect($p['x'])->toBeLessThan(230.0);
        } else {
            expect($p['x'])->toBeGreaterThan(600.0);
            expect($p['x'] + $p['w'])->toBeLessThanOrEqual(1060.0);
        }
    }
});

test('pills on a side increase in y and follow centroid order', function () {
    $left = collect(MapLabelLayout::build(mll_geometry(), mll_labels())['pills'])->where('side', 'L')->values();
    expect($left[0]['code'])->toBe(1);
    expect($left[1]['code'])->toBe(2);
    expect($left[1]['y'])->toBeGreaterThan($left[0]['y']);
});

test('each leader anchor is the centroid shifted by the gutter', function () {
    $p1 = collect(MapLabelLayout::build(mll_geometry(), mll_labels(), 230)['pills'])->firstWhere('code', 1);
    expect($p1['dotX'])->toBe(330.0);
    expect($p1['dotY'])->toBe(50.0);
});

test('skips cells with no code or no matching label', function () {
    $geo = mll_geometry();
    $geo['cells'][] = ['code' => null, 'name' => 'X', 'path' => '', 'cx' => 50.0, 'cy' => 50.0];
    $geo['cells'][] = ['code' => 9, 'name' => 'Y', 'path' => '', 'cx' => 60.0, 'cy' => 60.0];
    expect(MapLabelLayout::build($geo, mll_labels())['pills'])->toHaveCount(4);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend; php artisan test --filter=MapLabelLayout`
Expected: FAIL — `Class "App\Support\MapLabelLayout" not found`.

- [ ] **Step 3: Implement the helper**

Create `backend/app/Support/MapLabelLayout.php`:

```php
<?php

namespace App\Support;

class MapLabelLayout
{
    /**
     * Place name+value pills around the map perimeter with leader lines.
     *
     * @param  array{viewBox?:string,cells?:array}  $geometry  RegionMapGeometry output.
     * @param  array<int|string,array{name:string,value:string,color:string}>  $labels  keyed by cell code.
     * @return array{viewBox:string,mapTranslate:int,pills:array<int,array<string,mixed>>}
     */
    public static function build(array $geometry, array $labels, int $gutter = 230): array
    {
        [$vw, $vh] = self::viewDims($geometry['viewBox'] ?? '0 0 600 500');
        $outerW  = $vw + 2 * $gutter;
        $centerX = $vw / 2;
        $top     = 20.0;
        $padX    = 9.0;
        $gap     = 8.0;
        $nameCharW = 6.4;
        $valCharW  = 6.6;
        $h = 20.0;

        $left = [];
        $right = [];
        foreach ($geometry['cells'] ?? [] as $cell) {
            $code = $cell['code'] ?? null;
            if ($code === null || ! isset($labels[$code])) {
                continue;
            }
            if ($cell['cx'] < $centerX) {
                $left[] = $cell;
            } else {
                $right[] = $cell;
            }
        }
        usort($left,  fn ($a, $b) => $a['cy'] <=> $b['cy']);
        usort($right, fn ($a, $b) => $a['cy'] <=> $b['cy']);

        $pills = [];
        foreach (['L' => $left, 'R' => $right] as $side => $list) {
            $n = count($list);
            $step = $n > 1 ? ($vh - 2 * $top) / ($n - 1) : 0.0;
            foreach ($list as $i => $cell) {
                $code = $cell['code'];
                $lbl  = $labels[$code];
                $py   = $n > 1 ? $top + $i * $step : $vh / 2;
                $w    = 2 * $padX + mb_strlen($lbl['name']) * $nameCharW + $gap + mb_strlen($lbl['value']) * $valCharW;
                $px   = $side === 'L' ? 6.0 : $outerW - 6.0 - $w;
                $dotX = $cell['cx'] + $gutter;
                $dotY = $cell['cy'];
                $startX = $side === 'L' ? $px + $w : $px;
                $midX   = ($startX + $dotX) / 2;
                $leader = sprintf(
                    'M %.1f %.1f L %.1f %.1f L %.1f %.1f L %.1f %.1f',
                    $startX, $py, $midX, $py, $midX, $dotY, $dotX, $dotY
                );

                $pills[] = [
                    'code'   => $code,
                    'side'   => $side,
                    'x'      => round($px, 1),
                    'y'      => round($py, 1),
                    'w'      => round($w, 1),
                    'h'      => $h,
                    'name'   => $lbl['name'],
                    'value'  => $lbl['value'],
                    'color'  => $lbl['color'],
                    'leader' => $leader,
                    'dotX'   => round($dotX, 1),
                    'dotY'   => round($dotY, 1),
                ];
            }
        }

        return [
            'viewBox'      => sprintf('0 0 %d %d', (int) $outerW, (int) $vh),
            'mapTranslate' => $gutter,
            'pills'        => $pills,
        ];
    }

    /** @return array{0:float,1:float} */
    private static function viewDims(string $viewBox): array
    {
        $p = preg_split('/\s+/', trim($viewBox));
        return [isset($p[2]) ? (float) $p[2] : 600.0, isset($p[3]) ? (float) $p[3] : 500.0];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd backend; php artisan test --filter=MapLabelLayout`
Expected: PASS (7 tests).

Do NOT commit.

---

## Task 2: Update the page feature test (red)

**Files:**
- Modify: `backend/tests/Feature/Http/DistrictsPageTest.php`

Only the bare-GET test references the rank list. Update it for the new map markup. (District `name_full` still renders inside each cell's `<title>`, so those assertions stay valid. The peek tests are unchanged.)

- [ ] **Step 1: Replace the bare-GET test**

Find:

```php
test('GET /districts renders header card, map, and rank list without pre-selection', function () {
    $response = $this->get('/districts');

    $response->assertOk();
    $response->assertSee('districts-header', false);
    $response->assertSee('module-seg', false);
    $response->assertSee('districts-map', false);
    $response->assertSee('districts-ranklist', false);
    $response->assertSee('Андижон шаҳри', false);
    $response->assertSee('Асака тумани', false);
    $response->assertDontSee('Танланган ҳудуд', false);
    $response->assertDontSee('district-peek open', false);
    $response->assertDontSee('districts-table', false);
});
```

Replace with:

```php
test('GET /districts renders the navy map stage with pills, no rank list, no pre-selection', function () {
    $response = $this->get('/districts');

    $response->assertOk();
    $response->assertSee('districts-header', false);
    $response->assertSee('module-seg', false);
    $response->assertSee('districts-mapstage', false);
    $response->assertSee('map-pill', false);
    $response->assertSee('Андижон шаҳри', false);
    $response->assertSee('Асака тумани', false);
    $response->assertDontSee('districts-ranklist', false);
    $response->assertDontSee('Танланган ҳудуд', false);
    $response->assertDontSee('district-peek open', false);
});
```

- [ ] **Step 2: Run the page tests to verify the rewritten one fails**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: FAIL on the rewritten test (`districts-mapstage` / `map-pill` not present yet; `districts-ranklist` still present). Other tests still pass.

Do NOT commit.

---

## Task 3: Component — add `mapColors()` + `mapLayout()`

**Files:**
- Modify: `backend/app/Livewire/DistrictsPage.php`

Keep `colorScale()`/`colorRange()` for now (removed in Task 6). Add the two computed and extend `render()`.

- [ ] **Step 1: Add `mapColors()` and `mapLayout()` before `mapGeometry()`**

Insert these two methods immediately above the existing `#[Computed] public function mapGeometry(): array`:

```php
    /**
     * Binary fill color per district code: 'ok' | 'bad' | 'nd'.
     *
     * @return array<int|string,string>
     */
    #[Computed]
    public function mapColors(): array
    {
        $out = [];
        foreach ($this->statusByDistrict as $code => $status) {
            $out[$code] = match ($status) {
                'green'        => 'ok',
                'amber', 'red' => 'bad',
                default        => 'nd',
            };
        }
        return $out;
    }

    /**
     * Perimeter pill layout for the map (name + value + color per district).
     */
    #[Computed]
    public function mapLayout(): array
    {
        $fmt = fn ($v) => number_format((float) $v, 1, ',', ' ');
        $colors = $this->mapColors;
        $labels = [];
        foreach ($this->districts as $code => $district) {
            $fact   = $this->facts->get($code);
            $pct    = $fact?->pct_of_plan;
            $growth = $fact?->growth_pct;
            $value  = $pct !== null
                ? $fmt($pct) . '%'
                : ($growth !== null ? $fmt($growth) . '%' : '—');
            $labels[$code] = [
                'name'  => $district->name_short,
                'value' => $value,
                'color' => $colors[$code] ?? 'nd',
            ];
        }

        return \App\Support\MapLabelLayout::build($this->mapGeometry, $labels);
    }
```

- [ ] **Step 2: Extend `render()` to pass the new data**

Replace:

```php
    public function render()
    {
        return view('livewire.districts-page', [
            'mapGeometry' => $this->mapGeometry,
            'colorScale'  => $this->colorScale,
            'colorRange'  => $this->colorRange,
        ]);
    }
```

with:

```php
    public function render()
    {
        return view('livewire.districts-page', [
            'mapGeometry' => $this->mapGeometry,
            'mapColors'   => $this->mapColors,
            'mapLayout'   => $this->mapLayout,
            'colorScale'  => $this->colorScale,
            'colorRange'  => $this->colorRange,
        ]);
    }
```

- [ ] **Step 3: Confirm it compiles (tests still red on markup only)**

Run: `cd backend; php artisan test --filter='clicking a district opens'`
Expected: PASS (peek path renders; the new computed don't break existing rendering). The bare-GET test stays red until Task 4.

---

## Task 4: View — full-width navy map stage with pills

**Files:**
- Modify: `backend/resources/views/livewire/districts-page.blade.php`

Replace the whole `<div class="districts-grid"> … </div>` block (the map section + the rank-list section, currently lines ~121–253) with a single full-width map stage. The `@php` header block, the `<header class="districts-header">`, and the slide-over peek (after the grid) are unchanged.

- [ ] **Step 1: Replace the grid block**

Replace the entire block that starts with `    <div class="districts-grid">` and ends with its matching `    </div>` (immediately before `    <div class="district-peek-backdrop …">`) with:

```blade
    <section class="districts-mapstage">
        <header class="mapstage-head">
            <div>
                <strong>Ҳудудлар харитаси</strong>
                <span>Яшил — режада, қизил — эътибор талаб. Туман устига босинг.</span>
            </div>
            <div class="map-legend">
                <span><i class="ok"></i>Режада</span>
                <span><i class="bad"></i>Эътибор</span>
                <span><i class="nd"></i>Маълумот йўқ</span>
            </div>
        </header>
        <div class="mapstage-canvas" x-data="{hovered:null,x:0,y:0}">
            <svg viewBox="{{ $mapLayout['viewBox'] }}" class="region-map" role="img" aria-label="Ҳудудлар харитаси">
                <g transform="translate({{ $mapLayout['mapTranslate'] }},0)">
                    @foreach($mapGeometry['cells'] as $cell)
                        @php
                            $cellCode = $cell['code'] ?? null;
                            $cellDistrict = $cellCode !== null ? $districts->get($cellCode) : null;
                            $cellColor = $cellCode !== null ? ($mapColors[$cellCode] ?? 'nd') : 'nd';
                            $cellSel = $cellCode !== null && (string) $cellCode === (string) $selectedCode;
                            $cellName = $cellDistrict?->name_full ?? $cell['name'];
                        @endphp
                        <g class="map-cell {{ $cellColor }} {{ $cellSel ? 'selected' : '' }}"
                           @if($cellCode !== null)
                               wire:click="selectDistrict('{{ $cellCode }}')"
                               x-on:keydown.enter="$wire.selectDistrict('{{ $cellCode }}')"
                               x-on:keydown.space.prevent="$wire.selectDistrict('{{ $cellCode }}')"
                               x-on:mouseenter="hovered=@js($cellName)"
                               x-on:mouseleave="hovered=null"
                               x-on:mousemove="x=$event.offsetX; y=$event.offsetY"
                               tabindex="0"
                           @endif>
                            <title>{{ $cellName }}</title>
                            <path class="map-fill" d="{{ $cell['path'] }}"/>
                        </g>
                    @endforeach
                </g>
                <g class="map-leaders">
                    @foreach($mapLayout['pills'] as $pill)
                        <path class="map-leader" d="{{ $pill['leader'] }}"/>
                        <circle class="map-anchor" cx="{{ $pill['dotX'] }}" cy="{{ $pill['dotY'] }}" r="2.2"/>
                    @endforeach
                </g>
                <g class="map-pills">
                    @foreach($mapLayout['pills'] as $pill)
                        @php $psel = (string) $pill['code'] === (string) $selectedCode; @endphp
                        <g class="map-pill {{ $pill['color'] }} {{ $psel ? 'selected' : '' }}"
                           wire:click="selectDistrict('{{ $pill['code'] }}')"
                           x-on:keydown.enter="$wire.selectDistrict('{{ $pill['code'] }}')"
                           x-on:keydown.space.prevent="$wire.selectDistrict('{{ $pill['code'] }}')"
                           tabindex="0">
                            <rect class="pill-bg" x="{{ $pill['x'] }}" y="{{ $pill['y'] - $pill['h'] / 2 }}"
                                  width="{{ $pill['w'] }}" height="{{ $pill['h'] }}" rx="9"/>
                            <text class="pill-name" x="{{ $pill['x'] + 9 }}" y="{{ $pill['y'] + 4 }}">{{ $pill['name'] }}</text>
                            <text class="pill-value" x="{{ $pill['x'] + $pill['w'] - 9 }}" y="{{ $pill['y'] + 4 }}"
                                  text-anchor="end">{{ $pill['value'] }}</text>
                        </g>
                    @endforeach
                </g>
            </svg>
            <div class="map-tooltip" x-show="hovered" x-cloak
                 :style="`left:${x + 14}px; top:${y + 14}px`">
                <strong x-text="hovered"></strong>
            </div>
        </div>
    </section>
```

- [ ] **Step 2: Run the page tests**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: PASS — the bare-GET test now sees `districts-mapstage` + `map-pill` and no `districts-ranklist`; peek tests still pass; `Андижон шаҳри`/`Асака тумани` present via cell `<title>`.

---

## Task 5: CSS — navy stage + pills; remove superseded rules

**Files:**
- Modify: `backend/public/css/portal.css`

- [ ] **Step 1: Add the new map styles** immediately before `.districts-grid {` (the `.districts-grid` rule itself is removed in Step 2):

```css
    /* ===== professional navy map ===== */
    .districts-mapstage {
      position: relative;
      background: #0e1c3f;
      border: 1px solid #16264d;
      border-radius: 18px;
      padding: 14px 14px 18px;
      margin-bottom: 14px;
      box-shadow: 0 10px 30px rgba(20, 40, 90, .18);
    }
    .mapstage-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin: 2px 6px 8px; }
    .mapstage-head strong { color: #eaf1ff; font-size: 15px; }
    .mapstage-head span { display: block; color: #8ea0c8; font-size: 11.5px; margin-top: 2px; }
    .map-legend { display: flex; gap: 14px; color: #cdd8f0; font-size: 11px; white-space: nowrap; }
    .map-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 5px; vertical-align: middle; }
    .map-legend i.ok { background: #37a34a; }
    .map-legend i.bad { background: #e0473a; }
    .map-legend i.nd { background: #6b748c; }
    .mapstage-canvas { position: relative; }
    .region-map { width: 100%; height: auto; display: block; }
    .map-cell { cursor: pointer; outline: none; }
    .map-cell .map-fill { stroke: #0e1c3f; stroke-width: 1.2; stroke-linejoin: round; transition: filter var(--motion); }
    .map-cell.ok .map-fill { fill: #37a34a; }
    .map-cell.bad .map-fill { fill: #e0473a; }
    .map-cell.nd .map-fill { fill: #6b748c; }
    .map-cell:hover .map-fill, .map-cell:focus .map-fill { filter: brightness(1.1); }
    .map-cell.selected .map-fill { stroke: #ffffff; stroke-width: 2; }
    .map-leader { fill: none; stroke: #5e74a8; stroke-width: 1; opacity: .7; }
    .map-anchor { fill: #cdd8f0; }
    .map-pill { cursor: pointer; outline: none; }
    .pill-bg { fill: #22386b; stroke: #33518f; stroke-width: 1; transition: fill var(--motion); }
    .map-pill:hover .pill-bg, .map-pill:focus .pill-bg { fill: #2c4683; }
    .map-pill.selected .pill-bg { fill: #2c4683; stroke: #7fa0e0; stroke-width: 1.6; }
    .pill-name { fill: #dfe8fb; font-size: 11px; font-weight: 600; font-family: var(--font-sans); pointer-events: none; }
    .pill-value { font-size: 11px; font-weight: 800; font-family: var(--font-sans); pointer-events: none; }
    .map-pill.ok .pill-value { fill: #7fe39a; }
    .map-pill.bad .pill-value { fill: #ff9a8f; }
    .map-pill.nd .pill-value { fill: #cdd8f0; }
```

(Keep the existing `.map-tooltip` rules — reused by the new stage.)

- [ ] **Step 2: Delete the superseded rule blocks**

Read the file and delete the full rule block for each selector below (markup removed in Task 4). Keep `.map-tooltip*`, `.chip*`, `.mini-button*`, `.module-*`, `.districts-header*`, `.kpi-stat*`, `.district-peek*`.

- Layout: `.districts-grid`, `.districts-side` (if present), `.districts-map` (the old map card), `.districts-map::before`, `.districts-map::after`, `.districts-map-head`, `.districts-map-head strong`, `.districts-map-head span`
- Old map internals: `.districts-map-canvas`, `.districts-map-canvas::before`, `.andijan-map`, `.map-label` (and `.map-label.is-city`, `.map-label.selected, .map-label.hover`, `.map-label.selected`), `.map-value`, `.map-dot`, `.map-dot.selected`
- Old `.map-cell .map-fill` blocks and `.map-cell:hover/:focus .map-fill`, `.map-cell.selected .map-fill` (both pre-existing definitions) — these are redefined in Step 1; remove the OLD ones so only the new navy/binary versions remain
- Legend: `.districts-map-legend`, `.legend-bound`, `.legend-bar`, `.legend-bar.reverse`
- Rank list: `.districts-ranklist`, `.ranklist-head`, `.ranklist-head strong`, `.ranklist-head span`, `.ranklist-rows`, `.rank-row`, `.rank-row + .rank-row`, `.rank-row:hover`, `.rank-row:focus-visible`, `.rank-row.selected`, `.rank-rk`, `.rank-dot`, `.rank-row.green/.amber/.red .rank-dot`, `.rank-nm`, `.rank-vbar`, `.rank-bar`, `.rank-bar i`, `.rank-row.green/.amber/.red .rank-bar i`, `.rank-vv`

Then update any `@media (max-width: …)` lines referencing removed selectors (`.districts-grid`, `.districts-map`, `.districts-map-canvas`, `.andijan-map`, `.districts-map-head*`, `.districts-map-legend`, `.legend-chip`); delete those declarations.

- [ ] **Step 3: Verify selectors**

Run: `cd backend; Select-String -Path public/css/portal.css -Pattern 'districts-ranklist|rank-row|\.andijan-map|districts-map-canvas|\.legend-bar|\.map-label|\.map-dot|\.map-value|districts-grid' | Select-Object -First 12`
Expected: NO output.
Run: `cd backend; Select-String -Path public/css/portal.css -Pattern 'districts-mapstage|map-pill|map-leader|pill-bg|map-legend' | Select-Object -First 8`
Expected: matches present.

- [ ] **Step 4: Sanity test**

Run: `cd backend; php artisan test --filter=DistrictsPage`
Expected: PASS (CSS doesn't change assertions).

---

## Task 6: Remove the superseded palette; verify; commit

**Files:**
- Modify: `backend/app/Livewire/DistrictsPage.php`
- Delete: `backend/app/Support/MapColorScale.php`, `backend/tests/Unit/Support/MapColorScaleTest.php`

- [ ] **Step 1: Remove `colorScale()` and `colorRange()` computed**

Delete both methods from `DistrictsPage.php`:

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

- [ ] **Step 2: Drop them from `render()`**

Replace:

```php
    public function render()
    {
        return view('livewire.districts-page', [
            'mapGeometry' => $this->mapGeometry,
            'mapColors'   => $this->mapColors,
            'mapLayout'   => $this->mapLayout,
            'colorScale'  => $this->colorScale,
            'colorRange'  => $this->colorRange,
        ]);
    }
```

with:

```php
    public function render()
    {
        return view('livewire.districts-page', [
            'mapGeometry' => $this->mapGeometry,
            'mapColors'   => $this->mapColors,
            'mapLayout'   => $this->mapLayout,
        ]);
    }
```

- [ ] **Step 3: Delete the dead palette class + its test**

```bash
rm backend/app/Support/MapColorScale.php
rm backend/tests/Unit/Support/MapColorScaleTest.php
```

- [ ] **Step 4: Confirm no dangling references**

Run: `cd backend; Select-String -Path app,resources -Pattern 'MapColorScale|colorScale|colorRange' -Recurse | Select-Object -First 5`
Expected: NO output.

- [ ] **Step 5: Run the targeted suites (alone — shared DB)**

Run: `cd backend; php artisan test --filter=MapLabelLayout`  → expected PASS (7).
Run: `cd backend; php artisan test --filter=Districts`  → expected PASS (DistrictsPageTest + DistrictsPageSelectionTest + schema tests; no MapColorScale).

- [ ] **Step 6: Headless screenshot check**

Dev server on 8000 (start if needed: `cd backend; php artisan serve --host=127.0.0.1 --port=8000`). Capture:

```
& "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" --headless=new --disable-gpu --hide-scrollbars --no-first-run --user-data-dir="C:/Users/y.utepbergenov/AppData/Local/Temp/edge-promap" --window-size=1500,1050 --screenshot="C:/Users/y.utepbergenov/Desktop/hududlar-monitoringi-portali/.superpowers/verify-promap.png" "http://127.0.0.1:8000/districts"
```

Confirm: navy full-width stage, binary green/red districts with dark separators, name+value pills around both sides with leader lines, legend, no rank list. Load `…/districts?district=<code>` and confirm the peek opens and the selected cell/pill highlights.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Support/MapLabelLayout.php backend/tests/Unit/Support/MapLabelLayoutTest.php backend/app/Livewire/DistrictsPage.php backend/resources/views/livewire/districts-page.blade.php backend/public/css/portal.css backend/tests/Feature/Http/DistrictsPageTest.php
git rm backend/app/Support/MapColorScale.php backend/tests/Unit/Support/MapColorScaleTest.php
git commit -m "feat(districts): professional navy map with perimeter pill labels"
```

End the commit message with the project's `Co-Authored-By` trailer.

---

## Self-review notes

- **Spec coverage:** MapLabelLayout helper + algorithm + unit tests (T1) · binary color from status (T3 `mapColors`) · pill labels/value/name (T3 `mapLayout`, T4) · full-width navy stage + remove rank list (T4) · CSS navy/binary/pill/leader/legend + removals (T5) · remove MapColorScale/colorScale/colorRange (T6) · keep header + peek (untouched) · feature-test update (T2) · MapColorScaleTest deletion (T6). All spec sections map to a task.
- **Type consistency:** `MapLabelLayout::build($geometry,$labels,$gutter=230)` returns `{viewBox,mapTranslate,pills[{code,side,x,y,w,h,name,value,color,leader,dotX,dotY}]}` — produced in T1, consumed in T4; `mapColors` returns `code=>'ok'|'bad'|'nd'` used by both `mapLayout` labels and the cell fill class in T4. Class names (`districts-mapstage`, `map-pill`, `pill-bg`, `pill-name`, `pill-value`, `map-leader`, `map-anchor`, `map-cell.ok/.bad/.nd`) defined in T4 markup, styled in T5, asserted in T2.
- **Conceptual integrity:** binary color = standing; value in pills + hero + peek = plan-vs-fact; map/pill → peek → Профил/Журнал = drill chain. Pills live in the SVG viewBox so the whole map scales as one unit (responsive).
- **Sequencing:** removals (T6) come after the new view/CSS are in place so each task's breakage is bounded; the single commit lands everything green.
