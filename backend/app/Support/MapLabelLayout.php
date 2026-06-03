<?php

namespace App\Support;

class MapLabelLayout
{
    private const V_BAND = 34.0;  // top/bottom pill-row band height
    private const INSET  = 8.0;   // gap between a pill and the map edge
    private const PAD_X  = 8.0;
    private const GAP    = 7.0;
    private const NAME_CW = 6.4;
    private const VAL_CW  = 6.6;
    private const PILL_H = 20.0;

    /**
     * Place name+value pills around all four edges of the map (top/right/bottom/left)
     * with curved leader lines. The frame is cropped to the map's bounding box plus a
     * thin band on each side, so the container stays short.
     *
     * @param  array{viewBox?:string,cells?:array}  $geometry  RegionMapGeometry output.
     * @param  array<int|string,array{name:string,value:string,color:string}>  $labels  keyed by cell code.
     * @return array{viewBox:string,mapTransform:string,mapOffsetX:float,mapOffsetY:float,pills:array<int,array<string,mixed>>}
     */
    public static function build(array $geometry, array $labels, int $sideGutterMin = 24): array
    {
        [$vw, $vh] = self::viewDims($geometry['viewBox'] ?? '0 0 600 500');

        // Labeled cells + map bounding box + widest pill.
        $cells = [];
        $maxW = 0.0;
        $minX = INF; $maxX = -INF; $minY = INF; $maxY = -INF;
        foreach ($geometry['cells'] ?? [] as $cell) {
            $code = $cell['code'] ?? null;
            if ($code === null || ! isset($labels[$code])) {
                continue;
            }
            $cells[] = $cell;
            $maxW = max($maxW, self::pillWidth($labels[$code]));
            [$x0, $x1, $y0, $y1] = self::pathBbox($cell['path'] ?? '');
            if ($x0 === null) {
                $x0 = $x1 = (float) $cell['cx'];
                $y0 = $y1 = (float) $cell['cy'];
            }
            $minX = min($minX, $x0); $maxX = max($maxX, $x1);
            $minY = min($minY, $y0); $maxY = max($maxY, $y1);
        }

        if (empty($cells)) {
            return [
                'viewBox'      => sprintf('0 0 %d %d', (int) $vw, (int) $vh),
                'mapTransform' => 'translate(0,0)',
                'mapOffsetX'   => 0.0,
                'mapOffsetY'   => 0.0,
                'pills'        => [],
            ];
        }

        $mapW = max(1.0, $maxX - $minX);
        $mapH = max(1.0, $maxY - $minY);
        $vBand = self::V_BAND;
        $inset = self::INSET;

        $sg = max((float) $sideGutterMin, ceil($maxW) + $inset + 8.0);
        $outerW = $mapW + 2 * $sg;
        $outerH = $mapH + 2 * $vBand;
        $tx = $sg - $minX;           // map group translate
        $ty = $vBand - $minY;
        $cxc = $minX + $mapW / 2;    // bbox centre, for angle bucketing
        $cyc = $minY + $mapH / 2;

        $buckets = ['top' => [], 'right' => [], 'bottom' => [], 'left' => []];
        foreach ($cells as $cell) {
            $a = atan2($cell['cy'] - $cyc, $cell['cx'] - $cxc) * 180 / M_PI;
            if ($a >= -45 && $a < 45)       $s = 'right';
            elseif ($a >= 45 && $a < 135)   $s = 'bottom';
            elseif ($a >= -135 && $a < -45) $s = 'top';
            else                            $s = 'left';
            $buckets[$s][] = $cell;
        }

        $pills = [];
        $place = function (array $cell, string $side, float $px, float $py)
            use (&$pills, $labels, $tx, $ty) {
            $code = $cell['code'];
            $lbl  = $labels[$code];
            $w    = self::pillWidth($lbl);
            $h    = self::PILL_H;
            $dotX = $cell['cx'] + $tx;
            $dotY = $cell['cy'] + $ty;

            // Leader start = the pill edge facing the map.
            if ($side === 'top')         { $sx = $px + $w / 2; $sy = $py + $h / 2; $vert = true; }
            elseif ($side === 'bottom')  { $sx = $px + $w / 2; $sy = $py - $h / 2; $vert = true; }
            elseif ($side === 'left')    { $sx = $px + $w;     $sy = $py;          $vert = false; }
            else                         { $sx = $px;          $sy = $py;          $vert = false; }

            $leader = $vert
                ? sprintf('M %.1f %.1f C %.1f %.1f %.1f %.1f %.1f %.1f', $sx, $sy, $sx, ($sy + $dotY) / 2, $dotX, ($sy + $dotY) / 2, $dotX, $dotY)
                : sprintf('M %.1f %.1f C %.1f %.1f %.1f %.1f %.1f %.1f', $sx, $sy, ($sx + $dotX) / 2, $sy, ($sx + $dotX) / 2, $dotY, $dotX, $dotY);

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
        };

        // Top / bottom rows: spread by cx across the width.
        foreach (['top', 'bottom'] as $side) {
            $list = $buckets[$side];
            usort($list, fn ($a, $b) => $a['cx'] <=> $b['cx']);
            $n = count($list);
            foreach ($list as $i => $cell) {
                $w    = self::pillWidth($labels[$cell['code']]);
                $slot = $n > 0 ? $outerW / $n : $outerW;
                $px   = min($outerW - 6 - $w, max(6.0, $slot * $i + ($slot - $w) / 2));
                $py   = $side === 'top' ? $vBand / 2 : $outerH - $vBand / 2;
                $place($cell, $side, $px, $py);
            }
        }

        // Left / right columns: spread by cy down the map height.
        foreach (['left', 'right'] as $side) {
            $list = $buckets[$side];
            usort($list, fn ($a, $b) => $a['cy'] <=> $b['cy']);
            $n = count($list);
            $t = $vBand + 8; $b = $outerH - $vBand - 8;
            $step = $n > 1 ? ($b - $t) / ($n - 1) : 0.0;
            foreach ($list as $i => $cell) {
                $w  = self::pillWidth($labels[$cell['code']]);
                $py = $n > 1 ? $t + $i * $step : $outerH / 2;
                $px = $side === 'left' ? $sg - $inset - $w : $mapW + $sg + $inset;
                $place($cell, $side, $px, $py);
            }
        }

        return [
            'viewBox'      => sprintf('0 0 %d %d', (int) round($outerW), (int) round($outerH)),
            'mapTransform' => sprintf('translate(%.1f,%.1f)', $tx, $ty),
            'mapOffsetX'   => round($tx, 1),
            'mapOffsetY'   => round($ty, 1),
            'pills'        => $pills,
        ];
    }

    private static function pillWidth(array $lbl): float
    {
        return 2 * self::PAD_X + mb_strlen($lbl['name']) * self::NAME_CW + self::GAP + mb_strlen($lbl['value']) * self::VAL_CW;
    }

    /** @return array{0:?float,1:?float,2:?float,3:?float} [minX,maxX,minY,maxY] */
    private static function pathBbox(string $path): array
    {
        preg_match_all('/-?\d+(?:\.\d+)?/', $path, $m);
        $ns = $m[0];
        $xs = []; $ys = [];
        for ($i = 0; $i + 1 < count($ns); $i += 2) {
            $xs[] = (float) $ns[$i];
            $ys[] = (float) $ns[$i + 1];
        }
        if (! $xs) {
            return [null, null, null, null];
        }
        return [min($xs), max($xs), min($ys), max($ys)];
    }

    /** @return array{0:float,1:float} */
    private static function viewDims(string $viewBox): array
    {
        $p = preg_split('/\s+/', trim($viewBox));
        return [isset($p[2]) ? (float) $p[2] : 600.0, isset($p[3]) ? (float) $p[3] : 500.0];
    }
}
