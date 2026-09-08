<?php

namespace App\Support\Roadmaps;

/** Roman numeral for a section number: 1..39 → 'I'..'XXXIX'; anything ≤ 0 is an empty string. The inverse (with Cyrillic look-alikes) is RoadmapParser::romanToInt(). */
final class Roman
{
    public static function of(int $n): string
    {
        $out = '';
        foreach ([10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'] as $v => $r) {
            while ($n >= $v) {
                $out .= $r;
                $n   -= $v;
            }
        }

        return $out;
    }
}
