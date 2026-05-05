<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/data/regions_districts.json');
        if (! file_exists($jsonPath)) {
            $this->command->error("regions_districts.json not found at {$jsonPath}");
            return;
        }

        $regions = json_decode(file_get_contents($jsonPath), true);
        $now = now();
        $rows = [];

        foreach ($regions as $region) {
            $regionId = $region['id'];
            $folderShort = trim(explode('. ', $region['folder_name'], 2)[1] ?? '');
            $regionCode = \Database\Seeders\RegionSeeder::CODE_MAP[$folderShort] ?? null;
            if (! $regionCode) {
                continue;
            }

            foreach ($region['districts'] as $district) {
                $code = $this->makeCode($region['id'], $district);
                $altLabels = array_filter([
                    $district['name_short'] ?? null,
                    $district['name_full'] ?? null,
                    $district['name_latin'] ?? null,
                ]);

                $rows[] = [
                    'region_id'   => $regionId,
                    'region_code' => $regionCode,
                    'code'        => $code,
                    'name_short'  => $district['name_short'],
                    'name_full'   => $district['name_full'],
                    'name_latin'  => $district['name_latin'],
                    'alt_labels'  => json_encode(array_values(array_unique($altLabels)), JSON_UNESCAPED_UNICODE),
                    'kind'        => $district['kind'],
                    'sort_order'  => $district['sort_order'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        // Chunk to keep the upsert payload manageable
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('districts')->upsert(
                $chunk,
                ['region_code', 'code'],
                ['name_short', 'name_full', 'name_latin', 'alt_labels', 'kind', 'sort_order', 'updated_at']
            );
        }

        $this->command->info('Seeded ' . count($rows) . ' districts across ' . count($regions) . ' regions.');
    }

    /**
     * Build a stable per-region district code. Use latin transliteration if available,
     * else fall back to "d{sort_order}" — guaranteed unique within (region_id).
     */
    private function makeCode(int $regionId, array $district): string
    {
        $latin = $district['name_latin'] ?? null;
        if ($latin && trim($latin) !== '') {
            $slug = Str::slug($latin, '_');
            if ($slug !== '') {
                return $slug;
            }
        }
        return 'd' . str_pad((string) $district['sort_order'], 2, '0', STR_PAD_LEFT);
    }
}
