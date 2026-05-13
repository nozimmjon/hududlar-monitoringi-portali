<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Region;
use App\Support\Import\DistrictNameNormalizer;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class PatchWorkbookCityRows extends Command
{
    protected $signature = 'data:patch-city-rows
                            {--region=* : Restrict to listed region slugs (e.g. kashkadarya). Default = all 14.}
                            {--dry-run : Print report without saving.}';

    protected $description = 'Append " ш." to ambiguous bare-city rows in region xlsx workbooks.';

    public function handle(): int
    {
        $dataPath = config('import.data_path');
        if (! is_string($dataPath) || ! is_dir($dataPath)) {
            $this->error("data_path '{$dataPath}' not found or not a directory.");
            return self::FAILURE;
        }

        $regionSlugs = (array) $this->option('region');
        $dryRun = (bool) $this->option('dry-run');

        $query = Region::query()->orderBy('sort_order');
        if (! empty($regionSlugs)) {
            $query->whereIn('name_latin', $regionSlugs);
        }
        $regions = $query->get();

        $totalPatched = 0;
        $totalFiles = 0;
        $totalRegions = 0;

        foreach ($regions as $region) {
            $regionDir = $dataPath . DIRECTORY_SEPARATOR . $this->regionFolderName($region);
            if (! is_dir($regionDir)) {
                $this->line("{$region->code} {$region->name_latin}: data folder not found, skipping");
                continue;
            }

            $cityForms = $this->cityFormsForRegion($region->code);
            if (empty($cityForms)) continue;

            $regionPatchedAny = false;
            foreach (glob($regionDir . DIRECTORY_SEPARATOR . '*.xlsx') as $file) {
                $book = IOFactory::load($file);
                $dirty = false;

                foreach ($book->getAllSheets() as $sheet) {
                    $rows = $sheet->toArray(null, true, true, false);
                    if (! $this->isDistrictSheet($rows)) continue;

                    $patches = $this->patchSheet($sheet, $cityForms);
                    foreach ($patches as $p) {
                        $this->line(sprintf(
                            "%d %s | %s | %s | row %d | '%s' → '%s'",
                            $region->code,
                            $region->name_latin,
                            basename($file),
                            $sheet->getTitle(),
                            $p['row'],
                            $p['old'],
                            $p['new'],
                        ));
                        $totalPatched++;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $totalFiles++;
                    $regionPatchedAny = true;
                    if (! $dryRun) {
                        try {
                            (new XlsxWriter($book))->save($file);
                        } catch (\Throwable $e) {
                            $this->error("Failed to save {$file}: {$e->getMessage()} (close it in Excel and re-run)");
                        }
                    }
                }
                $book->disconnectWorksheets();
                unset($book);
            }

            if ($regionPatchedAny) $totalRegions++;
        }

        $this->info("Patched {$totalPatched} row(s) across {$totalFiles} xlsx file(s) in {$totalRegions} region(s).");
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
        for ($i = 0; $i < count($rows); $i++) {
            $val = $rows[$i][1] ?? null;
            if (! is_string($val)) continue;
            $trimmed = trim($val);
            if ($trimmed === '') continue;
            // Skip rows that carry an explicit district marker — they are unambiguously districts.
            if (preg_match('/\bтумани\b|\sт\.\s*$/u', $trimmed)) {
                continue;
            }
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
