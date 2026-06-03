<?php

namespace App\Support;

class MapColorScale
{
    /** 5-stop muted palette: terracotta → sand → wheat → sage → eucalyptus */
    private const STOPS = [
        [0.00, [207, 126, 107]],
        [0.25, [224, 168, 120]],
        [0.50, [232, 207, 142]],
        [0.75, [169, 199, 154]],
        [1.00, [111, 168, 136]],
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
        [$lastT, $lastC] = self::STOPS[count(self::STOPS) - 1];
        return sprintf('#%02x%02x%02x', $lastC[0], $lastC[1], $lastC[2]);
    }
}
