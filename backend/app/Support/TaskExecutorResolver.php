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
            $clean = preg_replace('/\s+ҳокимлиги$/u', '', $clean);
            $clean = preg_replace('/\s+ҳокимияти$/u', '', $clean);
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
            foreach ($districts as $d) {
                if ($d->name_full === $clean || $d->name_short === $clean) {
                    $ids[] = $d->id;
                    $matched = true;
                    break;
                }
                $alt = $d->alt_labels ?? [];
                if (is_array($alt)) {
                    foreach ($alt as $label) {
                        if (mb_strtolower($label) === mb_strtolower($clean)) {
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
}
