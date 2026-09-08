<?php

use App\Services\Roadmaps\RoadmapProgressReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

function readerCell(mixed $value, ?string $format = null, bool $formula = false)
{
    $sheet = (new Spreadsheet())->getActiveSheet();
    $cell  = $sheet->getCell('A1');
    $cell->setValue($value);
    if ($format !== null) {
        $cell->getStyle()->getNumberFormat()->setFormatCode($format);
    }

    return $cell;
}

test('number(): accepted spellings', function () {
    expect(RoadmapProgressReader::number(readerCell(7.8), 'X'))->toBe(7.8);
    expect(RoadmapProgressReader::number(readerCell('7,8'), 'X'))->toBe(7.8);
    expect(RoadmapProgressReader::number(readerCell('1 240,5'), 'X'))->toBe(1240.5);
    expect(RoadmapProgressReader::number(readerCell("1\u{00A0}240"), 'X'))->toBe(1240.0);
    expect(RoadmapProgressReader::number(readerCell("1\u{202F}240"), 'X'))->toBe(1240.0);
    expect(RoadmapProgressReader::number(readerCell(''), 'X'))->toBeNull();
    expect(RoadmapProgressReader::number(readerCell(null), 'X'))->toBeNull();
    expect(RoadmapProgressReader::number(readerCell('=12+8', formula: true), 'X'))->toBe(20.0);
});

test('number(): rejected cells name the reason', function () {
    expect(fn () => RoadmapProgressReader::number(readerCell(0.5, NumberFormat::FORMAT_PERCENTAGE), 'G4'))->toThrow(RuntimeException::class, 'фоиз');
    expect(fn () => RoadmapProgressReader::number(readerCell(20, NumberFormat::FORMAT_DATE_YYYYMMDD), 'G4'))->toThrow(RuntimeException::class, 'сана');
    expect(fn () => RoadmapProgressReader::number(readerCell('1,240'), 'G4'))->toThrow(RuntimeException::class, 'ноаниқ');
    expect(fn () => RoadmapProgressReader::number(readerCell('кўп'), 'G4'))->toThrow(RuntimeException::class, 'G4');
    expect(fn () => RoadmapProgressReader::number(readerCell(true), 'G4'))->toThrow(RuntimeException::class);
    expect(fn () => RoadmapProgressReader::number(readerCell('1e30'), 'G4'))->toThrow(RuntimeException::class, 'катта');
});

test('periodOf() reads and upper-cases the title period', function () {
    $sheet = (new Spreadsheet())->getActiveSheet();
    $sheet->setCellValue('A1', 'Сув хўжалиги йўл харитаси — X — 2026 · Ҳисобот даври: 2026-q3');
    expect(RoadmapProgressReader::periodOf($sheet))->toBe('2026-Q3');
    $sheet->setCellValue('A1', 'no period here');
    expect(RoadmapProgressReader::periodOf($sheet))->toBeNull();
});
