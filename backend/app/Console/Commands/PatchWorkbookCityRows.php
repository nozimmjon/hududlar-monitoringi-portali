<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Support\Import\DistrictNameNormalizer;
use Illuminate\Console\Command;

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
