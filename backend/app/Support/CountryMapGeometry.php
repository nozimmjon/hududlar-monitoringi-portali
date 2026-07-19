<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Whole-country map for the entry page: every district polygon from
 * districts.json merged per region into one compound SVG path, projected
 * into a 1000×640 viewBox (equirectangular with cos(mid-lat) correction).
 * Result is cached — the source file changes only when the geo reference
 * data is replaced.
 */
class CountryMapGeometry
{
    private const VIEW_W = 1000;
    private const VIEW_H = 640;
    private const PAD    = 0.03;
    /** Points closer than this (px) to the previous kept point are dropped. */
    private const THIN   = 0.7;

    /** @return list<array{code:int,name:string,d:string,cx:float,cy:float}> */
    public static function regions(): array
    {
        return Cache::rememberForever('country_map_geometry_v1', fn () => self::build());
    }

    /** @return list<array{code:int,name:string,d:string,cx:float,cy:float}> */
    private static function build(): array
    {
        $path = base_path('../districts.json');
        if (! is_file($path)) {
            $path = base_path('districts.json');
        }
        if (! is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true) ?: [];
        $features = $data['features'] ?? [];
        if ($features === []) {
            return [];
        }

        $lonMin = INF; $lonMax = -INF; $latMin = INF; $latMax = -INF;
        foreach ($features as $f) {
            foreach ($f['geometry']['coordinates'] ?? [] as $polygon) {
                foreach ($polygon as $ring) {
                    foreach ($ring as [$lon, $lat]) {
                        $lonMin = min($lonMin, $lon); $lonMax = max($lonMax, $lon);
                        $latMin = min($latMin, $lat); $latMax = max($latMax, $lat);
                    }
                }
            }
        }

        $k = cos(deg2rad(($latMin + $latMax) / 2));
        $spanLon = ($lonMax - $lonMin) * $k;
        $spanLat = $latMax - $latMin;
        $scale = min(
            self::VIEW_W * (1 - 2 * self::PAD) / $spanLon,
            self::VIEW_H * (1 - 2 * self::PAD) / $spanLat
        );
        $ox = (self::VIEW_W - $spanLon * $scale) / 2;
        $oy = (self::VIEW_H - $spanLat * $scale) / 2;

        $regions = [];
        foreach ($features as $f) {
            $props = $f['properties'] ?? [];
            $pcode = (string) ($props['ADM1_PCODE'] ?? '');
            if (! str_starts_with($pcode, 'UZ')) {
                continue;
            }
            $code = (int) ('17' . substr($pcode, 2));
            $regions[$code] ??= [
                'code' => $code,
                'name' => trim((string) ($props['ADM1_UZ'] ?? '')),
                'paths' => [], 'sx' => 0.0, 'sy' => 0.0, 'n' => 0,
            ];
            foreach ($f['geometry']['coordinates'] ?? [] as $polygon) {
                foreach ($polygon as $ring) {
                    $parts = [];
                    $lastX = null; $lastY = null;
                    $count = count($ring);
                    foreach ($ring as $i => [$lon, $lat]) {
                        $x = round($ox + ($lon - $lonMin) * $k * $scale, 1);
                        $y = round(self::VIEW_H - $oy - ($lat - $latMin) * $scale, 1);
                        if ($lastX !== null
                            && abs($x - $lastX) < self::THIN && abs($y - $lastY) < self::THIN
                            && $i !== $count - 1) {
                            continue;
                        }
                        $parts[] = ($parts === [] ? 'M' : 'L') . $x . ' ' . $y;
                        $regions[$code]['sx'] += $x;
                        $regions[$code]['sy'] += $y;
                        $regions[$code]['n']++;
                        $lastX = $x; $lastY = $y;
                    }
                    if (count($parts) > 2) {
                        $regions[$code]['paths'][] = implode(' ', $parts) . 'Z';
                    }
                }
            }
        }

        $out = [];
        foreach ($regions as $r) {
            $out[] = [
                'code' => $r['code'],
                'name' => $r['name'],
                'd'    => implode(' ', $r['paths']),
                'cx'   => round($r['sx'] / max(1, $r['n']), 1),
                'cy'   => round($r['sy'] / max(1, $r['n']), 1),
            ];
        }
        usort($out, fn ($a, $b) => $a['code'] <=> $b['code']);

        return $out;
    }
}
