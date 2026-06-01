<?php

namespace App\Support;

class TaskExecutorResolver
{
    /**
     * Resolve a per-region "Ижрочи" cell to a list of district IDs.
     * Region-level rows (containing "вилояти") are skipped.
     *
     * @param  iterable  $districts  District models for the region (need id, name_full, name_short, alt_labels)
     * @param  list<string>  $unmatched  collects clean tokens that matched nothing
     * @return list<int>
     */
    public static function districtIds(string $executor, iterable $districts, array &$unmatched): array
    {
        $tokens = preg_split('/[,\n]+/u', $executor) ?: [];
        $ids = [];

        foreach ($tokens as $token) {
            $clean = trim($token);
            // Strip hokimlik/hokimiyat suffixes — also catches truncated typo 'ҳокимлиг' (missing final и)
            $clean = preg_replace('/\s+ҳокимлиг(и)?$/u', '', $clean);
            $clean = preg_replace('/\s+ҳокимият(и)?$/u', '', $clean);
            $clean = trim((string) $clean);
            // Skip region-level executors: вилоят hokimliklar AND the Karakalpak
            // "Республикаси Вазирлар Кенгаши" (which has no "вилояти" token).
            if ($clean === ''
                || str_contains($clean, 'вилояти')
                || str_contains($clean, 'Республикаси')
                || str_contains($clean, 'Вазирлар')) {
                continue;
            }

            $matched = false;
            $cleanNorm = self::normalize($clean);
            foreach ($districts as $d) {
                // Exact match first (original form)
                if ($d->name_full === $clean || $d->name_short === $clean) {
                    $ids[] = $d->id;
                    $matched = true;
                    break;
                }
                // Normalized match (handles latin lookalikes, spacing, suffix variants)
                if (self::normalize($d->name_full) === $cleanNorm
                    || self::normalize((string) $d->name_short) === $cleanNorm) {
                    $ids[] = $d->id;
                    $matched = true;
                    break;
                }
                $alt = $d->alt_labels ?? [];
                if (is_array($alt)) {
                    foreach ($alt as $label) {
                        if (self::normalize($label) === $cleanNorm) {
                            $ids[] = $d->id;
                            $matched = true;
                            break 2;
                        }
                    }
                }
            }

            if (! $matched) {
                $unmatched[] = $clean;
            }
        }

        return array_values(array_unique($ids));
    }

    /** Normalize a name for fuzzy comparison: latin lookalikes, spacing, suffix variants. */
    private static function normalize(string $s): string
    {
        // Latin -> Cyrillic lookalikes commonly mixed into the source workbook.
        $s = strtr($s, [
            'o' => 'о', 'O' => 'О',
            'x' => 'х', 'X' => 'Х',
            'a' => 'а', 'A' => 'А',
            'e' => 'е', 'E' => 'Е',
            'c' => 'с', 'C' => 'С',
        ]);
        // Collapse whitespace.
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim((string) $s);
        // Suffix variants -> canonical.
        $s = preg_replace('/\s+туман$/u', ' тумани', $s);
        $s = preg_replace('/\s+шаҳар$/u', ' шаҳри', $s);
        return mb_strtolower((string) $s);
    }
}
