# Region-specific district map

**Date:** 2026-05-14
**Status:** Approved (pending user spec review)
**Scope:** Replace hardcoded `AndijanMapGeometry` SVG paths on the districts page with runtime geometry built from `districts.json` (GeoJSON FeatureCollection at the repo root). New `RegionMapGeometry` helper projects per-region polygons into a 600×500 SVG viewBox. Districts page renders the correct map for whatever region the session holds.

---

## 1. Goal

Open `/districts` after switching the session region from Andijan to e.g. Қашқадарё, and the SVG map currently still draws Andijan's hex-cell shapes. The `districts-page.blade.php` view imports `App\Support\AndijanMapGeometry::CELLS` and `::VIEWBOX` directly, which are 16 hand-drawn paths bound to the Andijan SOATO codes.

After this work, the map block on `/districts` renders polygons for the currently-selected region (any of 14). Andijan still renders. Cells stay clickable. Aside + table are unchanged.

## 2. Non-goals

- No Leaflet, Mapbox, or tile-based map engine.
- No DB-persisted geometry column (recompute per request; memoize in static property).
- No region zoom/pan controls.
- No new CSS apart from optional class rename.
- No replacement of hex-style fill with realistic shading — visual style stays the existing flat-fill + status color.
- No removal of `AndijanMapGeometry` (kept as static fallback / reference for now; safe to delete in a follow-up if no consumers).

## 3. Strategy

Add a static helper `App\Support\RegionMapGeometry::forRegion(int $regionCode): array` that:

1. Loads `districts.json` once per request (`?? static $data = json_decode(...)`).
2. Filters features by `properties.ADM1_PCODE`. Pcode-to-SOATO mapping: `UZ<xx>` ↔ SOATO `int("17{$xx}")`. E.g. `UZ03` ↔ 1703.
3. Computes a bounding box across all features of the region.
4. Builds an equirectangular linear projection that fits the region into a 600×500 viewBox with 5% padding, preserving aspect ratio.
5. For each feature: generates an SVG path string from the MultiPolygon outer rings, computes a centroid for the label, and resolves the cell's SOATO district code from `ADM2_PCODE`.

`DistrictsPage` Livewire adds one `#[Computed] mapGeometry()` returning the helper result. Blade replaces three references.

`districts-page.blade.php` currently looks up `$cellDistrict = $districts->firstWhere('name_full', $cell['name'])` — this becomes `$cellDistrict = $districts->get($cell['code'])` (the existing `$districts` collection is already keyed by `code`).

## 4. Code

### 4.1 `app/Support/RegionMapGeometry.php` (new)

```php
<?php

namespace App\Support;

class RegionMapGeometry
{
    private const VIEW_W = 600;
    private const VIEW_H = 500;
    private const PAD    = 0.05;

    private static ?array $cache = null;

    public static function forRegion(int $regionCode): array
    {
        $features = self::featuresForRegion($regionCode);
        if (empty($features)) {
            return ['viewBox' => '0 0 600 500', 'cells' => []];
        }

        [$lonMin, $lonMax, $latMin, $latMax] = self::bbox($features);
        $pad = self::PAD;
        $spanLon = ($lonMax - $lonMin) * (1 + 2 * $pad);
        $spanLat = ($latMax - $latMin) * (1 + 2 * $pad);
        $lonMin -= ($lonMax - $lonMin) * $pad;
        $latMin -= ($latMax - $latMin) * $pad;

        $scale = min(self::VIEW_W / $spanLon, self::VIEW_H / $spanLat);
        $offsetX = (self::VIEW_W - $spanLon * $scale) / 2;
        $offsetY = (self::VIEW_H - $spanLat * $scale) / 2;

        $project = function (float $lon, float $lat) use ($lonMin, $latMin, $scale, $offsetX, $offsetY) {
            return [
                $offsetX + ($lon - $lonMin) * $scale,
                self::VIEW_H - $offsetY - ($lat - $latMin) * $scale,
            ];
        };

        $cells = [];
        foreach ($features as $f) {
            $cells[] = self::buildCell($f, $project);
        }

        return [
            'viewBox' => '0 0 ' . self::VIEW_W . ' ' . self::VIEW_H,
            'cells'   => $cells,
        ];
    }

    private static function data(): array
    {
        if (self::$cache !== null) return self::$cache;
        $path = base_path('../districts.json');
        if (! is_file($path)) $path = base_path('districts.json');
        self::$cache = json_decode((string) file_get_contents($path), true) ?: ['features' => []];
        return self::$cache;
    }

    private static function regionPcode(int $regionCode): string
    {
        return 'UZ' . substr((string) $regionCode, -2);
    }

    private static function soatoFromAdm2(string $pcode): ?int
    {
        if (! str_starts_with($pcode, 'UZ')) return null;
        $tail = substr($pcode, 2);
        if ($tail === '' || ! ctype_digit($tail)) return null;
        return (int) ('17' . $tail);
    }

    /** @return list<array> */
    private static function featuresForRegion(int $regionCode): array
    {
        $target = self::regionPcode($regionCode);
        $out = [];
        foreach (self::data()['features'] ?? [] as $f) {
            if (($f['properties']['ADM1_PCODE'] ?? null) === $target) {
                $out[] = $f;
            }
        }
        return $out;
    }

    /** @return array{0:float,1:float,2:float,3:float} [lonMin, lonMax, latMin, latMax] */
    private static function bbox(array $features): array
    {
        $lonMin = PHP_FLOAT_MAX; $lonMax = -PHP_FLOAT_MAX;
        $latMin = PHP_FLOAT_MAX; $latMax = -PHP_FLOAT_MAX;
        foreach ($features as $f) {
            foreach ($f['geometry']['coordinates'] ?? [] as $polygon) {
                foreach ($polygon as $ring) {
                    foreach ($ring as [$lon, $lat]) {
                        if ($lon < $lonMin) $lonMin = $lon;
                        if ($lon > $lonMax) $lonMax = $lon;
                        if ($lat < $latMin) $latMin = $lat;
                        if ($lat > $latMax) $latMax = $lat;
                    }
                }
            }
        }
        return [$lonMin, $lonMax, $latMin, $latMax];
    }

    private static function buildCell(array $feature, callable $project): array
    {
        $props = $feature['properties'] ?? [];
        $name  = $props['Name_UZ'] ?? $props['ADM2_UZ'] ?? ($props['Name'] ?? '');
        $code  = self::soatoFromAdm2((string) ($props['ADM2_PCODE'] ?? ''));

        $segments = [];
        $sumX = 0.0; $sumY = 0.0; $count = 0;
        foreach ($feature['geometry']['coordinates'] ?? [] as $polygon) {
            foreach ($polygon as $ring) {
                $parts = [];
                foreach ($ring as $i => [$lon, $lat]) {
                    [$x, $y] = $project($lon, $lat);
                    $parts[] = ($i === 0 ? 'M' : 'L') . ' ' . number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '');
                    $sumX += $x; $sumY += $y; $count++;
                }
                $segments[] = implode(' ', $parts) . ' Z';
            }
        }

        $cx = $count > 0 ? $sumX / $count : self::VIEW_W / 2;
        $cy = $count > 0 ? $sumY / $count : self::VIEW_H / 2;

        return [
            'code' => $code,
            'name' => trim($name),
            'path' => implode(' ', $segments),
            'cx'   => $cx,
            'cy'   => $cy,
        ];
    }
}
```

### 4.2 `app/Livewire/DistrictsPage.php`

Add a `#[Computed]` method:

```php
#[Computed]
public function mapGeometry(): array
{
    return \App\Support\RegionMapGeometry::forRegion($this->regionCode);
}
```

Pass through in `render()`:

```php
'mapGeometry' => $this->mapGeometry,
```

### 4.3 `resources/views/livewire/districts-page.blade.php`

Three changes around lines 152, 175, 196:

1. Drop `use App\Support\AndijanMapGeometry;` at top.

2. Replace `<svg viewBox="{{ AndijanMapGeometry::VIEWBOX }}" …>` with `<svg viewBox="{{ $mapGeometry['viewBox'] }}" …>`.

3. Replace `@foreach(AndijanMapGeometry::CELLS as $cell)` (twice — once for paths, once for labels) with `@foreach($mapGeometry['cells'] as $cell)`.

4. Inside the loop, change district lookup from name to code:

   Before:
   ```php
   $cellDistrict = $districts->firstWhere('name_full', $cell['name']);
   $cellCode = $cellDistrict?->code ?? '';
   ```

   After:
   ```php
   $cellCode = $cell['code'] ?? '';
   $cellDistrict = $cellCode !== '' ? $districts->get($cellCode) : null;
   ```

   `$districts` collection is already keyed by `code` (see `DistrictsPage::districts()` computed property).

5. The `is-city` class is derived from `str_ends_with($cell['name'], ' шаҳри')`. Geojson uses `Name_UZ` like `Қарши ш.` (already canonicalized). Update the helper to check for `' ш.'` suffix:

   ```php
   $cellCity = str_ends_with($cell['name'], ' ш.') ? 'is-city' : '';
   ```

## 5. Tests

`tests/Unit/Support/RegionMapGeometryTest.php` (new):

```php
<?php

use App\Support\RegionMapGeometry;

test('Andijan map has at least 14 cells with non-empty paths', function () {
    $geo = RegionMapGeometry::forRegion(1703);
    expect($geo['viewBox'])->toBe('0 0 600 500');
    expect(count($geo['cells']))->toBeGreaterThanOrEqual(14);
    foreach ($geo['cells'] as $cell) {
        expect($cell['path'])->not->toBe('')->toMatch('/^M [0-9.]+ [0-9.]+/');
    }
});

test('Andijan cell codes all start with 1703', function () {
    $geo = RegionMapGeometry::forRegion(1703);
    foreach ($geo['cells'] as $cell) {
        if ($cell['code'] !== null) {
            expect((string) $cell['code'])->toStartWith('1703');
        }
    }
});

test('Kashkadarya returns multiple cells with 1710 prefix', function () {
    $geo = RegionMapGeometry::forRegion(1710);
    expect(count($geo['cells']))->toBeGreaterThanOrEqual(10);
    foreach ($geo['cells'] as $cell) {
        if ($cell['code'] !== null) {
            expect((string) $cell['code'])->toStartWith('1710');
        }
    }
});

test('Every region returns at least one cell', function () {
    $codes = [1735, 1703, 1706, 1708, 1710, 1712, 1714, 1718, 1722, 1724, 1726, 1727, 1730, 1733];
    foreach ($codes as $code) {
        $geo = RegionMapGeometry::forRegion($code);
        expect(count($geo['cells']))->toBeGreaterThan(0, "region {$code} has zero cells");
    }
});

test('Unknown region returns empty cells', function () {
    $geo = RegionMapGeometry::forRegion(9999);
    expect($geo['cells'])->toBe([]);
});
```

`tests/Feature/Livewire/DistrictsPageMapTest.php` (new):

```php
<?php

use App\Livewire\DistrictsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->artisan('import:all-regions', ['year' => 2026]);
});

test('districts page passes mapGeometry to view with region-specific cells', function () {
    Session::put('region_code', 1710);
    Livewire::test(DistrictsPage::class)
        ->assertViewHas('mapGeometry', function ($geo) {
            return is_array($geo)
                && count($geo['cells']) > 0
                && str_starts_with((string) $geo['cells'][0]['code'], '1710');
        });
});
```

The feature test runs `import:all-regions` to populate facts (DistrictsPage's `kpiOptions` query requires fact data). If the data folder isn't present on the test host, this test should `markTestSkipped`.

## 6. Files

| File | Action |
|---|---|
| `backend/app/Support/RegionMapGeometry.php` | new |
| `backend/app/Livewire/DistrictsPage.php` | add `mapGeometry()` computed + pass to view |
| `backend/resources/views/livewire/districts-page.blade.php` | swap geometry source + lookup-by-code + `is-city` check |
| `backend/tests/Unit/Support/RegionMapGeometryTest.php` | new |
| `backend/tests/Feature/Livewire/DistrictsPageMapTest.php` | new |

`AndijanMapGeometry.php` stays untouched (kept as legacy fallback for now; remove in a future cleanup pass if no other consumer exists).

## 7. Operator smoke

After implementation:

```bash
cd backend && php -d memory_limit=2G artisan migrate:fresh --seed
php -d memory_limit=2G artisan import:all-regions 2026
php artisan serve --port=8765
```

Visit `/districts`:
1. Default region (Andijan): map shows Andijan shape with all 14 districts + 2 cities outlined.
2. Switch region to Қашқадарё via sidebar dropdown. Page reloads. Map redraws with Kashkadarya shape (Karshi + 14 districts).
3. Switch to Тошкент шаҳри. Map redraws with Tashkent city districts (much smaller area, 12 inner districts).
4. Click a cell on each map → district selected in table + aside.
5. Hover label visible for each cell.

## 8. Risks

- **Performance:** `districts.json` is ~6 MB; first `json_decode` takes ~50 ms. Mitigation: static memoization within the request lifecycle. Subsequent calls reuse cached data. Future: pre-compute SVG paths into a sidecar PHP file at build time if needed.
- **Bounding box edge cases:** Tashkent city (1726) is geographically small but renders into the same 600×500 viewBox. Mitigation: relative scaling keeps it filling the viewport; padding (5%) avoids edge-touch. Verify in smoke.
- **Pcode↔SOATO mapping correctness:** if any region's GeoJSON `ADM1_PCODE` doesn't follow `UZ<last-2-of-SOATO>` pattern, that region renders empty. Mitigation: test #4 iterates all 14 regions, asserts ≥1 cell each. If a region fails, document the actual pcode in the helper (table override).
- **Missing districts in DB:** GeoJSON `ADM2_PCODE` may produce a SOATO code not seeded in our DB. Mitigation: blade lookup uses `$districts->get($code)` which returns `null` → cell renders with `grey` status, no name link. Acceptable.
- **Missing districts in GeoJSON:** DB district has no corresponding GeoJSON feature → district missing from map but present in table. Mitigation: acceptable for now.
- **Region name variants in GeoJSON:** GeoJSON `Name_UZ` may use abbreviated forms (`Олтинкўл тум.` not `Олтинкўл тумани`). Mitigation: we use the DB's `name_full` for the chip/label, not the GeoJSON name — GeoJSON name is only fallback if district code lookup fails.
- **Path string size:** large region (Karakalpakstan) with many vertices may produce 100KB+ SVG. Page weight grows. Mitigation: acceptable for now; future could simplify polygons via Douglas-Peucker.
