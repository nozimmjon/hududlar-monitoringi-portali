<?php

namespace App\Support\Roadmaps;

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
