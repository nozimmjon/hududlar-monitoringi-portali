<?php

namespace App\Support\Roadmaps;

/**
 * Suggests indicator lines for a measure from the quantities in its text
 * («24 та насос агрегатларини таъмирлаш» → label «Насос агрегатларини таъмирлаш»,
 * unit «та», plan 24). A starting point for the reviewer, never the final word.
 */
final class LineSuggester
{
    public const FALLBACK = ['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0];

    /** 1 280 · 1 280,5 · 26,7 · 24 (thousands groups separated by a space or NBSP). */
    private const NUMBER = '(?:\d{1,3}(?:[ \x{00A0}]\d{3})+|\d+)(?:[,.]\d+)?';

    /** number · optional magnitude · optional unit; the lookahead forbids a unit glued to a longer word («24 таъмирлаш»). */
    private const RE = '/(' . self::NUMBER . ')[ \x{00A0}]*(минг|млн|млрд)?[ \x{00A0}]*(та|дона|нафар|км|га|гектар|тонна|м³|м3|квт|сўм|долл\.?)?(?!\p{L})/iu';

    private const UNITS = ['гектар' => 'га', 'м3' => 'м³', 'квт' => 'кВт', 'долл' => 'долл.', 'долл.' => 'долл.'];

    /**
     * @param  list<string> $detailLines
     * @return list<array{label: string, unit: string, plan: float}>
     */
    public function suggest(string $title, array $detailLines): array
    {
        $out = [];
        foreach (array_merge([$title], $detailLines) as $text) {
            foreach ($this->linesOf($text) as $line) {
                $out[] = $line;
            }
        }

        return $out === [] ? [self::FALLBACK] : $out;
    }

    /** @return list<array{label: string, unit: string, plan: float}> */
    private function linesOf(string $text): array
    {
        if (preg_match_all(self::RE, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $found = [];
        foreach ($matches as $m) {
            $unit = isset($m[3]) && $m[3][0] !== '' ? mb_strtolower($m[3][0]) : null;
            if ($unit === null) {
                continue;                                   // bare number or a magnitude with no unit — ambiguous
            }
            $unit      = self::UNITS[$unit] ?? $unit;
            $magnitude = isset($m[2]) && $m[2][0] !== '' ? mb_strtolower($m[2][0]) . ' ' : '';
            $found[]   = [
                'span' => [$m[0][1], strlen($m[0][0])],
                'unit' => $magnitude . $unit,
                'plan' => (float) str_replace(',', '.', (string) preg_replace('/[ \x{00A0}]/u', '', $m[1][0])),
            ];
        }
        if ($found === []) {
            return [];
        }

        // Remove the matched quantities from the text (from the end, so byte offsets stay valid).
        $label = $text;
        foreach (array_reverse($found) as $f) {
            $label = substr_replace($label, ' ', $f['span'][0], $f['span'][1]);
        }
        $label = self::cleanLabel($label);

        $lines = [];
        foreach ($found as $i => $f) {
            $lines[] = ['label' => $i === 0 ? $label : $label . ' (' . ($i + 1) . ')', 'unit' => $f['unit'], 'plan' => $f['plan']];
        }

        return $lines;
    }

    private static function cleanLabel(string $label): string
    {
        $label = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $label));
        $label = (string) preg_replace('/^(?:\d+[.)]|[•\-–—])\s*/u', '', $label);
        $label = (string) preg_replace('/[\s.;:,]+$/u', '', $label);
        $label = mb_substr($label, 0, 255);
        if ($label === '') {
            return 'Ҳажм';
        }

        return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }
}
