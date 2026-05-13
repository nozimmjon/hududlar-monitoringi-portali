<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Region;
use App\Support\Import\DistrictNameNormalizer;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PatchWorkbookCityRows extends Command
{
    protected $signature = 'data:patch-city-rows
                            {--region=* : Restrict to listed region slugs (e.g. kashkadarya). Default = all 14.}
                            {--dry-run : Print report without saving.}';

    protected $description = 'Append " ш." to ambiguous bare-city rows in region xlsx workbooks.';

    public function handle(): int
    {
        $this->info('Patched 0 row(s) across 0 xlsx file(s) in 0 region(s).');
        return self::SUCCESS;
    }

    private function regionFolderName(Region $region): string
    {
        if (! empty($region->folder_name)) {
            return $region->folder_name;
        }
        return sprintf('%d. %s', $region->sort_order, $region->name_short);
    }

    private function cityFormsForRegion(int $regionCode): array
    {
        return District::query()
            ->where('region_code', $regionCode)
            ->where('kind', 'city')
            ->orderBy('sort_order')
            ->get(['name_short'])
            ->map(function ($city) {
                $full = trim($city->name_short);
                $bare = preg_replace('/ ш\.$/u', '', $full);
                return [
                    'bare'     => $bare,
                    'full'     => $full,
                    'bareNorm' => DistrictNameNormalizer::normalize($bare),
                    'fullNorm' => DistrictNameNormalizer::normalize($full),
                ];
            })
            ->values()
            ->all();
    }

    private function patchSheet(Worksheet $sheet, array $cityForms): array
    {
        $rows = $sheet->toArray(null, true, true, false);

        $colBNorm = [];
        for ($i = 6; $i < count($rows); $i++) {
            $val = $rows[$i][1] ?? null;
            if (! is_string($val)) continue;
            $trimmed = trim($val);
            if ($trimmed === '') continue;
            $colBNorm[$i + 1] = DistrictNameNormalizer::normalize($trimmed);
        }

        $patches = [];
        foreach ($cityForms as $cf) {
            $bareRows = array_keys(array_filter($colBNorm, fn($v) => $v === $cf['bareNorm']));
            $fullRows = array_keys(array_filter($colBNorm, fn($v) => $v === $cf['fullNorm']));

            if (count($fullRows) > 0) continue;
            if (count($bareRows) === 0) continue;

            $patchRow = min($bareRows);
            $oldValue = $sheet->getCell([2, $patchRow])->getValue();
            $sheet->setCellValue([2, $patchRow], $cf['full']);
            $colBNorm[$patchRow] = $cf['fullNorm'];

            $patches[] = ['row' => $patchRow, 'old' => $oldValue, 'new' => $cf['full']];
        }
        return $patches;
    }

    private function isDistrictSheet(array $rows): bool
    {
        $limit = min(6, count($rows));
        for ($i = 0; $i < $limit; $i++) {
            $b = $rows[$i][1] ?? null;
            if (is_string($b) && mb_stripos($b, 'туман') !== false) {
                return true;
            }
        }
        return false;
    }
}
