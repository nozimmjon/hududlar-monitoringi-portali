<?php

namespace Tests\Helpers;

use App\Services\Roadmaps\DocxTableReader;
use ZipArchive;

/**
 * Builds a minimal .docx shaped like the regional "ЙЎЛ ХАРИТАСИ" documents:
 * table 1 (approvers, one row) · title paragraphs · table 2 (5-column road map
 * with the real header row).
 *
 * Road-map rows:
 *   ['section',  'I. Вилоятда амалга ошириладиган йирик лойиҳалар']       → one merged cell (gridSpan=5)
 *   ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)']   → one merged cell
 *   ['measure',  $bodyLines, $fundingLines, $deadlineLines, $responsibleLines]  → 5 cells, first one empty
 *
 * Each *Lines value is list<string>: separate strings become separate paragraphs
 * (w:p); a "\n" inside one string becomes a soft break (w:br) inside one
 * paragraph — which the reader joins with a space.
 */
class RoadmapDocxBuilder
{
    private const CONTENT_TYPES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '</Types>';

    private const RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';

    public const HEADER = ['Т/р', 'Чора-тадбир номи', 'Лойиҳанинг молиялаштириш манбаси', 'Муддати', 'Масъуллар'];

    /**
     * @param list<array> $rows
     * @param list<string> $title
     * @param list<string> $approvers
     */
    public static function make(
        array $rows,
        ?string $path = null,
        array $title = ['2026 йилда Тест вилоятида сув хўжалиги соҳасида амалга ошириладиган', 'тадбирларнинг илмий ечимларига қаратилган', '“ЙЎЛ ХАРИТАСИ”'],
        array $approvers = ['ТАСДИҚЛАЙМАН Университет ректори', 'ТАСДИҚЛАЙМАН Сув хўжалиги вазири', 'ТАСДИҚЛАЙМАН Вилоят ҳокими'],
    ): string {
        $xml = '<w:tbl><w:tr>' . implode('', array_map(fn (string $a) => self::tc([$a]), $approvers)) . '</w:tr></w:tbl>';
        foreach ($title as $t) {
            $xml .= self::p($t);
        }

        $xml .= '<w:tbl>';
        $xml .= '<w:tr>' . implode('', array_map(fn (string $h) => self::tc([$h]), self::HEADER)) . '</w:tr>';
        foreach ($rows as $r) {
            $xml .= '<w:tr>';
            if ($r[0] === 'measure') {
                $xml .= self::tc([]) . self::tc($r[1]) . self::tc($r[2] ?? []) . self::tc($r[3] ?? []) . self::tc($r[4] ?? []);
            } elseif ($r[0] === 'raw') {
                // ['raw', [cellLines, cellLines, ...]] — arbitrary cell layout for edge cases
                foreach ($r[1] as $cell) {
                    $xml .= self::tc($cell);
                }
            } else {
                $xml .= self::tc([$r[1]], 5);
            }
            $xml .= '</w:tr>';
        }
        $xml .= '</w:tbl>';

        $xml .= '<w:tbl><w:tr>' . self::tc(['Вилоят ҳокимининг ўринбосари']) . self::tc([]) . self::tc(['Илмий маслаҳатчи']) . '</w:tr></w:tbl>';

        return self::write($xml, $path);
    }

    /** Wraps arbitrary body XML (w: prefix) in a minimal docx — for reader edge-case tests. */
    public static function makeRaw(string $bodyXml, ?string $path = null): string
    {
        return self::write($bodyXml, $path);
    }

    private static function write(string $bodyXml, ?string $path): string
    {
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="' . DocxTableReader::W_NS . '"><w:body>' . $bodyXml . '</w:body></w:document>';

        $path ??= tempnam(sys_get_temp_dir(), 'roadmap_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::CONTENT_TYPES);
        $zip->addFromString('_rels/.rels', self::RELS);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        return $path;
    }

    /** @param list<string> $lines */
    private static function tc(array $lines, int $span = 1): string
    {
        $pr = $span > 1 ? '<w:tcPr><w:gridSpan w:val="' . $span . '"/></w:tcPr>' : '';
        $ps = $lines === [] ? '<w:p/>' : implode('', array_map(fn (string $l) => self::p($l), $lines));

        return '<w:tc>' . $pr . $ps . '</w:tc>';
    }

    private static function p(string $text): string
    {
        $runs = [];
        foreach (explode("\n", $text) as $i => $part) {
            if ($i > 0) {
                $runs[] = '<w:r><w:br/></w:r>';
            }
            $runs[] = '<w:r><w:t xml:space="preserve">' . htmlspecialchars($part, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r>';
        }

        return '<w:p>' . implode('', $runs) . '</w:p>';
    }
}
