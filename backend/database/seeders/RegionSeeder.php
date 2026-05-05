<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    /**
     * Folder-name → stable English slug. Hardcoded because slugifying Cyrillic
     * without a transliteration table is fragile, and these 14 codes are stable.
     */
    public const CODE_MAP = [
        'Қорақалпоғистон Республикаси' => 'karakalpakstan',
        'Андижон'        => 'andijan',
        'Бухоро'         => 'bukhara',
        'Жиззах'         => 'jizzakh',
        'Қашқадарё'      => 'kashkadarya',
        'Навоий'         => 'navoiy',
        'Наманган'       => 'namangan',
        'Самарқанд'      => 'samarkand',
        'Сурхондарё'     => 'surkhandarya',
        'Сирдарё'        => 'syrdarya',
        'Тошкент вил'    => 'tashkent_region',
        'Фарғона'        => 'fergana',
        'Хоразм'         => 'khorezm',
        'Тошкент ш'      => 'tashkent_city',
    ];

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
            $folderShort = trim(explode('. ', $region['folder_name'], 2)[1] ?? '');
            $code = self::CODE_MAP[$folderShort] ?? null;
            if (! $code) {
                $this->command->warn("No code mapping for region: {$folderShort}");
                continue;
            }

            $rows[] = [
                'id'             => $region['id'],
                'code'           => $code,
                'name_short'     => $region['name_short'] ?? $folderShort,
                'name_full'      => $region['name_full'] ?? ($folderShort . ' вилояти'),
                'name_latin'     => $region['name_latin'],
                'folder_name'    => $region['folder_name'],
                'sort_order'     => $region['id'],
                'has_districts'  => ! empty($region['districts']),
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        DB::table('regions')->upsert(
            $rows,
            ['id'],
            ['code', 'name_short', 'name_full', 'name_latin', 'folder_name', 'sort_order', 'has_districts', 'updated_at']
        );

        $this->command->info('Seeded ' . count($rows) . ' regions.');
    }
}
