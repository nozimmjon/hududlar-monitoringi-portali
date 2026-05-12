<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionIndicatorAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $regions    = DB::table('regions')->pluck('code');
        $indicators = DB::table('indicators')->pluck('code');

        $rows = [];
        foreach ($regions as $regionCode) {
            foreach ($indicators as $indicatorCode) {
                $rows[] = [
                    'region_code'    => $regionCode,
                    'indicator_code' => $indicatorCode,
                    'status'         => 'available',
                    'note'           => null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('region_indicator_availability')->upsert(
                $chunk,
                ['region_code', 'indicator_code'],
                ['status', 'note', 'updated_at']
            );
        }

        // Apply known exceptions

        // 1726 = Tashkent city (SOATO), 1712 = Navoi (SOATO)
        DB::table('region_indicator_availability')
            ->where('region_code', 1726)
            ->where('indicator_code', 'agriculture')
            ->update([
                'status'     => 'not_applicable',
                'note'       => 'Тошкент шаҳри учун қишлоқ хўжалиги кесими йўқ.',
                'updated_at' => $now,
            ]);

        DB::table('region_indicator_availability')
            ->where('region_code', 1712)
            ->whereIn('indicator_code', ['grp','industry','agriculture','construction','services'])
            ->update([
                'status'     => 'blocked',
                'note'       => 'Манба макро 1.2 саҳифасида Сурхондарё маълумоти жойлаштирилган. Юқори манбадан тузатиш кутилмоқда.',
                'updated_at' => $now,
            ]);

        $this->command->info('Seeded ' . count($rows) . ' region_indicator_availability rows.');
    }
}
