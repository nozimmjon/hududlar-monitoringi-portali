<?php

namespace App\Services\Roadmaps;

use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\RoadmapPeriod;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Reads a filled template back into blocks keyed by the natural measure key.
 * Row rules (column A as PhpSpreadsheet reports it — a merged range has its value on
 * the first row only): a key in A starts a block; any other non-empty A (title,
 * header, section, district) ends the current block; an empty A with D/F/G/H all
 * empty is a filler row; an empty A with anything in D/F/G/H continues the block.
 * Two filler rows in a row close the block, so only a key row can open the next one —
 * a stray «ЖАМИ» typed far below the table is reported, never absorbed as a line.
 * Structural problems throw RuntimeException naming sheet and cell; nothing is written here.
 */
final class RoadmapProgressReader
{
    /** Blank rows in a row that close the open block. */
    private const FILLER_RUN = 2;

    /** Space, NBSP, narrow and thin no-break space — all of them turn up in pasted numbers. */
    private const SPACES = '[ \x{00A0}\x{202F}\x{2009}]';

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

    /**
     * Accepts 7,8 · 7.8 · 1 240 · 1 240,5 (spaces, NBSP and narrow spaces removed, comma → dot)
     * and a formula's calculated value. Everything a spreadsheet can silently distort — a
     * percent-formatted 0,5 that means 50, a date cell, «1,240» that could be 1240 or 1,24 —
     * throws instead of being guessed at, naming the cell.
     *
     * Pass a cell that is still the sheet's current one (fetch → call, nothing in between):
     * PhpSpreadsheet recycles cell objects, and a stale one reports the *collection's*
     * current coordinate — it would be styled by whichever cell was read last.
     */
    public static function number(Cell $cell, string $where): ?float
    {
        $format = (string) $cell->getStyle()->getNumberFormat()->getFormatCode();
        $value  = $cell->getValue();
        if ($cell->isFormula()) {
            try {
                $value = $cell->getCalculatedValue();
            } catch (Throwable $e) {
                throw new RuntimeException("{$where}: формула ҳисобланмади.", 0, $e);
            }
        }
        if (self::blank($value)) {
            return null;
        }
        if (is_bool($value)) {
            throw new RuntimeException("{$where}: катакда мантиқий қиймат — рақам эмас.");
        }

        $shown  = trim((string) $value);
        $number = is_int($value) || is_float($value);

        // «50%» is stored as 0,5 — taking that at face value would quietly divide the report by 100.
        if ($number && preg_match('/(?<!\\\\)%/', $format) === 1) {
            throw new RuntimeException("{$where}: катак фоиз форматида — 50% эмас, 50 деб ёзинг.");
        }
        if (Date::isDateTimeFormatCode($format)) {
            throw new RuntimeException("{$where}: катак сана форматида — рақам киритинг.");
        }
        if ($number) {
            return self::magnitude((float) $value, $shown, $where);
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

    /** @param array<string, array{sheet: string, row: int, lines: list<array<string, mixed>>}> $blocks */
    private function readSheet(Worksheet $sheet, array &$blocks): void
    {
        $name    = $sheet->getTitle();
        $current = null;
        $fillers = 0;
        $last    = $sheet->getHighestDataRow();

        for ($r = 1; $r <= $last; $r++) {
            $a = self::text($sheet, "A{$r}");
            if ($a !== '' && RoadmapKey::isKey($a)) {
                $a = RoadmapKey::canonical($a);                    // hand-edited padding («1733-05-0-2») must still match
                if (isset($blocks[$a])) {
                    $first = $blocks[$a];
                    throw new RuntimeException("{$name}!A{$r}: калит {$a} файлда иккинчи марта учради (биринчи марта {$first['sheet']}!A{$first['row']}).");
                }
                $blocks[$a] = ['sheet' => $name, 'row' => $r, 'lines' => []];
                $current    = $a;
                $fillers    = 0;
            } elseif ($a !== '') {
                $current = null;                                   // title, header, section or district row
                $fillers = 0;
                continue;
            }

            $label     = self::text($sheet, "D{$r}");
            $unit      = self::text($sheet, "E{$r}");
            $planRaw   = self::raw($sheet, "F{$r}");
            $actualRaw = self::raw($sheet, "G{$r}");
            $note      = self::text($sheet, "H{$r}");
            if ($label === '' && self::blank($planRaw) && self::blank($actualRaw) && $note === '') {
                if (++$fillers >= self::FILLER_RUN) {
                    $current = null;                               // the block ends here — only a key row opens the next one
                }
                continue;
            }

            $fillers = 0;
            if ($current === null) {
                throw new RuntimeException("{$name}!D{$r}: индикатор қатори калитсиз (A устуни бўш).");
            }
            if ($label === '') {
                throw new RuntimeException("{$name}!D{$r}: индикатор номи бўш.");
            }

            $blocks[$current]['lines'][] = [
                'row'    => $r,
                'label'  => mb_substr($label, 0, 255),
                'unit'   => $unit === '' ? null : mb_substr($unit, 0, 48),
                'plan'   => self::numberAt($sheet, "F{$r}", "{$name}!F{$r}"),
                'actual' => self::numberAt($sheet, "G{$r}", "{$name}!G{$r}"),
                'note'   => $note === '' ? null : mb_substr($note, 0, 500),
            ];
        }
    }

    private static function magnitude(float $n, string $shown, string $where): float
    {
        if (abs($n) > 1e12) {
            throw new RuntimeException("{$where}: «{$shown}» жуда катта.");
        }

        return $n;
    }

    /** Read straight through: a fetched cell must be consumed before the next fetch (see number()). */
    private static function numberAt(Worksheet $sheet, string $coord, string $where): ?float
    {
        return $sheet->cellExists($coord) ? self::number($sheet->getCell($coord), $where) : null;
    }

    /** Existing cells only: asking for a missing one would materialise it on every style-only row. */
    private static function raw(Worksheet $sheet, string $coord): mixed
    {
        return $sheet->cellExists($coord) ? $sheet->getCell($coord)->getValue() : null;
    }

    private static function text(Worksheet $sheet, string $coord): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}\x{2009}]+/u', ' ', (string) self::raw($sheet, $coord)));
    }

    private static function blank(mixed $v): bool
    {
        return $v === null || (! is_bool($v) && trim((string) $v) === '');   // FALSE is a value, not an empty cell
    }
}
