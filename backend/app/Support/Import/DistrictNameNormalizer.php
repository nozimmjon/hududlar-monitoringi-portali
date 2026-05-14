<?php

namespace App\Support\Import;

class DistrictNameNormalizer
{
    /**
     * Cyrillic orthography variants + Latin look-alikes that occasionally
     * appear in workbook cells.
     *
     * Қ/қ is intentionally NOT in this map — it is a distinct Cyrillic letter
     * used consistently in both source workbooks and seeded names.
     */
    private const CHAR_MAP = [
        'ў' => 'у', 'Ў' => 'У',
        'ҳ' => 'х', 'Ҳ' => 'Х',
        'ғ' => 'г', 'Ғ' => 'Г',
        'p' => 'р',
        'o' => 'о',
        'a' => 'а',
        'c' => 'с',
        'e' => 'е',
        'x' => 'х',
        'y' => 'у',
    ];

    public static function normalize(string $name): string
    {
        $s = mb_strtolower(trim($name));

        if (str_ends_with($s, ' шаҳри')) {
            $s = mb_substr($s, 0, -mb_strlen(' шаҳри')) . ' ш.';
        } elseif (str_ends_with($s, ' шаҳар')) {
            $s = mb_substr($s, 0, -mb_strlen(' шаҳар')) . ' ш.';
        } elseif (str_ends_with($s, ' шахри')) {
            $s = mb_substr($s, 0, -mb_strlen(' шахри')) . ' ш.';
        } elseif (str_ends_with($s, ' шахар')) {
            $s = mb_substr($s, 0, -mb_strlen(' шахар')) . ' ш.';
        } elseif (str_ends_with($s, ' ш.')) {
            // already canonical
        } elseif (str_ends_with($s, ' ш')) {
            $s = mb_substr($s, 0, -mb_strlen(' ш')) . ' ш.';
        } elseif (str_ends_with($s, ' тумани')) {
            $s = mb_substr($s, 0, -mb_strlen(' тумани'));
        } elseif (str_ends_with($s, ' туман')) {
            $s = mb_substr($s, 0, -mb_strlen(' туман'));
        } elseif (str_ends_with($s, ' т.')) {
            $s = mb_substr($s, 0, -mb_strlen(' т.'));
        } elseif (str_ends_with($s, ' т')) {
            $s = mb_substr($s, 0, -mb_strlen(' т'));
        }

        $s = strtr($s, self::CHAR_MAP);

        $s = trim(preg_replace('/\s+/u', ' ', $s));

        return $s;
    }
}
