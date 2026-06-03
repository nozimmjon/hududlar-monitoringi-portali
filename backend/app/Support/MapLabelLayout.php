<?php

namespace App\Support;

class MapLabelLayout
{
    private const RAD_X  = 40.0;  // pill-ring radius beyond the map, x
    private const RAD_Y  = 40.0;  // pill-ring radius beyond the map, y
    private const INSET  = 8.0;
    private const PAD_X  = 8.0;
    private const GAP    = 7.0;
    private const NAME_CW = 6.4;
    private const VAL_CW  = 6.6;
    private const PILL_H = 20.0;

    /**
     * Place name+value pills on an ellipse around the map (evenly spaced by angle,
     * ordered geographically) with curved leader lines to each district. The frame
     * is cropped to the map bbox plus a ring band, so the container stays compact.
     *
     * @param  array{viewBox?:string,cells?:array}  $geometry  RegionMapGeometry output.
     * @param  array<int|string,array{name:string,value:string,color:string}>  $labels  keyed by cell code.
     * @return array{viewBox:string,mapTransform:string,mapOffsetX:float,mapOffsetY:float,pills:array<int,array<string,mixed>>}
     */
    public static function build(array $geometry, array $labels): array
    {
        [$vw, $vh] = self::viewDims($geometry['viewBox'] ?? '0 0 600 500');

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
        $h = self::PILL_H;

        // Frame: map bbox + a ring band wide enough for the widest pill.
        $gx = self::RAD_X + ceil($maxW / 2) + 8.0;
        $gy = self::RAD_Y + $h + 6.0;
        $outerW = $mapW + 2 * $gx;
        $outerH = $mapH + 2 * $gy;
        $tx = $gx - $minX;
        $ty = $gy - $minY;
        $ocx = $outerW / 2;
        $ocy = $outerH / 2;
        $cxc = $minX + $mapW / 2;
        $cyc = $minY + $mapH / 2;
        $aRad = $mapW / 2 + self::RAD_X;
        $bRad = $mapH / 2 + self::RAD_Y;

        // Order districts around the ring by their true angle, then place at even
        // angular steps so the labels form a clean oval ring.
        usort($cells, fn ($a, $b) =>
            atan2($a['cy'] - $cyc, $a['cx'] - $cxc) <=> atan2($b['cy'] - $cyc, $b['cx'] - $cxc));
        $n = count($cells);

        $pills = [];
        foreach ($cells as $i => $cell) {
            $code = $cell['code'];
            $lbl  = $labels[$code];
            $w    = self::pillWidth($lbl);
            $th   = -M_PI / 2 + $i * 2 * M_PI / $n;     // start at top, go clockwise
            $ex   = $ocx + $aRad * cos($th);
            $ey   = $ocy + $bRad * sin($th);
            $topbot = abs(sin($th)) > 0.7;
            $right  = cos($th) >= 0;

            $px = $topbot ? $ex - $w / 2 : ($right ? $ex : $ex - $w);
            $px = min($outerW - 6 - $w, max(6.0, $px));
            $py = $ey;

            $dotX = $cell['cx'] + $tx;
            $dotY = $cell['cy'] + $ty;

            if ($topbot) {
                $sx = $px + $w / 2;
                $sy = sin($th) > 0 ? $py - $h / 2 : $py + $h / 2;
                $leader = sprintf('M %.1f %.1f C %.1f %.1f %.1f %.1f %.1f %.1f', $sx, $sy, $sx, ($sy + $dotY) / 2, $dotX, ($sy + $dotY) / 2, $dotX, $dotY);
            } else {
                $sx = $right ? $px : $px + $w;
                $sy = $py;
                $leader = sprintf('M %.1f %.1f C %.1f %.1f %.1f %.1f %.1f %.1f', $sx, $sy, ($sx + $dotX) / 2, $sy, ($sx + $dotX) / 2, $dotY, $dotX, $dotY);
            }

            $pills[] = [
                'code'   => $code,
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
