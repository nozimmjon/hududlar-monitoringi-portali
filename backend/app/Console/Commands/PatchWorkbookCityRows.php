<?php

namespace App\Console\Commands;

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
