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
    public static function build(array $geometry, array $labels, int $gutter = 24): array
    {
        [$vw, $vh] = self::viewDims($geometry['viewBox'] ?? '0 0 600 500');
        $centerX = $vw / 2;
        $top     = 20.0;
        $padX    = 9.0;
        $gap     = 8.0;
        $nameCharW = 6.4;
        $valCharW  = 6.6;
        $h = 20.0;
        $inset = 8.0; // gap between a pill and the map box edge

        $widthOf = fn (array $lbl): float =>
            2 * $padX + mb_strlen($lbl['name']) * $nameCharW + $gap + mb_strlen($lbl['value']) * $valCharW;

        $left = [];
        $right = [];
        $maxW = 0.0;
        foreach ($geometry['cells'] ?? [] as $cell) {
            $code = $cell['code'] ?? null;
            if ($code === null || ! isset($labels[$code])) {
                continue;
            }
            $maxW = max($maxW, $widthOf($labels[$code]));
            if ($cell['cx'] < $centerX) {
                $left[] = $cell;
            } else {
                $right[] = $cell;
            }
        }
        usort($left,  fn ($a, $b) => $a['cy'] <=> $b['cy']);
        usort($right, fn ($a, $b) => $a['cy'] <=> $b['cy']);

        // Gutter just wide enough to hold the widest pill against the map edge
        // (caller may force a wider one). Keeps pills hugging the map.
        $gutter = max($gutter, (int) ceil($maxW) + (int) $inset + 8);
        $outerW = $vw + 2 * $gutter;

        $pills = [];
        foreach (['L' => $left, 'R' => $right] as $side => $list) {
            $n = count($list);
            $step = $n > 1 ? ($vh - 2 * $top) / ($n - 1) : 0.0;
            foreach ($list as $i => $cell) {
                $code = $cell['code'];
                $lbl  = $labels[$code];
                $py   = $n > 1 ? $top + $i * $step : $vh / 2;
                $w    = $widthOf($lbl);
                // Pin pills to the INNER edge so they hug the map box.
                $px   = $side === 'L' ? $gutter - $inset - $w : $vw + $gutter + $inset;
                $dotX = $cell['cx'] + $gutter;
                $dotY = $cell['cy'];
                $startX = $side === 'L' ? $px + $w : $px;
                $midX   = ($startX + $dotX) / 2;
                // Smooth S-curve (cubic bezier) from the pill edge to the district —
                // control points share the mid-x at each end's y, giving a soft horizontal ease.
                $leader = sprintf(
                    'M %.1f %.1f C %.1f %.1f %.1f %.1f %.1f %.1f',
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
