<?php

namespace App\Support\Roadmaps;

use InvalidArgumentException;

/**
 * Stable identity of a measure inside the xlsx files:
 * {region SOATO}-{section no}-{district SOATO | 0}-{seq no}, e.g. 1733-5-1733208-3.
 * Survives a docx re-import (DB ids do not). Scoped to one road map: the key
 * carries no year/domain, the importer resolves those from its options.
 */
final class RoadmapKey
{
    public const REGEX = '/^(\d{4})-(\d+)-(\d+)-(\d+)$/D';

    public static function make(int $regionCode, int $sectionNo, ?int $districtCode, int $seqNo): string
    {
        return $regionCode . '-' . $sectionNo . '-' . ($districtCode ?? 0) . '-' . $seqNo;
    }

    public static function isKey(mixed $value): bool
    {
        return is_string($value) && preg_match(self::REGEX, self::clean($value)) === 1;
    }

    /** @return array{region:int, section:int, district:int, seq:int} district 0 = region-level */
    public static function parse(string $key): array
    {
        if (preg_match(self::REGEX, self::clean($key), $m) !== 1) {
            throw new InvalidArgumentException("Bad road-map key «{$key}».");
        }

        return ['region' => (int) $m[1], 'section' => (int) $m[2], 'district' => (int) $m[3], 'seq' => (int) $m[4]];
    }

    /** Normalises hand-edited keys (leading zeros, padding) to the form make() emits — the importer matches keys as strings. */
    public static function canonical(string $key): string
    {
        $p = self::parse($key);

        return self::make($p['region'], $p['section'], $p['district'], $p['seq']);
    }

    private static function clean(string $value): string
    {
        return (string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $value);
    }
}
