<?php

namespace App\Services\Roadmaps;

use RuntimeException;

/**
 * Turns DocxTableReader blocks of a regional "ЙЎЛ ХАРИТАСИ" into measures.
 *
 * Rows are classified by their TEXT, never by position: section headers start
 * with a Roman numeral ("I. …"), district headers with "N. <name> тумани (…)",
 * everything else with a non-empty second cell is a measure. The Т/р cell is
 * ignored (Word auto-numbering in some regions, literal numbers in others);
 * seq_no is counted per section/district.
 */
class RoadmapParser
{
    /**
     * Cyrillic capitals typed instead of Latin Roman numerals (І U+0406, Х U+0425).
     * Keep in sync with the character class in matchSectionHeader().
     */
    private const ROMAN_LOOKALIKES = ["\u{0406}" => 'I', "\u{0425}" => 'X'];

    private const ROMAN = ['I' => 1, 'V' => 5, 'X' => 10];

    /** @var callable(string):?int */
    private $resolveDistrict;

    /** @param callable(string):?int $resolveDistrict district name ("Боғот тумани") → districts.id, null if unknown */
    public function __construct(callable $resolveDistrict)
    {
        $this->resolveDistrict = $resolveDistrict;
    }

    /**
     * @param list<array> $blocks output of DocxTableReader::read()
     * @return array{title_text:string, approvers_text:?string, measures:list<array<string,mixed>>}
     */
    public function parse(array $blocks): array
    {
        $tables = array_values(array_filter($blocks, fn (array $b) => $b['type'] === 'tbl'));
        if (count($tables) < 2) {
            throw new RuntimeException('Ҳужжатда камида 2 та жадвал бўлиши керак (тасдиқловчилар + йўл харита), топилди: ' . count($tables));
        }

        $approvers = [];
        foreach ($tables[0]['rows'] as $row) {
            foreach ($row as $cell) {
                if ($cell !== []) {
                    $approvers[] = implode(' ', $cell);
                }
            }
        }

        // Title = body paragraphs between the first and the second table.
        $title = [];
        $seen  = 0;
        foreach ($blocks as $b) {
            if ($b['type'] === 'tbl') {
                if (++$seen === 2) {
                    break;
                }
                continue;
            }
            if ($seen === 1) {
                $title[] = $b['text'];
            }
        }

        return [
            'title_text'     => implode(' ', $title),
            'approvers_text' => $approvers === [] ? null : implode(' | ', $approvers),
            'measures'       => $this->measures($tables[1]['rows']),
        ];
    }

    /**
     * @param list<list<list<string>>> $rows
     * @return list<array<string,mixed>>
     */
    private function measures(array $rows): array
    {
        $out             = [];
        $section         = null;   // ['no'=>int,'title'=>string,'districts'=>bool]
        $district        = null;   // ['id'=>int,'head'=>?string]
        $seq             = 0;
        $expectedSection = 1;
        $seenDistricts   = [];   // [section_no][district_id] => source_row

        foreach ($rows as $i => $cells) {
            $nonEmpty = array_values(array_filter($cells, fn (array $c) => $c !== []));
            if ($nonEmpty === []) {
                continue;                                              // spacer row
            }

            if (mb_strtolower(implode(' ', $cells[0] ?? [])) === 'т/р') {
                continue;                                              // repeated header row — never a measure
            }

            if (count($nonEmpty) === 1) {
                $text = implode(' ', $nonEmpty[0]);

                if ($h = self::matchSectionHeader($text)) {
                    if ($h['no'] !== $expectedSection) {
                        throw new RuntimeException("{$i}-қатор: бўлим рақами кутилган {$expectedSection}, топилди {$h['no']} («{$text}»)");
                    }
                    $expectedSection++;
                    $section  = ['no' => $h['no'], 'title' => $h['title'], 'districts' => mb_stripos($h['title'], 'туман') !== false];
                    $district = null;
                    $seq      = 0;
                    continue;
                }

                if ($d = self::matchDistrictHeader($text)) {
                    if (! $section || ! $section['districts']) {
                        throw new RuntimeException("{$i}-қатор: туман сарлавҳаси туманлар бўлимидан ташқарида («{$text}»)");
                    }
                    $id = ($this->resolveDistrict)($d['name']);
                    if ($id === null) {
                        throw new RuntimeException("{$i}-қатор: туман топилмади — «{$d['name']}». districts.alt_labels га қўшинг ёки ҳужжатни текширинг.");
                    }
                    if (isset($seenDistricts[$section['no']][$id])) {
                        throw new RuntimeException("{$i}-қатор: «{$d['name']}» шу бўлимда иккинчи марта учради (аввалги қатор: {$seenDistricts[$section['no']][$id]})");
                    }
                    $seenDistricts[$section['no']][$id] = $i;
                    $district = ['id' => $id, 'head' => $d['head']];
                    $seq      = 0;
                    continue;
                }

                if (count($cells) === 1) {
                    throw new RuntimeException("{$i}-қатор: танилмаган бирлашган қатор («{$text}»)");
                }
                // else: a 5-cell row with only one cell filled — falls through to the measure path
            }

            $body = $cells[1] ?? [];
            if ($body === []) {
                throw new RuntimeException("{$i}-қатор: чора-тадбир матни (2-устун) бўш");
            }
            if (! $section) {
                throw new RuntimeException("{$i}-қатор: бўлим сарлавҳасидан олдин чора-тадбир");
            }
            if ($section['districts'] && ! $district) {
                throw new RuntimeException("{$i}-қатор: туман сарлавҳасидан олдин чора-тадбир");
            }

            $split = self::splitMeasure($body);
            $out[] = [
                'section_no'         => $section['no'],
                'section_title'      => $section['title'],
                'district_id'        => $district['id'] ?? null,
                'district_head_text' => $district['head'] ?? null,
                'seq_no'             => ++$seq,
                'title'              => $split['title'],
                'details'            => $split['details'],
                'body_raw'           => implode("\n", $body),
                'funding_text'       => self::nullIfEmpty(self::joinLines($cells[2] ?? [])),
                'deadline_text'      => self::nullIfEmpty(self::joinLines($cells[3] ?? [])),
                'responsible_text'   => self::nullIfEmpty(self::joinLines($cells[4] ?? [])),
                'source_row'         => $i,
            ];
        }

        if ($out === []) {
            throw new RuntimeException('Йўл харита жадвалида бирорта чора-тадбир топилмади.');
        }

        return $out;
    }

    private static function nullIfEmpty(string $s): ?string
    {
        return $s === '' ? null : $s;
    }

    public static function romanToInt(string $s): ?int
    {
        $s = strtr(mb_strtoupper(trim($s)), self::ROMAN_LOOKALIKES);
        // No /u on purpose: this ASCII-only guard is what makes the byte-wise str_split below safe.
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
        // Keep in sync with ROMAN_LOOKALIKES.
        if (preg_match('/^([IVXivx\x{0406}\x{0456}\x{0425}\x{0445}]+)\s*\.\s*(.+)$/u', trim($text), $m) !== 1) {
            return null;
        }
        $no = self::romanToInt($m[1]);

        return $no === null ? null : ['no' => $no, 'title' => trim($m[2])];
    }

    /**
     * "1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)" → name + hokim text.
     * Also accepts city rows, e.g. "N. <name> шаҳар (…)" (Фарғона: "Қувасой шаҳар").
     * The parenthesis is optional; a leading "масъул –" is stripped from it.
     *
     * @return ?array{name:string,head:?string}
     */
    public static function matchDistrictHeader(string $text): ?array
    {
        $re = '/^\d+\s*[.)]\s*(.+?(?:тумани|туман|шаҳри|шахри|шаҳар|шахар))\s*(?:\((.*)\))?\s*[.;]?\s*$/u';
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
        if ($lines === []) {
            throw new RuntimeException('Чора-тадбир матни бўш');
        }

        $first = $lines[0];
        $rest  = array_slice($lines, 1);
        $pos   = mb_stripos($first, 'жумладан');

        if ($pos !== false) {
            $title = mb_substr($first, 0, $pos);
            if (trim($title, " ,:;") === '') {
                // «жумладан» opens the line with nothing meaningful before it — keep the whole line as the title.
                $title = $first;
            } else {
                $after = ltrim(mb_substr($first, $pos + mb_strlen('жумладан')), " :,;");   // keep the item's own trailing ";"
                if ($after !== '') {
                    array_unshift($rest, $after);
                }
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
                $out .= preg_match('/[).]$/u', $lines[$i - 1]) === 1 ? ', ' : ' ';
            }
            $out .= $line;
        }

        return $out;
    }
}
