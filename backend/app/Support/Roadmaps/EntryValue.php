<?php

namespace App\Support\Roadmaps;

use InvalidArgumentException;

/**
 * Numbers typed into the admin grid. Unlike the xlsx readers (where «1,240» is
 * ambiguous and refused), a comma typed by hand is a decimal comma. Mirrored in
 * public/js/ad-entry.js (parse/fmt) — keep the two in step.
 */
final class EntryValue
{
    public const MAX = 1e12;

    /** Ordinary, no-break, narrow no-break and thin spaces — all of them turn up in pasted numbers. */
    private const SPACES = '/[ \x{00A0}\x{202F}\x{2009}]/u';

    /** @throws InvalidArgumentException with the short message the cell shows */
    public static function parse(?string $raw): ?float
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $s = str_replace(',', '.', (string) preg_replace(self::SPACES, '', $s));
        if (preg_match('/^-?\d+(\.\d+)?$/', $s) !== 1) {
            throw new InvalidArgumentException('рақам эмас');
        }
        $v = (float) $s;
        if ($v < 0) {
            throw new InvalidArgumentException('манфий бўлмасин');
        }
        if ($v > self::MAX) {
            throw new InvalidArgumentException('жуда катта');
        }

        return $v;
    }

    /** Up to 6 decimals, trailing zeros dropped, decimal comma, no thousands separator; '' for null. */
    public static function format(null|float|int|string $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $s = rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');

        return str_replace('.', ',', $s === '-0' ? '0' : $s);
    }
}
