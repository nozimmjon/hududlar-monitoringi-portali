<?php

namespace Tests\Helpers;

use App\Models\Roadmap;
use App\Models\RoadmapMeasure;
use App\Models\RoadmapMeasureLine;
use App\Models\User;
use App\Services\Roadmaps\MeasureLineSync;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The Хоразм road map the admin-panel tests work on (xlsx layout, lines predefined):
 *   I/1  «Куловот» канали            · L1 Бажарилиш даражаси % 100       · deadline 2026 йил декабрь
 *   I/2  484,5 млн м3 сув             · L1 Тежалган сув млн м3 484,5       · deadline 2026 йил июнь (reached from June on)
 *   II · Боғот/1  бетонлаштириш       · L1 Хўжаликлараро каналлар км 7,8 · L2 Ички каналлар км 33
 *   II · Гурлан/1 насос агрегатлари   · L1 Насос агрегатлари та 24
 * («млн м3» is the xlsx spelling; XlsxRoadmapParser normalises it to «млн м³» on import, so
 * the stored line reads with the superscript.)
 * roadmap:template puts them on rows 4, 5, 8–9 and 11 of the «Хоразм вилояти» sheet.
 * Callers seed first ($this->seed()).
 */
final class AdminFixture
{
    public static function roadmap(): Roadmap
    {
        $file = RoadmapXlsxBuilder::make([
            ['section', 'I. Вилоятда амалга ошириладиган йирик лойиҳалар'],
            ['measure', ['B' => 1, 'C' => '«Куловот» каналини реконструкция қилиш.', 'D' => 'Бажарилиш даражаси', 'E' => '%', 'F' => 100,
                'I' => '2026 йил декабрь', 'J' => 'СХВ (Ў.Шералиев)']],
            ['measure', ['B' => 2, 'C' => '484,5 млн м3 сувни иқтисод қилиш.', 'D' => 'Тежалган сув', 'E' => 'млн м3', 'F' => '484,5',
                'I' => '2026 йил июнь', 'J' => 'ИТҲБ (Э.Нурметов)']],
            ['section', 'II. Туманларда амалга ошириладиган лойиҳалар'],
            ['district', '1. Боғот тумани (масъул – туман ҳокими Ж.Назаров)'],
            ['measure', ['B' => 1, 'C' => "Суғориш тармоқларини бетонлаштириш, жумладан:\n1. 7,8 км хўжаликлараро каналлар;\n2. 33 км ички каналлар.",
                'D' => 'Хўжаликлараро каналлар', 'E' => 'км', 'F' => '7,8', 'I' => '2026 йил декабрь', 'J' => 'ИТҲБ (Э.Нурметов)']],
            ['line', ['D' => 'Ички каналлар', 'E' => 'км', 'F' => 33]],
            ['district', '2. Гурлан тумани (масъул – туман ҳокими Р.Ўразбоев)'],
            ['measure', ['B' => 1, 'C' => '24 та насос агрегатларини таъмирлаш.', 'D' => 'Насос агрегатлари', 'E' => 'та', 'F' => 24,
                'I' => '2026 йил декабрь', 'J' => 'ИТҲБ']],
        ], null, 'Хоразм вилояти');

        try {
            if (Artisan::call('import:roadmap', ['--region' => 1733, '--file' => $file]) !== 0) {
                throw new \RuntimeException('AdminFixture import failed: ' . Artisan::output());
            }
        } finally {
            @unlink($file);
        }
        Artisan::output();   // drain

        return Roadmap::where('region_code', 1733)->firstOrFail();
    }

    public static function user(string $email = 'admin@example.test'): User
    {
        return User::factory()->create(['name' => 'Админ', 'email' => $email, 'password' => 'secret-123']);
    }

    public static function measure(int $sectionNo, int $seqNo, ?int $districtCode = null): RoadmapMeasure
    {
        $q = RoadmapMeasure::where('section_no', $sectionNo)->where('seq_no', $seqNo);
        $q = $districtCode === null
            ? $q->whereNull('district_id')
            : $q->whereHas('district', fn ($d) => $d->where('code', $districtCode));

        return $q->firstOrFail();
    }

    public static function line(RoadmapMeasure $measure, int $lineNo): RoadmapMeasureLine
    {
        return $measure->lines()->where('line_no', $lineNo)->firstOrFail();
    }

    /**
     * Report a period the way the importer does: every line of the measure gets a row;
     * $actuals / $notes are keyed by line_no (missing = null). A key that names a line_no
     * the measure does not have throws — silently ignoring it would let a test that targets
     * the wrong line pass anyway.
     */
    public static function report(RoadmapMeasure $measure, string $period, array $actuals, array $notes = []): void
    {
        $measure->load('lines.progress');
        $lineNos = $measure->lines->pluck('line_no')->all();
        self::assertKnownLines($actuals, $lineNos, '$actuals');
        self::assertKnownLines($notes, $lineNos, '$notes');

        $lines = [];
        foreach ($measure->lines as $l) {
            $lines[] = [
                'label'  => $l->label,
                'unit'   => $l->unit,
                'plan'   => $l->plan_value === null ? null : (float) $l->plan_value,
                'actual' => $actuals[$l->line_no] ?? null,
                'note'   => $notes[$l->line_no] ?? null,
            ];
        }
        MeasureLineSync::sync($measure, $lines, $period, $measure->roadmap->year);
        $measure->unsetRelation('lines');
    }

    /** @param array<int, mixed> $values @param list<int> $lineNos */
    private static function assertKnownLines(array $values, array $lineNos, string $argName): void
    {
        foreach (array_keys($values) as $lineNo) {
            if (! in_array($lineNo, $lineNos, true)) {
                throw new \InvalidArgumentException(
                    "AdminFixture::report(): {$argName} has no line_no {$lineNo} on this measure (has: " . implode(', ', $lineNos) . ').'
                );
            }
        }
    }

    /**
     * A filled roadmap:template file for Хоразм: $cells are coordinate => value on the
     * «Хоразм вилояти» sheet (G4/G5/G8/G9/G11 are the «Амалда» cells). Caller unlinks it.
     */
    public static function template(string $period = '2026-09', array $cells = []): string
    {
        $stub = tempnam(sys_get_temp_dir(), 'rmadm_');
        @unlink($stub);                                 // roadmap:template writes its own file; don't leave the tempnam twin behind
        $out = $stub . '.xlsx';

        if (Artisan::call('roadmap:template', ['--region' => 1733, '--period' => $period, '--out' => $out]) !== 0) {
            throw new \RuntimeException('AdminFixture template failed: ' . Artisan::output());
        }
        Artisan::output();   // drain

        if ($cells !== []) {
            $book = IOFactory::load($out);
            try {
                $sheet = $book->getSheetByName('Хоразм вилояти')
                    ?? throw new \RuntimeException('sheet «Хоразм вилояти» missing in the template');
                foreach ($cells as $coord => $value) {
                    $sheet->setCellValue($coord, $value);
                }
                IOFactory::createWriter($book, 'Xlsx')->save($out);
            } finally {
                $book->disconnectWorksheets();
            }
        }

        return $out;
    }
}
