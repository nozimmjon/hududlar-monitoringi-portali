<?php

namespace App\Services\Roadmaps;

use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Support\Roadmaps\LineSuggester;
use App\Support\Roadmaps\RoadmapKey;
use App\Support\Roadmaps\Roman;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Builds the xlsx the regions fill: one sheet per road map shaped like the document
 * (section / district header rows, the measure text merged down its indicator rows),
 * yellow unlocked «Амалда»/«Изоҳ» cells, everything else protected, a hidden key column.
 */
final class RoadmapTemplateWriter
{
    public const HEADERS = ['Калит', '№', 'Чора-тадбир', 'Индикатор', 'Ўлчов', 'Режа', 'Амалда', 'Изоҳ', 'Муддат', 'Масъуллар'];
    public const INSTRUCTIONS_TITLE = 'Йўриқнома';

    private const WIDTHS = ['A' => 14, 'B' => 5, 'C' => 60, 'D' => 40, 'E' => 9, 'F' => 10, 'G' => 10, 'H' => 28, 'I' => 16, 'J' => 30];

    /** One line of Calibri 11 in points — the unit row heights are computed in. */
    private const LINE_HEIGHT = 15.0;

    private const INSTRUCTIONS = [
        'Фақат сариқ устунларни тўлдиринг: G «Амалда» ва (ихтиёрий) H «Изоҳ».',
        '«Амалда» — рақам, «Ўлчов» устунидаги бирликда, йил бошидан жами (ойлик қўшимча эмас).',
        'Ҳали бошланмаган индикатор учун 0 ёзинг ёки бўш қолдиринг.',
        'Қаторларни қўшманг ва ўчирманг, бошқа устунларни ўзгартирманг — варақ ҳимояланган.',
        'Файлни фақат .xlsx кўринишида сақланг (CSV/XLS эмас) — акс ҳолда яширин калит устуни йўқолади.',
        'Варақларни қайта номламанг, ўчирманг ва жойини алмаштирманг.',
        'Файлни ҳисобот ойидан кейинги ойнинг 5-санасигача қайтаринг.',
        'Саволлар бўйича мониторинг платформаси маъмурига мурожаат қилинг.',
    ];

    /** @var array<int, array{measures: int, lines: int, suggested: int}> per region code, filled by build() */
    public array $stats = [];

    public function __construct(private readonly LineSuggester $suggester = new LineSuggester())
    {
    }

    /** @param iterable<Roadmap> $roadmaps with region, measures (document order), measures.district, measures.lines.progress loaded */
    public function build(iterable $roadmaps, string $period): Spreadsheet
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);
        $this->stats = [];
        $titles      = [];
        foreach ($roadmaps as $roadmap) {
            // name_full, not name_short: 1726 and 1727 are both «Тошкент», and a clashing title
            // would be silently renamed to «Тошкент 1» by PhpSpreadsheet — fail loudly instead.
            $title = self::sheetTitle($roadmap->region->name_full);
            if (in_array($title, $titles, true)) {
                throw new RuntimeException("Duplicate sheet title «{$title}»");
            }
            $titles[] = $title;
            $this->addRegionSheet($book, $roadmap, $period, $title);
        }
        $this->addInstructionSheet($book, $titles, $period);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /** Excel forbids []:*?/\ in sheet titles and caps them at 31 characters. */
    public static function sheetTitle(string $name): string
    {
        return mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $name)), 0, 31);
    }

    /** @return list<array{label: ?string, unit: ?string, plan: ?float, actual: ?float, note: ?string, suggested: bool}> */
    public function linesFor(RoadmapMeasure $m, string $period): array
    {
        if ($m->lines->isNotEmpty()) {
            return $m->lines->map(function ($line) use ($period) {
                $p = $line->progress->firstWhere('report_period', $period);

                return [
                    'label'     => $line->label,
                    'unit'      => $line->unit,
                    'plan'      => $line->plan_value !== null ? (float) $line->plan_value : null,
                    'actual'    => $p?->actual_value !== null ? (float) $p->actual_value : null,
                    'note'      => $p?->note,
                    'suggested' => false,
                ];
            })->all();
        }

        return array_map(
            fn (array $s) => $s + ['actual' => null, 'note' => null, 'suggested' => true],
            $this->suggester->suggest($m->title, $m->detailLines()),
        );
    }

    private function addRegionSheet(Spreadsheet $book, Roadmap $roadmap, string $period, string $title): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);

        $sheet->setCellValue('A1', "Сув хўжалиги йўл харитаси — {$roadmap->region->name_full} — {$roadmap->year} · Ҳисобот даври: {$period}");
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $sheet->fromArray(self::HEADERS, null, 'A2');
        $sheet->getStyle('A2:J2')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A2:J2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
        $sheet->getStyle('G2:H2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C65911');
        $sheet->getStyle('A2:J2')->getAlignment()->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->freezePane('A3');
        foreach (self::WIDTHS as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->getColumnDimension('A')->setVisible(false);

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 2);

        $stats      = ['measures' => 0, 'lines' => 0, 'suggested' => 0];
        $row        = 3;
        $sectionNo  = null;
        $districtId = null;
        $districtNo = 0;

        foreach ($roadmap->measures as $m) {
            if ($m->section_no !== $sectionNo) {
                $sectionNo  = $m->section_no;
                $districtId = null;
                $districtNo = 0;
                $this->headerRow($sheet, $row++, Roman::of($m->section_no) . '. ' . $m->section_title, 'DBE5F1');
            }
            if ($m->district_id !== null && $m->district_id !== $districtId) {
                $districtId = $m->district_id;
                $districtNo++;
                $head = $m->district_head_text ? " (масъул – {$m->district_head_text})" : '';
                $this->headerRow($sheet, $row++, "{$districtNo}. {$m->district->name_full}{$head}", 'EAF1FB');
            }

            $lines = $this->linesFor($m, $period);      // never empty: LineSuggester::suggest() falls back to one «Бажарилиш даражаси» line
            $start = $row;
            $sheet->setCellValueExplicit("A{$row}", RoadmapKey::make($roadmap->region_code, $m->section_no, $m->district?->code, $m->seq_no), DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $m->seq_no);
            $this->setText($sheet, "C{$row}", $m->body_raw);
            $this->setText($sheet, "I{$row}", $m->deadline_text);
            $this->setText($sheet, "J{$row}", $m->responsible_text);
            foreach ($lines as $l) {
                $this->setText($sheet, "D{$row}", $l['label']);
                $this->setText($sheet, "E{$row}", $l['unit']);
                $sheet->setCellValue("F{$row}", $l['plan']);
                $sheet->setCellValue("G{$row}", $l['actual']);
                $this->setText($sheet, "H{$row}", $l['note']);
                $this->fillCells($sheet, $row);
                $row++;
                $stats['lines']++;
                if ($l['suggested']) {
                    $stats['suggested']++;
                }
            }
            $end = $row - 1;
            if ($end > $start) {
                foreach (['A', 'B', 'C', 'I', 'J'] as $c) {
                    $sheet->mergeCells("{$c}{$start}:{$c}{$end}");
                }
                $this->fitMergedBlock($sheet, $m, $start, $end);
            }
            $sheet->getStyle("A{$start}:A{$end}")->getFont()->getColor()->setRGB('999999');
            $stats['measures']++;
        }

        $last = max($row - 1, 3);
        $sheet->getStyle("A3:J{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle("F3:G{$last}")->getNumberFormat()->setFormatCode('#,##0.##');

        $protection = $sheet->getProtection();
        $protection->setSheet(true);
        $protection->setFormatColumns(false);       // false = allowed while protected
        $protection->setFormatRows(false);

        $this->stats[$roadmap->region_code] = $stats;
    }

    /**
     * Excel auto-fits wrapped text only in unmerged cells, so a measure merged down several
     * indicator rows would clip its «Чора-тадбир» text. Give such blocks explicit heights:
     * spread whatever the merged columns need beyond what the per-row «Индикатор» text already
     * occupies evenly over the rows. Single-row blocks are left on auto-fit.
     */
    private function fitMergedBlock(Worksheet $sheet, RoadmapMeasure $m, int $start, int $end): void
    {
        $need = max(self::wrappedLines($m->body_raw, 58), self::wrappedLines($m->responsible_text, 28));   // C is 60 wide, J is 30
        $base = [];
        for ($r = $start; $r <= $end; $r++) {
            $base[$r] = max(1, self::wrappedLines((string) $sheet->getCell("D{$r}")->getValue(), 38));     // D is 40 wide
        }
        $extra  = max(0, $need - array_sum($base));
        $perRow = (int) ceil($extra / count($base));
        foreach ($base as $r => $lines) {
            $sheet->getRowDimension($r)->setRowHeight(self::LINE_HEIGHT * ($lines + $perRow));
        }
    }

    /** Rough wrapped-line count of a text in a column that fits ~$perLine characters. */
    private static function wrappedLines(?string $text, int $perLine): int
    {
        $n = 0;
        foreach (preg_split('/\R/u', (string) $text) ?: [''] as $paragraph) {
            $n += max(1, (int) ceil(mb_strlen($paragraph) / $perLine));
        }

        return max(1, $n);
    }

    /** Text is written explicitly so a cell starting with «=» never becomes a formula; null stays an empty cell. */
    private function setText(Worksheet $sheet, string $coordinate, ?string $text): void
    {
        if ($text === null || $text === '') {
            $sheet->setCellValue($coordinate, null);

            return;
        }

        $sheet->setCellValueExplicit($coordinate, $text, DataType::TYPE_STRING);
    }

    private function headerRow(Worksheet $sheet, int $row, string $text, string $rgb): void
    {
        $sheet->setCellValue("A{$row}", $text);
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    /** Yellow, unlocked «Амалда» (numeric-validated) and «Изоҳ» for one line row. */
    private function fillCells(Worksheet $sheet, int $row): void
    {
        $sheet->getStyle("G{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $sheet->getStyle("G{$row}:H{$row}")->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

        $v = $sheet->getCell("G{$row}")->getDataValidation();
        $v->setType(DataValidation::TYPE_DECIMAL)
            ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)
            ->setFormula1('0')
            ->setAllowBlank(true)
            ->setShowInputMessage(true)
            ->setPromptTitle('Амалда')
            ->setPrompt('Йил бошидан жами, «Ўлчов» устунидаги бирликда')
            ->setShowErrorMessage(true)
            ->setErrorTitle('Амалда')
            ->setError('Фақат рақам киритинг (ўлчов бирлигида, йил бошидан жами)');
    }

    /** @param list<string> $titles the region sheets in this workbook */
    private function addInstructionSheet(Spreadsheet $book, array $titles, string $period): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(self::INSTRUCTIONS_TITLE);
        $sheet->setCellValue('A1', 'Йўриқнома — файлни қандай тўлдириш керак');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->setCellValue('A2', 'Файл: ' . implode(', ', $titles) . " · Ҳисобот даври: {$period}");
        $sheet->getStyle('A2')->getFont()->setBold(false)->setSize(11);
        foreach (self::INSTRUCTIONS as $i => $text) {
            $sheet->setCellValue('A' . ($i + 4), ($i + 1) . '. ' . $text);
        }
        $sheet->getColumnDimension('A')->setWidth(110);
        $sheet->getStyle('A1:A' . (count(self::INSTRUCTIONS) + 3))->getAlignment()->setWrapText(true);
    }
}
