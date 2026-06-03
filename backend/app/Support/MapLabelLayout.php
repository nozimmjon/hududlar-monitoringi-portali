<?php

namespace App\Support;

class MapLabelLayout
{
    private const RAD_X  = 50.0;  // pill-ring radius beyond the map, x
    private const RAD_Y  = 38.0;  // pill-ring radius beyond the map, y
    private const PAD_X  = 8.0;
    private const GAP    = 7.0;
    private const NAME_CW = 6.4;
    private const VAL_CW  = 6.6;
    private const PILL_H = 20.0;
    private const MIN_GAP = 7.0;  // min space between adjacent pills

    /**
     * Place name+value pills on an ellipse around the map, ordered by geographic
     * angle, then iteratively repel overlapping pills apart along the ring so none
     * collide. Curved leaders connect each pill to its district. The frame crops to
     * the map bbox plus the ring band.
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

        $gx = self::RAD_X + ceil($maxW / 2) + 10.0;
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

        // Order around the ring by true angle, seed at even angular steps.
        usort($cells, fn ($a, $b) =>
            atan2($a['cy'] - $cyc, $a['cx'] - $cxc) <=> atan2($b['cy'] - $cyc, $b['cx'] - $cxc));
        $n = count($cells);

        $items = [];
        foreach ($cells as $i => $cell) {
            $th = -M_PI / 2 + $i * 2 * M_PI / $n;
            $items[] = [
                'cell' => $cell,
                'w'    => self::pillWidth($labels[$cell['code']]),
                'th'   => $th,
                'x'    => $ocx + $aRad * cos($th),
                'y'    => $ocy + $bRad * sin($th),
            ];
        }

        // Repel overlapping pills apart along the ring (rotate + reproject).
        for ($iter = 0; $iter < 300; $iter++) {
            $moved = false;
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $ox = ($items[$i]['w'] + $items[$j]['w']) / 2 + self::MIN_GAP - abs($items[$i]['x'] - $items[$j]['x']);
                    $oy = $h + self::MIN_GAP - abs($items[$i]['y'] - $items[$j]['y']);
                    if ($ox > 0 && $oy > 0) {
                        $d = $items[$j]['th'] - $items[$i]['th'];
                        if ($d > M_PI)  $d -= 2 * M_PI;
                        if ($d < -M_PI) $d += 2 * M_PI;
                        $s = $d >= 0 ? 1 : -1;
                        $items[$i]['th'] -= 0.015 * $s;
                        $items[$j]['th'] += 0.015 * $s;
                        $items[$i]['x'] = $ocx + $aRad * cos($items[$i]['th']);
                        $items[$i]['y'] = $ocy + $bRad * sin($items[$i]['th']);
                        $items[$j]['x'] = $ocx + $aRad * cos($items[$j]['th']);
                        $items[$j]['y'] = $ocy + $bRad * sin($items[$j]['th']);
                        $moved = true;
                    }
                }
            }
            if (! $moved) {
                break;
            }
        }

        $pills = [];
        foreach ($items as $it) {
            $cell = $it['cell'];
            $lbl  = $labels[$cell['code']];
            $w    = $it['w'];
            $th   = $it['th'];
            $right  = cos($th) >= 0;
            $topbot = abs(sin($th)) > 0.6;

            $px = min($outerW - 6 - $w, max(6.0, $it['x'] - $w / 2));
            $py = $it['y'];
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
                'code'   => $cell['code'],
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
