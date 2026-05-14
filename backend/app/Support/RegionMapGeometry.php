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
            return ['viewBox' => '0 0 ' . self::VIEW_W . ' ' . self::VIEW_H, 'cells' => []];
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
