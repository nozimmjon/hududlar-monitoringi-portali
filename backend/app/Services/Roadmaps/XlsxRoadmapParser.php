<?php

namespace App\Services\Roadmaps;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Reads a regional road map returned in OUR xlsx template layout (sheet 0; «Йўриқнома»
 * and anything else is ignored): row 1 the title, row 2 the A–J header, then section
 * headers, district headers, measures and their «Индикатор» lines.
 *
 * Rows are classified by their text and by which of C/D/E/F carry something — never by
 * a number the region typed. Column A («Калит») is read for its text only: the regions
 * copied the keys from another region's file (every one of the thirteen has 1733-… keys,
 * Андижон has AND-…), so they say nothing about identity. Column B («№») is just as
 * unreliable — duplicates, gaps, restarts — so seq_no counts the measure rows inside
 * (section, district) and a disagreeing № is reported as a warning, never obeyed.
 *
 * Structural problems throw RuntimeException naming the row or cell; recoverable oddities
 * (a shifted row, a plan given as a range, a № that does not match) land in 'warnings'.
 */
final class XlsxRoadmapParser
{
    /** What to do with a plan written as a range («18-25»). */
    public const RANGE_MODES = ['null', 'lower', 'upper'];

    /** The indicator the template suggests when a measure has no countable one. */
    public const COMPLETION_LABEL = 'Бажарилиш даражаси';

    private const COLUMNS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

    /** Space, NBSP, narrow and thin no-break space — all of them turn up in pasted text. */
    private const SPACES = '[\s\x{00A0}\x{202F}\x{2009}]';

    /** @var callable(string):?int */
    private $resolveDistrict;

    /**
     * @param callable(string):?int $resolveDistrict district name ("Боғот тумани") → districts.id, null if unknown
     * @param string $range one of RANGE_MODES: what a plan like «18-25» becomes
     * @param bool $shiftedRows accept the one-column-left rows one region typed (see shifted())
     */
    public function __construct(callable $resolveDistrict, private string $range = 'null', private bool $shiftedRows = true)
    {
        if (! in_array($this->range, self::RANGE_MODES, true)) {
            throw new InvalidArgumentException('range must be one of: ' . implode(', ', self::RANGE_MODES));
        }
        $this->resolveDistrict = $resolveDistrict;
    }

    /** @return array{title_text:string, approvers_text:null, measures:list<array<string,mixed>>, warnings:list<string>} */
    public function parseFile(string $file): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);                 // styles would be read for every cell the region ever formatted
        $book = $reader->load($file);

        try {
            return $this->parse($book->getSheet(0));
        } finally {
            $book->disconnectWorksheets();
        }
    }

    /** @return array{title_text:string, approvers_text:null, measures:list<array<string,mixed>>, warnings:list<string>} */
    public function parse(Worksheet $sheet): array
    {
        if (mb_stripos(self::flat(self::raw($sheet, 'C2')), 'Чора-тадбир') === false
            || mb_stripos(self::flat(self::raw($sheet, 'D2')), 'Индикатор') === false) {
            throw new RuntimeException('2-қатор сарлавҳаси танилмади (header row not recognised): C2 «Чора-тадбир», D2 «Индикатор» бўлиши керак.');
        }

        $measures        = [];
        $warnings        = [];
        $section         = null;   // ['no'=>int,'title'=>string,'districts'=>bool]
        $district        = null;   // ['id'=>int,'head'=>?string]
        $seq             = 0;
        $expectedSection = 1;
        $seenDistricts   = [];     // [section_no][district_id] => source row
        $current         = null;   // index in $measures the indicator rows belong to

        $last = $sheet->getHighestDataRow();
        for ($r = 3; $r <= $last; $r++) {
            $cells = [];
            foreach (self::COLUMNS as $col) {
                $cells[$col] = self::raw($sheet, $col . $r);
            }
            $a = self::flat($cells['A']);
            $b = self::flat($cells['B']);
            $c = self::block($cells['C']);               // paragraphs matter here
            $d = self::flat($cells['D']);
            $e = self::flat($cells['E']);
            $f = self::flat($cells['F']);
            $g = self::flat($cells['G']);
            $h = self::flat($cells['H']);
            $i = self::flat($cells['I']);
            $j = self::block($cells['J']);               // paragraphs matter here too

            if ($c === '' && $d === '') {
                // The header text sits in A or in B, and Жиззах left a stale key in A above
                // one of the district headers — so take whichever column actually reads like one.
                $header = null;
                foreach ([$a, $b] as $head) {
                    if ($head === '') {
                        continue;
                    }
                    if ($found = RoadmapParser::matchSectionHeader($head)) {
                        $header = ['kind' => 'section', 'text' => $head, 'found' => $found];
                        break;
                    }
                    if ($found = self::matchDistrictHeader($head)) {
                        $header = ['kind' => 'district', 'text' => $head, 'found' => $found];
                        break;
                    }
                }

                if ($header !== null && $header['kind'] === 'section') {
                    ['text' => $head, 'found' => $found] = $header;
                    if ($found['no'] !== $expectedSection) {
                        throw new RuntimeException("{$r}-қатор: бўлим рақами кутилган {$expectedSection}, топилди {$found['no']} («{$head}»)");
                    }
                    $expectedSection++;
                    $section = [
                        'no'        => $found['no'],
                        'title'     => mb_substr($found['title'], 0, 255),
                        'districts' => mb_stripos($found['title'], 'туман') !== false,
                    ];
                    $district = null;
                    $seq      = 0;
                    $current  = null;
                    continue;
                }

                if ($header !== null) {
                    ['text' => $head, 'found' => $found] = $header;
                    if (! $section || ! $section['districts']) {
                        throw new RuntimeException("{$r}-қатор: туман сарлавҳаси туманлар бўлимидан ташқарида («{$head}»)");
                    }
                    $id = ($this->resolveDistrict)($found['name']);
                    if ($id === null) {
                        throw new RuntimeException("{$r}-қатор: туман топилмади — «{$found['name']}» («{$head}»). districts.alt_labels га қўшинг ёки файлни текширинг.");
                    }
                    if (isset($seenDistricts[$section['no']][$id])) {
                        throw new RuntimeException("{$r}-қатор: «{$found['name']}» шу бўлимда иккинчи марта учради (аввалги қатор: {$seenDistricts[$section['no']][$id]})");
                    }
                    $seenDistricts[$section['no']][$id] = $r;
                    $district = ['id' => $id, 'head' => $found['head']];
                    $seq      = 0;
                    $current  = null;
                    continue;
                }

                if ($b === '' && $e === '' && $f === '' && $g === '' && $h === '' && $i === '' && $j === '') {
                    continue;                            // spacer row, or a key the region left behind in A
                }
            }

            $shifted = $this->shiftedRows && $c === '' && $b === '' && $d !== ''
                && mb_strtolower($e) === mb_strtolower(self::COMPLETION_LABEL) && $f === '%';

            if ($c !== '' || $shifted) {
                if (! $section) {
                    throw new RuntimeException("{$r}-қатор: бўлим сарлавҳасидан олдин чора-тадбир");
                }
                if ($section['districts'] && ! $district) {
                    throw new RuntimeException("{$r}-қатор: туман сарлавҳасидан олдин чора-тадбир");
                }
                $seq++;
                if (! $shifted && preg_match('/^\d+$/', $b) === 1 && (int) $b !== $seq) {
                    $warnings[] = "r{$r}: № {$b} in file, counted {$seq}";
                }
                if ($shifted) {
                    $warnings[] = "r{$r}: columns shifted left — read D as the measure text";
                }

                $body  = $shifted ? $d : $c;
                $split = RoadmapParser::splitMeasure(explode("\n", $body));
                $measures[] = [
                    'section_no'         => $section['no'],
                    'section_title'      => $section['title'],
                    'district_id'        => $district['id'] ?? null,
                    'district_head_text' => $district['head'] ?? null,
                    'seq_no'             => $seq,
                    'title'              => $split['title'],
                    'details'            => $split['details'],
                    'body_raw'           => $body,
                    'deadline_text'      => $shifted ? null : RoadmapParser::nullIfEmpty(mb_substr($i, 0, 128)),
                    'responsible_text'   => $shifted ? null : RoadmapParser::nullIfEmpty(RoadmapParser::joinLines($j === '' ? [] : explode("\n", $j))),
                    'source_row'         => $r,
                    'lines'              => [],
                ];
                $current = array_key_last($measures);

                if ($shifted) {
                    $measures[$current]['lines'][] = [
                        'label' => self::COMPLETION_LABEL, 'unit' => '%', 'plan' => 100.0, 'actual' => null, 'note' => null,
                    ];
                } elseif ($d !== '' || $e !== '' || $f !== '' || $g !== '' || $h !== '') {
                    $measures[$current]['lines'][] = $this->line($d, $e, $cells['F'], $cells['G'], $h, $r, $warnings);
                }
                continue;
            }

            if ($d !== '' || $e !== '' || $f !== '') {
                if ($current === null) {
                    throw new RuntimeException("D{$r}: индикатор қатори чора-тадбирсиз.");
                }
                $measures[$current]['lines'][] = $this->line($d, $e, $cells['F'], $cells['G'], $h, $r, $warnings);
                continue;
            }

            $filled = [];
            foreach (['A' => $a, 'B' => $b, 'C' => $c, 'D' => $d, 'E' => $e, 'F' => $f, 'G' => $g, 'H' => $h, 'I' => $i, 'J' => $j] as $col => $value) {
                if ($value !== '') {
                    $filled[] = $col . '=«' . mb_substr($value, 0, 60) . '»';
                }
            }
            throw new RuntimeException("{$r}-қатор: қатор тури аниқланмади — " . implode(' | ', $filled));
        }

        if ($measures === []) {
            throw new RuntimeException('Файлда бирорта чора-тадбир топилмади.');
        }
        foreach ($measures as $m) {
            if ($m['lines'] === []) {
                $warnings[] = "r{$m['source_row']}: measure without indicator lines";
            }
        }

        return [
            'title_text'     => self::flat(self::raw($sheet, 'A1')),
            'approvers_text' => null,                    // the xlsx layout has no ТАСДИҚЛАЙМАН block
            'measures'       => $measures,
            'warnings'       => $warnings,
        ];
    }

    /**
     * "1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)" → name + hokim text. The number is
     * optional (Сирдарё dropped it on the first district), the parenthesis may be missing
     * («Қувасой шаҳар») or never closed (Тошкент, Сирдарё) — take it to the end of the line then.
     *
     * @return ?array{name:string,head:?string}
     */
    public static function matchDistrictHeader(string $text): ?array
    {
        if (preg_match('/^\s*(?:\d+\s*[.)]\s*)?(.+?(?:тумани|шаҳри|шаҳар))(?!\p{L})(.*)$/u', trim($text), $m) !== 1) {
            return null;
        }

        $head = null;
        if (preg_match('/\((.*)$/u', $m[2], $paren) === 1) {
            $inner = (string) preg_replace('/\)\s*[.;]?\s*$/u', '', trim($paren[1]));
            $inner = trim((string) preg_replace('/^масъул\s*[-–—]?\s*/ui', '', trim($inner)));
            $head  = $inner === '' ? null : mb_substr($inner, 0, 255);
        }

        return ['name' => trim($m[1]), 'head' => $head];
    }

    /**
     * @param list<string> $warnings
     * @return array{label:string, unit:?string, plan:?float, actual:?float, note:?string}
     */
    private function line(string $label, string $unit, mixed $plan, mixed $actual, string $note, int $row, array &$warnings): array
    {
        if ($label === '') {
            throw new RuntimeException("D{$row}: индикатор номи бўш.");
        }

        return [
            'label'  => mb_substr($label, 0, 255),
            'unit'   => self::unit($unit),
            'plan'   => $this->number($plan, "F{$row}", 'plan', $row, $warnings),
            'actual' => $this->number($actual, "G{$row}", 'actual', $row, $warnings),
            'note'   => $note === '' ? null : mb_substr($note, 0, 500),
        ];
    }

    /**
     * Accepts 7,8 · 7.8 · 1 240 · 15848 and the int/float a spreadsheet stores directly.
     * «18-25» is a range: one region wrote the saving it expects as one, and there is no
     * honest single plan in it — $range decides between dropping it and taking a bound.
     *
     * @param list<string> $warnings
     */
    private function number(mixed $value, string $where, string $kind, int $row, array &$warnings): ?float
    {
        if ($value === null || (! is_bool($value) && trim((string) $value) === '')) {
            return null;
        }
        if (is_bool($value)) {
            throw new RuntimeException("{$where}: катакда мантиқий қиймат — рақам эмас.");
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $shown = trim((string) $value);
        if (preg_match('/^(\d+(?:[,.]\d+)?)\s*[-–—]\s*(\d+(?:[,.]\d+)?)$/u', $shown, $m) === 1) {
            $bound = $this->range === 'lower' ? $m[1] : $m[2];
            $taken = $this->range === 'null' ? null : (float) str_replace(',', '.', $bound);
            $warnings[] = $taken === null
                ? "r{$row}: {$kind} «{$shown}» is a range — stored without a {$kind}"
                : "r{$row}: {$kind} «{$shown}» is a range — stored as {$bound}";

            return $taken;
        }

        $plain = str_replace(',', '.', (string) preg_replace('/' . self::SPACES . '/u', '', $shown));
        if (! is_numeric($plain)) {
            throw new RuntimeException("{$where}: «{$shown}» рақам эмас.");
        }

        return (float) $plain;
    }

    /** «млн м3» / «млн м 3» → «млн м³», «Га» → «га»; the template and the page print these as typed. */
    private static function unit(string $unit): ?string
    {
        if ($unit === '') {
            return null;
        }
        $unit = (string) preg_replace('/м\s?3/u', 'м³', $unit);
        if (mb_strtolower($unit) === 'га') {
            $unit = 'га';
        }

        return mb_substr($unit, 0, 48);
    }

    /** Existing cells only: asking for a missing one would materialise it on every style-only row. */
    private static function raw(Worksheet $sheet, string $coord): mixed
    {
        return $sheet->cellExists($coord) ? $sheet->getCell($coord)->getValue() : null;
    }

    /** One line: every run of whitespace — newlines included — collapses to a single space. */
    private static function flat(mixed $value): string
    {
        return trim((string) preg_replace('/' . self::SPACES . '+/u', ' ', (string) $value));
    }

    /** Paragraphs kept: each line collapsed and trimmed, empty ones dropped, joined with "\n". */
    private static function block(mixed $value): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', (string) $value) ?: [] as $line) {
            $line = self::flat($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }
}
