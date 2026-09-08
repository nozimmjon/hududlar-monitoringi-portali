<?php

namespace App\Support\Roadmaps;

use InvalidArgumentException;

/**
 * Stable identity of a measure inside the xlsx files:
 * {region SOATO}-{section no}-{district SOATO | 0}-{seq no}, e.g. 1733-5-1733208-3.
 * Survives a docx re-import (DB ids do not).
 */
final class RoadmapKey
{
    public const REGEX = '/^(\d{4})-(\d{1,2})-(\d+)-(\d{1,3})$/D';

    public static function make(int $regionCode, int $sectionNo, ?int $districtCode, int $seqNo): string
    {
        return $regionCode . '-' . $sectionNo . '-' . ($districtCode ?? 0) . '-' . $seqNo;
    }

    public static function isKey(mixed $value): bool
    {
        return is_string($value) && preg_match(self::REGEX, trim($value)) === 1;
    }

    /** @return array{region:int, section:int, district:int, seq:int} district 0 = region-level */
    public static function parse(string $key): array
    {
        if (preg_match(self::REGEX, trim($key), $m) !== 1) {
            throw new InvalidArgumentException("Bad road-map key «{$key}».");
        }

        return ['region' => (int) $m[1], 'section' => (int) $m[2], 'district' => (int) $m[3], 'seq' => (int) $m[4]];
    }
}
