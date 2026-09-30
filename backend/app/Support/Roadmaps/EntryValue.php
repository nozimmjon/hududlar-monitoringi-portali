<?php

namespace App\Support\Roadmaps;

use InvalidArgumentException;

/**
 * Numbers typed into the admin grid. Unlike the xlsx readers (where «1,240» is
 * ambiguous and refused), a comma typed by hand is a decimal comma. `parse` is
 * mirrored in public/js/ad-entry.js (the grid formats server-side, so there is
 * no JS `format`) — keep the two parsers in step.
 */
final class EntryValue
{
    private const MAX = 1e12;

    /** Ordinary, no-break, narrow no-break and thin spaces — all of them turn up in pasted numbers. */
    private const SPACES = '/[ \x{00A0}\x{202F}\x{2009}]/u';

    /** @throws InvalidArgumentException with the short message the cell shows */
    public static function parse(?string $raw): ?float
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $stripped = preg_replace(self::SPACES, '', $s);
        if ($stripped === null) {                 // invalid UTF-8 — not an empty cell
            throw new InvalidArgumentException('рақам эмас');
        }
        if ($stripped === '') {                   // nothing but (unicode) spaces
            return null;
        }
        $s = str_replace(',', '.', $stripped);
        if (preg_match('/^-?\d+(\.\d+)?$/D', $s) !== 1) {
            throw new InvalidArgumentException('рақам эмас');
        }
        $v = (float) $s;
        if ($v < 0) {
            throw new InvalidArgumentException('манфий бўлмасин');
        }
        if ($v > self::MAX) {
            throw new InvalidArgumentException('жуда катта');
        }

        return round($v, 6) + 0.0;
    }

    /**
     * Up to 6 decimals, trailing zeros dropped, decimal comma, no thousands separator;
     * '' for null. '' in gives '' out; -0 renders as 0; a non-numeric string renders as
     * '0' — callers pass floats, ints, null or the decimal cast's strings only.
     */
    public static function format(null|float|int|string $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $s = rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');

        return str_replace('.', ',', $s === '-0' ? '0' : $s);
    }
}
