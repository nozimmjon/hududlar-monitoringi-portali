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

    /**
     * number · optional magnitude · unit · optional grammatical suffix («гектарда», «тадан», «кВтлик»);
     * the lookahead forbids a unit glued to a longer word or a following digit («24 таъмирлаш», «5 км2»).
     * Space/NBSP only between the parts — must not span a line break.
     */
    private const RE = '/(' . self::NUMBER . ')[ \x{00A0}]*(минг|млн|млрд)?[ \x{00A0}]*(та|дона|нафар|км|га|гектар|тонна|м³|м3|квт|сўм|долл\.?|%|фоиз)(?:да|дан|га|ги|лик|ли|ини|нинг|ида)?(?![\p{L}\d])/iu';

    /**
     * A leading list marker («1.», «2)», «•», «-», «–», «—») stripped before matching so it cannot merge into
     * a following number. The third alternative also strips a bare marker digit («1» in «1 132,8») that would
     * otherwise misread as the start of a thousands group — real thousands groups («1 280») have no decimal
     * tail on the following 3-digit cluster, so the lookahead only fires when one is present.
     */
    private const LEADING_MARKER = '/^[\s\x{00A0}]*(?:\d+[.)]|[•\-–—]|\d{1,3}(?=[ \x{00A0}]\d{3}[,.]\d))[ \x{00A0}]*/u';

    private const UNITS = ['гектар' => 'га', 'м3' => 'м³', 'квт' => 'кВт', 'долл' => 'долл.', 'долл.' => 'долл.', 'фоиз' => '%'];

    /**
     * @param  list<string> $detailLines
     * @return list<array{label: string, unit: string, plan: float}>
     */
    public function suggest(string $title, array $detailLines): array
    {
        $titleLines = $this->linesOf($title);
        $bodyLines  = [];
        foreach ($detailLines as $text) {
            foreach ($this->linesOf($text) as $line) {
                $bodyLines[] = $line;
            }
        }

        // A title that only totals its own breakdown («64 нафар …» over «9 нафар — Урганч», «20 нафар — Хива», …)
        // would otherwise double the count; keep the breakdown and drop the repeated aggregate.
        if ($bodyLines !== [] && count($titleLines) === 1) {
            $sum      = 0.0;
            $sameUnit = true;
            foreach ($bodyLines as $line) {
                $sum      += $line['plan'];
                $sameUnit = $sameUnit && $line['unit'] === $titleLines[0]['unit'];
            }
            if ($sameUnit && abs($titleLines[0]['plan'] - $sum) < 0.01) {
                $titleLines = [];
            }
        }

        $out = array_merge($titleLines, $bodyLines);

        return $out === [] ? [self::FALLBACK] : $out;
    }

    /** @return list<array{label: string, unit: string, plan: float}> */
    private function linesOf(string $text): array
    {
        $text = (string) preg_replace(self::LEADING_MARKER, '', $text);

        if (! preg_match_all(self::RE, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
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
        $label = rtrim($label);
        if ($label === '') {
            return 'Ҳажм';
        }

        return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }
}
