<?php

namespace App\Services\Roadmaps;

use RuntimeException;

/**
 * Turns DocxTableReader blocks of a regional "ЙЎЛ ХАРИТАСИ" into measures.
 *
 * Rows are classified by their TEXT, never by position: section headers start
 * with a Roman numeral ("I. …"), district headers with "N. <name> тумани (…)",
 * everything else with a non-empty second cell is a measure. The Т/р cell is
 * ignored (Word auto-numbering; some regions type "1." in it).
 */
class RoadmapParser
{
    /** Cyrillic capitals typed instead of Latin Roman numerals (І U+0406, Х U+0425). */
    private const ROMAN_LOOKALIKES = ["\u{0406}" => 'I', "\u{0425}" => 'X'];

    private const ROMAN = ['I' => 1, 'V' => 5, 'X' => 10];

    /** @var callable(string):?int */
    private $resolveDistrict;

    /** @param callable(string):?int $resolveDistrict district name ("Боғот тумани") → districts.id, null if unknown */
    public function __construct(callable $resolveDistrict)
    {
        $this->resolveDistrict = $resolveDistrict;
    }

    public function parse(array $blocks): array
    {
        throw new RuntimeException('not implemented yet');
    }

    public static function romanToInt(string $s): ?int
    {
        $s = strtr(mb_strtoupper(trim($s)), self::ROMAN_LOOKALIKES);
        if ($s === '' || preg_match('/^[IVX]+$/', $s) !== 1) {
            return null;
        }
        $total = 0;
        $prev  = 0;
        foreach (array_reverse(str_split($s)) as $ch) {
            $v = self::ROMAN[$ch];
            $total += $v < $prev ? -$v : $v;
            $prev = max($prev, $v);
        }

        return $total;
    }

    /** @return ?array{no:int,title:string} */
    public static function matchSectionHeader(string $text): ?array
    {
        // Latin I/V/X plus the Cyrillic look-alikes І/і (U+0406/U+0456) and Х/х (U+0425/U+0445).
        if (preg_match('/^([IVXivx\x{0406}\x{0456}\x{0425}\x{0445}]+)\s*\.\s*(.+)$/u', trim($text), $m) !== 1) {
            return null;
        }
        $no = self::romanToInt($m[1]);

        return $no === null ? null : ['no' => $no, 'title' => trim($m[2])];
    }

    /**
     * "1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)" → name + hokim text.
     * The parenthesis is optional; a leading "масъул –" is stripped from it.
     *
     * @return ?array{name:string,head:?string}
     */
    public static function matchDistrictHeader(string $text): ?array
    {
        $re = '/^\d+\s*\.\s*(.+?(?:тумани|туман|шаҳри|шахри))\s*(?:\((.*)\))?\s*$/u';
        if (preg_match($re, trim($text), $m) !== 1) {
            return null;
        }
        $head = isset($m[2]) ? trim((string) preg_replace('/^масъул\s*[–—-]\s*/u', '', trim($m[2]))) : '';

        return ['name' => trim($m[1]), 'head' => $head === '' ? null : $head];
    }

    /**
     * @param list<string> $lines non-empty cell lines
     * @return array{title:string,details:?string}
     */
    public static function splitMeasure(array $lines): array
    {
        $first = $lines[0];
        $rest  = array_slice($lines, 1);
        $pos   = mb_stripos($first, 'жумладан');

        if ($pos !== false) {
            $title = mb_substr($first, 0, $pos);
            $after = ltrim(mb_substr($first, $pos + mb_strlen('жумладан')), " :,;");   // keep the item's own trailing ";"
            if ($after !== '') {
                array_unshift($rest, $after);
            }
        } else {
            $title = $first;
        }

        $title = trim((string) preg_replace('/[\s,:;]+$/u', '', $title));

        return ['title' => $title, 'details' => $rest === [] ? null : implode("\n", $rest)];
    }

    /**
     * Join cell lines back into one string: the document already carries commas
     * where it wants separation, so a soft wrap joins with a space; a line that
     * ends in ")" or "." is a complete item and gets ", " before the next one.
     *
     * @param list<string> $lines
     */
    public static function joinLines(array $lines): string
    {
        $out = '';
        foreach ($lines as $i => $line) {
            if ($i > 0) {
                $out .= preg_match('/[).]$/u', $out) === 1 ? ', ' : ' ';
            }
            $out .= $line;
        }

        return $out;
    }
}
