<?php

namespace App\Services\Roadmaps;

use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\RoadmapPeriod;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Reads a filled template back into blocks keyed by the natural measure key.
 * Row rules (column A as PhpSpreadsheet reports it — a merged range has its value on
 * the first row only): a key in A starts a block; any other non-empty A (title,
 * header, section, district) ends the current block; an empty A with D/F/G/H all
 * empty is a filler row; an empty A with anything in D/F/G/H continues the block.
 * Structural problems throw RuntimeException naming sheet and cell; nothing is written here.
 */
final class RoadmapProgressReader
{
    /**
     * @return array{period: ?string, blocks: array<string, array{sheet: string, row: int, lines: list<array{row: int, label: string, unit: ?string, plan: ?float, actual: ?float, note: ?string}>}>}
     */
    public function read(Spreadsheet $book): array
    {
        $period = null;
        $blocks = [];
        foreach ($book->getAllSheets() as $sheet) {
            if ($sheet->getTitle() === RoadmapTemplateWriter::INSTRUCTIONS_TITLE) {
                continue;
            }
            $sheetPeriod = self::periodOf($sheet);
            if ($sheetPeriod !== null) {
                if ($period !== null && $sheetPeriod !== $period) {
                    throw new RuntimeException("«{$sheet->getTitle()}» варағи {$sheetPeriod} даври учун, биринчи варақ {$period} учун.");
                }
                $period = $sheetPeriod;
            }
            $this->readSheet($sheet, $blocks);
        }

        return ['period' => $period, 'blocks' => $blocks];
    }

    /** The period written into the title row by roadmap:template («… Ҳисобот даври: 2026-09»). */
    public static function periodOf(Worksheet $sheet): ?string
    {
        $title = (string) $sheet->getCell('A1')->getValue();
        if (preg_match('/Ҳисобот даври:\s*(\S+)/u', $title, $m) === 1 && RoadmapPeriod::isValid(strtoupper($m[1]))) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /** Accepts 7,8 · 7.8 · 1 240 · 1 240,5 (spaces and NBSP removed, comma → dot); anything else non-empty throws. */
    public static function number(mixed $v, string $where): ?float
    {
        if (self::blank($v)) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = str_replace(',', '.', (string) preg_replace('/[ \x{00A0}]/u', '', (string) $v));
        if (! is_numeric($s)) {
            throw new RuntimeException("{$where}: «{$v}» рақам эмас.");
        }

        return (float) $s;
    }

    /** @param array<string, array{sheet: string, row: int, lines: list<array<string, mixed>>}> $blocks */
    private function readSheet(Worksheet $sheet, array &$blocks): void
    {
        $name    = $sheet->getTitle();
        $current = null;
        $last    = $sheet->getHighestDataRow();

        for ($r = 1; $r <= $last; $r++) {
            $a = self::text($sheet, "A{$r}");
            if ($a !== '' && RoadmapKey::isKey($a)) {
                $a = RoadmapKey::canonical($a);                    // hand-edited padding («1733-05-0-2») must still match
                if (isset($blocks[$a])) {
                    throw new RuntimeException("{$name}!A{$r}: калит {$a} файлда иккинчи марта учради.");
                }
                $blocks[$a] = ['sheet' => $name, 'row' => $r, 'lines' => []];
                $current    = $a;
            } elseif ($a !== '') {
                $current = null;                                   // title, header, section or district row
                continue;
            }

            $label     = self::text($sheet, "D{$r}");
            $unit      = self::text($sheet, "E{$r}");
            $planRaw   = $sheet->getCell("F{$r}")->getValue();
            $actualRaw = $sheet->getCell("G{$r}")->getValue();
            $note      = self::text($sheet, "H{$r}");
            if ($label === '' && self::blank($planRaw) && self::blank($actualRaw) && $note === '') {
                continue;                                          // filler row — the block stays open
            }
            if ($current === null) {
                throw new RuntimeException("{$name}!{$r}: индикатор қатори калитсиз (A устуни бўш).");
            }
            if ($label === '') {
                throw new RuntimeException("{$name}!D{$r}: индикатор номи бўш.");
            }

            $blocks[$current]['lines'][] = [
                'row'    => $r,
                'label'  => mb_substr($label, 0, 255),
                'unit'   => $unit === '' ? null : mb_substr($unit, 0, 48),
                'plan'   => self::number($planRaw, "{$name}!F{$r}"),
                'actual' => self::number($actualRaw, "{$name}!G{$r}"),
                'note'   => $note === '' ? null : mb_substr($note, 0, 500),
            ];
        }
    }

    private static function text(Worksheet $sheet, string $coord): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $sheet->getCell($coord)->getValue()));
    }

    private static function blank(mixed $v): bool
    {
        return $v === null || trim((string) $v) === '';
    }
}
