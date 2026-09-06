<?php

namespace App\Services\Roadmaps;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Reads word/document.xml of a .docx and returns the body as ordered blocks:
 * paragraphs (cleaned text) and tables (rows → cells → non-empty cleaned lines).
 *
 * Horizontally merged cells (w:gridSpan) arrive as ONE cell; vertically merged
 * continuation cells arrive as an empty cell. Line breaks inside a cell come
 * from paragraph boundaries (w:p) and soft breaks (w:br / w:cr) alike.
 */
class DocxTableReader
{
    public const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @return list<array{type:'p',text:string}|array{type:'tbl',rows:list<list<list<string>>>}>
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Файлни docx сифатида очиб бўлмади: {$path} (эски .doc бўлса Word'да .docx қилиб сақланг).");
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || trim($xml) === '') {
            throw new RuntimeException("word/document.xml топилмади ёки бўш — {$path} .docx эмас (эски .doc бўлса Word'да .docx қилиб сақланг).");
        }

        $dom  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok   = $dom->loadXML($xml, LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if (! $ok) {
            throw new RuntimeException("document.xml ни ўқиб бўлмади: {$path}");
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::W_NS);
        $body = $xp->query('/w:document/w:body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new RuntimeException("w:body топилмади: {$path}");
        }

        $blocks = [];
        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::W_NS) {
                continue;
            }
            if ($node->localName === 'p') {
                $text = implode(' ', $this->paragraphLines($node, $xp));
                if ($text !== '') {
                    $blocks[] = ['type' => 'p', 'text' => $text];
                }
            } elseif ($node->localName === 'tbl') {
                $blocks[] = ['type' => 'tbl', 'rows' => $this->tableRows($node, $xp)];
            }
        }

        return $blocks;
    }

    /**
     * @return list<list<list<string>>>
     *
     * Known limits (absent from all current files): rows wrapped in w:sdt content
     * controls are skipped; a nested table's paragraphs flatten into the outer cell's lines.
     */
    private function tableRows(DOMElement $tbl, DOMXPath $xp): array
    {
        $rows = [];
        foreach ($xp->query('./w:tr', $tbl) as $tr) {
            $cells = [];
            foreach ($xp->query('./w:tc', $tr) as $tc) {
                $lines = [];
                foreach ($xp->query('.//w:p', $tc) as $p) {
                    foreach ($this->paragraphLines($p, $xp) as $line) {
                        $lines[] = $line;
                    }
                }
                $cells[] = $lines;
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /** @return list<string> cleaned, non-empty lines of one paragraph */
    private function paragraphLines(DOMElement $p, DOMXPath $xp): array
    {
        $buf = '';
        foreach ($xp->query('.//w:t | .//w:br | .//w:cr | .//w:r/w:tab | .//w:noBreakHyphen', $p) as $n) {
            $buf .= match ($n->localName) {
                't'             => $n->textContent,
                'tab'           => ' ',
                'noBreakHyphen' => '-',
                default         => "\n",
            };
        }

        $out = [];
        foreach (explode("\n", $buf) as $raw) {
            $clean = self::clean($raw);
            if ($clean !== '') {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /** Collapse NBSP variants and runs of whitespace to single spaces. */
    public static function clean(string $v): string
    {
        $v = str_replace(["\u{00A0}", "\u{2007}", "\u{202F}"], ' ', $v);

        return trim((string) preg_replace('/\s+/u', ' ', $v));
    }
}
