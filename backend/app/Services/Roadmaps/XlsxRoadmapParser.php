<?php

namespace App\Services\Roadmaps;

use App\Models\RoadmapMeasureLine;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

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
     * @param bool $withStyles read number formats and evaluate formulas, so a percent- or date-formatted
     *                         cell is refused instead of silently divided by 100 — worth the load only
     *                         when the numbers are actually being imported (--period)
     * @param bool $shiftedRows accept the one-column-left row Сурхондарё typed; off = abort on it
     */
    public function __construct(
        callable $resolveDistrict,
        private string $range = 'null',
        private bool $withStyles = false,
        private bool $shiftedRows = true,
    ) {
        if (! in_array($this->range, self::RANGE_MODES, true)) {
            throw new InvalidArgumentException('range must be one of: ' . implode(', ', self::RANGE_MODES));
        }
        $this->resolveDistrict = $resolveDistrict;
    }

    /** @return array{title_text:string, approvers_text:null, measures:list<array<string,mixed>>, warnings:list<string>} */
    public function parseFile(string $file): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(! $this->withStyles);
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

            $looksShifted = $c === '' && $b === '' && $d !== ''
                && mb_strtolower($e) === mb_strtolower(self::COMPLETION_LABEL) && $f === '%';
            $shifted = $this->shiftedRows && $looksShifted;

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
                    $measures[$current]['lines'][] = $this->line($sheet, $d, $e, $h, $r, $warnings);
                }
                continue;
            }

            if ($looksShifted) {
                // Reading it as a line is what happens next, and «%» is not a plan — say why.
                throw new RuntimeException("F{$r}: «{$f}» рақам эмас — қатор чапга силжиган кўринади (--no-shifted берилган).");
            }

            if ($d !== '' || $e !== '' || $f !== '') {
                if ($current === null) {
                    throw new RuntimeException("D{$r}: индикатор қатори чора-тадбирсиз.");
                }
                $measures[$current]['lines'][] = $this->line($sheet, $d, $e, $h, $r, $warnings);
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
    private function line(Worksheet $sheet, string $label, string $unit, string $note, int $row, array &$warnings): array
    {
        if ($label === '') {
            throw new RuntimeException("D{$row}: индикатор номи бўш.");
        }
        if (mb_strlen($label) > RoadmapMeasureLine::LABEL_MAX) {
            throw new RuntimeException("D{$row}: индикатор номи жуда узун (" . mb_strlen($label) . ' белги) — катакка бутун матн ёпиштирилганми?');
        }

        return [
            'label'  => $label,
            'unit'   => self::unit($unit),
            'plan'   => $this->number($sheet, "F{$row}", 'plan', $row, $warnings),
            'actual' => $this->number($sheet, "G{$row}", 'actual', $row, $warnings),
            'note'   => $note === '' ? null : mb_substr($note, 0, 500),
        ];
    }

    /**
     * Accepts 7,8 · 7.8 · 1 240 · 15848 and the int/float a spreadsheet stores directly.
     * «18-25» is a range: one region wrote the saving it expects as one, and there is no
     * honest single plan in it — $range decides between dropping it and taking a bound.
     * Everything a spreadsheet can silently distort — a percent-formatted 0,5 that means 50,
     * a date cell, «1,240» that could be 1240 or 1,24 — throws instead of being guessed at,
     * naming the cell, exactly as RoadmapProgressReader::number() does for the filled template.
     * The style checks need $withStyles; without it the format code reads empty and only the
     * value-shaped guards apply.
     *
     * @param list<string> $warnings
     */
    private function number(Worksheet $sheet, string $where, string $kind, int $row, array &$warnings): ?float
    {
        if (! $sheet->cellExists($where)) {
            return null;
        }

        // Fetch → read, nothing in between: PhpSpreadsheet recycles Cell objects, and a stale
        // one reports the collection's current coordinate — it would carry the last cell's style.
        $cell   = $sheet->getCell($where);
        $format = $this->withStyles ? (string) $cell->getStyle()->getNumberFormat()->getFormatCode() : '';
        $value  = $cell->getValue();
        if ($this->withStyles && $cell->isFormula()) {
            try {
                $value = $cell->getCalculatedValue();
            } catch (Throwable $e) {
                throw new RuntimeException("{$where}: формула ҳисобланмади.", 0, $e);
            }
        }

        if ($value === null || (! is_bool($value) && trim((string) $value) === '')) {
            return null;
        }
        if (is_bool($value)) {
            throw new RuntimeException("{$where}: катакда мантиқий қиймат — рақам эмас.");
        }

        $shown  = trim((string) $value);
        $number = is_int($value) || is_float($value);

        // «50%» is stored as 0,5 — taking that at face value would quietly divide the report by 100.
        if ($number && $format !== '' && preg_match('/(?<!\\\\)%/', $format) === 1) {
            throw new RuntimeException("{$where}: катак фоиз форматида — 50% эмас, 50 деб ёзинг.");
        }
        if ($format !== '' && Date::isDateTimeFormatCode($format)) {
            throw new RuntimeException("{$where}: катак сана форматида — рақам киритинг.");
        }
        if ($number) {
            return self::magnitude((float) $value, $shown, $where);
        }

        if (preg_match('/^(\d+(?:[,.]\d+)?)\s*[-–—]\s*(\d+(?:[,.]\d+)?)$/u', $shown, $m) === 1) {
            $bound = $this->range === 'lower' ? $m[1] : $m[2];
            $taken = $this->range === 'null' ? null : (float) str_replace(',', '.', $bound);
            $warnings[] = $taken === null
                ? "r{$row}: {$kind} «{$shown}» is a range — stored without a {$kind}"
                : "r{$row}: {$kind} «{$shown}» is a range — stored as {$bound}";

            return $taken;
        }

        $stripped = (string) preg_replace('/' . self::SPACES . '/u', '', $shown);
        if (preg_match('/^\d{1,3}(,\d{3})+$/D', $stripped) === 1) {
            throw new RuntimeException("{$where}: «{$shown}» ноаниқ — 1240 бўлса «1 240», 1,24 бўлса «1,24» деб ёзинг.");
        }
        $plain = str_replace(',', '.', $stripped);
        if (! is_numeric($plain)) {
            throw new RuntimeException("{$where}: «{$shown}» рақам эмас.");
        }

        return self::magnitude((float) $plain, $shown, $where);
    }

    private static function magnitude(float $n, string $shown, string $where): float
    {
        if (abs($n) > 1e12) {
            throw new RuntimeException("{$where}: «{$shown}» жуда катта.");
        }

        return $n;
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
